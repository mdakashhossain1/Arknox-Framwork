<?php

namespace App\Core;

/**
 * Application Service Provider
 * 
 * Registers core application services
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register()
    {
        // Register core application services
        $this->container->singleton('config', function ($app) {
            return new \App\Core\Config();
        });

        $this->container->singleton('request', function ($app) {
            return new \App\Core\Request();
        });

        $this->container->singleton('response', function ($app) {
            return new \App\Core\Response();
        });

        $this->container->singleton('router', function ($app) {
            return new \App\Core\Router();
        });

        $this->container->singleton('session', function ($app) {
            return new \App\Core\Session();
        });
    }

    /**
     * Boot services
     */
    public function boot()
    {
        // Boot application services
    }
}
