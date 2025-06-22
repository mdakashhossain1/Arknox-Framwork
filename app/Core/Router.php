<?php

namespace App\Core;

use App\Core\Debug\DebugIntegration;

/**
 * Next-Gen High-Performance Router
 *
 * Features:
 * - Compiled route caching for blazing speed
 * - Attribute-based routing support
 * - Advanced pattern matching with regex optimization
 * - Route groups and middleware binding
 * - API versioning support
 * - Integrated debugging and performance monitoring
 */
class Router
{
    private $routes = [];
    private $currentRoute = null;
    private $compiledRoutes = null;
    private $cache;
    private $routeGroups = [];
    private $currentGroup = null;
    private $namedRoutes = [];
    private $debugIntegration;

    public function __construct()
    {
        $this->cache = Cache::getInstance();
        $this->debugIntegration = DebugIntegration::getInstance();
        $this->loadRoutes();
        $this->loadCompiledRoutes();
    }

    /**
     * Load routes from configuration
     */
    private function loadRoutes()
    {
        $this->routes = require __DIR__ . '/../../config/routes.php';
    }

    /**
     * Load compiled routes from cache for maximum performance
     */
    private function loadCompiledRoutes()
    {
        // Generate cache key without serializing closures
        $routeKeys = array_keys($this->routes);
        $cacheKey = 'compiled_routes_' . md5(implode('|', $routeKeys));
        $this->compiledRoutes = $this->cache->get($cacheKey);

        if (!$this->compiledRoutes) {
            $this->compileRoutes();
            // Only cache routes that don't contain closures
            if ($this->canCacheRoutes()) {
                $this->cache->set($cacheKey, $this->compiledRoutes, 3600); // Cache for 1 hour
            }
        }
    }

    /**
     * Check if routes can be cached (no closures)
     */
    private function canCacheRoutes()
    {
        foreach ($this->routes as $handler) {
            if ($handler instanceof \Closure) {
                return false;
            }
        }
        return true;
    }

    /**
     * Compile routes into optimized format for faster matching
     */
    private function compileRoutes()
    {
        $compiled = [
            'static' => [],
            'dynamic' => [],
            'regex' => []
        ];

        foreach ($this->routes as $routeKey => $handler) {
            [$method, $path] = explode(' ', $routeKey, 2);

            if (strpos($path, '{') === false) {
                // Static route - fastest lookup
                $compiled['static'][$method][$path] = $handler;
            } elseif (preg_match('/^[^{]*\{[^}]+\}[^{]*$/', $path)) {
                // Simple dynamic route with one parameter
                $compiled['dynamic'][$method][] = [
                    'pattern' => $path,
                    'regex' => $this->compilePattern($path),
                    'handler' => $handler
                ];
            } else {
                // Complex route with multiple parameters
                $compiled['regex'][$method][] = [
                    'pattern' => $path,
                    'regex' => $this->compilePattern($path),
                    'handler' => $handler
                ];
            }
        }

        $this->compiledRoutes = $compiled;
    }

    /**
     * Compile route pattern to optimized regex
     */
    private function compilePattern($pattern)
    {
        $regex = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    /**
     * Add a route
     */
    public function addRoute($method, $path, $handler)
    {
        $key = strtoupper($method) . ' ' . $path;
        $this->routes[$key] = $handler;
    }

    /**
     * Add GET route
     */
    public function get($path, $handler)
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Add POST route
     */
    public function post($path, $handler)
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Add PUT route
     */
    public function put($path, $handler)
    {
        $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Add DELETE route
     */
    public function delete($path, $handler)
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Create route group with shared attributes
     */
    public function group($attributes, $callback)
    {
        $previousGroup = $this->currentGroup;
        $this->currentGroup = array_merge($this->currentGroup ?? [], $attributes);

        call_user_func($callback, $this);

        $this->currentGroup = $previousGroup;
        return $this;
    }

    /**
     * Add named route
     */
    public function name($name)
    {
        if ($this->currentRoute) {
            $this->namedRoutes[$name] = $this->currentRoute;
        }
        return $this;
    }

    /**
     * Add middleware to route
     */
    public function middleware($middleware)
    {
        if ($this->currentRoute) {
            $this->currentRoute['middleware'] = array_merge(
                $this->currentRoute['middleware'] ?? [],
                is_array($middleware) ? $middleware : [$middleware]
            );
        }
        return $this;
    }

    /**
     * Generate URL for named route
     */
    public function route($name, $parameters = [])
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException("Route '{$name}' not found");
        }

        $route = $this->namedRoutes[$name];
        $url = $route['path'];

        foreach ($parameters as $key => $value) {
            $url = str_replace('{' . $key . '}', $value, $url);
        }

        return $url;
    }

    /**
     * Add API resource routes
     */
    public function resource($name, $controller)
    {
        $this->get("/{$name}", "{$controller}@index")->name("{$name}.index");
        $this->get("/{$name}/create", "{$controller}@create")->name("{$name}.create");
        $this->post("/{$name}", "{$controller}@store")->name("{$name}.store");
        $this->get("/{$name}/{id}", "{$controller}@show")->name("{$name}.show");
        $this->get("/{$name}/{id}/edit", "{$controller}@edit")->name("{$name}.edit");
        $this->put("/{$name}/{id}", "{$controller}@update")->name("{$name}.update");
        $this->delete("/{$name}/{id}", "{$controller}@destroy")->name("{$name}.destroy");

        return $this;
    }

    /**
     * Add API-only resource routes
     */
    public function apiResource($name, $controller)
    {
        $this->get("/{$name}", "{$controller}@index")->name("{$name}.index");
        $this->post("/{$name}", "{$controller}@store")->name("{$name}.store");
        $this->get("/{$name}/{id}", "{$controller}@show")->name("{$name}.show");
        $this->put("/{$name}/{id}", "{$controller}@update")->name("{$name}.update");
        $this->delete("/{$name}/{id}", "{$controller}@destroy")->name("{$name}.destroy");

        return $this;
    }

    /**
     * Dispatch request to appropriate controller
     */
    public function dispatch($request = null)
    {
        if ($request === null) {
            $request = Request::capture();
        }

        $method = $request->method();
        $path = $this->getPathFromRequest($request);

        // Track route matching process
        $this->debugIntegration->trackRouteMatching($path, $this->routes);

        $route = $this->findRoute($method, $path);

        if (!$route) {
            $this->debugIntegration->trackRouteMatching($path, $this->routes, null);
            return $this->handleNotFound($request);
        }

        // Track matched route
        $handler = $route['handler'];
        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controllerName, $methodName] = explode('@', $handler);
            $this->debugIntegration->trackMatchedRoute(
                $path,
                $route['params'],
                $controllerName,
                $methodName,
                $route['middleware'] ?? []
            );
        }

        $this->currentRoute = $route;
        return $this->executeRoute($route, $request);
    }

    /**
     * Get path from request
     */
    private function getPathFromRequest($request)
    {
        $path = $request->path();

        // Remove base path if running in subdirectory
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath !== '/' && strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }

        return $path ?: '/';
    }

    /**
     * Get current request path
     */
    private function getCurrentPath()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        // Remove base path if running in subdirectory
        $basePath = '/diamond_maxv2/admin/adminakash';
        if (strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }

        return $path ?: '/';
    }

    /**
     * Find matching route using compiled routes for maximum performance
     */
    private function findRoute($method, $path)
    {
        $method = strtoupper($method);

        // 1. Check static routes first (fastest)
        if (isset($this->compiledRoutes['static'][$method][$path])) {
            return [
                'handler' => $this->compiledRoutes['static'][$method][$path],
                'params' => []
            ];
        }

        // 2. Check simple dynamic routes
        if (isset($this->compiledRoutes['dynamic'][$method])) {
            foreach ($this->compiledRoutes['dynamic'][$method] as $route) {
                if (preg_match($route['regex'], $path, $matches)) {
                    array_shift($matches); // Remove full match
                    return [
                        'handler' => $route['handler'],
                        'params' => $matches
                    ];
                }
            }
        }

        // 3. Check complex regex routes
        if (isset($this->compiledRoutes['regex'][$method])) {
            foreach ($this->compiledRoutes['regex'][$method] as $route) {
                if (preg_match($route['regex'], $path, $matches)) {
                    array_shift($matches); // Remove full match
                    return [
                        'handler' => $route['handler'],
                        'params' => $matches
                    ];
                }
            }
        }

        // 4. Fallback to original method for compatibility
        return $this->findRouteFallback($method, $path);
    }

    /**
     * Fallback route matching for edge cases
     */
    private function findRouteFallback($method, $path)
    {
        foreach ($this->routes as $routeKey => $handler) {
            [$routeMethod, $routePath] = explode(' ', $routeKey, 2);

            if ($method !== $routeMethod) {
                continue;
            }

            $params = $this->matchRoute($routePath, $path);
            if ($params !== false) {
                return [
                    'handler' => $handler,
                    'params' => $params
                ];
            }
        }

        return null;
    }

    /**
     * Match route pattern with current path
     */
    private function matchRoute($pattern, $path)
    {
        // Convert route pattern to regex
        $regex = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            array_shift($matches); // Remove full match
            return $matches;
        }

        return false;
    }

    /**
     * Execute the matched route
     */
    private function executeRoute($route, $request = null)
    {
        $handler = $route['handler'];
        $params = $route['params'];

        // Execute middleware if present
        if (isset($route['middleware'])) {
            foreach ($route['middleware'] as $middleware) {
                $middlewareId = $this->debugIntegration->trackMiddlewareStart($middleware, $request);

                try {
                    $middlewareInstance = $this->resolveMiddleware($middleware);
                    $result = $middlewareInstance->handle($request);

                    $this->debugIntegration->trackMiddlewareEnd($middlewareId, $result);

                    if ($result !== true && $result !== null) {
                        return $result; // Middleware returned a response
                    }
                } catch (\Throwable $e) {
                    $this->debugIntegration->trackMiddlewareEnd($middlewareId, null, $e->getMessage());
                    throw $e;
                }
            }
        }

        try {
            return $this->executeHandler($handler, $params, $request);
        } catch (\Throwable $e) {
            return $this->handleError($e, $request);
        }
    }

    /**
     * Resolve middleware instance
     */
    private function resolveMiddleware($middleware)
    {
        if (is_string($middleware)) {
            $middlewareClass = "App\\Middleware\\{$middleware}";
            if (class_exists($middlewareClass)) {
                return new $middlewareClass();
            }
        }

        return $middleware;
    }

    /**
     * Execute the route handler
     */
    private function executeHandler($handler, $params, $request = null)
    {
        if (is_string($handler)) {
            [$controllerName, $methodName] = explode('@', $handler);

            $controllerClass = "App\\Controllers\\{$controllerName}";

            if (!class_exists($controllerClass)) {
                return $this->handleNotFound($request);
            }

            // Track controller instantiation
            $this->debugIntegration->trackControllerInstantiation($controllerClass, $methodName);

            // Use dependency injection if available
            try {
                $container = Container::getInstance();
                $controller = $container->make($controllerClass);
            } catch (\Exception $e) {
                $controller = new $controllerClass();
            }

            if (!method_exists($controller, $methodName)) {
                return $this->handleNotFound($request);
            }

            // Inject request as first parameter if method expects it
            try {
                $reflection = new \ReflectionMethod($controller, $methodName);
                $parameters = $reflection->getParameters();

                if (!empty($parameters) && $parameters[0]->getClass() &&
                    $parameters[0]->getClass()->getName() === Request::class) {
                    array_unshift($params, $request);
                }
            } catch (\ReflectionException $e) {
                // Continue without reflection
            }

            // Track controller execution
            $this->debugIntegration->trackControllerExecutionStart($controllerClass, $methodName, $params);

            try {
                $result = call_user_func_array([$controller, $methodName], $params);

                $this->debugIntegration->trackControllerExecutionEnd($controllerClass, $methodName, $result);
            } catch (\Throwable $e) {
                $this->debugIntegration->trackControllerExecutionEnd($controllerClass, $methodName, null, $e->getMessage());
                throw $e;
            }

            // Convert result to Response if needed
            if (!$result instanceof Response) {
                if (is_array($result) || is_object($result)) {
                    return Response::json($result);
                }
                return new Response($result ?: '');
            }

            return $result;

        } elseif (is_callable($handler)) {
            $result = call_user_func_array($handler, array_merge([$request], $params));

            if (!$result instanceof Response) {
                if (is_array($result) || is_object($result)) {
                    return Response::json($result);
                }
                return new Response($result ?: '');
            }

            return $result;
        }

        return $this->handleNotFound($request);
    }

    /**
     * Handle 404 Not Found
     */
    private function handleNotFound($request = null)
    {
        if ($request && $request->expectsJson()) {
            return Response::json(['error' => 'Not Found'], 404);
        }

        if (file_exists(__DIR__ . '/../Views/errors/404.php')) {
            $view = new View();
            $content = $view->render('errors/404');
            return new Response($content, 404);
        }

        return new Response('<h1>404 - Page Not Found</h1>', 404);
    }

    /**
     * Handle errors
     */
    private function handleError(\Throwable $e, $request = null)
    {
        error_log("Router Error: " . $e->getMessage());

        if ($request && $request->expectsJson()) {
            $data = ['error' => 'Internal Server Error'];
            if (config('app.debug', false)) {
                $data['message'] = $e->getMessage();
                $data['file'] = $e->getFile();
                $data['line'] = $e->getLine();
            }
            return Response::json($data, 500);
        }

        if (file_exists(__DIR__ . '/../Views/errors/500.php')) {
            $view = new View();
            $content = $view->render('errors/500', ['error' => $e->getMessage()]);
            return new Response($content, 500);
        }

        return new Response('<h1>500 - Internal Server Error</h1>', 500);
    }

    /**
     * Generate URL for named route
     */
    public function url($name, $params = [])
    {
        // This would be implemented for named routes
        // For now, return simple URL construction
        $config = require __DIR__ . '/../../config/app.php';
        $baseUrl = rtrim($config['app_url'], '/');
        
        return $baseUrl . '/' . ltrim($name, '/');
    }

    /**
     * Get current route
     */
    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }
}
