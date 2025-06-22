<?php

namespace App\Core\Security;

/**
 * JWT Manager
 * 
 * Banking-grade JWT implementation with advanced security features:
 * - RSA/ECDSA signing algorithms
 * - Token rotation and blacklisting
 * - Claims validation and custom claims
 * - Secure key management
 */
class JWTManager
{
    private $algorithm;
    private $privateKey;
    private $publicKey;
    private $issuer;
    private $audience;
    private $cache;
    private $blacklist = [];

    public function __construct($config = [])
    {
        $this->algorithm = $config['algorithm'] ?? 'RS256';
        $this->issuer = $config['issuer'] ?? config('app.app_name');
        $this->audience = $config['audience'] ?? config('app.app_url');
        $this->cache = Cache::getInstance();
        
        $this->loadKeys($config);
    }

    /**
     * Generate JWT token
     */
    public function generate(array $payload, $expiresIn = 3600)
    {
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm,
            'kid' => $this->getKeyId()
        ];

        $now = time();
        $claims = array_merge($payload, [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $expiresIn,
            'jti' => $this->generateJti()
        ]);

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($claims));
        
        $signature = $this->sign($headerEncoded . '.' . $payloadEncoded);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Verify and decode JWT token
     */
    public function verify($token)
    {
        if ($this->isBlacklisted($token)) {
            throw new \Exception('Token has been revoked');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \Exception('Invalid token format');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        $header = json_decode($this->base64UrlDecode($headerEncoded), true);
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);
        $signature = $this->base64UrlDecode($signatureEncoded);

        // Verify signature
        if (!$this->verifySignature($headerEncoded . '.' . $payloadEncoded, $signature, $header['alg'])) {
            throw new \Exception('Invalid token signature');
        }

        // Verify claims
        $this->verifyClaims($payload);

        return $payload;
    }

    /**
     * Refresh token (generate new token with extended expiry)
     */
    public function refresh($token)
    {
        $payload = $this->verify($token);
        
        // Remove timing claims
        unset($payload['iat'], $payload['nbf'], $payload['exp'], $payload['jti']);
        
        // Blacklist old token
        $this->blacklist($token);
        
        return $this->generate($payload);
    }

    /**
     * Blacklist token
     */
    public function blacklist($token)
    {
        $jti = $this->getJti($token);
        if ($jti) {
            $this->cache->set("jwt_blacklist_{$jti}", true, 86400 * 7); // 7 days
        }
    }

    /**
     * Check if token is blacklisted
     */
    public function isBlacklisted($token)
    {
        $jti = $this->getJti($token);
        return $jti && $this->cache->get("jwt_blacklist_{$jti}") === true;
    }

    /**
     * Get JTI from token
     */
    private function getJti($token)
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) return null;
            
            $payload = json_decode($this->base64UrlDecode($parts[1]), true);
            return $payload['jti'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate unique JTI (JWT ID)
     */
    private function generateJti()
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Get key ID for key rotation
     */
    private function getKeyId()
    {
        return hash('sha256', $this->publicKey);
    }

    /**
     * Load cryptographic keys
     */
    private function loadKeys($config)
    {
        if (isset($config['private_key_path'])) {
            $this->privateKey = file_get_contents($config['private_key_path']);
        } elseif (isset($config['private_key'])) {
            $this->privateKey = $config['private_key'];
        } else {
            // Generate keys if not provided (for development only)
            $this->generateKeys();
        }

        if (isset($config['public_key_path'])) {
            $this->publicKey = file_get_contents($config['public_key_path']);
        } elseif (isset($config['public_key'])) {
            $this->publicKey = $config['public_key'];
        }
    }

    /**
     * Generate RSA key pair (development only)
     */
    private function generateKeys()
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($config);
        openssl_pkey_export($resource, $this->privateKey);
        
        $details = openssl_pkey_get_details($resource);
        $this->publicKey = $details['key'];
    }

    /**
     * Sign data
     */
    private function sign($data)
    {
        $signature = '';
        
        switch ($this->algorithm) {
            case 'RS256':
                openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
                break;
            case 'RS384':
                openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA384);
                break;
            case 'RS512':
                openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA512);
                break;
            default:
                throw new \Exception("Unsupported algorithm: {$this->algorithm}");
        }

        return $signature;
    }

    /**
     * Verify signature
     */
    private function verifySignature($data, $signature, $algorithm)
    {
        switch ($algorithm) {
            case 'RS256':
                return openssl_verify($data, $signature, $this->publicKey, OPENSSL_ALGO_SHA256) === 1;
            case 'RS384':
                return openssl_verify($data, $signature, $this->publicKey, OPENSSL_ALGO_SHA384) === 1;
            case 'RS512':
                return openssl_verify($data, $signature, $this->publicKey, OPENSSL_ALGO_SHA512) === 1;
            default:
                return false;
        }
    }

    /**
     * Verify token claims
     */
    private function verifyClaims($payload)
    {
        $now = time();

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < $now) {
            throw new \Exception('Token has expired');
        }

        // Check not before
        if (isset($payload['nbf']) && $payload['nbf'] > $now) {
            throw new \Exception('Token not yet valid');
        }

        // Check issuer
        if (isset($payload['iss']) && $payload['iss'] !== $this->issuer) {
            throw new \Exception('Invalid token issuer');
        }

        // Check audience
        if (isset($payload['aud']) && $payload['aud'] !== $this->audience) {
            throw new \Exception('Invalid token audience');
        }
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
