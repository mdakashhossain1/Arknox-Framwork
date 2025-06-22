<?php

/**
 * Arknox Framework Advanced Debug System Bootstrap
 * 
 * This file initializes the advanced debugging system for the Arknox Framework.
 * Include this file early in your application bootstrap process to enable
 * comprehensive debugging capabilities.
 */

// Only initialize debug system if debugging is enabled
if (config('app.debug', false) && config('app.environment') !== 'production') {
    
    // Initialize the debug integration system
    $debugIntegration = \App\Core\Debug\DebugIntegration::getInstance();
    
    // Set up output buffering to capture content for debug interface injection
    if (!ob_get_level()) {
        ob_start();
    }
    
    // Register shutdown function to ensure debug interface is injected
    register_shutdown_function(function() use ($debugIntegration) {
        $debugIntegration->injectDebugInterface();
    });
    
    // Optional: Set up custom error reporting for better debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    
    // Optional: Increase memory limit for debug operations
    $currentLimit = ini_get('memory_limit');
    if ($currentLimit !== '-1' && intval($currentLimit) < 256) {
        ini_set('memory_limit', '256M');
    }
    
    // Optional: Increase execution time for debug operations
    $currentTime = ini_get('max_execution_time');
    if ($currentTime > 0 && $currentTime < 60) {
        ini_set('max_execution_time', 60);
    }
}
