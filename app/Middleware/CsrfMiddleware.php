<?php

namespace App\Middleware;

use App\Core\Session;
use App\Core\Request;
use App\Core\Logger;

/**
 * CSRF Protection Middleware
 * 
 * Handles CSRF token validation for POST, PUT, PATCH, DELETE requests.
 */
class CsrfMiddleware
{
    private $session;
    private $request;
    private $logger;
    private $whitelistedRoutes = ['/health', '/api/health'];

    public function __construct()
    {
        $this->session = new Session();
        $this->request = new Request();
        $this->logger = new Logger();
    }

    /**
     * Handle CSRF protection
     */
    public function handle($next = null)
    {
        // Skip CSRF check for GET, HEAD, OPTIONS requests
        if (in_array($this->request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            if ($next && is_callable($next)) {
                return $next();
            }
            return true;
        }

        // Validate CSRF token for state-changing requests
        if (!$this->validateCsrfToken()) {
            $this->handleCsrfFailure();
            return false;
        }

        // Continue to next middleware or controller
        if ($next && is_callable($next)) {
            return $next();
        }

        return true;
    }

    /**
     * Validate CSRF token
     */
    public function validateCsrfToken()
    {
        $token = $this->getTokenFromRequest();
        $sessionToken = $this->session->getCsrfToken();

        if (!$token || !$sessionToken) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Get CSRF token from request
     */
    private function getTokenFromRequest()
    {
        // Check POST data first
        $token = $this->request->input('_token');
        
        if (!$token) {
            // Check headers (for AJAX requests)
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }
        
        if (!$token) {
            // Check X-Requested-With header token
            $token = $_SERVER['HTTP_X_XSRF_TOKEN'] ?? null;
        }

        return $token;
    }

    /**
     * Handle CSRF validation failure
     */
    private function handleCsrfFailure()
    {
        $this->logCsrfFailure();

        // Handle AJAX requests
        if ($this->request->isAjax()) {
            http_response_code(419);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'CSRF token mismatch. Please refresh the page and try again.',
                'error_code' => 'CSRF_TOKEN_MISMATCH'
            ]);
            exit;
        }

        // Handle regular form submissions
        $this->session->flash('error', 'Security token mismatch. Please try again.');
        
        // Redirect back to previous page
        $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard';
        header("Location: {$referer}");
        exit;
    }

    /**
     * Log CSRF failure
     */
    private function logCsrfFailure()
    {
        $config = require __DIR__ . '/../../config/app.php';
        
        if ($config['log_errors']) {
            $timestamp = date('Y-m-d H:i:s');
            $ip = $this->request->ip();
            $userAgent = $this->request->userAgent();
            $uri = $this->request->uri();
            $method = $this->request->method();
            
            $logMessage = "[{$timestamp}] CSRF: Token mismatch - IP: {$ip} - Method: {$method} - URI: {$uri} - UA: {$userAgent}" . PHP_EOL;
            
            error_log($logMessage, 3, $config['error_log_path']);
        }
    }

    /**
     * Generate new CSRF token
     */
    public function generateToken()
    {
        return $this->session->getCsrfToken();
    }

    /**
     * Get CSRF token for forms
     */
    public function getTokenField()
    {
        $token = $this->generateToken();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Get CSRF token for AJAX requests
     */
    public function getTokenForAjax()
    {
        return $this->generateToken();
    }

    /**
     * Verify token manually (for custom validation)
     */
    public function verifyToken($token)
    {
        $sessionToken = $this->session->getCsrfToken();
        return $token && $sessionToken && hash_equals($sessionToken, $token);
    }
}
