<?php

namespace App\Core\Debug;

use App\Core\Router;
use App\Core\Controller;
use App\Core\Model;
use App\Core\View;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Debug Integration
 * 
 * Integrates the debugging system with existing framework components
 * to automatically capture debugging information during request processing
 */
class DebugIntegration
{
    private static $instance = null;
    private $enabled = false;
    private $errorHandler;
    private $dataFlowTracker;
    private $routeDebugger;
    private $databaseDebugger;
    private $mvcFlowVisualizer;
    private $debugInterface;

    public function __construct()
    {
        $this->enabled = config('app.debug', false) && config('app.environment') !== 'production';
        
        if ($this->enabled) {
            $this->initializeDebugComponents();
            $this->integrateWithFramework();
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
     * Initialize debug components
     */
    private function initializeDebugComponents()
    {
        $this->errorHandler = AdvancedErrorHandler::getInstance();
        $this->dataFlowTracker = DataFlowTracker::getInstance();
        $this->routeDebugger = RouteDebugger::getInstance();
        $this->databaseDebugger = DatabaseDebugger::getInstance();
        $this->mvcFlowVisualizer = MvcFlowVisualizer::getInstance();
        $this->debugInterface = AdvancedDebugInterface::getInstance();
    }

    /**
     * Integrate debugging with framework components
     */
    private function integrateWithFramework()
    {
        // Start MVC flow tracking
        $this->mvcFlowVisualizer->startStep('request_received', [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'timestamp' => microtime(true)
        ]);

        // Hook into various framework events
        $this->hookIntoRouter();
        $this->hookIntoDatabase();
        $this->hookIntoView();
        $this->setupResponseInterception();
    }

    /**
     * Hook into router for route debugging
     */
    private function hookIntoRouter()
    {
        // This would ideally be done through events or hooks in the Router class
        // For now, we'll provide methods that can be called from the Router
    }

    /**
     * Hook into database for query debugging
     */
    private function hookIntoDatabase()
    {
        // This would ideally be done through database events
        // For now, we'll provide methods that can be called from Database operations
    }

    /**
     * Hook into view for rendering debugging
     */
    private function hookIntoView()
    {
        // This would ideally be done through view events
        // For now, we'll provide methods that can be called from View operations
    }

    /**
     * Setup response interception to inject debug interface
     */
    private function setupResponseInterception()
    {
        // Register shutdown function to inject debug interface
        register_shutdown_function([$this, 'injectDebugInterface']);
    }

    /**
     * Track route matching (to be called from Router)
     */
    public function trackRouteMatching($requestedRoute, $availableRoutes, $matchedRoute = null)
    {
        if (!$this->enabled) return;

        $this->mvcFlowVisualizer->startStep('route_matching', [
            'requested_route' => $requestedRoute,
            'available_routes_count' => count($availableRoutes),
            'match_found' => $matchedRoute !== null
        ]);

        $this->routeDebugger->trackRouteMatching($requestedRoute, $availableRoutes, $matchedRoute);

        if ($matchedRoute) {
            $this->mvcFlowVisualizer->completeStep('route_matching', [
                'matched_route' => $matchedRoute
            ]);
        } else {
            $this->mvcFlowVisualizer->errorStep('route_matching', 'No matching route found');
        }
    }

    /**
     * Track matched route (to be called from Router)
     */
    public function trackMatchedRoute($route, $params, $controller, $method, $middleware = [])
    {
        if (!$this->enabled) return;

        $this->routeDebugger->trackMatchedRoute($route, $params, $controller, $method, $middleware);
        
        $this->dataFlowTracker->trackControllerInput($controller, $method, [
            'route_params' => $params,
            'middleware' => $middleware
        ], 'route');
    }

    /**
     * Track middleware execution start (to be called from Router)
     */
    public function trackMiddlewareStart($middleware, $request)
    {
        if (!$this->enabled) return null;

        $this->mvcFlowVisualizer->startStep('middleware_processing', [
            'middleware' => $middleware,
            'request_method' => $request->method() ?? 'GET'
        ]);

        return $this->routeDebugger->trackMiddlewareStart($middleware, $request);
    }

    /**
     * Track middleware execution end (to be called from Router)
     */
    public function trackMiddlewareEnd($middlewareId, $result = null, $error = null)
    {
        if (!$this->enabled) return;

        $this->routeDebugger->trackMiddlewareEnd($middlewareId, $result, $error);

        if ($error) {
            $this->mvcFlowVisualizer->errorStep('middleware_processing', $error);
        } else {
            $this->mvcFlowVisualizer->completeStep('middleware_processing', [
                'result' => $result ? 'success' : 'no_result'
            ]);
        }
    }

    /**
     * Track controller instantiation (to be called from Router)
     */
    public function trackControllerInstantiation($controller, $method)
    {
        if (!$this->enabled) return;

        $this->mvcFlowVisualizer->startStep('controller_instantiation', [
            'controller' => $controller,
            'method' => $method
        ]);

        $this->mvcFlowVisualizer->completeStep('controller_instantiation');
    }

    /**
     * Track controller execution start (to be called from Controller)
     */
    public function trackControllerExecutionStart($controller, $method, $params)
    {
        if (!$this->enabled) return;

        $this->mvcFlowVisualizer->startStep('controller_execution', [
            'controller' => $controller,
            'method' => $method,
            'params' => $params
        ]);

        $this->routeDebugger->trackControllerExecution($controller, $method, $params);
        
        $this->dataFlowTracker->trackControllerInput($controller, $method, $params, 'execution');
    }

    /**
     * Track controller execution end (to be called from Controller)
     */
    public function trackControllerExecutionEnd($controller, $method, $result, $error = null)
    {
        if (!$this->enabled) return;

        if ($error) {
            $this->mvcFlowVisualizer->errorStep('controller_execution', $error);
        } else {
            $this->mvcFlowVisualizer->completeStep('controller_execution', [
                'result_type' => gettype($result),
                'has_result' => $result !== null
            ]);
        }

        $this->dataFlowTracker->trackControllerProcessing($controller, $method, [], $result);
    }

    /**
     * Track model operation (to be called from Model)
     */
    public function trackModelOperation($model, $operation, $data = [], $queryId = null)
    {
        if (!$this->enabled) return;

        $this->mvcFlowVisualizer->startStep('model_operations', [
            'model' => $model,
            'operation' => $operation,
            'data_size' => is_array($data) ? count($data) : (is_string($data) ? strlen($data) : 0)
        ]);

        $this->databaseDebugger->trackModelOperation($model, $operation, $data, $queryId);
        $this->dataFlowTracker->trackModelInput($model, $operation, $data);
    }

    /**
     * Track model operation result (to be called from Model)
     */
    public function trackModelOperationResult($model, $operation, $result, $error = null)
    {
        if (!$this->enabled) return;

        if ($error) {
            $this->mvcFlowVisualizer->errorStep('model_operations', $error);
        } else {
            $this->mvcFlowVisualizer->completeStep('model_operations', [
                'result_count' => is_array($result) ? count($result) : (is_object($result) ? 1 : 0)
            ]);
        }

        $this->dataFlowTracker->trackModelOutput($model, $operation, $result);
    }

    /**
     * Track database query (to be called from Database)
     */
    public function trackDatabaseQuery($sql, $bindings = [], $executionTime = 0, $result = null, $error = null)
    {
        if (!$this->enabled) return;

        return $this->databaseDebugger->trackQuery($sql, $bindings, $executionTime, $result, $error);
    }

    /**
     * Track view rendering start (to be called from View)
     */
    public function trackViewRenderingStart($view, $template, $data)
    {
        if (!$this->enabled) return;

        $this->mvcFlowVisualizer->startStep('view_rendering', [
            'view' => $view,
            'template' => $template,
            'data_keys' => is_array($data) ? array_keys($data) : []
        ]);

        $this->dataFlowTracker->trackViewInput($view, $data, $template);
    }

    /**
     * Track view rendering end (to be called from View)
     */
    public function trackViewRenderingEnd($view, $template, $renderTime, $outputSize, $error = null)
    {
        if (!$this->enabled) return;

        if ($error) {
            $this->mvcFlowVisualizer->errorStep('view_rendering', $error);
        } else {
            $this->mvcFlowVisualizer->completeStep('view_rendering', [
                'render_time' => $renderTime,
                'output_size' => $outputSize
            ]);
        }

        $this->dataFlowTracker->trackViewRendering($view, $template, $renderTime, $outputSize);
    }

    /**
     * Track response sent (to be called when response is sent)
     */
    public function trackResponseSent($response)
    {
        if (!$this->enabled) return;

        $this->mvcFlowVisualizer->startStep('response_sent', [
            'status_code' => $response->getStatusCode(),
            'content_type' => $response->getHeader('Content-Type'),
            'content_length' => strlen($response->getContent())
        ]);

        $this->mvcFlowVisualizer->completeStep('response_sent');
    }

    /**
     * Inject debug interface into response
     */
    public function injectDebugInterface()
    {
        if (!$this->enabled) return;

        // Only inject if we're in a web context and not an AJAX request
        if (php_sapi_name() === 'cli' || 
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
            return;
        }

        // Get the current output buffer
        $output = ob_get_contents();
        if ($output === false) {
            return;
        }

        // Only inject into HTML responses
        if (strpos($output, '<html') === false && strpos($output, '<body') === false) {
            return;
        }

        // Create a mock response to inject the debug interface
        $response = new Response($output);
        $response = $this->debugInterface->injectDebugInterface($response);
        
        // Clear the output buffer and output the modified content
        ob_clean();
        echo $response->getContent();
    }

    /**
     * Get debug summary for quick access
     */
    public function getDebugSummary()
    {
        if (!$this->enabled) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'mvc_flow' => $this->mvcFlowVisualizer->getFlowSummary(),
            'route_info' => $this->routeDebugger->getRouteDebugInfo()['summary'] ?? [],
            'database_stats' => $this->databaseDebugger->getDatabaseDebugInfo()['statistics'] ?? [],
            'data_flow' => $this->dataFlowTracker->getDataFlowSummary(),
            'errors' => count($this->errorHandler->getDebugData()['errors'] ?? []),
            'exceptions' => count($this->errorHandler->getDebugData()['exceptions'] ?? [])
        ];
    }

    /**
     * Check if debugging is enabled
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Reset all debug data
     */
    public function reset()
    {
        if (!$this->enabled) return;

        $this->dataFlowTracker->reset();
        $this->routeDebugger->reset();
        $this->databaseDebugger->reset();
        $this->mvcFlowVisualizer->reset();
    }
}
