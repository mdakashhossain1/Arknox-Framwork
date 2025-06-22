<?php

/**
 * Security Configuration
 * 
 * This file contains all security-related configuration options
 * for the Diamond Max Admin application.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    |
    | Configure CSRF protection settings including token lifetime,
    | regeneration frequency, and whitelisted routes.
    |
    */
    'csrf' => [
        'enabled' => true,
        'token_lifetime' => 3600, // 1 hour
        'regenerate_on_login' => true,
        'whitelisted_routes' => [
            '/health',
            '/api/health',
            '/migration/status'
        ],
        'header_names' => [
            'X-CSRF-TOKEN',
            'X-XSRF-TOKEN'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Security
    |--------------------------------------------------------------------------
    |
    | Configure password hashing, strength requirements, and policies.
    |
    */
    'password' => [
        'algorithm' => PASSWORD_ARGON2ID,
        'options' => [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ],
        'min_length' => 8,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'max_age_days' => 90,
        'history_count' => 5, // Remember last 5 passwords
        'common_passwords_check' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    |
    | Configure secure session handling including cookie settings,
    | regeneration policies, and timeout values.
    |
    */
    'session' => [
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_samesite' => 'Strict',
        'regenerate_interval' => 1800, // 30 minutes
        'absolute_timeout' => 7200,    // 2 hours
        'idle_timeout' => 3600,        // 1 hour
        'validate_ip' => true,
        'validate_user_agent' => true,
        'encrypt_data' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for different types of requests to prevent
    | brute force attacks and abuse.
    |
    */
    'rate_limiting' => [
        'enabled' => true,
        'storage' => 'file', // file, redis, database
        'limits' => [
            'login' => [
                'max_attempts' => 5,
                'time_window' => 900, // 15 minutes
                'lockout_duration' => 1800 // 30 minutes
            ],
            'api' => [
                'max_attempts' => 60,
                'time_window' => 60, // 1 minute
                'lockout_duration' => 300 // 5 minutes
            ],
            'general' => [
                'max_attempts' => 100,
                'time_window' => 60, // 1 minute
                'lockout_duration' => 60 // 1 minute
            ],
            'password_reset' => [
                'max_attempts' => 3,
                'time_window' => 3600, // 1 hour
                'lockout_duration' => 3600 // 1 hour
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Sanitization
    |--------------------------------------------------------------------------
    |
    | Configure input sanitization and validation rules.
    |
    */
    'input_sanitization' => [
        'enabled' => true,
        'auto_sanitize' => true,
        'allowed_html_tags' => [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre'
        ],
        'allowed_html_attributes' => [
            'class', 'id', 'style'
        ],
        'max_input_length' => 10000,
        'strip_null_bytes' => true,
        'normalize_unicode' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Security
    |--------------------------------------------------------------------------
    |
    | Configure secure file upload handling including type validation,
    | size limits, and virus scanning.
    |
    */
    'file_upload' => [
        'enabled' => true,
        'max_file_size' => 5 * 1024 * 1024, // 5MB
        'allowed_extensions' => [
            'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'documents' => ['pdf', 'doc', 'docx', 'txt'],
            'archives' => ['zip', 'rar']
        ],
        'allowed_mime_types' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain', 'application/zip'
        ],
        'scan_for_viruses' => false, // Requires ClamAV
        'quarantine_suspicious' => true,
        'validate_image_content' => true,
        'max_image_dimensions' => [
            'width' => 2000,
            'height' => 2000
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Configure HTTP security headers to protect against various attacks.
    |
    */
    'headers' => [
        'enabled' => true,
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'content_security_policy' => [
            'default-src' => "'self'",
            'script-src' => "'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com",
            'style-src' => "'self' 'unsafe-inline' https://fonts.googleapis.com",
            'font-src' => "'self' https://fonts.gstatic.com",
            'img-src' => "'self' data: https:",
            'connect-src' => "'self'",
            'frame-ancestors' => "'none'"
        ],
        'strict_transport_security' => [
            'enabled' => false, // Enable only with HTTPS
            'max_age' => 31536000,
            'include_subdomains' => true,
            'preload' => false
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption
    |--------------------------------------------------------------------------
    |
    | Configure encryption settings for sensitive data.
    |
    */
    'encryption' => [
        'algorithm' => 'AES-256-GCM',
        'key_rotation_interval' => 2592000, // 30 days
        'derive_keys' => true,
        'secure_key_storage' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Configure security audit logging for compliance and monitoring.
    |
    */
    'audit_logging' => [
        'enabled' => true,
        'log_authentication' => true,
        'log_authorization' => true,
        'log_data_access' => true,
        'log_configuration_changes' => true,
        'log_failed_attempts' => true,
        'retention_days' => 365,
        'log_format' => 'json',
        'include_request_data' => false, // For privacy
        'include_response_data' => false
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Filtering
    |--------------------------------------------------------------------------
    |
    | Configure IP-based access control and filtering.
    |
    */
    'ip_filtering' => [
        'enabled' => false,
        'whitelist' => [
            // '127.0.0.1',
            // '192.168.1.0/24'
        ],
        'blacklist' => [
            // Known malicious IPs
        ],
        'auto_blacklist' => [
            'enabled' => true,
            'failed_attempts_threshold' => 10,
            'time_window' => 3600, // 1 hour
            'blacklist_duration' => 86400 // 24 hours
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    |
    | Configure 2FA settings (for future implementation).
    |
    */
    'two_factor_auth' => [
        'enabled' => false,
        'required_for_admin' => false,
        'backup_codes_count' => 8,
        'totp_window' => 30,
        'remember_device_days' => 30
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Monitoring
    |--------------------------------------------------------------------------
    |
    | Configure security monitoring and alerting.
    |
    */
    'monitoring' => [
        'enabled' => true,
        'alert_on_multiple_failures' => true,
        'alert_threshold' => 5,
        'alert_time_window' => 300, // 5 minutes
        'monitor_file_changes' => false,
        'monitor_database_changes' => true,
        'suspicious_activity_detection' => true
    ]
];
