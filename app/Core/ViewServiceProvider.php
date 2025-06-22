<?php

namespace App\Core;

/**
 * View Service Provider
 * 
 * Registers view and template services
 */
class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register()
    {
        // Register view engine
        $this->container->singleton('view', function ($app) {
            return new \App\Core\View();
        });

        // Register Twig engine
        $this->container->singleton('twig', function ($app) {
            return new \App\Core\Template\TwigEngine();
        });
    }

    /**
     * Boot services
     */
    public function boot()
    {
        // Boot view services
    }
}
