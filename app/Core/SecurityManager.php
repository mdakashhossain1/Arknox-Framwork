<?php

namespace App\Core;

/**
 * Security Manager
 * 
 * Provides comprehensive security features including input sanitization,
 * XSS protection, SQL injection prevention, and security headers.
 */
class SecurityManager
{
    private $config;
    private $logger;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/app.php';
        $this->logger = new Logger();
    }

    /**
     * Sanitize input data
     */
    public function sanitizeInput($data, $type = 'string')
    {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return $this->sanitizeInput($item, $type);
            }, $data);
        }

        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
            
            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);
            
            case 'int':
            case 'integer':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
            
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            
            case 'html':
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            case 'sql':
                return $this->escapeSqlString($data);
            
            case 'filename':
                return $this->sanitizeFilename($data);
            
            case 'alphanumeric':
                return preg_replace('/[^a-zA-Z0-9]/', '', $data);
            
            case 'slug':
                return $this->createSlug($data);
            
            default: // string
                return trim(strip_tags($data));
        }
    }

    /**
     * Escape HTML output
     */
    public function escapeHtml($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'escapeHtml'], $data);
        }
        
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escape for JavaScript output
     */
    public function escapeJs($data)
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    /**
     * Escape for CSS output
     */
    public function escapeCss($data)
    {
        return preg_replace('/[^a-zA-Z0-9\-_]/', '', $data);
    }

    /**
     * Escape for URL output
     */
    public function escapeUrl($data)
    {
        return urlencode($data);
    }

    /**
     * Validate and sanitize file upload
     */
    public function validateFileUpload($file, array $options = [])
    {
        $defaults = [
            'max_size' => 5 * 1024 * 1024, // 5MB
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
            'allowed_mimes' => [
                'image/jpeg', 'image/png', 'image/gif', 
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
            'scan_virus' => false,
            'check_dimensions' => false,
            'max_width' => 2000,
            'max_height' => 2000
        ];

        $options = array_merge($defaults, $options);
        $errors = [];

        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'No file uploaded or invalid upload';
            return ['valid' => false, 'errors' => $errors];
        }

        // Check file size
        if ($file['size'] > $options['max_size']) {
            $errors[] = 'File size exceeds maximum allowed size';
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $options['allowed_types'])) {
            $errors[] = 'File type not allowed';
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $options['allowed_mimes'])) {
            $errors[] = 'Invalid file format';
        }

        // Check if it's actually an image for image files
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                $errors[] = 'Invalid image file';
            } elseif ($options['check_dimensions']) {
                if ($imageInfo[0] > $options['max_width'] || $imageInfo[1] > $options['max_height']) {
                    $errors[] = 'Image dimensions exceed maximum allowed size';
                }
            }
        }

        // Virus scanning (if enabled and available)
        if ($options['scan_virus'] && function_exists('clamav_scan_file')) {
            $scanResult = clamav_scan_file($file['tmp_name']);
            if ($scanResult !== false) {
                $errors[] = 'File failed virus scan';
                $this->logger->warning('Virus detected in uploaded file', [
                    'filename' => $file['name'],
                    'scan_result' => $scanResult
                ]);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized_name' => $this->sanitizeFilename($file['name']),
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => $file['size']
        ];
    }

    /**
     * Generate secure random token
     */
    public function generateSecureToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Hash password securely
     */
    public function hashPassword($password)
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ]);
    }

    /**
     * Verify password
     */
    public function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Check password strength
     */
    public function checkPasswordStrength($password)
    {
        $score = 0;
        $feedback = [];

        // Length check
        if (strlen($password) >= 8) {
            $score += 1;
        } else {
            $feedback[] = 'Password should be at least 8 characters long';
        }

        // Uppercase check
        if (preg_match('/[A-Z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Password should contain at least one uppercase letter';
        }

        // Lowercase check
        if (preg_match('/[a-z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Password should contain at least one lowercase letter';
        }

        // Number check
        if (preg_match('/[0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Password should contain at least one number';
        }

        // Special character check
        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Password should contain at least one special character';
        }

        // Common password check
        if ($this->isCommonPassword($password)) {
            $score -= 2;
            $feedback[] = 'Password is too common, please choose a more unique password';
        }

        $strength = 'weak';
        if ($score >= 4) {
            $strength = 'strong';
        } elseif ($score >= 3) {
            $strength = 'medium';
        }

        return [
            'score' => max(0, $score),
            'strength' => $strength,
            'feedback' => $feedback
        ];
    }

    /**
     * Set security headers
     */
    public function setSecurityHeaders()
    {
        // Prevent XSS attacks
        header('X-XSS-Protection: 1; mode=block');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
               "font-src 'self' https://fonts.gstatic.com; " .
               "img-src 'self' data: https:; " .
               "connect-src 'self';";
        header("Content-Security-Policy: {$csp}");
        
        // HTTPS enforcement (if enabled)
        if ($this->config['force_https'] ?? false) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Rate limiting check
     */
    public function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300)
    {
        $key = "rate_limit_{$identifier}";
        $attempts = $this->getCacheValue($key, 0);
        
        if ($attempts >= $maxAttempts) {
            $this->logger->warning('Rate limit exceeded', [
                'identifier' => $identifier,
                'attempts' => $attempts,
                'max_attempts' => $maxAttempts
            ]);
            return false;
        }
        
        $this->setCacheValue($key, $attempts + 1, $timeWindow);
        return true;
    }

    /**
     * Log security event
     */
    public function logSecurityEvent($event, array $context = [])
    {
        $context['ip'] = $this->getClientIp();
        $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $context['timestamp'] = time();
        
        $this->logger->warning("Security Event: {$event}", $context);
    }

    /**
     * Private helper methods
     */
    private function escapeSqlString($string)
    {
        return str_replace(["'", '"', '\\', "\0"], ["''", '""', '\\\\', ''], $string);
    }

    private function sanitizeFilename($filename)
    {
        // Remove path traversal attempts
        $filename = basename($filename);
        
        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Prevent hidden files
        $filename = ltrim($filename, '.');
        
        // Ensure it's not empty
        if (empty($filename)) {
            $filename = 'file_' . time();
        }
        
        return $filename;
    }

    private function createSlug($string)
    {
        $slug = strtolower(trim($string));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    private function isCommonPassword($password)
    {
        $commonPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123',
            'password123', 'admin', 'letmein', 'welcome', 'monkey',
            'dragon', 'master', 'shadow', 'superman', 'michael'
        ];
        
        return in_array(strtolower($password), $commonPasswords);
    }

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

    private function getCacheValue($key, $default = null)
    {
        // Simple file-based cache for rate limiting
        $cacheFile = sys_get_temp_dir() . '/security_cache_' . md5($key);
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && $data['expires'] > time()) {
                return $data['value'];
            }
        }
        
        return $default;
    }

    private function setCacheValue($key, $value, $ttl = 300)
    {
        $cacheFile = sys_get_temp_dir() . '/security_cache_' . md5($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        file_put_contents($cacheFile, json_encode($data));
    }
}
