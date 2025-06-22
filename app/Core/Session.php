<?php

namespace App\Core;

/**
 * Session Class
 * 
 * Provides convenient methods for session management
 * including flash messages and secure session handling.
 */
class Session
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            $config = require __DIR__ . '/../../config/app.php';
            
            // Configure session settings
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            
            session_name($config['session_name']);
            session_start();
            
            // Regenerate session ID periodically for security
            if (!$this->has('_session_started')) {
                session_regenerate_id(true);
                $this->set('_session_started', time());
            }
        }
    }

    /**
     * Set session value
     */
    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Get session value
     */
    public function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session key exists
     */
    public function has($key)
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session key
     */
    public function remove($key)
    {
        unset($_SESSION[$key]);
    }

    /**
     * Clear all session data
     */
    public function clear()
    {
        $_SESSION = [];
    }

    /**
     * Destroy session
     */
    public function destroy()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    /**
     * Set flash message
     */
    public function flash($key, $message)
    {
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
        if (!isset($_SESSION['_flash'][$key])) {
            return null;
        }

        $message = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        
        return $message;
    }

    /**
     * Check if flash message exists
     */
    public function hasFlash($key)
    {
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Get all flash messages
     */
    public function getAllFlash()
    {
        $flash = $_SESSION['_flash'] ?? [];
        $_SESSION['_flash'] = [];
        return $flash;
    }

    /**
     * Regenerate session ID
     */
    public function regenerate($deleteOld = true)
    {
        session_regenerate_id($deleteOld);
    }

    /**
     * Get session ID
     */
    public function getId()
    {
        return session_id();
    }

    /**
     * Set session lifetime
     */
    public function setLifetime($seconds)
    {
        ini_set('session.gc_maxlifetime', $seconds);
        ini_set('session.cookie_lifetime', $seconds);
    }

    /**
     * Check if session is expired
     */
    public function isExpired($maxLifetime = 7200)
    {
        $lastActivity = $this->get('_last_activity', time());
        return (time() - $lastActivity) > $maxLifetime;
    }

    /**
     * Update last activity timestamp
     */
    public function updateActivity()
    {
        $this->set('_last_activity', time());
    }

    /**
     * Get CSRF token
     */
    public function getCsrfToken()
    {
        if (!$this->has('_token')) {
            $this->set('_token', bin2hex(random_bytes(32)));
        }
        return $this->get('_token');
    }

    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken($token)
    {
        return hash_equals($this->getCsrfToken(), $token);
    }
}
