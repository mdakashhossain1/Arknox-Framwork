<?php

namespace App\Core;

/**
 * Configuration Manager
 * 
 * High-performance configuration management with caching,
 * environment variable support, and dot notation access
 */
class Config
{
    private static $instance = null;
    private $items = [];
    private $cache;
    private $loaded = [];

    public function __construct()
    {
        $this->cache = Cache::getInstance();
        $this->loadEnvironmentVariables();
        $this->loadCoreConfigs();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * Get configuration value using dot notation
     */
    public function get($key, $default = null)
    {
        if (strpos($key, '.') === false) {
            return $this->items[$key] ?? $default;
        }

        $keys = explode('.', $key);
        $config = $this->items;

        foreach ($keys as $segment) {
            if (!is_array($config) || !array_key_exists($segment, $config)) {
                return $default;
            }
            $config = $config[$segment];
        }

        return $config;
    }

    /**
     * Set configuration value using dot notation
     */
    public function set($key, $value)
    {
        if (strpos($key, '.') === false) {
            $this->items[$key] = $value;
            return;
        }

        $keys = explode('.', $key);
        $config = &$this->items;

        foreach ($keys as $segment) {
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }

        $config = $value;
    }

    /**
     * Check if configuration key exists
     */
    public function has($key)
    {
        return $this->get($key) !== null;
    }

    /**
     * Get all configuration items
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * Load configuration from file
     */
    public function load($name, $path = null)
    {
        if (isset($this->loaded[$name])) {
            return $this->items[$name];
        }

        $cacheKey = "config_{$name}";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            $this->items[$name] = $cached;
            $this->loaded[$name] = true;
            return $cached;
        }

        $path = $path ?: $this->getConfigPath($name);

        if (!file_exists($path)) {
            throw new \Exception("Configuration file not found: {$path}");
        }

        $config = require $path;
        
        if (!is_array($config)) {
            throw new \Exception("Configuration file must return an array: {$path}");
        }

        // Process environment variables
        $config = $this->processEnvironmentVariables($config);

        $this->items[$name] = $config;
        $this->loaded[$name] = true;

        // Cache the configuration
        $this->cache->set($cacheKey, $config, 3600);

        return $config;
    }

    /**
     * Reload configuration from file
     */
    public function reload($name)
    {
        unset($this->loaded[$name]);
        $this->cache->delete("config_{$name}");
        return $this->load($name);
    }

    /**
     * Load core configuration files
     */
    private function loadCoreConfigs()
    {
        $coreConfigs = ['app', 'database', 'security'];

        foreach ($coreConfigs as $config) {
            try {
                $this->load($config);
            } catch (\Exception $e) {
                // Log error but continue loading other configs
                error_log("Failed to load config '{$config}': " . $e->getMessage());
            }
        }
    }

    /**
     * Load environment variables from .env file
     */
    private function loadEnvironmentVariables()
    {
        $envFile = __DIR__ . '/../../.env';
        
        if (!file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) {
                continue; // Skip comments
            }

            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes
                if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                    $value = $matches[2];
                }

                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }

    /**
     * Process environment variables in configuration
     */
    private function processEnvironmentVariables($config)
    {
        if (is_array($config)) {
            foreach ($config as $key => $value) {
                $config[$key] = $this->processEnvironmentVariables($value);
            }
        } elseif (is_string($config) && strpos($config, 'env(') === 0) {
            // Parse env() function calls
            if (preg_match('/^env\(([^,)]+)(?:,\s*(.+))?\)$/', $config, $matches)) {
                $envKey = trim($matches[1], '"\'');
                $default = isset($matches[2]) ? trim($matches[2], '"\'') : null;
                $config = $this->env($envKey, $default);
            }
        }

        return $config;
    }

    /**
     * Get environment variable
     */
    public function env($key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);

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

        // Handle quoted strings
        if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
            return $matches[2];
        }

        return $value;
    }

    /**
     * Get configuration file path
     */
    private function getConfigPath($name)
    {
        return __DIR__ . "/../../config/{$name}.php";
    }

    /**
     * Clear configuration cache
     */
    public function clearCache()
    {
        foreach ($this->loaded as $name => $loaded) {
            $this->cache->delete("config_{$name}");
        }
    }

    /**
     * Magic method for property access
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * Magic method for property setting
     */
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * Magic method for property existence check
     */
    public function __isset($key)
    {
        return $this->has($key);
    }
}

/**
 * Global config helper function
 */
if (!function_exists('config')) {
    function config($key = null, $default = null)
    {
        $config = Config::getInstance();
        
        if ($key === null) {
            return $config;
        }

        return $config->get($key, $default);
    }
}

/**
 * Global env helper function
 */
if (!function_exists('env')) {
    function env($key, $default = null)
    {
        return Config::getInstance()->env($key, $default);
    }
}
