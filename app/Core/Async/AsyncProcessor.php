<?php

namespace App\Core\Async;

/**
 * Async Processor
 * 
 * Asynchronous processing support
 */
class AsyncProcessor
{
    protected static $processes = [];
    protected static $callbacks = [];

    /**
     * Execute a task asynchronously
     */
    public static function async($callable, $args = [])
    {
        $id = uniqid();
        
        // For demonstration - in production, use proper async mechanisms
        static::$processes[$id] = [
            'callable' => $callable,
            'args' => $args,
            'status' => 'pending',
            'result' => null,
            'error' => null,
            'started_at' => time()
        ];

        // Simulate async execution
        static::executeAsync($id);

        return $id;
    }

    /**
     * Execute with callback
     */
    public static function asyncWithCallback($callable, $args = [], $callback = null)
    {
        $id = static::async($callable, $args);
        
        if ($callback) {
            static::$callbacks[$id] = $callback;
        }

        return $id;
    }

    /**
     * Wait for a process to complete
     */
    public static function wait($id, $timeout = 30)
    {
        $start = time();
        
        while (time() - $start < $timeout) {
            if (isset(static::$processes[$id]) && static::$processes[$id]['status'] === 'completed') {
                return static::$processes[$id]['result'];
            }
            
            if (isset(static::$processes[$id]) && static::$processes[$id]['status'] === 'failed') {
                throw new \Exception(static::$processes[$id]['error']);
            }
            
            usleep(100000); // 100ms
        }

        throw new \Exception("Process {$id} timed out");
    }

    /**
     * Get process status
     */
    public static function getStatus($id)
    {
        return static::$processes[$id] ?? null;
    }

    /**
     * Execute all pending processes
     */
    public static function processAll()
    {
        foreach (static::$processes as $id => $process) {
            if ($process['status'] === 'pending') {
                static::executeAsync($id);
            }
        }
    }

    /**
     * Execute a process asynchronously
     */
    protected static function executeAsync($id)
    {
        $process = static::$processes[$id];
        
        try {
            static::$processes[$id]['status'] = 'running';
            
            $result = call_user_func_array($process['callable'], $process['args']);
            
            static::$processes[$id]['status'] = 'completed';
            static::$processes[$id]['result'] = $result;
            static::$processes[$id]['completed_at'] = time();

            // Execute callback if exists
            if (isset(static::$callbacks[$id])) {
                call_user_func(static::$callbacks[$id], $result, null);
            }

        } catch (\Exception $e) {
            static::$processes[$id]['status'] = 'failed';
            static::$processes[$id]['error'] = $e->getMessage();
            static::$processes[$id]['completed_at'] = time();

            // Execute callback with error
            if (isset(static::$callbacks[$id])) {
                call_user_func(static::$callbacks[$id], null, $e);
            }
        }
    }

    /**
     * Promise-like interface
     */
    public static function promise($callable, $args = [])
    {
        return new AsyncPromise($callable, $args);
    }

    /**
     * Execute multiple tasks in parallel
     */
    public static function parallel($tasks)
    {
        $ids = [];
        
        foreach ($tasks as $task) {
            $ids[] = static::async($task['callable'], $task['args'] ?? []);
        }

        return $ids;
    }

    /**
     * Wait for all processes to complete
     */
    public static function waitAll($ids, $timeout = 30)
    {
        $results = [];
        
        foreach ($ids as $id) {
            $results[$id] = static::wait($id, $timeout);
        }

        return $results;
    }

    /**
     * Clean up completed processes
     */
    public static function cleanup()
    {
        foreach (static::$processes as $id => $process) {
            if (in_array($process['status'], ['completed', 'failed'])) {
                unset(static::$processes[$id]);
                unset(static::$callbacks[$id]);
            }
        }
    }
}

/**
 * Async Promise Class
 */
class AsyncPromise
{
    protected $id;
    protected $thenCallbacks = [];
    protected $catchCallbacks = [];

    public function __construct($callable, $args = [])
    {
        $this->id = AsyncProcessor::async($callable, $args);
    }

    public function then($callback)
    {
        $this->thenCallbacks[] = $callback;
        return $this;
    }

    public function catch($callback)
    {
        $this->catchCallbacks[] = $callback;
        return $this;
    }

    public function resolve()
    {
        try {
            $result = AsyncProcessor::wait($this->id);
            
            foreach ($this->thenCallbacks as $callback) {
                $result = call_user_func($callback, $result);
            }
            
            return $result;
        } catch (\Exception $e) {
            foreach ($this->catchCallbacks as $callback) {
                call_user_func($callback, $e);
            }
            
            throw $e;
        }
    }
}
