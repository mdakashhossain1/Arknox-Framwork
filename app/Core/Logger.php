<?php

namespace App\Core;

/**
 * Logger Class
 * 
 * Provides comprehensive logging functionality with different log levels.
 */
class Logger
{
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';

    private $config;
    private $logPath;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/app.php';
        $this->logPath = $this->config['log_path'] ?? __DIR__ . '/../../logs';
        
        // Ensure log directory exists
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * Log emergency message
     */
    public function emergency($message, array $context = [])
    {
        return $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * Log alert message
     */
    public function alert($message, array $context = [])
    {
        return $this->log(self::ALERT, $message, $context);
    }

    /**
     * Log critical message
     */
    public function critical($message, array $context = [])
    {
        return $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Log error message
     */
    public function error($message, array $context = [])
    {
        return $this->log(self::ERROR, $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning($message, array $context = [])
    {
        return $this->log(self::WARNING, $message, $context);
    }

    /**
     * Log notice message
     */
    public function notice($message, array $context = [])
    {
        return $this->log(self::NOTICE, $message, $context);
    }

    /**
     * Log info message
     */
    public function info($message, array $context = [])
    {
        return $this->log(self::INFO, $message, $context);
    }

    /**
     * Log debug message
     */
    public function debug($message, array $context = [])
    {
        return $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Log message with specified level
     */
    public function log($level, $message, array $context = [])
    {
        // Check if logging is enabled
        if (!($this->config['log_errors'] ?? true)) {
            return false;
        }

        // Check log level
        if (!$this->shouldLog($level)) {
            return false;
        }

        try {
            $logEntry = $this->formatLogEntry($level, $message, $context);
            $logFile = $this->getLogFile($level);
            
            return file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) !== false;
        } catch (\Exception $e) {
            // Fallback to error_log if our logging fails
            error_log("Logger failed: " . $e->getMessage());
            error_log("Original message: [{$level}] {$message}");
            return false;
        }
    }

    /**
     * Check if we should log this level
     */
    private function shouldLog($level)
    {
        $logLevel = $this->config['log_level'] ?? self::INFO;
        
        $levels = [
            self::EMERGENCY => 0,
            self::ALERT => 1,
            self::CRITICAL => 2,
            self::ERROR => 3,
            self::WARNING => 4,
            self::NOTICE => 5,
            self::INFO => 6,
            self::DEBUG => 7
        ];

        $currentLevelValue = $levels[$logLevel] ?? 6;
        $messageLevelValue = $levels[$level] ?? 6;

        return $messageLevelValue <= $currentLevelValue;
    }

    /**
     * Format log entry
     */
    private function formatLogEntry($level, $message, array $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        
        // Add request context
        $requestContext = $this->getRequestContext();
        $context = array_merge($requestContext, $context);
        
        // Format message
        $message = $this->interpolate($message, $context);
        
        // Build log entry
        $logEntry = "[{$timestamp}] {$levelUpper}: {$message}";
        
        // Add context if present
        if (!empty($context)) {
            $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $logEntry .= " Context: {$contextJson}";
        }
        
        return $logEntry . PHP_EOL;
    }

    /**
     * Get log file path for level
     */
    private function getLogFile($level)
    {
        $date = date('Y-m-d');
        
        // Separate files for different levels
        if (in_array($level, [self::EMERGENCY, self::ALERT, self::CRITICAL, self::ERROR])) {
            return $this->logPath . "/error-{$date}.log";
        } elseif ($level === self::WARNING) {
            return $this->logPath . "/warning-{$date}.log";
        } elseif ($level === self::DEBUG) {
            return $this->logPath . "/debug-{$date}.log";
        } else {
            return $this->logPath . "/app-{$date}.log";
        }
    }

    /**
     * Get request context
     */
    private function getRequestContext()
    {
        return [
            'ip' => $this->getClientIp(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'user_id' => $_SESSION['admin_id'] ?? null,
            'session_id' => session_id() ?: null,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
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
     * Interpolate context values into message placeholders
     */
    private function interpolate($message, array $context = [])
    {
        $replace = [];
        
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        
        return strtr($message, $replace);
    }

    /**
     * Log SQL query
     */
    public function logQuery($sql, array $bindings = [], $time = null)
    {
        if (!($this->config['log_queries'] ?? false)) {
            return false;
        }

        $context = [
            'sql' => $sql,
            'bindings' => $bindings,
            'execution_time' => $time
        ];

        return $this->debug('SQL Query executed', $context);
    }

    /**
     * Log authentication event
     */
    public function logAuth($event, $userId = null, array $context = [])
    {
        $context['auth_event'] = $event;
        $context['user_id'] = $userId;
        
        return $this->info("Authentication: {$event}", $context);
    }

    /**
     * Log security event
     */
    public function logSecurity($event, array $context = [])
    {
        $context['security_event'] = $event;
        
        return $this->warning("Security: {$event}", $context);
    }

    /**
     * Log performance metric
     */
    public function logPerformance($metric, $value, array $context = [])
    {
        $context['performance_metric'] = $metric;
        $context['value'] = $value;
        
        return $this->info("Performance: {$metric} = {$value}", $context);
    }

    /**
     * Clean old log files
     */
    public function cleanOldLogs($days = 30)
    {
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        $cleaned = 0;
        
        if (is_dir($this->logPath)) {
            $files = glob($this->logPath . '/*.log');
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    if (unlink($file)) {
                        $cleaned++;
                    }
                }
            }
        }
        
        $this->info("Log cleanup completed", ['files_cleaned' => $cleaned, 'days' => $days]);
        
        return $cleaned;
    }

    /**
     * Get log statistics
     */
    public function getLogStats()
    {
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'files_by_type' => [],
            'oldest_log' => null,
            'newest_log' => null
        ];
        
        if (is_dir($this->logPath)) {
            $files = glob($this->logPath . '/*.log');
            $stats['total_files'] = count($files);
            
            foreach ($files as $file) {
                $size = filesize($file);
                $stats['total_size'] += $size;
                
                $basename = basename($file);
                $type = explode('-', $basename)[0];
                
                if (!isset($stats['files_by_type'][$type])) {
                    $stats['files_by_type'][$type] = ['count' => 0, 'size' => 0];
                }
                
                $stats['files_by_type'][$type]['count']++;
                $stats['files_by_type'][$type]['size'] += $size;
                
                $mtime = filemtime($file);
                if ($stats['oldest_log'] === null || $mtime < $stats['oldest_log']) {
                    $stats['oldest_log'] = $mtime;
                }
                if ($stats['newest_log'] === null || $mtime > $stats['newest_log']) {
                    $stats['newest_log'] = $mtime;
                }
            }
        }
        
        return $stats;
    }

    /**
     * Static helper to get logger instance
     */
    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new static();
        }
        return $instance;
    }
}
