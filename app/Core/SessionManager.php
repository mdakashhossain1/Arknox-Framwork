<?php

namespace App\Core;

/**
 * Enhanced Session Manager
 * 
 * Provides advanced session management with security features.
 */
class SessionManager
{
    private $config;
    private $isStarted = false;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/app.php';
        $this->configureSession();
    }

    /**
     * Configure session settings
     */
    private function configureSession()
    {
        // Set session configuration
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Set session lifetime
        $lifetime = $this->config['session_lifetime'] ?? 7200; // 2 hours default
        ini_set('session.gc_maxlifetime', $lifetime);
        ini_set('session.cookie_lifetime', $lifetime);
        
        // Set session name
        session_name($this->config['session_name'] ?? 'DIAMONDMAX_ADMIN_SESSION');
        
        // Set session save path if specified
        if (isset($this->config['session_save_path'])) {
            session_save_path($this->config['session_save_path']);
        }
    }

    /**
     * Start session with security checks
     */
    public function start()
    {
        if ($this->isStarted || session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }

        // Start session
        if (!session_start()) {
            throw new \Exception('Failed to start session');
        }

        $this->isStarted = true;

        // Perform security checks
        $this->validateSession();
        $this->regenerateIfNeeded();

        return true;
    }

    /**
     * Validate session security
     */
    private function validateSession()
    {
        // Check if session was created with different IP (basic security)
        if (isset($_SESSION['_ip_address'])) {
            $currentIp = $this->getClientIp();
            if ($_SESSION['_ip_address'] !== $currentIp) {
                $this->logSecurityEvent('IP_MISMATCH', "Session IP mismatch: {$_SESSION['_ip_address']} vs {$currentIp}");
                $this->destroy();
                return false;
            }
        } else {
            $_SESSION['_ip_address'] = $this->getClientIp();
        }

        // Check user agent (basic fingerprinting)
        if (isset($_SESSION['_user_agent'])) {
            $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if ($_SESSION['_user_agent'] !== $currentUserAgent) {
                $this->logSecurityEvent('USER_AGENT_MISMATCH', 'Session user agent mismatch');
                $this->destroy();
                return false;
            }
        } else {
            $_SESSION['_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }

        return true;
    }

    /**
     * Regenerate session ID if needed
     */
    private function regenerateIfNeeded()
    {
        $lastRegeneration = $_SESSION['_last_regeneration'] ?? 0;
        $regenerationInterval = $this->config['session_regeneration_interval'] ?? 1800; // 30 minutes

        if ((time() - $lastRegeneration) > $regenerationInterval) {
            $this->regenerate();
        }
    }

    /**
     * Regenerate session ID
     */
    public function regenerate($deleteOld = true)
    {
        if (!$this->isStarted) {
            $this->start();
        }

        session_regenerate_id($deleteOld);
        $_SESSION['_last_regeneration'] = time();
        
        $this->logSecurityEvent('SESSION_REGENERATED', 'Session ID regenerated');
    }

    /**
     * Set session value
     */
    public function set($key, $value)
    {
        if (!$this->isStarted) {
            $this->start();
        }

        $_SESSION[$key] = $value;
    }

    /**
     * Get session value
     */
    public function get($key, $default = null)
    {
        if (!$this->isStarted) {
            $this->start();
        }

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session key exists
     */
    public function has($key)
    {
        if (!$this->isStarted) {
            $this->start();
        }

        return isset($_SESSION[$key]);
    }

    /**
     * Remove session key
     */
    public function remove($key)
    {
        if (!$this->isStarted) {
            $this->start();
        }

        unset($_SESSION[$key]);
    }

    /**
     * Clear all session data
     */
    public function clear()
    {
        if (!$this->isStarted) {
            $this->start();
        }

        $_SESSION = [];
    }

    /**
     * Destroy session completely
     */
    public function destroy()
    {
        if (!$this->isStarted) {
            $this->start();
        }

        // Clear session data
        $_SESSION = [];

        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Destroy session
        session_destroy();
        $this->isStarted = false;

        $this->logSecurityEvent('SESSION_DESTROYED', 'Session destroyed');
    }

    /**
     * Set flash message
     */
    public function flash($key, $message)
    {
        if (!$this->isStarted) {
            $this->start();
        }

        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }
        $_SESSION['_flash'][$key] = $message;
    }

    /**
     * Get flash message
     */
    public function getFlash($key)
    {
        if (!$this->isStarted) {
            $this->start();
        }

        if (!isset($_SESSION['_flash'][$key])) {
            return null;
        }

        $message = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        
        return $message;
    }

    /**
     * Get all flash messages
     */
    public function getAllFlash()
    {
        if (!$this->isStarted) {
            $this->start();
        }

        $flash = $_SESSION['_flash'] ?? [];
        $_SESSION['_flash'] = [];
        return $flash;
    }

    /**
     * Generate and get CSRF token
     */
    public function getCsrfToken()
    {
        if (!$this->isStarted) {
            $this->start();
        }

        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken($token)
    {
        $sessionToken = $this->getCsrfToken();
        return $token && hash_equals($sessionToken, $token);
    }

    /**
     * Get session ID
     */
    public function getId()
    {
        return session_id();
    }

    /**
     * Check if session is active
     */
    public function isActive()
    {
        return $this->isStarted && session_status() === PHP_SESSION_ACTIVE;
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
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Log security events
     */
    private function logSecurityEvent($event, $details = '')
    {
        if ($this->config['log_errors']) {
            $timestamp = date('Y-m-d H:i:s');
            $sessionId = $this->getId();
            $ip = $this->getClientIp();
            
            $logMessage = "[{$timestamp}] SESSION_SECURITY: {$event} - Session: {$sessionId} - IP: {$ip} - {$details}" . PHP_EOL;
            
            error_log($logMessage, 3, $this->config['error_log_path']);
        }
    }

    /**
     * Get session statistics
     */
    public function getStats()
    {
        if (!$this->isStarted) {
            $this->start();
        }

        return [
            'session_id' => $this->getId(),
            'is_active' => $this->isActive(),
            'last_regeneration' => $this->get('_last_regeneration', 0),
            'ip_address' => $this->get('_ip_address'),
            'user_agent' => $this->get('_user_agent'),
            'data_size' => strlen(serialize($_SESSION))
        ];
    }
}
