<?php

namespace App\Core\Plugin;

/**
 * Plugin Manager
 * 
 * Modular plugin architecture with hooks and filters
 */
class PluginManager
{
    protected static $plugins = [];
    protected static $hooks = [];
    protected static $filters = [];
    protected static $loadedPlugins = [];

    /**
     * Register a plugin
     */
    public static function register($name, $pluginClass)
    {
        static::$plugins[$name] = $pluginClass;
    }

    /**
     * Load a plugin
     */
    public static function load($name)
    {
        if (isset(static::$loadedPlugins[$name])) {
            return static::$loadedPlugins[$name];
        }

        if (!isset(static::$plugins[$name])) {
            throw new \Exception("Plugin '{$name}' not found");
        }

        $pluginClass = static::$plugins[$name];
        $plugin = new $pluginClass();

        if (!$plugin instanceof PluginInterface) {
            throw new \Exception("Plugin '{$name}' must implement PluginInterface");
        }

        // Initialize plugin
        $plugin->boot();
        static::$loadedPlugins[$name] = $plugin;

        return $plugin;
    }

    /**
     * Load all registered plugins
     */
    public static function loadAll()
    {
        foreach (static::$plugins as $name => $class) {
            static::load($name);
        }
    }

    /**
     * Add an action hook
     */
    public static function addAction($hook, $callback, $priority = 10)
    {
        if (!isset(static::$hooks[$hook])) {
            static::$hooks[$hook] = [];
        }

        static::$hooks[$hook][] = [
            'callback' => $callback,
            'priority' => $priority
        ];

        // Sort by priority
        usort(static::$hooks[$hook], function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }

    /**
     * Execute action hooks
     */
    public static function doAction($hook, ...$args)
    {
        if (!isset(static::$hooks[$hook])) {
            return;
        }

        foreach (static::$hooks[$hook] as $action) {
            call_user_func_array($action['callback'], $args);
        }
    }

    /**
     * Add a filter hook
     */
    public static function addFilter($hook, $callback, $priority = 10)
    {
        if (!isset(static::$filters[$hook])) {
            static::$filters[$hook] = [];
        }

        static::$filters[$hook][] = [
            'callback' => $callback,
            'priority' => $priority
        ];

        // Sort by priority
        usort(static::$filters[$hook], function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }

    /**
     * Apply filter hooks
     */
    public static function applyFilters($hook, $value, ...$args)
    {
        if (!isset(static::$filters[$hook])) {
            return $value;
        }

        foreach (static::$filters[$hook] as $filter) {
            $value = call_user_func_array($filter['callback'], array_merge([$value], $args));
        }

        return $value;
    }

    /**
     * Check if plugin is loaded
     */
    public static function isLoaded($name)
    {
        return isset(static::$loadedPlugins[$name]);
    }

    /**
     * Get loaded plugin
     */
    public static function getPlugin($name)
    {
        return static::$loadedPlugins[$name] ?? null;
    }

    /**
     * Get all loaded plugins
     */
    public static function getLoadedPlugins()
    {
        return static::$loadedPlugins;
    }

    /**
     * Unload a plugin
     */
    public static function unload($name)
    {
        if (isset(static::$loadedPlugins[$name])) {
            $plugin = static::$loadedPlugins[$name];
            
            if (method_exists($plugin, 'shutdown')) {
                $plugin->shutdown();
            }
            
            unset(static::$loadedPlugins[$name]);
        }
    }

    /**
     * Discover plugins from directory
     */
    public static function discover($directory)
    {
        if (!is_dir($directory)) {
            return;
        }

        $plugins = glob($directory . '/*/plugin.php');
        
        foreach ($plugins as $pluginFile) {
            $pluginDir = dirname($pluginFile);
            $pluginName = basename($pluginDir);
            
            // Load plugin configuration
            $config = static::loadPluginConfig($pluginFile);
            
            if ($config && isset($config['class'])) {
                // Include plugin files
                static::includePluginFiles($pluginDir);
                
                // Register plugin
                static::register($pluginName, $config['class']);
            }
        }
    }

    /**
     * Load plugin configuration
     */
    protected static function loadPluginConfig($pluginFile)
    {
        if (!file_exists($pluginFile)) {
            return null;
        }

        return include $pluginFile;
    }

    /**
     * Include plugin files
     */
    protected static function includePluginFiles($pluginDir)
    {
        // Include all PHP files in the plugin directory
        $files = glob($pluginDir . '/*.php');
        
        foreach ($files as $file) {
            if (basename($file) !== 'plugin.php') {
                require_once $file;
            }
        }
    }

    /**
     * Get plugin information
     */
    public static function getPluginInfo($name)
    {
        $plugin = static::getPlugin($name);
        
        if (!$plugin) {
            return null;
        }

        return [
            'name' => $name,
            'class' => get_class($plugin),
            'version' => method_exists($plugin, 'getVersion') ? $plugin->getVersion() : '1.0.0',
            'description' => method_exists($plugin, 'getDescription') ? $plugin->getDescription() : '',
            'author' => method_exists($plugin, 'getAuthor') ? $plugin->getAuthor() : '',
            'loaded' => true
        ];
    }

    /**
     * Enable a plugin
     */
    public static function enable($name)
    {
        $plugin = static::load($name);
        
        if (method_exists($plugin, 'enable')) {
            $plugin->enable();
        }
        
        static::doAction('plugin_enabled', $name, $plugin);
    }

    /**
     * Disable a plugin
     */
    public static function disable($name)
    {
        $plugin = static::getPlugin($name);
        
        if ($plugin && method_exists($plugin, 'disable')) {
            $plugin->disable();
        }
        
        static::doAction('plugin_disabled', $name, $plugin);
    }
}
