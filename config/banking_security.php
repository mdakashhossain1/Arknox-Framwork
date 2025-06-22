<?php
/**
 * Banking-Grade Security Configuration
 * 
 * Comprehensive security configuration for banking and financial applications
 * with compliance support for PCI DSS, SOX, GDPR, and other regulations.
 */

return [
    // JWT Configuration
    'jwt' => [
        'algorithm' => 'RS256',
        'issuer' => env('APP_NAME', 'MVC Framework'),
        'audience' => env('APP_URL', 'http://localhost'),
        'access_token_ttl' => 3600,    // 1 hour
        'refresh_token_ttl' => 2592000, // 30 days
        'private_key_path' => env('JWT_PRIVATE_KEY_PATH'),
        'public_key_path' => env('JWT_PUBLIC_KEY_PATH'),
        'blacklist_grace_period' => 300, // 5 minutes
    ],

    // OAuth2 Configuration
    'oauth2' => [
        'authorization_code_ttl' => 600,    // 10 minutes
        'access_token_ttl' => 3600,         // 1 hour
        'refresh_token_ttl' => 2592000,     // 30 days
        'enable_pkce' => true,
        'require_pkce' => true,
        'supported_scopes' => ['read', 'write', 'admin'],
    ],

    // Advanced Rate Limiting
    'rate_limiting' => [
        'enabled' => true,
        'default_limit' => 200,     // requests per minute
        'login_limit' => 5,         // login attempts per minute
        'api_limit' => 100,         // API requests per minute
        'admin_limit' => 50,        // admin requests per minute
        'burst_protection' => true,
        'sliding_window' => true,
        'redis_enabled' => false,   // Use Redis for distributed rate limiting
    ],

    // Threat Detection
    'threat_detection' => [
        'enabled' => true,
        'block_threshold' => 80,    // Block requests with risk score >= 80
        'alert_threshold' => 50,    // Alert on requests with risk score >= 50
        'brute_force_threshold' => 5,
        'request_threshold' => 100, // requests per minute from single IP
        'max_content_length' => 1048576, // 1MB
        'enable_behavioral_analysis' => true,
        'enable_ip_reputation' => true,
        'enable_bot_detection' => true,
    ],

    // Enhanced Input Sanitization
    'input_sanitization' => [
        'auto_sanitize' => true,
        'allowed_tags' => '<p><br><strong><em><ul><ol><li>',
        'strip_dangerous_protocols' => true,
        'max_input_length' => 10000,
        'enable_xss_protection' => true,
        'enable_sql_injection_protection' => true,
    ],

    // Advanced Session Security
    'session_security' => [
        'secure_cookies' => env('APP_ENV') === 'production',
        'httponly_cookies' => true,
        'samesite_cookies' => 'Strict',
        'regenerate_id' => true,
        'timeout' => 1800,          // 30 minutes
        'absolute_timeout' => 28800, // 8 hours
        'encrypt_session_data' => true,
    ],

    // Enhanced File Upload Security
    'file_upload' => [
        'max_size' => 5 * 1024 * 1024, // 5MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
        'scan_uploads' => true,
        'quarantine_suspicious' => true,
        'virus_scanning' => env('ENABLE_VIRUS_SCANNING', false),
        'content_type_validation' => true,
        'filename_sanitization' => true,
    ],

    // Advanced IP Filtering
    'ip_filtering' => [
        'enabled' => false,
        'whitelist' => [],
        'blacklist' => [],
        'block_private_ips' => env('APP_ENV') === 'production',
        'geo_blocking' => [
            'enabled' => false,
            'allowed_countries' => [],
            'blocked_countries' => [],
        ],
    ],

    // Comprehensive Security Headers
    'security_headers' => [
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'strict_transport_security' => 'max-age=31536000; includeSubDomains; preload',
        'content_security_policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'",
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'permissions_policy' => 'geolocation=(), microphone=(), camera=()',
    ],

    // Enhanced Audit Logging
    'audit_logging' => [
        'enabled' => true,
        'log_authentication' => true,
        'log_authorization' => true,
        'log_data_access' => true,
        'log_configuration_changes' => true,
        'log_failed_attempts' => true,
        'log_file_operations' => true,
        'retention_days' => 2555,   // 7 years for financial compliance
        'encrypt_logs' => true,
        'real_time_alerts' => true,
    ],

    // Advanced Encryption
    'encryption' => [
        'algorithm' => 'AES-256-GCM',
        'key_rotation_days' => 90,
        'at_rest_encryption' => true,
        'in_transit_encryption' => true,
        'key_derivation' => 'PBKDF2',
        'key_iterations' => 100000,
    ],

    // Banking-Grade Password Policy
    'password_policy' => [
        'min_length' => 12,
        'max_length' => 128,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'max_age_days' => 90,
        'history_count' => 12,
        'lockout_attempts' => 3,
        'lockout_duration' => 1800, // 30 minutes
        'complexity_score' => 80,   // Minimum complexity score
    ],

    // Compliance Settings
    'compliance' => [
        'pci_dss' => [
            'enabled' => true,
            'level' => 1, // 1-4, where 1 is highest
            'quarterly_scans' => true,
            'penetration_testing' => true,
        ],
        'sox' => [
            'enabled' => true,
            'financial_controls' => true,
            'audit_trail_retention' => 2555, // 7 years
        ],
        'gdpr' => [
            'enabled' => true,
            'data_protection_by_design' => true,
            'breach_notification_hours' => 72,
            'right_to_be_forgotten' => true,
        ],
        'basel_iii' => [
            'enabled' => true,
            'operational_risk_monitoring' => true,
            'capital_adequacy_reporting' => true,
        ],
        'aml' => [
            'enabled' => true,
            'transaction_monitoring' => true,
            'suspicious_activity_reporting' => true,
            'customer_due_diligence' => true,
        ],
        'kyc' => [
            'enabled' => true,
            'customer_identification' => true,
            'beneficial_ownership' => true,
            'ongoing_monitoring' => true,
        ],
    ],

    // Multi-Factor Authentication
    'mfa' => [
        'enabled' => true,
        'required_for_admin' => true,
        'methods' => ['totp', 'sms', 'email'],
        'backup_codes' => true,
        'remember_device_days' => 30,
    ],

    // API Security
    'api_security' => [
        'require_https' => env('APP_ENV') === 'production',
        'api_key_required' => false,
        'cors_enabled' => true,
        'cors_origins' => ['*'],
        'cors_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
        'cors_headers' => ['Content-Type', 'Authorization'],
    ],

    // Real-time Monitoring
    'monitoring' => [
        'enabled' => true,
        'alert_channels' => ['email', 'slack', 'webhook'],
        'critical_alert_threshold' => 5, // minutes
        'performance_monitoring' => true,
        'security_monitoring' => true,
        'compliance_monitoring' => true,
    ],

    // Incident Response
    'incident_response' => [
        'enabled' => true,
        'auto_block_critical_threats' => true,
        'escalation_matrix' => [
            'low' => ['security_team'],
            'medium' => ['security_team', 'it_manager'],
            'high' => ['security_team', 'it_manager', 'ciso'],
            'critical' => ['security_team', 'it_manager', 'ciso', 'ceo']
        ],
        'response_time_sla' => [
            'critical' => 15,  // minutes
            'high' => 60,      // minutes
            'medium' => 240,   // minutes
            'low' => 1440      // minutes (24 hours)
        ]
    ],
];
