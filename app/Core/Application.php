<?php

namespace App\Core;

use App\Core\ServiceProvider;

/**
 * Application Bootstrap Class
 *
 * Main application class that bootstraps the entire framework
 * with dependency injection, service providers, and performance optimization
 */
class Application extends Container
{
    const VERSION = '2.0.0';

    protected $basePath;
    protected $hasBeenBootstrapped = false;
    protected $booted = false;
    protected $serviceProviders = [];
    protected $loadedProviders = [];
    protected $deferredServices = [];
    protected $bootingCallbacks = [];
    protected $bootedCallbacks = [];
    protected $terminatingCallbacks = [];

    public function __construct($basePath = null)
    {
        if ($basePath) {
            $this->setBasePath($basePath);
        }

        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
        $this->registerCoreContainerAliases();
    }

    /**
     * Get the version number of the application
     */
    public function version()
    {
        return static::VERSION;
    }

    /**
     * Set the base path for the application
     */
    public function setBasePath($basePath)
    {
        $this->basePath = rtrim($basePath, '\/');

        $this->bindPathsInContainer();

        return $this;
    }

    /**
     * Get the base path of the application
     */
    public function basePath($path = '')
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Bootstrap the application for HTTP requests
     */
    public function bootstrapWith(array $bootstrappers)
    {
        $this->hasBeenBootstrapped = true;

        foreach ($bootstrappers as $bootstrapper) {
            $this->make($bootstrapper)->bootstrap($this);
        }
    }

    /**
     * Register a callback to run before the application is booted
     */
    public function booting($callback)
    {
        $this->bootingCallbacks[] = $callback;
    }

    /**
     * Register a callback to run after the application is booted
     */
    public function booted($callback)
    {
        $this->bootedCallbacks[] = $callback;

        if ($this->isBooted()) {
            call_user_func($callback, $this);
        }
    }

    /**
     * Boot the application's service providers
     */
    public function boot()
    {
        if ($this->isBooted()) {
            return;
        }

        // Fire booting callbacks
        array_walk($this->bootingCallbacks, function ($callback) {
            call_user_func($callback, $this);
        });

        array_walk($this->serviceProviders, function ($p) {
            $this->bootProvider($p);
        });

        $this->booted = true;

        // Fire booted callbacks
        array_walk($this->bootedCallbacks, function ($callback) {
            call_user_func($callback, $this);
        });
    }

    /**
     * Boot the given service provider
     */
    protected function bootProvider(ServiceProvider $provider)
    {
        if (method_exists($provider, 'boot')) {
            return $this->call([$provider, 'boot']);
        }
    }

    /**
     * Register a service provider with the application
     */
    public function register($provider, $force = false)
    {
        if (($registered = $this->getProvider($provider)) && !$force) {
            return $registered;
        }

        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }

        $provider->register();

        if (property_exists($provider, 'bindings')) {
            foreach ($provider->bindings as $key => $value) {
                $this->bind($key, $value);
            }
        }

        if (property_exists($provider, 'singletons')) {
            foreach ($provider->singletons as $key => $value) {
                $this->singleton($key, $value);
            }
        }

        $this->markAsRegistered($provider);

        if ($this->isBooted()) {
            $this->bootProvider($provider);
        }

        return $provider;
    }

    /**
     * Get the registered service provider instance if it exists
     */
    public function getProvider($provider)
    {
        return array_values($this->getProviders($provider))[0] ?? null;
    }

    /**
     * Get the registered service provider instances if any exist
     */
    public function getProviders($provider)
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return array_filter($this->serviceProviders, function ($value) use ($name) {
            return $value instanceof $name;
        });
    }

    /**
     * Resolve a service provider instance from the class name
     */
    public function resolveProvider($provider)
    {
        return new $provider($this);
    }

    /**
     * Mark the given provider as registered
     */
    protected function markAsRegistered($provider)
    {
        $this->serviceProviders[] = $provider;
        $this->loadedProviders[get_class($provider)] = true;
    }

    /**
     * Determine if the application has been bootstrapped before
     */
    public function hasBeenBootstrapped()
    {
        return $this->hasBeenBootstrapped;
    }

    /**
     * Determine if the application has booted
     */
    public function isBooted()
    {
        return $this->booted;
    }

    /**
     * Register the basic bindings into the container
     */
    protected function registerBaseBindings()
    {
        static::setInstance($this);

        $this->instance('app', $this);
        $this->instance(Container::class, $this);
        $this->singleton('config', Config::class);
    }

    /**
     * Register all of the base service providers
     */
    protected function registerBaseServiceProviders()
    {
        $this->register(new \App\Core\AppServiceProvider($this));
        $this->register(new \App\Core\DatabaseServiceProvider($this));
        $this->register(new \App\Core\ViewServiceProvider($this));
    }

    /**
     * Register the core class aliases in the container
     */
    protected function registerCoreContainerAliases()
    {
        foreach ([
            'app' => [self::class, Container::class],
            'config' => [Config::class],
            'cache' => [Cache::class],
            'events' => [EventDispatcher::class],
            'router' => [Router::class],
            'security' => [SecurityManager::class],
            'performance' => [PerformanceOptimizer::class],
        ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    /**
     * Bind all of the application paths in the container
     */
    protected function bindPathsInContainer()
    {
        $this->instance('path', $this->path());
        $this->instance('path.base', $this->basePath());
        $this->instance('path.config', $this->configPath());
        $this->instance('path.public', $this->publicPath());
        $this->instance('path.storage', $this->storagePath());
    }

    /**
     * Get the path to the application "app" directory
     */
    public function path($path = '')
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'app' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the application configuration files
     */
    public function configPath($path = '')
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'config' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the public / web directory
     */
    public function publicPath($path = '')
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'public' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the storage directory
     */
    public function storagePath($path = '')
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'storage' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the environment file directory
     */
    public function environmentPath()
    {
        return $this->basePath;
    }

    /**
     * Get the environment file name
     */
    public function environmentFile()
    {
        return '.env';
    }

    /**
     * Determine if the application is running in the console
     */
    public function runningInConsole()
    {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
    }

    /**
     * Determine if the application is running unit tests
     */
    public function runningUnitTests()
    {
        return $this->bound('env') && $this->make('env') === 'testing';
    }

    /**
     * Call a method on the default request
     */
    public function call($callback, array $parameters = [], $defaultMethod = null)
    {
        return call_user_func_array($callback, $parameters);
    }

    /**
     * Handle HTTP request
     */
    public function handle($request)
    {
        try {
            // Boot the application if not already booted
            if (!$this->isBooted()) {
                $this->boot();
            }

            // Get the router and dispatch the request
            $router = $this->make('router');
            $response = $router->dispatch($request);

            // Ensure we have a Response object
            if (!$response instanceof Response) {
                $response = new Response($response);
            }

            return $response;

        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Handle exceptions
     */
    protected function handleException($e)
    {
        // Log the exception
        error_log("Application Exception: " . $e->getMessage());

        // Return error response
        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

        if (config('app.debug', false)) {
            $content = [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        } else {
            $content = ['error' => 'Internal Server Error'];
        }

        return Response::json($content, $statusCode);
    }

    /**
     * Terminate the application
     */
    public function terminate($request = null, $response = null)
    {
        foreach ($this->terminatingCallbacks as $terminating) {
            call_user_func($terminating, $this, $request, $response);
        }
    }
}
