<?php

namespace App\Core;

/**
 * Database Service Provider
 * 
 * Registers database services
 */
class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register()
    {
        // Register database manager
        $this->container->singleton('db', function ($app) {
            return new \App\Core\Database\DatabaseManager();
        });

        // Register query builder
        $this->container->singleton('query', function ($app) {
            return new \App\Core\Database\QueryBuilder();
        });
    }

    /**
     * Boot services
     */
    public function boot()
    {
        // Boot database services
    }
}
