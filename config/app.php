<?php
/**
 * Application Configuration
 * 
 * This file contains the main application configuration settings.
 */

return [
    // Application Settings
    'app_name' => 'Diamond Max Admin',
    'app_version' => '2.0.0',
    'app_url' => 'http://localhost/diamond_maxv2/admin/adminakash',
    'timezone' => 'UTC',
    
    // Environment
    'environment' => 'development', // development, production
    'debug' => true,
    
    // Security
    'session_name' => 'diamond_max_admin',
    'session_lifetime' => 7200, // 2 hours in seconds
    'csrf_token_name' => '_token',
    
    // File Upload Settings
    'upload_max_size' => 5 * 1024 * 1024, // 5MB in bytes
    'allowed_image_types' => ['jpg', 'jpeg', 'png', 'gif'],
    'upload_path' => '../slider_images_home/',
    
    // Pagination
    'default_page_size' => 10,
    'max_page_size' => 100,
    
    // Paths
    'views_path' => __DIR__ . '/../app/Views/',
    'assets_path' => 'assets/',

    // Template Engine Settings
    'twig_enabled' => true,
    'twig_cache_path' => __DIR__ . '/../cache/twig/',
    'twig_auto_reload' => true, // Set to false in production
    'twig_strict_variables' => false,
    'twig_autoescape' => 'html',
    'twig_charset' => 'UTF-8',
    
    // Error Handling
    'log_errors' => true,
    'error_log_path' => __DIR__ . '/../logs/error.log',

    // Advanced Debugging Configuration
    'debug_enabled' => true,
    'debug_interface_enabled' => true,
    'debug_data_flow_tracking' => true,
    'debug_route_tracking' => true,
    'debug_database_tracking' => true,
    'debug_mvc_flow_visualization' => true,
    'debug_performance_monitoring' => true,
    'debug_error_context_capture' => true,
    'debug_max_query_log' => 100,
    'debug_slow_query_threshold' => 0.1, // 100ms
    'debug_memory_threshold' => 50 * 1024 * 1024, // 50MB
    'debug_execution_time_threshold' => 1.0, // 1 second
    'debug_export_enabled' => true,
    'debug_toolbar_position' => 'bottom', // bottom, top
    'debug_panel_max_height' => '70vh',
    
    // Cache Settings
    'cache_enabled' => true,
    'cache_lifetime' => 3600, // 1 hour

    // Performance Settings
    'asset_optimization' => true,
    'performance_debug' => false,
    'force_https' => false,
    'log_level' => 'debug',

    // Asset Settings
    'asset_url' => '/assets',
];
