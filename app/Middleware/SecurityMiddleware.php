<?php

namespace App\Middleware;

use App\Core\SecurityManager;
use App\Core\SecurityAudit;
use App\Core\Logger;

/**
 * Security Middleware
 * 
 * Applies comprehensive security measures including headers,
 * input sanitization, and security monitoring.
 */
class SecurityMiddleware
{
    private $securityManager;
    private $securityAudit;
    private $logger;
    private $config;

    public function __construct()
    {
        $this->securityManager = new SecurityManager();
        $this->securityAudit = new SecurityAudit();
        $this->logger = new Logger();
        $this->config = require __DIR__ . '/../../config/security.php';
    }

    /**
     * Handle security middleware
     */
    public function handle($next = null)
    {
        // Set security headers
        $this->setSecurityHeaders();
        
        // Check IP filtering
        if (!$this->checkIpFiltering()) {
            $this->securityAudit->logSecurityEvent('IP_BLOCKED', 'high', [
                'ip' => $this->getClientIp(),
                'reason' => 'IP in blacklist or not in whitelist'
            ]);
            http_response_code(403);
            exit('Access denied');
        }

        // Monitor suspicious activity
        $this->monitorSuspiciousActivity();

        // Sanitize input if enabled
        if ($this->config['input_sanitization']['auto_sanitize']) {
            $this->sanitizeInput();
        }

        // Continue to next middleware
        return $next ? $next() : true;
    }

    /**
     * Set security headers
     */
    private function setSecurityHeaders()
    {
        if (!$this->config['headers']['enabled']) {
            return;
        }

        // X-Frame-Options
        if ($this->config['headers']['x_frame_options']) {
            header('X-Frame-Options: ' . $this->config['headers']['x_frame_options']);
        }

        // X-Content-Type-Options
        if ($this->config['headers']['x_content_type_options']) {
            header('X-Content-Type-Options: ' . $this->config['headers']['x_content_type_options']);
        }

        // X-XSS-Protection
        if ($this->config['headers']['x_xss_protection']) {
            header('X-XSS-Protection: ' . $this->config['headers']['x_xss_protection']);
        }

        // Referrer-Policy
        if ($this->config['headers']['referrer_policy']) {
            header('Referrer-Policy: ' . $this->config['headers']['referrer_policy']);
        }

        // Content-Security-Policy
        if (!empty($this->config['headers']['content_security_policy'])) {
            $csp = [];
            foreach ($this->config['headers']['content_security_policy'] as $directive => $value) {
                $csp[] = "{$directive} {$value}";
            }
            header('Content-Security-Policy: ' . implode('; ', $csp));
        }

        // Strict-Transport-Security (only if HTTPS)
        if (isset($_SERVER['HTTPS']) && $this->config['headers']['strict_transport_security']['enabled']) {
            $hsts = 'max-age=' . $this->config['headers']['strict_transport_security']['max_age'];
            
            if ($this->config['headers']['strict_transport_security']['include_subdomains']) {
                $hsts .= '; includeSubDomains';
            }
            
            if ($this->config['headers']['strict_transport_security']['preload']) {
                $hsts .= '; preload';
            }
            
            header('Strict-Transport-Security: ' . $hsts);
        }

        // Additional security headers
        header('X-Permitted-Cross-Domain-Policies: none');
        header('X-Download-Options: noopen');
        header('X-DNS-Prefetch-Control: off');
    }

    /**
     * Check IP filtering
     */
    private function checkIpFiltering()
    {
        if (!$this->config['ip_filtering']['enabled']) {
            return true;
        }

        $clientIp = $this->getClientIp();

        // Check blacklist
        if (!empty($this->config['ip_filtering']['blacklist'])) {
            foreach ($this->config['ip_filtering']['blacklist'] as $blockedIp) {
                if ($this->ipMatches($clientIp, $blockedIp)) {
                    return false;
                }
            }
        }

        // Check whitelist (if configured)
        if (!empty($this->config['ip_filtering']['whitelist'])) {
            $allowed = false;
            foreach ($this->config['ip_filtering']['whitelist'] as $allowedIp) {
                if ($this->ipMatches($clientIp, $allowedIp)) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Monitor suspicious activity
     */
    private function monitorSuspiciousActivity()
    {
        if (!$this->config['monitoring']['enabled']) {
            return;
        }

        $suspiciousPatterns = [
            // SQL injection patterns
            '/(\s*(union|select|insert|update|delete|drop|create|alter)\s+)/i',
            '/(\s*(or|and)\s+\d+\s*=\s*\d+)/i',
            
            // XSS patterns
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            
            // Path traversal
            '/\.\.[\/\\\\]/i',
            '/\.(exe|bat|cmd|sh|php|pl|cgi)$/i',
            
            // Command injection
            '/[;&|`$()]/i'
        ];

        $requestData = array_merge($_GET, $_POST);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // Check request data
        foreach ($requestData as $key => $value) {
            if (is_string($value)) {
                foreach ($suspiciousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->securityAudit->logSecurityEvent('SUSPICIOUS_INPUT_DETECTED', 'high', [
                            'pattern' => $pattern,
                            'field' => $key,
                            'value' => substr($value, 0, 100),
                            'uri' => $uri
                        ]);
                        break;
                    }
                }
            }
        }

        // Check user agent
        $suspiciousUserAgents = [
            'sqlmap', 'nikto', 'nmap', 'masscan', 'zap', 'burp',
            'acunetix', 'nessus', 'openvas', 'w3af'
        ];

        foreach ($suspiciousUserAgents as $suspiciousUA) {
            if (stripos($userAgent, $suspiciousUA) !== false) {
                $this->securityAudit->logSecurityEvent('SUSPICIOUS_USER_AGENT', 'medium', [
                    'user_agent' => $userAgent,
                    'pattern' => $suspiciousUA
                ]);
                break;
            }
        }

        // Check for rapid requests (simple rate limiting)
        $this->checkRapidRequests();
    }

    /**
     * Sanitize input data
     */
    private function sanitizeInput()
    {
        // Sanitize GET parameters
        foreach ($_GET as $key => $value) {
            $_GET[$key] = $this->securityManager->sanitizeInput($value);
        }

        // Sanitize POST parameters
        foreach ($_POST as $key => $value) {
            $_POST[$key] = $this->securityManager->sanitizeInput($value);
        }

        // Sanitize COOKIE parameters
        foreach ($_COOKIE as $key => $value) {
            $_COOKIE[$key] = $this->securityManager->sanitizeInput($value);
        }
    }

    /**
     * Check for rapid requests
     */
    private function checkRapidRequests()
    {
        $ip = $this->getClientIp();
        $key = "rapid_requests_{$ip}";
        
        // Simple file-based tracking
        $trackingFile = sys_get_temp_dir() . '/security_tracking_' . md5($key);
        $currentTime = time();
        $timeWindow = 60; // 1 minute
        $maxRequests = 30; // Max 30 requests per minute

        $requests = [];
        if (file_exists($trackingFile)) {
            $data = json_decode(file_get_contents($trackingFile), true);
            if ($data) {
                $requests = array_filter($data, function($timestamp) use ($currentTime, $timeWindow) {
                    return ($currentTime - $timestamp) <= $timeWindow;
                });
            }
        }

        $requests[] = $currentTime;

        if (count($requests) > $maxRequests) {
            $this->securityAudit->logSecurityEvent('RAPID_REQUESTS_DETECTED', 'medium', [
                'ip' => $ip,
                'request_count' => count($requests),
                'time_window' => $timeWindow
            ]);
        }

        // Save updated tracking data
        file_put_contents($trackingFile, json_encode($requests));
    }

    /**
     * Check if IP matches pattern (supports CIDR)
     */
    private function ipMatches($ip, $pattern)
    {
        if ($ip === $pattern) {
            return true;
        }

        // Check CIDR notation
        if (strpos($pattern, '/') !== false) {
            list($subnet, $mask) = explode('/', $pattern);
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && 
                filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                
                $ipLong = ip2long($ip);
                $subnetLong = ip2long($subnet);
                $maskLong = -1 << (32 - (int)$mask);
                
                return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
            }
        }

        return false;
    }

    /**
     * Get client IP address
     */
    private function getClientIp()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Log security event
     */
    private function logSecurityEvent($event, $context = [])
    {
        $context['ip'] = $this->getClientIp();
        $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $context['uri'] = $_SERVER['REQUEST_URI'] ?? '';
        $context['method'] = $_SERVER['REQUEST_METHOD'] ?? '';
        
        $this->logger->warning("Security Event: {$event}", $context);
        $this->securityAudit->logSecurityEvent($event, 'medium', $context);
    }
}
