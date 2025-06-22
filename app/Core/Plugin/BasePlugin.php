<?php

namespace App\Core\Plugin;

/**
 * Base Plugin Class
 * 
 * Abstract base class for plugins
 */
abstract class BasePlugin implements PluginInterface
{
    protected $name;
    protected $version = '1.0.0';
    protected $description = '';
    protected $author = '';
    protected $enabled = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = $this->name ?: static::class;
    }

    /**
     * Boot the plugin
     */
    public function boot()
    {
        $this->registerHooks();
        $this->registerFilters();
        $this->enabled = true;
    }

    /**
     * Register action hooks
     */
    protected function registerHooks()
    {
        // Override in child classes
    }

    /**
     * Register filter hooks
     */
    protected function registerFilters()
    {
        // Override in child classes
    }

    /**
     * Enable the plugin
     */
    public function enable()
    {
        $this->enabled = true;
        $this->onEnable();
    }

    /**
     * Disable the plugin
     */
    public function disable()
    {
        $this->enabled = false;
        $this->onDisable();
    }

    /**
     * Called when plugin is enabled
     */
    protected function onEnable()
    {
        // Override in child classes
    }

    /**
     * Called when plugin is disabled
     */
    protected function onDisable()
    {
        // Override in child classes
    }

    /**
     * Shutdown the plugin
     */
    public function shutdown()
    {
        $this->enabled = false;
        $this->onShutdown();
    }

    /**
     * Called when plugin is shutdown
     */
    protected function onShutdown()
    {
        // Override in child classes
    }

    /**
     * Check if plugin is enabled
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Get plugin name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get plugin version
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Get plugin description
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Get plugin author
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * Add an action hook
     */
    protected function addAction($hook, $method, $priority = 10)
    {
        PluginManager::addAction($hook, [$this, $method], $priority);
    }

    /**
     * Add a filter hook
     */
    protected function addFilter($hook, $method, $priority = 10)
    {
        PluginManager::addFilter($hook, [$this, $method], $priority);
    }

    /**
     * Get plugin configuration
     */
    protected function getConfig($key = null, $default = null)
    {
        $config = $this->loadConfig();
        
        if ($key === null) {
            return $config;
        }
        
        return $config[$key] ?? $default;
    }

    /**
     * Load plugin configuration
     */
    protected function loadConfig()
    {
        $configFile = $this->getPluginPath() . '/config.php';
        
        if (file_exists($configFile)) {
            return include $configFile;
        }
        
        return [];
    }

    /**
     * Get plugin path
     */
    protected function getPluginPath()
    {
        $reflection = new \ReflectionClass($this);
        return dirname($reflection->getFileName());
    }

    /**
     * Load a view file
     */
    protected function view($view, $data = [])
    {
        $viewFile = $this->getPluginPath() . '/views/' . $view . '.php';
        
        if (!file_exists($viewFile)) {
            throw new \Exception("View file not found: {$viewFile}");
        }
        
        extract($data);
        
        ob_start();
        include $viewFile;
        return ob_get_clean();
    }

    /**
     * Include a file from plugin directory
     */
    protected function include($file)
    {
        $filePath = $this->getPluginPath() . '/' . $file;
        
        if (file_exists($filePath)) {
            return include $filePath;
        }
        
        return null;
    }

    /**
     * Log a message
     */
    protected function log($message, $level = 'info')
    {
        error_log("[{$this->name}] [{$level}] {$message}");
    }
}
