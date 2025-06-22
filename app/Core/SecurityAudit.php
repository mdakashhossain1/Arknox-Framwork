<?php

namespace App\Core;

/**
 * Security Audit Logger
 * 
 * Provides comprehensive security audit logging for compliance
 * and security monitoring purposes.
 */
class SecurityAudit
{
    private $config;
    private $logger;
    private $auditLogPath;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/security.php';
        $this->logger = new Logger();
        $this->auditLogPath = __DIR__ . '/../../logs/security_audit.log';
        
        // Ensure audit log directory exists
        $logDir = dirname($this->auditLogPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Log authentication event
     */
    public function logAuthentication($event, $userId = null, $success = true, array $context = [])
    {
        if (!$this->config['audit_logging']['log_authentication']) {
            return;
        }

        $auditData = [
            'event_type' => 'authentication',
            'event' => $event,
            'user_id' => $userId,
            'success' => $success,
            'timestamp' => time(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id(),
            'context' => $context
        ];

        $this->writeAuditLog($auditData);
    }

    /**
     * Log authorization event
     */
    public function logAuthorization($resource, $action, $userId = null, $success = true, array $context = [])
    {
        if (!$this->config['audit_logging']['log_authorization']) {
            return;
        }

        $auditData = [
            'event_type' => 'authorization',
            'resource' => $resource,
            'action' => $action,
            'user_id' => $userId,
            'success' => $success,
            'timestamp' => time(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id(),
            'context' => $context
        ];

        $this->writeAuditLog($auditData);
    }

    /**
     * Log data access event
     */
    public function logDataAccess($table, $action, $recordId = null, $userId = null, array $context = [])
    {
        if (!$this->config['audit_logging']['log_data_access']) {
            return;
        }

        $auditData = [
            'event_type' => 'data_access',
            'table' => $table,
            'action' => $action,
            'record_id' => $recordId,
            'user_id' => $userId,
            'timestamp' => time(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id(),
            'context' => $context
        ];

        $this->writeAuditLog($auditData);
    }

    /**
     * Log configuration change
     */
    public function logConfigurationChange($setting, $oldValue, $newValue, $userId = null, array $context = [])
    {
        if (!$this->config['audit_logging']['log_configuration_changes']) {
            return;
        }

        $auditData = [
            'event_type' => 'configuration_change',
            'setting' => $setting,
            'old_value' => $this->sanitizeValue($oldValue),
            'new_value' => $this->sanitizeValue($newValue),
            'user_id' => $userId,
            'timestamp' => time(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id(),
            'context' => $context
        ];

        $this->writeAuditLog($auditData);
    }

    /**
     * Log security event
     */
    public function logSecurityEvent($event, $severity = 'medium', array $context = [])
    {
        $auditData = [
            'event_type' => 'security_event',
            'event' => $event,
            'severity' => $severity,
            'timestamp' => time(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id(),
            'user_id' => $_SESSION['admin_id'] ?? null,
            'context' => $context
        ];

        $this->writeAuditLog($auditData);

        // Also log to regular security log
        $this->logger->warning("Security Event: {$event}", $context);
    }

    /**
     * Log failed attempt
     */
    public function logFailedAttempt($type, $identifier, array $context = [])
    {
        if (!$this->config['audit_logging']['log_failed_attempts']) {
            return;
        }

        $auditData = [
            'event_type' => 'failed_attempt',
            'attempt_type' => $type,
            'identifier' => $identifier,
            'timestamp' => time(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id(),
            'context' => $context
        ];

        $this->writeAuditLog($auditData);
    }

    /**
     * Log file operation
     */
    public function logFileOperation($operation, $filename, $userId = null, $success = true, array $context = [])
    {
        $auditData = [
            'event_type' => 'file_operation',
            'operation' => $operation,
            'filename' => basename($filename), // Only log filename, not full path
            'user_id' => $userId,
            'success' => $success,
            'timestamp' => time(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id(),
            'context' => $context
        ];

        $this->writeAuditLog($auditData);
    }

    /**
     * Get audit log entries
     */
    public function getAuditLogs($filters = [], $limit = 100, $offset = 0)
    {
        $logs = [];
        
        if (!file_exists($this->auditLogPath)) {
            return $logs;
        }

        $handle = fopen($this->auditLogPath, 'r');
        if (!$handle) {
            return $logs;
        }

        $lineNumber = 0;
        $collected = 0;
        
        while (($line = fgets($handle)) !== false && $collected < $limit) {
            $lineNumber++;
            
            if ($lineNumber <= $offset) {
                continue;
            }

            $logEntry = json_decode(trim($line), true);
            if (!$logEntry) {
                continue;
            }

            // Apply filters
            if ($this->matchesFilters($logEntry, $filters)) {
                $logs[] = $logEntry;
                $collected++;
            }
        }

        fclose($handle);
        
        return array_reverse($logs); // Most recent first
    }

    /**
     * Get audit statistics
     */
    public function getAuditStats($timeframe = 86400) // 24 hours default
    {
        $stats = [
            'total_events' => 0,
            'authentication_events' => 0,
            'authorization_events' => 0,
            'security_events' => 0,
            'failed_attempts' => 0,
            'data_access_events' => 0,
            'configuration_changes' => 0,
            'unique_ips' => [],
            'unique_users' => [],
            'events_by_hour' => []
        ];

        $cutoffTime = time() - $timeframe;
        $logs = $this->getAuditLogs(['timestamp_after' => $cutoffTime], 10000);

        foreach ($logs as $log) {
            $stats['total_events']++;
            
            // Count by event type
            $eventType = $log['event_type'] ?? 'unknown';
            $key = $eventType . '_events';
            if (isset($stats[$key])) {
                $stats[$key]++;
            }

            // Track unique IPs
            $ip = $log['ip_address'] ?? 'unknown';
            if (!in_array($ip, $stats['unique_ips'])) {
                $stats['unique_ips'][] = $ip;
            }

            // Track unique users
            $userId = $log['user_id'] ?? null;
            if ($userId && !in_array($userId, $stats['unique_users'])) {
                $stats['unique_users'][] = $userId;
            }

            // Events by hour
            $hour = date('H', $log['timestamp']);
            if (!isset($stats['events_by_hour'][$hour])) {
                $stats['events_by_hour'][$hour] = 0;
            }
            $stats['events_by_hour'][$hour]++;
        }

        $stats['unique_ip_count'] = count($stats['unique_ips']);
        $stats['unique_user_count'] = count($stats['unique_users']);
        
        return $stats;
    }

    /**
     * Clean old audit logs
     */
    public function cleanOldLogs()
    {
        $retentionDays = $this->config['audit_logging']['retention_days'] ?? 365;
        $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);
        
        if (!file_exists($this->auditLogPath)) {
            return 0;
        }

        $tempFile = $this->auditLogPath . '.tmp';
        $inputHandle = fopen($this->auditLogPath, 'r');
        $outputHandle = fopen($tempFile, 'w');
        
        if (!$inputHandle || !$outputHandle) {
            return 0;
        }

        $removedCount = 0;
        $keptCount = 0;

        while (($line = fgets($inputHandle)) !== false) {
            $logEntry = json_decode(trim($line), true);
            
            if ($logEntry && isset($logEntry['timestamp']) && $logEntry['timestamp'] >= $cutoffTime) {
                fwrite($outputHandle, $line);
                $keptCount++;
            } else {
                $removedCount++;
            }
        }

        fclose($inputHandle);
        fclose($outputHandle);

        // Replace original file with cleaned version
        rename($tempFile, $this->auditLogPath);

        $this->logger->info("Audit log cleanup completed", [
            'removed_entries' => $removedCount,
            'kept_entries' => $keptCount,
            'retention_days' => $retentionDays
        ]);

        return $removedCount;
    }

    /**
     * Write audit log entry
     */
    private function writeAuditLog(array $auditData)
    {
        if (!$this->config['audit_logging']['enabled']) {
            return;
        }

        // Add common fields
        $auditData['audit_id'] = uniqid('audit_', true);
        $auditData['application'] = 'diamond_max_admin';
        $auditData['version'] = '2.0.0';

        // Format as JSON
        $logLine = json_encode($auditData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        // Write to audit log file
        file_put_contents($this->auditLogPath, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Check if log entry matches filters
     */
    private function matchesFilters(array $logEntry, array $filters)
    {
        foreach ($filters as $key => $value) {
            switch ($key) {
                case 'event_type':
                    if (($logEntry['event_type'] ?? '') !== $value) {
                        return false;
                    }
                    break;
                
                case 'user_id':
                    if (($logEntry['user_id'] ?? null) != $value) {
                        return false;
                    }
                    break;
                
                case 'ip_address':
                    if (($logEntry['ip_address'] ?? '') !== $value) {
                        return false;
                    }
                    break;
                
                case 'timestamp_after':
                    if (($logEntry['timestamp'] ?? 0) < $value) {
                        return false;
                    }
                    break;
                
                case 'timestamp_before':
                    if (($logEntry['timestamp'] ?? 0) > $value) {
                        return false;
                    }
                    break;
            }
        }
        
        return true;
    }

    /**
     * Sanitize sensitive values for logging
     */
    private function sanitizeValue($value)
    {
        if (is_string($value) && strlen($value) > 100) {
            return substr($value, 0, 100) . '...';
        }
        
        // Don't log passwords or other sensitive data
        if (is_string($value) && preg_match('/password|secret|key|token/i', $value)) {
            return '[REDACTED]';
        }
        
        return $value;
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
}
