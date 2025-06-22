<?php

namespace App\Middleware;

use App\Core\Session;
use App\Core\Request;
use App\Core\View;

/**
 * Rate Limiting Middleware
 * 
 * Handles rate limiting to prevent abuse and brute force attacks.
 */
class RateLimitMiddleware
{
    private $session;
    private $request;
    private $limits;

    public function __construct()
    {
        $this->session = new Session();
        $this->request = new Request();
        
        // Define rate limits (requests per time window in seconds)
        $this->limits = [
            'login' => ['requests' => 5, 'window' => 900], // 5 attempts per 15 minutes
            'api' => ['requests' => 60, 'window' => 60],   // 60 requests per minute
            'general' => ['requests' => 100, 'window' => 60], // 100 requests per minute
        ];
    }

    /**
     * Handle rate limiting
     */
    public function handle($limitType = 'general', $next = null)
    {
        if (!$this->checkRateLimit($limitType)) {
            $this->handleRateLimitExceeded($limitType);
            return false;
        }

        // Record this request
        $this->recordRequest($limitType);

        // Continue to next middleware or controller
        if ($next && is_callable($next)) {
            return $next();
        }

        return true;
    }

    /**
     * Check if rate limit is exceeded
     */
    public function checkRateLimit($limitType)
    {
        $limit = $this->limits[$limitType] ?? $this->limits['general'];
        $ip = $this->request->ip();
        $currentTime = time();
        
        // Get request history for this IP and limit type
        $sessionKey = "rate_limit_{$limitType}_{$ip}";
        $requests = $this->session->get($sessionKey, []);
        
        // Clean old requests outside the time window
        $requests = array_filter($requests, function($timestamp) use ($currentTime, $limit) {
            return ($currentTime - $timestamp) < $limit['window'];
        });
        
        // Check if limit is exceeded
        if (count($requests) >= $limit['requests']) {
            $this->logRateLimitExceeded($limitType, $ip, count($requests));
            return false;
        }
        
        return true;
    }

    /**
     * Record a request
     */
    public function recordRequest($limitType)
    {
        $ip = $this->request->ip();
        $currentTime = time();
        $limit = $this->limits[$limitType] ?? $this->limits['general'];
        
        $sessionKey = "rate_limit_{$limitType}_{$ip}";
        $requests = $this->session->get($sessionKey, []);
        
        // Add current request
        $requests[] = $currentTime;
        
        // Clean old requests
        $requests = array_filter($requests, function($timestamp) use ($currentTime, $limit) {
            return ($currentTime - $timestamp) < $limit['window'];
        });
        
        // Store updated requests
        $this->session->set($sessionKey, $requests);
    }

    /**
     * Handle rate limit exceeded
     */
    private function handleRateLimitExceeded($limitType)
    {
        $limit = $this->limits[$limitType] ?? $this->limits['general'];
        $retryAfter = $limit['window'];
        
        // Set retry-after header
        header("Retry-After: {$retryAfter}");
        
        // Handle AJAX requests
        if ($this->request->isAjax()) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $retryAfter
            ]);
            exit;
        }

        // Handle regular requests
        http_response_code(429);

        // Try to use the 429 error view
        $errorTemplate = __DIR__ . '/../Views/errors/429.php';
        if (file_exists($errorTemplate)) {
            $view = new View();
            echo $view->render('errors/429', [
                'retry_after' => $retryAfter,
                'minutes' => ceil($retryAfter / 60)
            ]);
        } else {
            // Fallback to simple HTML
            echo $this->getRateLimitPage($limitType, $retryAfter);
        }
        exit;
    }

    /**
     * Get rate limit exceeded page
     */
    private function getRateLimitPage($limitType, $retryAfter)
    {
        $minutes = ceil($retryAfter / 60);
        
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Limit Exceeded - Diamond Max Admin</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #e74c3c; margin-bottom: 20px; }
        p { color: #666; line-height: 1.6; margin-bottom: 20px; }
        .retry-info { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚫 Rate Limit Exceeded</h1>
        <p>You have made too many requests in a short period of time.</p>
        <div class="retry-info">
            <strong>Please wait approximately ' . $minutes . ' minute(s) before trying again.</strong>
        </div>
        <p>This limit is in place to protect our servers and ensure fair usage for all users.</p>
        <a href="javascript:history.back()" class="btn">Go Back</a>
    </div>
    
    <script>
        // Auto-refresh after retry period
        setTimeout(function() {
            window.location.reload();
        }, ' . ($retryAfter * 1000) . ');
    </script>
</body>
</html>';
    }

    /**
     * Log rate limit exceeded event
     */
    private function logRateLimitExceeded($limitType, $ip, $requestCount)
    {
        $config = require __DIR__ . '/../../config/app.php';
        
        if ($config['log_errors']) {
            $timestamp = date('Y-m-d H:i:s');
            $userAgent = $this->request->userAgent();
            $uri = $this->request->uri();
            $method = $this->request->method();
            
            $logMessage = "[{$timestamp}] RATE_LIMIT: {$limitType} limit exceeded - IP: {$ip} - Requests: {$requestCount} - Method: {$method} - URI: {$uri} - UA: {$userAgent}" . PHP_EOL;
            
            error_log($logMessage, 3, $config['error_log_path']);
        }
    }

    /**
     * Get remaining requests for a limit type
     */
    public function getRemainingRequests($limitType = 'general')
    {
        $limit = $this->limits[$limitType] ?? $this->limits['general'];
        $ip = $this->request->ip();
        $currentTime = time();
        
        $sessionKey = "rate_limit_{$limitType}_{$ip}";
        $requests = $this->session->get($sessionKey, []);
        
        // Clean old requests
        $requests = array_filter($requests, function($timestamp) use ($currentTime, $limit) {
            return ($currentTime - $timestamp) < $limit['window'];
        });
        
        return max(0, $limit['requests'] - count($requests));
    }

    /**
     * Get time until rate limit resets
     */
    public function getResetTime($limitType = 'general')
    {
        $limit = $this->limits[$limitType] ?? $this->limits['general'];
        $ip = $this->request->ip();
        $currentTime = time();
        
        $sessionKey = "rate_limit_{$limitType}_{$ip}";
        $requests = $this->session->get($sessionKey, []);
        
        if (empty($requests)) {
            return 0;
        }
        
        $oldestRequest = min($requests);
        return max(0, $limit['window'] - ($currentTime - $oldestRequest));
    }

    /**
     * Clear rate limit for IP (admin function)
     */
    public function clearRateLimit($limitType, $ip = null)
    {
        if (!$ip) {
            $ip = $this->request->ip();
        }
        
        $sessionKey = "rate_limit_{$limitType}_{$ip}";
        $this->session->remove($sessionKey);
    }

    /**
     * Check if IP is currently rate limited
     */
    public function isRateLimited($limitType = 'general')
    {
        return !$this->checkRateLimit($limitType);
    }
}
