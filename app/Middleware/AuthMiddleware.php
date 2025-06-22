<?php

namespace App\Middleware;

use App\Core\Session;
use App\Core\Request;

/**
 * Authentication Middleware
 * 
 * Handles authentication checks and redirects for protected routes.
 */
class AuthMiddleware
{
    private $session;
    private $request;

    public function __construct()
    {
        $this->session = new Session();
        $this->request = new Request();
    }

    /**
     * Handle authentication check
     */
    public function handle($next = null)
    {
        // Check if user is authenticated
        if (!$this->isAuthenticated()) {
            $this->redirectToLogin();
            return false;
        }

        // Check session expiration
        if ($this->isSessionExpired()) {
            $this->destroySession();
            $this->redirectToLogin('Your session has expired. Please login again.');
            return false;
        }

        // Update last activity
        $this->updateLastActivity();

        // Regenerate session ID periodically for security
        $this->regenerateSessionIfNeeded();

        // Continue to next middleware or controller
        if ($next && is_callable($next)) {
            return $next();
        }

        return true;
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated()
    {
        return $this->session->has('admin_id') && 
               $this->session->has('admin_name') && 
               $this->session->has('admin_email');
    }

    /**
     * Check if session is expired
     */
    public function isSessionExpired()
    {
        $lastActivity = $this->session->get('_last_activity');
        
        if (!$lastActivity) {
            return true;
        }

        $config = require __DIR__ . '/../../config/app.php';
        $sessionLifetime = $config['session_lifetime'] ?? 7200; // 2 hours default

        return (time() - $lastActivity) > $sessionLifetime;
    }

    /**
     * Update last activity timestamp
     */
    public function updateLastActivity()
    {
        $this->session->set('_last_activity', time());
    }

    /**
     * Regenerate session ID if needed
     */
    public function regenerateSessionIfNeeded()
    {
        $lastRegeneration = $this->session->get('_last_regeneration', 0);
        
        // Regenerate every 30 minutes
        if ((time() - $lastRegeneration) > 1800) {
            $this->session->regenerate();
            $this->session->set('_last_regeneration', time());
        }
    }

    /**
     * Destroy session
     */
    public function destroySession()
    {
        $this->session->destroy();
    }

    /**
     * Redirect to login page
     */
    public function redirectToLogin($message = null)
    {
        if ($message) {
            $this->session->flash('error', $message);
        }

        // Handle AJAX requests
        if ($this->request->isAjax()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Authentication required',
                'redirect' => '/login'
            ]);
            exit;
        }

        // Regular redirect
        header('Location: /login');
        exit;
    }

    /**
     * Get authenticated admin ID
     */
    public function getAuthUserId()
    {
        return $this->session->get('admin_id');
    }

    /**
     * Get authenticated admin data
     */
    public function getAuthUser()
    {
        return [
            'id' => $this->session->get('admin_id'),
            'name' => $this->session->get('admin_name'),
            'email' => $this->session->get('admin_email'),
            'phone' => $this->session->get('admin_phone')
        ];
    }

    /**
     * Check if user has specific permission (placeholder for future role-based access)
     */
    public function hasPermission($permission)
    {
        // For now, all authenticated admins have all permissions
        // This can be extended for role-based access control
        return $this->isAuthenticated();
    }

    /**
     * Log authentication events
     */
    public function logAuthEvent($event, $details = '')
    {
        $config = require __DIR__ . '/../../config/app.php';
        
        if ($config['log_errors']) {
            $timestamp = date('Y-m-d H:i:s');
            $userId = $this->getAuthUserId() ?? 'guest';
            $ip = $this->request->ip();
            $userAgent = $this->request->userAgent();
            
            $logMessage = "[{$timestamp}] AUTH: {$event} - User: {$userId} - IP: {$ip} - {$details} - UA: {$userAgent}" . PHP_EOL;
            
            error_log($logMessage, 3, $config['error_log_path']);
        }
    }

    /**
     * Check for suspicious activity
     */
    public function checkSuspiciousActivity()
    {
        $ip = $this->request->ip();
        $currentTime = time();
        
        // Get failed login attempts from session
        $failedAttempts = $this->session->get('failed_login_attempts', []);
        
        // Clean old attempts (older than 1 hour)
        $failedAttempts = array_filter($failedAttempts, function($attempt) use ($currentTime) {
            return ($currentTime - $attempt['time']) < 3600;
        });
        
        // Count attempts from current IP
        $ipAttempts = array_filter($failedAttempts, function($attempt) use ($ip) {
            return $attempt['ip'] === $ip;
        });
        
        // Block if more than 5 failed attempts in 1 hour
        if (count($ipAttempts) >= 5) {
            $this->logAuthEvent('BLOCKED', "Too many failed attempts from IP: {$ip}");
            
            if ($this->request->isAjax()) {
                http_response_code(429);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Too many failed attempts. Please try again later.'
                ]);
                exit;
            }
            
            http_response_code(429);
            echo '<h1>429 - Too Many Requests</h1><p>Too many failed login attempts. Please try again later.</p>';
            exit;
        }
        
        // Update session with cleaned attempts
        $this->session->set('failed_login_attempts', $failedAttempts);
        
        return true;
    }

    /**
     * Record failed login attempt
     */
    public function recordFailedAttempt()
    {
        $ip = $this->request->ip();
        $currentTime = time();
        
        $failedAttempts = $this->session->get('failed_login_attempts', []);
        
        $failedAttempts[] = [
            'ip' => $ip,
            'time' => $currentTime,
            'user_agent' => $this->request->userAgent()
        ];
        
        $this->session->set('failed_login_attempts', $failedAttempts);
        
        $this->logAuthEvent('FAILED_LOGIN', "Failed login attempt from IP: {$ip}");
    }

    /**
     * Clear failed login attempts on successful login
     */
    public function clearFailedAttempts()
    {
        $this->session->remove('failed_login_attempts');
    }

    /**
     * Create login session
     */
    public function createLoginSession($admin)
    {
        // Clear any existing session data
        $this->session->clear();
        
        // Set admin session data
        $this->session->set('admin_id', $admin['id']);
        $this->session->set('admin_name', $admin['name']);
        $this->session->set('admin_email', $admin['email']);
        $this->session->set('admin_phone', $admin['phone']);
        
        // Set security timestamps
        $this->session->set('_last_activity', time());
        $this->session->set('_last_regeneration', time());
        $this->session->set('_login_time', time());
        $this->session->set('_login_ip', $this->request->ip());
        
        // Regenerate session ID for security
        $this->session->regenerate();
        
        // Clear failed attempts
        $this->clearFailedAttempts();
        
        // Log successful login
        $this->logAuthEvent('LOGIN_SUCCESS', "Admin {$admin['name']} (ID: {$admin['id']}) logged in");
    }

    /**
     * Handle logout
     */
    public function logout()
    {
        $adminId = $this->getAuthUserId();
        $adminName = $this->session->get('admin_name', 'Unknown');
        
        // Log logout
        $this->logAuthEvent('LOGOUT', "Admin {$adminName} (ID: {$adminId}) logged out");
        
        // Destroy session
        $this->destroySession();
    }
}
