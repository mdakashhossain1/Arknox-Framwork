<?php
/**
 * Autoloader for MVC Framework
 *
 * This file provides automatic class loading for the MVC framework
 * following PSR-4 autoloading standards.
 */

// Load Composer autoloader first (for third-party packages like Twig)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

spl_autoload_register(function ($className) {
    // Define the base directory for the namespace prefix
    $baseDir = __DIR__ . '/app/';
    
    // Define namespace mappings
    $namespaces = [
        'App\\Controllers\\' => $baseDir . 'Controllers/',
        'App\\Models\\' => $baseDir . 'Models/',
        'App\\Core\\Database\\' => $baseDir . 'Core/Database/',
        'App\\Core\\Package\\' => $baseDir . 'Core/Package/',
        'App\\Core\\Template\\' => $baseDir . 'Core/Template/',
        'App\\Core\\' => $baseDir . 'Core/',
        'App\\Middleware\\' => $baseDir . 'Middleware/',
        'App\\Console\\' => $baseDir . 'Console/',
        'App\\Exceptions\\' => $baseDir . 'Exceptions/',
    ];
    
    // Check if the class uses one of our namespaces
    foreach ($namespaces as $namespace => $directory) {
        if (strpos($className, $namespace) === 0) {
            // Remove the namespace prefix from the class name
            $relativeClass = substr($className, strlen($namespace));
            
            // Replace namespace separators with directory separators
            $file = $directory . str_replace('\\', '/', $relativeClass) . '.php';
            
            // If the file exists, require it
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
    
    // Fallback for classes without namespace (legacy support)
    $legacyFile = $baseDir . $className . '.php';
    if (file_exists($legacyFile)) {
        require_once $legacyFile;
    }
});

// Load helper functions
require_once __DIR__ . '/app/Core/helpers.php';

// Load configuration
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
