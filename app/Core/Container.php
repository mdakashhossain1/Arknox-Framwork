<?php

namespace App\Core;

/**
 * Dependency Injection Container
 * 
 * High-performance DI container with auto-wiring, service tagging,
 * and singleton management for enterprise-grade applications
 */
class Container
{
    private static $instance = null;
    private $bindings = [];
    private $instances = [];
    private $aliases = [];
    private $tags = [];
    private $resolved = [];
    private $buildStack = [];

    /**
     * Get container instance (singleton)
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * Set the globally available instance of the container
     */
    public static function setInstance($container = null)
    {
        return self::$instance = $container;
    }

    /**
     * Bind a service to the container
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];

        // Remove existing instance if rebinding
        unset($this->instances[$abstract]);

        return $this;
    }

    /**
     * Bind a singleton service
     */
    public function singleton($abstract, $concrete = null)
    {
        return $this->bind($abstract, $concrete, true);
    }

    /**
     * Bind an existing instance
     */
    public function instance($abstract, $instance)
    {
        $this->instances[$abstract] = $instance;
        return $this;
    }

    /**
     * Create an alias for a service
     */
    public function alias($abstract, $alias)
    {
        $this->aliases[$alias] = $abstract;
        return $this;
    }

    /**
     * Tag services for group resolution
     */
    public function tag($abstracts, $tags)
    {
        $tags = is_array($tags) ? $tags : [$tags];
        
        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }
            
            foreach ((array) $abstracts as $abstract) {
                $this->tags[$tag][] = $abstract;
            }
        }

        return $this;
    }

    /**
     * Resolve tagged services
     */
    public function tagged($tag)
    {
        if (!isset($this->tags[$tag])) {
            return [];
        }

        $services = [];
        foreach ($this->tags[$tag] as $abstract) {
            $services[] = $this->make($abstract);
        }

        return $services;
    }

    /**
     * Resolve a service from the container
     */
    public function make($abstract, $parameters = [])
    {
        // Resolve alias
        $abstract = $this->getAlias($abstract);

        // Return existing instance if singleton
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);
        
        // Build the object
        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($concrete, $parameters);
        }

        // Store singleton instances
        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        $this->resolved[$abstract] = true;

        return $object;
    }

    /**
     * Build a concrete instance
     */
    public function build($concrete, $parameters = [])
    {
        // If concrete is a closure, execute it
        if ($concrete instanceof \Closure) {
            return $concrete($this, $parameters);
        }

        // Prevent circular dependencies
        if (in_array($concrete, $this->buildStack)) {
            throw new \Exception("Circular dependency detected: " . implode(' -> ', $this->buildStack) . " -> {$concrete}");
        }

        $this->buildStack[] = $concrete;

        try {
            $reflector = new \ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            array_pop($this->buildStack);
            throw new \Exception("Target class [{$concrete}] does not exist.");
        }

        if (!$reflector->isInstantiable()) {
            array_pop($this->buildStack);
            throw new \Exception("Target [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            array_pop($this->buildStack);
            return new $concrete;
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

        array_pop($this->buildStack);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor dependencies
     */
    protected function resolveDependencies($dependencies, $parameters = [])
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            $result = null;

            // Check if parameter was provided
            if (array_key_exists($dependency->name, $parameters)) {
                $result = $parameters[$dependency->name];
            } elseif ($dependency->getClass()) {
                // Auto-wire class dependency
                $result = $this->make($dependency->getClass()->name);
            } elseif ($dependency->isDefaultValueAvailable()) {
                // Use default value
                $result = $dependency->getDefaultValue();
            } else {
                throw new \Exception("Unable to resolve dependency [{$dependency->name}]");
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Get the alias for an abstract type
     */
    protected function getAlias($abstract)
    {
        return isset($this->aliases[$abstract]) ? $this->aliases[$abstract] : $abstract;
    }

    /**
     * Get the concrete type for an abstract type
     */
    protected function getConcrete($abstract)
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Determine if the given concrete is buildable
     */
    protected function isBuildable($concrete, $abstract)
    {
        return $concrete === $abstract || $concrete instanceof \Closure;
    }

    /**
     * Determine if the given type is shared
     */
    protected function isShared($abstract)
    {
        return isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared'];
    }

    /**
     * Check if service is bound
     */
    public function bound($abstract)
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]) || isset($this->aliases[$abstract]);
    }

    /**
     * Check if service has been resolved
     */
    public function resolved($abstract)
    {
        return isset($this->resolved[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Flush the container
     */
    public function flush()
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
        $this->tags = [];
        $this->resolved = [];
        $this->buildStack = [];
    }

    /**
     * Magic method to resolve services
     */
    public function __get($key)
    {
        return $this->make($key);
    }

    /**
     * Magic method to check if service exists
     */
    public function __isset($key)
    {
        return $this->bound($key);
    }
}
