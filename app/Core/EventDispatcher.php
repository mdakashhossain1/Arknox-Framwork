<?php

namespace App\Core;

/**
 * Event Dispatcher
 * 
 * High-performance event system with priority handling,
 * wildcard listeners, and async event processing
 */
class EventDispatcher
{
    private static $instance = null;
    private $listeners = [];
    private $wildcardListeners = [];
    private $sorted = [];
    private $firing = [];

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
     * Register an event listener
     */
    public function listen($events, $listener, $priority = 0)
    {
        foreach ((array) $events as $event) {
            if (strpos($event, '*') !== false) {
                $this->setupWildcardListener($event, $listener, $priority);
            } else {
                $this->listeners[$event][$priority][] = $this->makeListener($listener);
                unset($this->sorted[$event]);
            }
        }

        return $this;
    }

    /**
     * Register a one-time event listener
     */
    public function once($events, $listener, $priority = 0)
    {
        $onceListener = function (...$args) use (&$onceListener, $listener, $events) {
            $this->forget($events, $onceListener);
            return call_user_func($listener, ...$args);
        };

        return $this->listen($events, $onceListener, $priority);
    }

    /**
     * Fire an event
     */
    public function fire($event, $payload = [], $halt = false)
    {
        // Normalize event name
        [$event, $payload] = $this->parseEventAndPayload($event, $payload);

        if (isset($this->firing[$event])) {
            return null; // Prevent infinite loops
        }

        $this->firing[$event] = true;

        $responses = [];

        try {
            $listeners = $this->getListeners($event);

            foreach ($listeners as $listener) {
                $response = call_user_func($listener, $event, $payload);

                if ($halt && $response !== null) {
                    unset($this->firing[$event]);
                    return $response;
                }

                if ($response === false) {
                    break;
                }

                $responses[] = $response;
            }
        } finally {
            unset($this->firing[$event]);
        }

        return $halt ? null : $responses;
    }

    /**
     * Fire an event until first non-null response
     */
    public function until($event, $payload = [])
    {
        return $this->fire($event, $payload, true);
    }

    /**
     * Get all listeners for an event
     */
    public function getListeners($eventName)
    {
        $listeners = $this->listeners[$eventName] ?? [];

        // Add wildcard listeners
        $wildcardListeners = $this->getWildcardListeners($eventName);
        foreach ($wildcardListeners as $priority => $wildcardListener) {
            $listeners[$priority] = array_merge($listeners[$priority] ?? [], $wildcardListener);
        }

        if (!isset($this->sorted[$eventName])) {
            $this->sortListeners($eventName);
        }

        return $this->sorted[$eventName];
    }

    /**
     * Check if event has listeners
     */
    public function hasListeners($eventName)
    {
        return !empty($this->listeners[$eventName]) || !empty($this->getWildcardListeners($eventName));
    }

    /**
     * Remove event listeners
     */
    public function forget($event, $listener = null)
    {
        if ($listener === null) {
            unset($this->listeners[$event], $this->sorted[$event]);
            return true;
        }

        if (!isset($this->listeners[$event])) {
            return false;
        }

        foreach ($this->listeners[$event] as $priority => $listeners) {
            foreach ($listeners as $key => $registeredListener) {
                if ($registeredListener === $listener) {
                    unset($this->listeners[$event][$priority][$key]);
                    unset($this->sorted[$event]);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Remove all listeners
     */
    public function flush()
    {
        $this->listeners = [];
        $this->wildcardListeners = [];
        $this->sorted = [];
        $this->firing = [];
    }

    /**
     * Setup wildcard listener
     */
    protected function setupWildcardListener($event, $listener, $priority)
    {
        $this->wildcardListeners[$event][$priority][] = $this->makeListener($listener);
    }

    /**
     * Get wildcard listeners for event
     */
    protected function getWildcardListeners($eventName)
    {
        $wildcards = [];

        foreach ($this->wildcardListeners as $key => $listeners) {
            if ($this->eventMatches($key, $eventName)) {
                foreach ($listeners as $priority => $listener) {
                    $wildcards[$priority] = array_merge($wildcards[$priority] ?? [], $listener);
                }
            }
        }

        return $wildcards;
    }

    /**
     * Check if event matches wildcard pattern
     */
    protected function eventMatches($pattern, $eventName)
    {
        if ($pattern === $eventName) {
            return true;
        }

        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);

        return (bool) preg_match('#^' . $pattern . '$#', $eventName);
    }

    /**
     * Sort listeners by priority
     */
    protected function sortListeners($eventName)
    {
        $this->sorted[$eventName] = [];

        if (isset($this->listeners[$eventName])) {
            krsort($this->listeners[$eventName]);

            foreach ($this->listeners[$eventName] as $priority => $listeners) {
                foreach ($listeners as $listener) {
                    $this->sorted[$eventName][] = $listener;
                }
            }
        }
    }

    /**
     * Make a listener callable
     */
    protected function makeListener($listener)
    {
        if (is_string($listener) && strpos($listener, '@') !== false) {
            return $this->createClassListener($listener);
        }

        return $listener;
    }

    /**
     * Create class-based listener
     */
    protected function createClassListener($listener)
    {
        [$class, $method] = explode('@', $listener, 2);

        return function (...$args) use ($class, $method) {
            $container = Container::getInstance();
            $instance = $container->make($class);
            return call_user_func_array([$instance, $method], $args);
        };
    }

    /**
     * Parse event and payload
     */
    protected function parseEventAndPayload($event, $payload)
    {
        if (is_object($event)) {
            return [get_class($event), [$event]];
        }

        return [$event, (array) $payload];
    }

    /**
     * Static helper methods
     */
    public static function dispatch($event, $payload = [], $halt = false)
    {
        return static::getInstance()->fire($event, $payload, $halt);
    }

    public static function subscribe($events, $listener, $priority = 0)
    {
        return static::getInstance()->listen($events, $listener, $priority);
    }
}
