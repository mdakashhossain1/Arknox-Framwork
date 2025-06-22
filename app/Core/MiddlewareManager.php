<?php

namespace App\Core;

/**
 * Middleware Manager
 * 
 * Handles middleware execution in a pipeline pattern.
 */
class MiddlewareManager
{
    private $middleware = [];
    private $globalMiddleware = [];

    public function __construct()
    {
        // Register global middleware that runs on every request
        $this->globalMiddleware = [
            \App\Middleware\SecurityMiddleware::class,
            \App\Middleware\RateLimitMiddleware::class,
        ];
    }

    /**
     * Add middleware to the stack
     */
    public function add($middleware, $params = [])
    {
        $this->middleware[] = [
            'class' => $middleware,
            'params' => $params
        ];
        return $this;
    }

    /**
     * Add multiple middleware
     */
    public function addMultiple($middlewareList)
    {
        foreach ($middlewareList as $middleware) {
            if (is_array($middleware)) {
                $this->add($middleware['class'], $middleware['params'] ?? []);
            } else {
                $this->add($middleware);
            }
        }
        return $this;
    }

    /**
     * Execute middleware pipeline
     */
    public function execute($finalHandler = null)
    {
        // Combine global and route-specific middleware
        $allMiddleware = array_merge($this->globalMiddleware, $this->middleware);
        
        // Create the middleware pipeline
        $pipeline = $this->createPipeline($allMiddleware, $finalHandler);
        
        // Execute the pipeline
        return $pipeline();
    }

    /**
     * Create middleware pipeline using closure composition
     */
    private function createPipeline($middlewareList, $finalHandler)
    {
        // Start with the final handler
        $pipeline = function() use ($finalHandler) {
            if ($finalHandler && is_callable($finalHandler)) {
                return $finalHandler();
            }
            return true;
        };

        // Wrap each middleware around the pipeline (in reverse order)
        foreach (array_reverse($middlewareList) as $middlewareData) {
            $pipeline = $this->wrapMiddleware($middlewareData, $pipeline);
        }

        return $pipeline;
    }

    /**
     * Wrap middleware around the next handler
     */
    private function wrapMiddleware($middlewareData, $next)
    {
        return function() use ($middlewareData, $next) {
            $middlewareClass = is_array($middlewareData) ? $middlewareData['class'] : $middlewareData;
            $params = is_array($middlewareData) ? ($middlewareData['params'] ?? []) : [];

            // Instantiate middleware
            $middleware = new $middlewareClass();

            // Execute middleware with next handler
            if (method_exists($middleware, 'handle')) {
                // Pass parameters to handle method
                if (!empty($params)) {
                    return call_user_func_array([$middleware, 'handle'], array_merge($params, [$next]));
                } else {
                    return $middleware->handle($next);
                }
            }

            // If no handle method, just continue to next
            return $next();
        };
    }

    /**
     * Get middleware for specific route patterns
     */
    public static function getRouteMiddleware($route)
    {
        $middleware = [];

        // Authentication middleware for protected routes
        if (!in_array($route, ['/login', '/forgot-password', '/reset-password'])) {
            $middleware[] = \App\Middleware\AuthMiddleware::class;
        }

        // CSRF middleware for state-changing requests
        $middleware[] = \App\Middleware\CsrfMiddleware::class;

        // Rate limiting for login routes
        if (in_array($route, ['/login', '/forgot-password', '/reset-password'])) {
            $middleware[] = [
                'class' => \App\Middleware\RateLimitMiddleware::class,
                'params' => ['login']
            ];
        }

        // API rate limiting
        if (strpos($route, '/api/') === 0) {
            $middleware[] = [
                'class' => \App\Middleware\RateLimitMiddleware::class,
                'params' => ['api']
            ];
        }

        return $middleware;
    }

    /**
     * Execute middleware for a specific route
     */
    public static function executeForRoute($route, $finalHandler = null)
    {
        $manager = new self();
        $routeMiddleware = self::getRouteMiddleware($route);
        
        $manager->addMultiple($routeMiddleware);
        
        return $manager->execute($finalHandler);
    }

    /**
     * Handle middleware errors
     */
    public function handleError($error, $middleware = null)
    {
        $config = require __DIR__ . '/../../config/app.php';
        
        if ($config['log_errors']) {
            $timestamp = date('Y-m-d H:i:s');
            $middlewareName = $middleware ? get_class($middleware) : 'Unknown';
            $errorMessage = is_string($error) ? $error : $error->getMessage();
            
            $logMessage = "[{$timestamp}] MIDDLEWARE_ERROR: {$middlewareName} - {$errorMessage}" . PHP_EOL;
            error_log($logMessage, 3, $config['error_log_path']);
        }

        // Handle different types of errors
        if ($error instanceof \Exception) {
            throw $error;
        }

        return false;
    }

    /**
     * Clear middleware stack
     */
    public function clear()
    {
        $this->middleware = [];
        return $this;
    }

    /**
     * Get current middleware stack
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * Check if middleware exists in stack
     */
    public function hasMiddleware($middlewareClass)
    {
        foreach ($this->middleware as $middleware) {
            $class = is_array($middleware) ? $middleware['class'] : $middleware;
            if ($class === $middlewareClass) {
                return true;
            }
        }
        return false;
    }

    /**
     * Remove middleware from stack
     */
    public function removeMiddleware($middlewareClass)
    {
        $this->middleware = array_filter($this->middleware, function($middleware) use ($middlewareClass) {
            $class = is_array($middleware) ? $middleware['class'] : $middleware;
            return $class !== $middlewareClass;
        });
        
        // Re-index array
        $this->middleware = array_values($this->middleware);
        
        return $this;
    }

    /**
     * Execute only authentication middleware (for quick auth checks)
     */
    public static function checkAuth()
    {
        $authMiddleware = new \App\Middleware\AuthMiddleware();
        return $authMiddleware->handle();
    }

    /**
     * Execute only CSRF middleware (for CSRF validation)
     */
    public static function checkCsrf()
    {
        $csrfMiddleware = new \App\Middleware\CsrfMiddleware();
        return $csrfMiddleware->handle();
    }

    /**
     * Execute only rate limit middleware (for rate limit checks)
     */
    public static function checkRateLimit($limitType = 'general')
    {
        $rateLimitMiddleware = new \App\Middleware\RateLimitMiddleware();
        return $rateLimitMiddleware->handle($limitType);
    }
}
