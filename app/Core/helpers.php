<?php

/**
 * Arknox Framework Helper Functions
 * 
 * Cross-platform utility functions for Arknox Framework
 */

if (!function_exists('arknox_path')) {
    /**
     * Get Arknox framework path
     */
    function arknox_path($path = '')
    {
        $basePath = defined('ARKNOX_ROOT') ? ARKNOX_ROOT : dirname(__DIR__, 2);
        
        if (empty($path)) {
            return $basePath;
        }
        
        return $basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('app_path')) {
    /**
     * Get application path
     */
    function app_path($path = '')
    {
        $appPath = defined('ARKNOX_APP') ? ARKNOX_APP : arknox_path('app');
        
        if (empty($path)) {
            return $appPath;
        }
        
        return $appPath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('config_path')) {
    /**
     * Get configuration path
     */
    function config_path($path = '')
    {
        $configPath = defined('ARKNOX_CONFIG') ? ARKNOX_CONFIG : arknox_path('config');
        
        if (empty($path)) {
            return $configPath;
        }
        
        return $configPath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get storage path
     */
    function storage_path($path = '')
    {
        $storagePath = defined('ARKNOX_STORAGE') ? ARKNOX_STORAGE : arknox_path('storage');
        
        if (empty($path)) {
            return $storagePath;
        }
        
        return $storagePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('public_path')) {
    /**
     * Get public path
     */
    function public_path($path = '')
    {
        $publicPath = defined('ARKNOX_PUBLIC') ? ARKNOX_PUBLIC : arknox_path('public');
        
        if (empty($path)) {
            return $publicPath;
        }
        
        return $publicPath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('normalize_path')) {
    /**
     * Normalize path for cross-platform compatibility
     */
    function normalize_path($path)
    {
        // Convert backslashes to forward slashes
        $path = str_replace('\\', '/', $path);
        
        // Remove duplicate slashes
        $path = preg_replace('#/+#', '/', $path);
        
        // Convert back to platform-specific separators
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}

if (!function_exists('is_windows')) {
    /**
     * Check if running on Windows
     */
    function is_windows()
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}

if (!function_exists('is_macos')) {
    /**
     * Check if running on macOS
     */
    function is_macos()
    {
        return PHP_OS_FAMILY === 'Darwin';
    }
}

if (!function_exists('is_linux')) {
    /**
     * Check if running on Linux
     */
    function is_linux()
    {
        return PHP_OS_FAMILY === 'Linux';
    }
}

if (!function_exists('get_platform_info')) {
    /**
     * Get platform information
     */
    function get_platform_info()
    {
        return [
            'os_family' => PHP_OS_FAMILY,
            'os' => PHP_OS,
            'architecture' => php_uname('m'),
            'hostname' => php_uname('n'),
            'version' => php_uname('r'),
            'is_windows' => is_windows(),
            'is_macos' => is_macos(),
            'is_linux' => is_linux(),
        ];
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable value
     */
    function env($key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convert string representations to actual types
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }
        
        // Remove quotes if present
        if (strlen($value) > 1 && $value[0] === '"' && $value[-1] === '"') {
            return substr($value, 1, -1);
        }
        
        return $value;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config($key, $default = null)
    {
        static $config = [];
        
        if (empty($config)) {
            // Load configuration files
            $configFiles = glob(config_path('*.php'));
            
            foreach ($configFiles as $file) {
                $name = basename($file, '.php');
                $config[$name] = include $file;
            }
        }
        
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            
            $value = $value[$segment];
        }
        
        return $value;
    }
}

if (!function_exists('arknox_version')) {
    /**
     * Get Arknox Framework version
     */
    function arknox_version()
    {
        static $version = null;
        
        if ($version === null) {
            $composerPath = arknox_path('composer.json');
            
            if (file_exists($composerPath)) {
                $composer = json_decode(file_get_contents($composerPath), true);
                $version = $composer['version'] ?? '1.0.0';
            } else {
                $version = '1.0.0';
            }
        }
        
        return $version;
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die (for debugging)
     */
    function dd(...$vars)
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
        
        die(1);
    }
}

if (!function_exists('dump')) {
    /**
     * Dump variable (for debugging)
     */
    function dump(...$vars)
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
    }
}

if (!function_exists('logger')) {
    /**
     * Log a message
     */
    function logger($message, $level = 'info', $context = [])
    {
        $logFile = storage_path('logs/arknox.log');
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cache_path')) {
    /**
     * Get cache path
     */
    function cache_path($path = '')
    {
        $cachePath = storage_path('cache');
        
        if (empty($path)) {
            return $cachePath;
        }
        
        return $cachePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('temp_path')) {
    /**
     * Get temporary path
     */
    function temp_path($path = '')
    {
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arknox';
        
        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }
        
        if (empty($path)) {
            return $tempPath;
        }
        
        return $tempPath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('make_executable')) {
    /**
     * Make file executable (Unix/Linux/macOS only)
     */
    function make_executable($file)
    {
        if (!is_windows() && file_exists($file)) {
            chmod($file, 0755);
            return true;
        }
        
        return false;
    }
}
