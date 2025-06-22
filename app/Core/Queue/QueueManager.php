<?php

namespace App\Core\Queue;

/**
 * Queue Manager
 * 
 * Modern queue system for background job processing
 */
class QueueManager
{
    protected static $queues = [];
    protected static $workers = [];
    protected static $defaultQueue = 'default';

    /**
     * Push a job to the queue
     */
    public static function push($job, $data = [], $queue = null)
    {
        $queue = $queue ?: static::$defaultQueue;
        
        $jobData = [
            'id' => uniqid(),
            'job' => $job,
            'data' => $data,
            'attempts' => 0,
            'created_at' => time(),
            'available_at' => time()
        ];

        static::$queues[$queue][] = $jobData;
        
        return $jobData['id'];
    }

    /**
     * Push a job with delay
     */
    public static function later($delay, $job, $data = [], $queue = null)
    {
        $queue = $queue ?: static::$defaultQueue;
        
        $jobData = [
            'id' => uniqid(),
            'job' => $job,
            'data' => $data,
            'attempts' => 0,
            'created_at' => time(),
            'available_at' => time() + $delay
        ];

        static::$queues[$queue][] = $jobData;
        
        return $jobData['id'];
    }

    /**
     * Pop a job from the queue
     */
    public static function pop($queue = null)
    {
        $queue = $queue ?: static::$defaultQueue;
        
        if (empty(static::$queues[$queue])) {
            return null;
        }

        $now = time();
        
        foreach (static::$queues[$queue] as $index => $job) {
            if ($job['available_at'] <= $now) {
                unset(static::$queues[$queue][$index]);
                static::$queues[$queue] = array_values(static::$queues[$queue]);
                return $job;
            }
        }

        return null;
    }

    /**
     * Process jobs in a queue
     */
    public static function work($queue = null, $maxJobs = null)
    {
        $queue = $queue ?: static::$defaultQueue;
        $processed = 0;

        while (true) {
            $job = static::pop($queue);
            
            if (!$job) {
                sleep(1);
                continue;
            }

            try {
                static::processJob($job);
                $processed++;
                
                if ($maxJobs && $processed >= $maxJobs) {
                    break;
                }
            } catch (\Exception $e) {
                static::handleFailedJob($job, $e);
            }
        }

        return $processed;
    }

    /**
     * Process a single job
     */
    protected static function processJob($jobData)
    {
        $job = $jobData['job'];
        $data = $jobData['data'];

        if (is_callable($job)) {
            return call_user_func($job, $data);
        }

        if (is_string($job) && class_exists($job)) {
            $instance = new $job();
            return $instance->handle($data);
        }

        throw new \Exception("Invalid job: {$job}");
    }

    /**
     * Handle failed job
     */
    protected static function handleFailedJob($jobData, $exception)
    {
        $jobData['attempts']++;
        $jobData['last_error'] = $exception->getMessage();

        // Retry logic
        if ($jobData['attempts'] < 3) {
            $jobData['available_at'] = time() + (60 * $jobData['attempts']); // Exponential backoff
            static::$queues[static::$defaultQueue][] = $jobData;
        } else {
            // Move to failed jobs
            static::$queues['failed'][] = $jobData;
        }
    }

    /**
     * Get queue size
     */
    public static function size($queue = null)
    {
        $queue = $queue ?: static::$defaultQueue;
        return count(static::$queues[$queue] ?? []);
    }

    /**
     * Clear a queue
     */
    public static function clear($queue = null)
    {
        $queue = $queue ?: static::$defaultQueue;
        static::$queues[$queue] = [];
    }

    /**
     * Get all queues
     */
    public static function getQueues()
    {
        return array_keys(static::$queues);
    }

    /**
     * Start a worker daemon
     */
    public static function daemon($queue = null)
    {
        $queue = $queue ?: static::$defaultQueue;
        
        echo "Starting queue worker for: {$queue}\n";
        
        while (true) {
            static::work($queue, 1);
        }
    }
}
