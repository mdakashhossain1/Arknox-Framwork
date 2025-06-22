<?php

namespace App\Core\Debug;

/**
 * Route Debugger
 * 
 * Provides comprehensive route debugging capabilities showing which route
 * was matched, route parameters, middleware execution, and controller method calls
 */
class RouteDebugger
{
    private static $instance = null;
    private $enabled = false;
    private $routeInfo = [];
    private $middlewareExecution = [];
    private $routeMatching = [];
    private $currentRequest = [];

    public function __construct()
    {
        $this->enabled = config('app.debug', false) && config('app.environment') !== 'production';
        
        if ($this->enabled) {
            $this->initializeRouteTracking();
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * Initialize route tracking
     */
    private function initializeRouteTracking()
    {
        $this->currentRequest = [
            'id' => uniqid('route_'),
            'timestamp' => microtime(true),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'query_string' => $_SERVER['QUERY_STRING'] ?? '',
            'path_info' => $_SERVER['PATH_INFO'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
    }

    /**
     * Track route matching process
     */
    public function trackRouteMatching($requestedRoute, $availableRoutes, $matchedRoute = null, $matchingProcess = [])
    {
        if (!$this->enabled) return;

        $this->routeMatching = [
            'requested_route' => $requestedRoute,
            'available_routes' => $this->sanitizeRoutes($availableRoutes),
            'matched_route' => $matchedRoute,
            'matching_process' => $matchingProcess,
            'match_found' => $matchedRoute !== null,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage()
        ];

        // Track in advanced error handler
        $this->trackInErrorHandler('route_matching', 'Router', 'matchRoute', $this->routeMatching);
    }

    /**
     * Track matched route details
     */
    public function trackMatchedRoute($route, $params, $controller, $method, $middleware = [])
    {
        if (!$this->enabled) return;

        $this->routeInfo = [
            'route_pattern' => $route,
            'route_params' => $params,
            'controller' => $controller,
            'method' => $method,
            'middleware' => $middleware,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage()
        ];

        // Track in advanced error handler
        $this->trackInErrorHandler('route_matched', $controller, $method, $this->routeInfo);
    }

    /**
     * Track middleware execution
     */
    public function trackMiddlewareStart($middleware, $request, $order = 0)
    {
        if (!$this->enabled) return;

        $middlewareId = uniqid('mw_');
        
        $this->middlewareExecution[$middlewareId] = [
            'id' => $middlewareId,
            'middleware' => $middleware,
            'order' => $order,
            'status' => 'started',
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(),
            'request_data' => $this->sanitizeRequestData($request),
            'end_time' => null,
            'end_memory' => null,
            'execution_time' => null,
            'memory_used' => null,
            'result' => null,
            'error' => null
        ];

        return $middlewareId;
    }

    /**
     * Track middleware completion
     */
    public function trackMiddlewareEnd($middlewareId, $result = null, $error = null)
    {
        if (!$this->enabled || !isset($this->middlewareExecution[$middlewareId])) return;

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $this->middlewareExecution[$middlewareId]['status'] = $error ? 'error' : 'completed';
        $this->middlewareExecution[$middlewareId]['end_time'] = $endTime;
        $this->middlewareExecution[$middlewareId]['end_memory'] = $endMemory;
        $this->middlewareExecution[$middlewareId]['execution_time'] = 
            $endTime - $this->middlewareExecution[$middlewareId]['start_time'];
        $this->middlewareExecution[$middlewareId]['memory_used'] = 
            $endMemory - $this->middlewareExecution[$middlewareId]['start_memory'];
        $this->middlewareExecution[$middlewareId]['result'] = $this->sanitizeData($result);
        $this->middlewareExecution[$middlewareId]['error'] = $error;

        // Track in advanced error handler
        $this->trackInErrorHandler(
            'middleware_executed', 
            $this->middlewareExecution[$middlewareId]['middleware'], 
            'handle', 
            $this->middlewareExecution[$middlewareId]
        );
    }

    /**
     * Track controller method execution
     */
    public function trackControllerExecution($controller, $method, $params, $startTime = null)
    {
        if (!$this->enabled) return;

        $executionData = [
            'controller' => $controller,
            'method' => $method,
            'params' => $params,
            'start_time' => $startTime ?: microtime(true),
            'start_memory' => memory_get_usage(),
            'timestamp' => microtime(true)
        ];

        // Track in advanced error handler
        $this->trackInErrorHandler('controller_execution', $controller, $method, $executionData);

        return $executionData;
    }

    /**
     * Track route parameters and their validation
     */
    public function trackRouteParameters($params, $validation = [], $sanitization = [])
    {
        if (!$this->enabled) return;

        $parameterInfo = [
            'parameters' => $params,
            'validation_rules' => $validation,
            'sanitization_applied' => $sanitization,
            'parameter_count' => count($params),
            'timestamp' => microtime(true)
        ];

        // Add to route info
        if (isset($this->routeInfo)) {
            $this->routeInfo['parameter_details'] = $parameterInfo;
        }

        // Track in advanced error handler
        $this->trackInErrorHandler('route_parameters', 'Router', 'validateParameters', $parameterInfo);
    }

    /**
     * Track route caching information
     */
    public function trackRouteCaching($cacheKey, $cacheHit, $cacheData = null)
    {
        if (!$this->enabled) return;

        $cacheInfo = [
            'cache_key' => $cacheKey,
            'cache_hit' => $cacheHit,
            'cache_data' => $this->sanitizeData($cacheData),
            'timestamp' => microtime(true)
        ];

        // Add to route info
        if (isset($this->routeInfo)) {
            $this->routeInfo['cache_info'] = $cacheInfo;
        }

        // Track in advanced error handler
        $this->trackInErrorHandler('route_caching', 'Router', 'checkCache', $cacheInfo);
    }

    /**
     * Track route group information
     */
    public function trackRouteGroup($groupName, $groupMiddleware, $groupPrefix)
    {
        if (!$this->enabled) return;

        $groupInfo = [
            'group_name' => $groupName,
            'group_middleware' => $groupMiddleware,
            'group_prefix' => $groupPrefix,
            'timestamp' => microtime(true)
        ];

        // Add to route info
        if (isset($this->routeInfo)) {
            $this->routeInfo['group_info'] = $groupInfo;
        }

        // Track in advanced error handler
        $this->trackInErrorHandler('route_group', 'Router', 'processGroup', $groupInfo);
    }

    /**
     * Get comprehensive route debugging information
     */
    public function getRouteDebugInfo()
    {
        return [
            'request' => $this->currentRequest,
            'route_matching' => $this->routeMatching,
            'route_info' => $this->routeInfo,
            'middleware_execution' => array_values($this->middlewareExecution),
            'summary' => $this->generateRouteSummary()
        ];
    }

    /**
     * Generate route debugging summary
     */
    private function generateRouteSummary()
    {
        $summary = [
            'route_matched' => !empty($this->routeInfo),
            'middleware_count' => count($this->middlewareExecution),
            'total_middleware_time' => 0,
            'total_middleware_memory' => 0,
            'middleware_errors' => 0,
            'route_parameters_count' => 0,
            'cache_hit' => false
        ];

        // Calculate middleware metrics
        foreach ($this->middlewareExecution as $middleware) {
            if (isset($middleware['execution_time'])) {
                $summary['total_middleware_time'] += $middleware['execution_time'];
            }
            if (isset($middleware['memory_used'])) {
                $summary['total_middleware_memory'] += $middleware['memory_used'];
            }
            if ($middleware['status'] === 'error') {
                $summary['middleware_errors']++;
            }
        }

        // Route parameters
        if (isset($this->routeInfo['route_params'])) {
            $summary['route_parameters_count'] = count($this->routeInfo['route_params']);
        }

        // Cache information
        if (isset($this->routeInfo['cache_info'])) {
            $summary['cache_hit'] = $this->routeInfo['cache_info']['cache_hit'];
        }

        return $summary;
    }

    /**
     * Sanitize routes for safe display
     */
    private function sanitizeRoutes($routes)
    {
        if (!is_array($routes)) {
            return [];
        }

        $sanitized = [];
        foreach ($routes as $route => $handler) {
            $sanitized[$route] = [
                'handler' => is_callable($handler) ? 'Closure' : (string)$handler,
                'type' => gettype($handler)
            ];
        }

        return $sanitized;
    }

    /**
     * Sanitize request data
     */
    private function sanitizeRequestData($request)
    {
        if (is_object($request) && method_exists($request, 'all')) {
            return $this->sanitizeData($request->all());
        }

        return $this->sanitizeData($request);
    }

    /**
     * Sanitize data for safe storage
     */
    private function sanitizeData($data)
    {
        if (is_string($data)) {
            return strlen($data) > 500 ? substr($data, 0, 500) . '... [truncated]' : $data;
        }
        
        if (is_array($data) || is_object($data)) {
            $serialized = json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
            return strlen($serialized) > 1000 ? 
                substr($serialized, 0, 1000) . '... [truncated]' : 
                json_decode($serialized, true);
        }
        
        return $data;
    }

    /**
     * Track in advanced error handler
     */
    private function trackInErrorHandler($stage, $component, $method, $data)
    {
        $errorHandler = AdvancedErrorHandler::getInstance();
        if ($errorHandler && $errorHandler->isEnabled()) {
            $errorHandler->trackRoute(
                $this->routeInfo['route_pattern'] ?? 'unknown',
                $this->routeInfo['route_params'] ?? [],
                $this->routeInfo['middleware'] ?? [],
                $component,
                $method
            );
        }
    }

    /**
     * Check if debugging is enabled
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Reset route debugging data
     */
    public function reset()
    {
        $this->routeInfo = [];
        $this->middlewareExecution = [];
        $this->routeMatching = [];
        $this->initializeRouteTracking();
    }
}
