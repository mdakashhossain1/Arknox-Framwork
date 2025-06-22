<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security\ThreatDetector;
use App\Core\Security\JWTManager;
use App\Core\SecurityManager;
use App\Core\SecurityAudit;

/**
 * Banking-Grade Security Middleware
 * 
 * Comprehensive security middleware that integrates all security features:
 * - Advanced threat detection
 * - JWT token validation
 * - Rate limiting
 * - IP filtering
 * - Security headers
 * - Audit logging
 */
class BankingSecurityMiddleware
{
    private $threatDetector;
    private $jwtManager;
    private $securityManager;
    private $securityAudit;
    private $config;

    public function __construct()
    {
        $this->threatDetector = new ThreatDetector();
        $this->jwtManager = new JWTManager();
        $this->securityManager = new SecurityManager();
        $this->securityAudit = new SecurityAudit();
        $this->config = config('security', []);
    }

    /**
     * Handle the request through security pipeline
     */
    public function handle(Request $request, $next = null)
    {
        // 1. Threat Detection Analysis
        $threatAnalysis = $this->threatDetector->analyzeRequest($request);
        
        if ($threatAnalysis['blocked']) {
            $this->securityAudit->logSecurityEvent('request_blocked_by_threat_detector', 'critical', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'risk_score' => $threatAnalysis['risk_score'],
                'threats' => $threatAnalysis['threats']
            ]);
            
            return $this->createBlockedResponse($request, $threatAnalysis);
        }

        // 2. Rate Limiting
        if (!$this->checkRateLimit($request)) {
            $this->securityAudit->logSecurityEvent('rate_limit_exceeded', 'high', [
                'ip' => $request->ip(),
                'path' => $request->path()
            ]);
            
            return Response::json(['error' => 'Rate limit exceeded'], 429);
        }

        // 3. IP Filtering
        if (!$this->checkIPFiltering($request)) {
            $this->securityAudit->logSecurityEvent('ip_blocked', 'high', [
                'ip' => $request->ip(),
                'reason' => 'IP in blacklist or not in whitelist'
            ]);
            
            return Response::json(['error' => 'Access denied'], 403);
        }

        // 4. JWT Token Validation (for API routes)
        if ($this->requiresAuthentication($request)) {
            $authResult = $this->validateAuthentication($request);
            if (!$authResult['valid']) {
                $this->securityAudit->logAuthentication('jwt_validation_failed', null, false, [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                    'reason' => $authResult['reason']
                ]);
                
                return Response::json(['error' => 'Unauthorized'], 401);
            }
            
            // Add user context to request
            $request->merge(['auth_user' => $authResult['user']]);
        }

        // 5. CSRF Protection
        if ($this->requiresCSRFProtection($request)) {
            if (!$this->validateCSRFToken($request)) {
                $this->securityAudit->logSecurityEvent('csrf_validation_failed', 'high', [
                    'ip' => $request->ip(),
                    'path' => $request->path()
                ]);
                
                return Response::json(['error' => 'CSRF token mismatch'], 403);
            }
        }

        // 6. Input Sanitization
        $this->sanitizeInput($request);

        // 7. Set Security Headers
        $response = $next ? $next($request) : new Response();
        $this->setSecurityHeaders($response);

        // 8. Log successful request
        $this->securityAudit->logSecurityEvent('request_processed', 'info', [
            'ip' => $request->ip(),
            'path' => $request->path(),
            'method' => $request->method(),
            'risk_score' => $threatAnalysis['risk_score']
        ]);

        return $response;
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit(Request $request)
    {
        $identifier = $this->getRateLimitIdentifier($request);
        $limits = $this->getRateLimits($request);
        
        foreach ($limits as $window => $maxRequests) {
            if (!$this->securityManager->checkRateLimit($identifier, $maxRequests, $window)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get rate limit identifier
     */
    private function getRateLimitIdentifier(Request $request)
    {
        // Use IP + User Agent for anonymous requests
        // Use user ID for authenticated requests
        $authUser = $request->input('auth_user');
        
        if ($authUser) {
            return 'user_' . $authUser['id'];
        }
        
        return 'ip_' . $request->ip() . '_' . md5($request->userAgent());
    }

    /**
     * Get rate limits based on request type
     */
    private function getRateLimits(Request $request)
    {
        $path = $request->path();
        
        // API endpoints have stricter limits
        if (strpos($path, '/api/') === 0) {
            return [
                60 => 100,    // 100 requests per minute
                3600 => 1000  // 1000 requests per hour
            ];
        }
        
        // Login endpoints have very strict limits
        if (strpos($path, '/login') !== false || strpos($path, '/auth') !== false) {
            return [
                60 => 5,      // 5 requests per minute
                3600 => 20    // 20 requests per hour
            ];
        }
        
        // Default limits
        return [
            60 => 200,    // 200 requests per minute
            3600 => 2000  // 2000 requests per hour
        ];
    }

    /**
     * Check IP filtering
     */
    private function checkIPFiltering(Request $request)
    {
        $ip = $request->ip();
        
        // Check whitelist (if configured)
        $whitelist = $this->config['ip_whitelist'] ?? [];
        if (!empty($whitelist) && !in_array($ip, $whitelist)) {
            return false;
        }
        
        // Check blacklist
        $blacklist = $this->config['ip_blacklist'] ?? [];
        if (in_array($ip, $blacklist)) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if request requires authentication
     */
    private function requiresAuthentication(Request $request)
    {
        $path = $request->path();
        
        // API routes require authentication
        if (strpos($path, '/api/') === 0) {
            return true;
        }
        
        // Admin routes require authentication
        if (strpos($path, '/admin/') === 0) {
            return true;
        }
        
        return false;
    }

    /**
     * Validate authentication
     */
    private function validateAuthentication(Request $request)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return ['valid' => false, 'reason' => 'No token provided'];
        }
        
        try {
            $payload = $this->jwtManager->verify($token);
            return [
                'valid' => true,
                'user' => $payload
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'reason' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if request requires CSRF protection
     */
    private function requiresCSRFProtection(Request $request)
    {
        // Only for state-changing operations
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']) &&
               !$request->expectsJson(); // API requests use JWT, not CSRF
    }

    /**
     * Validate CSRF token
     */
    private function validateCSRFToken(Request $request)
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');
        
        if (!$token) {
            return false;
        }
        
        return $this->securityManager->validateCSRFToken($token);
    }

    /**
     * Sanitize request input
     */
    private function sanitizeInput(Request $request)
    {
        $sanitized = [];
        
        foreach ($request->all() as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = $this->securityManager->sanitizeInput($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        $request->replace($sanitized);
    }

    /**
     * Set security headers
     */
    private function setSecurityHeaders(Response $response)
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
        ];
        
        foreach ($headers as $name => $value) {
            $response->header($name, $value);
        }
        
        return $response;
    }

    /**
     * Create blocked response
     */
    private function createBlockedResponse(Request $request, $threatAnalysis)
    {
        if ($request->expectsJson()) {
            return Response::json([
                'error' => 'Request blocked for security reasons',
                'code' => 'SECURITY_VIOLATION'
            ], 403);
        }
        
        // Return HTML error page for web requests
        return Response::make('<h1>Access Denied</h1><p>Your request has been blocked for security reasons.</p>', 403);
    }
}
