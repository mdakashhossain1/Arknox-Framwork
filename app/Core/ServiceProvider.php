<?php

namespace App\Core;

/**
 * Service Provider Base Class
 * 
 * Base class for all service providers in the framework.
 * Provides bootstrapping and service registration capabilities.
 */
abstract class ServiceProvider
{
    protected $container;
    protected $defer = false;
    protected $provides = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register services in the container
     */
    abstract public function register();

    /**
     * Bootstrap services after all providers are registered
     */
    public function boot()
    {
        // Override in child classes if needed
    }

    /**
     * Get the services provided by this provider
     */
    public function provides()
    {
        return $this->provides;
    }

    /**
     * Determine if the provider is deferred
     */
    public function isDeferred()
    {
        return $this->defer;
    }

    /**
     * Get the container instance
     */
    protected function app()
    {
        return $this->container;
    }
}



/**
 * Service Provider Manager
 * 
 * Manages the registration and booting of service providers
 */
class ServiceProviderManager
{
    private $container;
    private $providers = [];
    private $loadedProviders = [];
    private $deferredServices = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register a service provider
     */
    public function register($provider)
    {
        if (is_string($provider)) {
            $provider = new $provider($this->container);
        }

        if (!$provider instanceof ServiceProvider) {
            throw new \InvalidArgumentException('Provider must be an instance of ServiceProvider');
        }

        $providerName = get_class($provider);

        if (isset($this->loadedProviders[$providerName])) {
            return $provider;
        }

        $this->providers[] = $provider;
        $this->loadedProviders[$providerName] = true;

        if (!$provider->isDeferred()) {
            $provider->register();
        } else {
            $this->registerDeferredProvider($provider);
        }

        return $provider;
    }

    /**
     * Boot all registered providers
     */
    public function boot()
    {
        foreach ($this->providers as $provider) {
            if (!$provider->isDeferred()) {
                $provider->boot();
            }
        }
    }

    /**
     * Register deferred provider
     */
    private function registerDeferredProvider(ServiceProvider $provider)
    {
        foreach ($provider->provides() as $service) {
            $this->deferredServices[$service] = get_class($provider);
        }
    }

    /**
     * Load deferred provider when needed
     */
    public function loadDeferredProvider($service)
    {
        if (!isset($this->deferredServices[$service])) {
            return;
        }

        $providerClass = $this->deferredServices[$service];
        $provider = new $providerClass($this->container);
        
        $provider->register();
        $provider->boot();

        unset($this->deferredServices[$service]);
    }

    /**
     * Get all registered providers
     */
    public function getProviders()
    {
        return $this->providers;
    }
}
