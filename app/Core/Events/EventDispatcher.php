<?php

namespace App\Core\Events;

/**
 * Event Dispatcher
 * 
 * Modern event system with async support
 */
class EventDispatcher
{
    protected static $listeners = [];
    protected static $wildcards = [];
    protected static $queuedEvents = [];

    /**
     * Register an event listener
     */
    public static function listen($event, $listener, $priority = 0)
    {
        if (str_contains($event, '*')) {
            static::$wildcards[$event][] = ['listener' => $listener, 'priority' => $priority];
        } else {
            static::$listeners[$event][] = ['listener' => $listener, 'priority' => $priority];
        }

        // Sort by priority
        if (isset(static::$listeners[$event])) {
            usort(static::$listeners[$event], function($a, $b) {
                return $b['priority'] <=> $a['priority'];
            });
        }
    }

    /**
     * Dispatch an event
     */
    public static function dispatch($event, $payload = [], $halt = false)
    {
        $responses = [];

        // Direct listeners
        if (isset(static::$listeners[$event])) {
            foreach (static::$listeners[$event] as $listener) {
                $response = static::callListener($listener['listener'], $event, $payload);
                
                if ($halt && $response === false) {
                    return false;
                }
                
                $responses[] = $response;
            }
        }

        // Wildcard listeners
        foreach (static::$wildcards as $pattern => $listeners) {
            if (static::matchesPattern($pattern, $event)) {
                foreach ($listeners as $listener) {
                    $response = static::callListener($listener['listener'], $event, $payload);
                    
                    if ($halt && $response === false) {
                        return false;
                    }
                    
                    $responses[] = $response;
                }
            }
        }

        return $halt ? true : $responses;
    }

    /**
     * Queue an event for later processing
     */
    public static function queue($event, $payload = [])
    {
        static::$queuedEvents[] = ['event' => $event, 'payload' => $payload];
    }

    /**
     * Process queued events
     */
    public static function flush()
    {
        while (!empty(static::$queuedEvents)) {
            $queued = array_shift(static::$queuedEvents);
            static::dispatch($queued['event'], $queued['payload']);
        }
    }

    /**
     * Call a listener
     */
    protected static function callListener($listener, $event, $payload)
    {
        if (is_callable($listener)) {
            return call_user_func($listener, $event, $payload);
        }

        if (is_string($listener) && class_exists($listener)) {
            $instance = new $listener();
            return $instance->handle($event, $payload);
        }

        return null;
    }

    /**
     * Check if event matches pattern
     */
    protected static function matchesPattern($pattern, $event)
    {
        $pattern = str_replace('*', '.*', $pattern);
        return preg_match('/^' . $pattern . '$/', $event);
    }

    /**
     * Remove all listeners for an event
     */
    public static function forget($event)
    {
        unset(static::$listeners[$event]);
    }

    /**
     * Get all listeners for an event
     */
    public static function getListeners($event)
    {
        return static::$listeners[$event] ?? [];
    }

    /**
     * Check if event has listeners
     */
    public static function hasListeners($event)
    {
        return !empty(static::$listeners[$event]);
    }
}
