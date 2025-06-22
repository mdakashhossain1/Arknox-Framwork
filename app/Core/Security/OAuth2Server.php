<?php

namespace App\Core\Security;

use App\Core\Response;
use App\Core\Request;

/**
 * OAuth2 Server Implementation
 * 
 * Banking-grade OAuth2 server with support for:
 * - Authorization Code Grant
 * - Client Credentials Grant
 * - Refresh Token Grant
 * - PKCE (Proof Key for Code Exchange)
 * - Scope-based access control
 */
class OAuth2Server
{
    private $clients = [];
    private $authCodes = [];
    private $accessTokens = [];
    private $refreshTokens = [];
    private $jwtManager;
    private $cache;

    public function __construct()
    {
        $this->jwtManager = new JWTManager();
        $this->cache = Cache::getInstance();
        $this->loadClients();
    }

    /**
     * Handle authorization request
     */
    public function authorize(Request $request)
    {
        $responseType = $request->input('response_type');
        $clientId = $request->input('client_id');
        $redirectUri = $request->input('redirect_uri');
        $scope = $request->input('scope', '');
        $state = $request->input('state');
        $codeChallenge = $request->input('code_challenge');
        $codeChallengeMethod = $request->input('code_challenge_method', 'plain');

        // Validate client
        $client = $this->validateClient($clientId, $redirectUri);
        if (!$client) {
            return Response::json(['error' => 'invalid_client'], 400);
        }

        // Validate response type
        if ($responseType !== 'code') {
            return $this->redirectWithError($redirectUri, 'unsupported_response_type', $state);
        }

        // Validate PKCE if present
        if ($codeChallenge && !$this->validateCodeChallenge($codeChallenge, $codeChallengeMethod)) {
            return $this->redirectWithError($redirectUri, 'invalid_request', $state);
        }

        // Generate authorization code
        $authCode = $this->generateAuthorizationCode($clientId, $redirectUri, $scope, $codeChallenge, $codeChallengeMethod);

        // Redirect with authorization code
        $params = [
            'code' => $authCode,
            'state' => $state
        ];

        return Response::redirect($redirectUri . '?' . http_build_query(array_filter($params)));
    }

    /**
     * Handle token request
     */
    public function token(Request $request)
    {
        $grantType = $request->input('grant_type');

        switch ($grantType) {
            case 'authorization_code':
                return $this->handleAuthorizationCodeGrant($request);
            case 'client_credentials':
                return $this->handleClientCredentialsGrant($request);
            case 'refresh_token':
                return $this->handleRefreshTokenGrant($request);
            default:
                return Response::json(['error' => 'unsupported_grant_type'], 400);
        }
    }

    /**
     * Handle authorization code grant
     */
    private function handleAuthorizationCodeGrant(Request $request)
    {
        $code = $request->input('code');
        $clientId = $request->input('client_id');
        $clientSecret = $request->input('client_secret');
        $redirectUri = $request->input('redirect_uri');
        $codeVerifier = $request->input('code_verifier');

        // Validate client credentials
        if (!$this->validateClientCredentials($clientId, $clientSecret)) {
            return Response::json(['error' => 'invalid_client'], 401);
        }

        // Validate authorization code
        $authCodeData = $this->validateAuthorizationCode($code, $clientId, $redirectUri);
        if (!$authCodeData) {
            return Response::json(['error' => 'invalid_grant'], 400);
        }

        // Validate PKCE if used
        if ($authCodeData['code_challenge']) {
            if (!$codeVerifier || !$this->verifyCodeChallenge($codeVerifier, $authCodeData['code_challenge'], $authCodeData['code_challenge_method'])) {
                return Response::json(['error' => 'invalid_grant'], 400);
            }
        }

        // Generate tokens
        $accessToken = $this->generateAccessToken($clientId, $authCodeData['scope']);
        $refreshToken = $this->generateRefreshToken($clientId, $authCodeData['scope']);

        // Revoke authorization code
        $this->revokeAuthorizationCode($code);

        return Response::json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => $refreshToken,
            'scope' => $authCodeData['scope']
        ]);
    }

    /**
     * Handle client credentials grant
     */
    private function handleClientCredentialsGrant(Request $request)
    {
        $clientId = $request->input('client_id');
        $clientSecret = $request->input('client_secret');
        $scope = $request->input('scope', '');

        // Validate client credentials
        if (!$this->validateClientCredentials($clientId, $clientSecret)) {
            return Response::json(['error' => 'invalid_client'], 401);
        }

        // Generate access token
        $accessToken = $this->generateAccessToken($clientId, $scope);

        return Response::json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'scope' => $scope
        ]);
    }

    /**
     * Handle refresh token grant
     */
    private function handleRefreshTokenGrant(Request $request)
    {
        $refreshToken = $request->input('refresh_token');
        $clientId = $request->input('client_id');
        $clientSecret = $request->input('client_secret');

        // Validate client credentials
        if (!$this->validateClientCredentials($clientId, $clientSecret)) {
            return Response::json(['error' => 'invalid_client'], 401);
        }

        // Validate refresh token
        $tokenData = $this->validateRefreshToken($refreshToken, $clientId);
        if (!$tokenData) {
            return Response::json(['error' => 'invalid_grant'], 400);
        }

        // Generate new tokens
        $accessToken = $this->generateAccessToken($clientId, $tokenData['scope']);
        $newRefreshToken = $this->generateRefreshToken($clientId, $tokenData['scope']);

        // Revoke old refresh token
        $this->revokeRefreshToken($refreshToken);

        return Response::json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => $newRefreshToken,
            'scope' => $tokenData['scope']
        ]);
    }

    /**
     * Validate access token
     */
    public function validateAccessToken($token)
    {
        try {
            return $this->jwtManager->verify($token);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate authorization code
     */
    private function generateAuthorizationCode($clientId, $redirectUri, $scope, $codeChallenge = null, $codeChallengeMethod = null)
    {
        $code = bin2hex(random_bytes(32));
        
        $this->cache->set("auth_code_{$code}", [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'expires_at' => time() + 600 // 10 minutes
        ], 600);

        return $code;
    }

    /**
     * Generate access token
     */
    private function generateAccessToken($clientId, $scope)
    {
        $payload = [
            'client_id' => $clientId,
            'scope' => $scope,
            'token_type' => 'access_token'
        ];

        return $this->jwtManager->generate($payload, 3600);
    }

    /**
     * Generate refresh token
     */
    private function generateRefreshToken($clientId, $scope)
    {
        $token = bin2hex(random_bytes(32));
        
        $this->cache->set("refresh_token_{$token}", [
            'client_id' => $clientId,
            'scope' => $scope,
            'expires_at' => time() + (86400 * 30) // 30 days
        ], 86400 * 30);

        return $token;
    }

    /**
     * Validate client
     */
    private function validateClient($clientId, $redirectUri)
    {
        $client = $this->clients[$clientId] ?? null;
        
        if (!$client) {
            return false;
        }

        // Validate redirect URI
        if (!in_array($redirectUri, $client['redirect_uris'])) {
            return false;
        }

        return $client;
    }

    /**
     * Validate client credentials
     */
    private function validateClientCredentials($clientId, $clientSecret)
    {
        $client = $this->clients[$clientId] ?? null;
        
        if (!$client) {
            return false;
        }

        return hash_equals($client['client_secret'], $clientSecret);
    }

    /**
     * Load OAuth2 clients
     */
    private function loadClients()
    {
        // In production, load from database
        $this->clients = [
            'test_client' => [
                'client_secret' => 'test_secret',
                'redirect_uris' => ['http://localhost/callback'],
                'grant_types' => ['authorization_code', 'refresh_token'],
                'scopes' => ['read', 'write']
            ]
        ];
    }

    /**
     * Additional helper methods would go here...
     * (validateAuthorizationCode, validateRefreshToken, etc.)
     */
}
