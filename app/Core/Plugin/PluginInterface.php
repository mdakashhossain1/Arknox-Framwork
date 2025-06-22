<?php

namespace App\Core\Plugin;

/**
 * Plugin Interface
 * 
 * Interface that all plugins must implement
 */
interface PluginInterface
{
    /**
     * Boot the plugin
     */
    public function boot();

    /**
     * Get plugin name
     */
    public function getName();

    /**
     * Get plugin version
     */
    public function getVersion();

    /**
     * Get plugin description
     */
    public function getDescription();

    /**
     * Get plugin author
     */
    public function getAuthor();
}
