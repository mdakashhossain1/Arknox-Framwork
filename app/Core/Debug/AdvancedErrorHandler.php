<?php

namespace App\Core\Debug;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Advanced Error Handler
 * 
 * Provides comprehensive error reporting and debugging information
 * that goes beyond Laravel's capabilities with visual data flow tracking,
 * detailed stack traces, and interactive debugging interface.
 */
class AdvancedErrorHandler
{
    private static $instance = null;
    private $debugData = [];
    private $enabled = false;
    private $startTime;
    private $dataFlow = [];
    private $routeInfo = [];
    private $mvcFlow = [];
    private $errorContext = [];

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->enabled = config('app.debug', false) && config('app.environment') !== 'production';
        
        if ($this->enabled) {
            $this->registerHandlers();
            $this->initializeDataTracking();
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
     * Register error and exception handlers
     */
    private function registerHandlers()
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Initialize data tracking systems
     */
    private function initializeDataTracking()
    {
        $this->debugData = [
            'errors' => [],
            'exceptions' => [],
            'queries' => [],
            'routes' => [],
            'data_flow' => [],
            'mvc_flow' => [],
            'performance' => [
                'start_time' => $this->startTime,
                'start_memory' => memory_get_usage(),
                'peak_memory' => 0,
                'execution_time' => 0
            ],
            'request_info' => [],
            'context' => []
        ];
    }

    /**
     * Handle PHP errors
     */
    public function handleError($severity, $message, $file, $line, $context = [])
    {
        if (!$this->enabled || !(error_reporting() & $severity)) {
            return false;
        }

        $errorData = [
            'type' => 'error',
            'severity' => $severity,
            'severity_name' => $this->getSeverityName($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'context' => $context,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        $this->debugData['errors'][] = $errorData;
        $this->captureErrorContext($errorData);

        // Don't prevent normal error handling
        return false;
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleException(\Throwable $exception)
    {
        if (!$this->enabled) {
            $this->renderBasicErrorPage($exception);
            return;
        }

        $exceptionData = [
            'type' => 'exception',
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'trace' => $exception->getTrace(),
            'trace_string' => $exception->getTraceAsString(),
            'previous' => $this->getPreviousExceptions($exception),
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(),
            'request_info' => $this->captureRequestInfo()
        ];

        $this->debugData['exceptions'][] = $exceptionData;
        $this->captureErrorContext($exceptionData);
        
        // Render advanced error page
        $this->renderAdvancedErrorPage($exceptionData);
    }

    /**
     * Handle script shutdown
     */
    public function handleShutdown()
    {
        if (!$this->enabled) {
            return;
        }

        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }

        $this->debugData['performance']['execution_time'] = microtime(true) - $this->startTime;
        $this->debugData['performance']['peak_memory'] = memory_get_peak_usage();
    }

    /**
     * Track data flow through MVC components
     */
    public function trackDataFlow($component, $method, $data, $context = [])
    {
        if (!$this->enabled) return;

        $this->dataFlow[] = [
            'component' => $component,
            'method' => $method,
            'data' => $this->sanitizeData($data),
            'context' => $context,
            'timestamp' => microtime(true),
            'memory' => memory_get_usage(),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];

        $this->debugData['data_flow'] = $this->dataFlow;
    }

    /**
     * Track route information
     */
    public function trackRoute($route, $params, $middleware, $controller, $method)
    {
        if (!$this->enabled) return;

        $this->routeInfo = [
            'route' => $route,
            'params' => $params,
            'middleware' => $middleware,
            'controller' => $controller,
            'method' => $method,
            'timestamp' => microtime(true)
        ];

        $this->debugData['routes'] = $this->routeInfo;
    }

    /**
     * Track MVC flow
     */
    public function trackMvcFlow($stage, $component, $status, $data = [], $error = null)
    {
        if (!$this->enabled) return;

        $this->mvcFlow[] = [
            'stage' => $stage,
            'component' => $component,
            'status' => $status, // 'success', 'error', 'warning'
            'data' => $this->sanitizeData($data),
            'error' => $error,
            'timestamp' => microtime(true),
            'memory' => memory_get_usage()
        ];

        $this->debugData['mvc_flow'] = $this->mvcFlow;
    }

    /**
     * Add database query information
     */
    public function addQuery($sql, $bindings = [], $time = 0, $result = null)
    {
        if (!$this->enabled) return;

        $this->debugData['queries'][] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'execution_time' => $time,
            'result_count' => is_array($result) ? count($result) : (is_object($result) ? 1 : 0),
            'timestamp' => microtime(true),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];
    }

    /**
     * Get severity name from error level
     */
    private function getSeverityName($severity)
    {
        $severities = [
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Standards',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];

        return $severities[$severity] ?? 'Unknown Error';
    }

    /**
     * Get previous exceptions chain
     */
    private function getPreviousExceptions(\Throwable $exception)
    {
        $previous = [];
        $current = $exception->getPrevious();
        
        while ($current) {
            $previous[] = [
                'class' => get_class($current),
                'message' => $current->getMessage(),
                'file' => $current->getFile(),
                'line' => $current->getLine(),
                'code' => $current->getCode()
            ];
            $current = $current->getPrevious();
        }

        return $previous;
    }

    /**
     * Capture request information
     */
    private function captureRequestInfo()
    {
        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'query_string' => $_SERVER['QUERY_STRING'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'headers' => getallheaders() ?: [],
            'post_data' => $_POST,
            'get_data' => $_GET,
            'session_data' => $_SESSION ?? [],
            'cookies' => $_COOKIE
        ];
    }

    /**
     * Capture error context
     */
    private function captureErrorContext($errorData)
    {
        $this->errorContext = [
            'included_files' => get_included_files(),
            'declared_classes' => get_declared_classes(),
            'loaded_extensions' => get_loaded_extensions(),
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? ''
        ];

        $this->debugData['context'] = $this->errorContext;
    }

    /**
     * Sanitize data for safe display
     */
    private function sanitizeData($data)
    {
        if (is_string($data)) {
            return strlen($data) > 1000 ? substr($data, 0, 1000) . '...' : $data;
        }
        
        if (is_array($data) || is_object($data)) {
            return json_decode(json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR), true);
        }
        
        return $data;
    }

    /**
     * Render basic error page for production
     */
    private function renderBasicErrorPage(\Throwable $exception)
    {
        http_response_code(500);
        
        if (file_exists(__DIR__ . '/../../Views/errors/500.php')) {
            $view = new View();
            echo $view->render('errors/500', ['error' => 'Internal Server Error']);
        } else {
            echo '<h1>500 - Internal Server Error</h1>';
        }
        
        exit;
    }

    /**
     * Get all debug data
     */
    public function getDebugData()
    {
        return $this->debugData;
    }

    /**
     * Check if debugging is enabled
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Render advanced error page with comprehensive debugging information
     */
    private function renderAdvancedErrorPage($exceptionData)
    {
        http_response_code(500);

        // Prepare data for the error page
        $debugData = $this->getDebugData();
        $debugData['current_exception'] = $exceptionData;

        // Create the advanced error page HTML
        $html = $this->generateAdvancedErrorHtml($debugData);

        echo $html;
        exit;
    }

    /**
     * Generate comprehensive error page HTML
     */
    private function generateAdvancedErrorHtml($debugData)
    {
        $exception = $debugData['current_exception'];

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arknox Framework - Advanced Debug Information</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            line-height: 1.6;
        }
        .debug-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .debug-header {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.3);
        }
        .debug-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: white;
        }
        .debug-header .exception-type {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 15px;
        }
        .debug-header .file-info {
            background: rgba(0,0,0,0.3);
            padding: 15px;
            border-radius: 8px;
            font-family: "Courier New", monospace;
        }
        .debug-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .debug-tab {
            background: #1e293b;
            border: none;
            color: #e2e8f0;
            padding: 12px 20px;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .debug-tab:hover {
            background: #334155;
        }
        .debug-tab.active {
            background: #3b82f6;
            color: white;
        }
        .debug-content {
            background: #1e293b;
            border-radius: 0 12px 12px 12px;
            padding: 25px;
            min-height: 400px;
        }
        .debug-panel {
            display: none;
        }
        .debug-panel.active {
            display: block;
        }
        .stack-trace {
            background: #0f172a;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            border-left: 4px solid #ef4444;
        }
        .stack-item {
            margin-bottom: 15px;
            padding: 15px;
            background: #1e293b;
            border-radius: 6px;
            border-left: 3px solid #64748b;
        }
        .stack-item:hover {
            border-left-color: #3b82f6;
        }
        .stack-file {
            color: #60a5fa;
            font-family: "Courier New", monospace;
            font-size: 14px;
        }
        .stack-line {
            color: #fbbf24;
            font-weight: bold;
        }
        .stack-function {
            color: #34d399;
            margin-top: 5px;
        }
        .data-flow-item {
            background: #1e293b;
            margin: 10px 0;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #10b981;
        }
        .data-flow-item.error {
            border-left-color: #ef4444;
        }
        .mvc-flow {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 20px 0;
        }
        .mvc-step {
            background: #1e293b;
            padding: 20px;
            border-radius: 8px;
            flex: 1;
            min-width: 200px;
            border: 2px solid #10b981;
        }
        .mvc-step.error {
            border-color: #ef4444;
        }
        .mvc-step.warning {
            border-color: #f59e0b;
        }
        .query-item {
            background: #1e293b;
            margin: 10px 0;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #8b5cf6;
        }
        .query-sql {
            font-family: "Courier New", monospace;
            background: #0f172a;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            overflow-x: auto;
        }
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .performance-card {
            background: #1e293b;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .performance-value {
            font-size: 2rem;
            font-weight: bold;
            color: #3b82f6;
            margin-bottom: 5px;
        }
        .code-snippet {
            background: #0f172a;
            padding: 15px;
            border-radius: 6px;
            font-family: "Courier New", monospace;
            font-size: 13px;
            overflow-x: auto;
            margin: 10px 0;
        }
        .highlight-line {
            background: rgba(239, 68, 68, 0.2);
            margin: 0 -15px;
            padding: 0 15px;
        }
        .json-data {
            background: #0f172a;
            padding: 15px;
            border-radius: 6px;
            font-family: "Courier New", monospace;
            font-size: 12px;
            overflow-x: auto;
            max-height: 300px;
            overflow-y: auto;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-success { background: #10b981; }
        .status-error { background: #ef4444; }
        .status-warning { background: #f59e0b; }
        .collapsible {
            cursor: pointer;
            user-select: none;
        }
        .collapsible:hover {
            color: #3b82f6;
        }
        .collapsible-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .collapsible-content.open {
            max-height: 1000px;
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <div class="debug-header">
            <h1>🐛 Arknox Framework Debug</h1>
            <div class="exception-type">' . htmlspecialchars($exception['class']) . '</div>
            <div class="file-info">
                <strong>File:</strong> ' . htmlspecialchars($exception['file']) . '<br>
                <strong>Line:</strong> ' . $exception['line'] . '<br>
                <strong>Message:</strong> ' . htmlspecialchars($exception['message']) . '
            </div>
        </div>

        <div class="debug-tabs">
            <button class="debug-tab active" onclick="showPanel(\'overview\')">📋 Overview</button>
            <button class="debug-tab" onclick="showPanel(\'stack-trace\')">📚 Stack Trace</button>
            <button class="debug-tab" onclick="showPanel(\'data-flow\')">🔄 Data Flow</button>
            <button class="debug-tab" onclick="showPanel(\'mvc-flow\')">🏗️ MVC Flow</button>
            <button class="debug-tab" onclick="showPanel(\'database\')">🗄️ Database</button>
            <button class="debug-tab" onclick="showPanel(\'performance\')">⚡ Performance</button>
            <button class="debug-tab" onclick="showPanel(\'request\')">🌐 Request</button>
            <button class="debug-tab" onclick="showPanel(\'context\')">🔧 Context</button>
        </div>

        <div class="debug-content">
            ' . $this->generateDebugPanels($debugData) . '
        </div>
    </div>

    <script>
        function showPanel(panelName) {
            // Hide all panels
            document.querySelectorAll(".debug-panel").forEach(panel => {
                panel.classList.remove("active");
            });

            // Remove active class from all tabs
            document.querySelectorAll(".debug-tab").forEach(tab => {
                tab.classList.remove("active");
            });

            // Show selected panel
            document.getElementById(panelName + "-panel").classList.add("active");

            // Add active class to clicked tab
            event.target.classList.add("active");
        }

        function toggleCollapsible(element) {
            const content = element.nextElementSibling;
            content.classList.toggle("open");
        }

        // Initialize collapsibles
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".collapsible").forEach(item => {
                item.addEventListener("click", function() {
                    toggleCollapsible(this);
                });
            });
        });
    </script>
</body>
</html>';
    }

    /**
     * Generate debug panels HTML
     */
    private function generateDebugPanels($debugData)
    {
        $exception = $debugData['current_exception'];

        $html = '';

        // Overview Panel
        $html .= '<div id="overview-panel" class="debug-panel active">';
        $html .= '<h2>🔍 Error Overview</h2>';
        $html .= '<div class="stack-trace">';
        $html .= '<h3>Exception Details</h3>';
        $html .= '<p><strong>Type:</strong> ' . htmlspecialchars($exception['class']) . '</p>';
        $html .= '<p><strong>Message:</strong> ' . htmlspecialchars($exception['message']) . '</p>';
        $html .= '<p><strong>File:</strong> ' . htmlspecialchars($exception['file']) . '</p>';
        $html .= '<p><strong>Line:</strong> ' . $exception['line'] . '</p>';
        if ($exception['code']) {
            $html .= '<p><strong>Code:</strong> ' . $exception['code'] . '</p>';
        }
        $html .= '</div>';

        // Show code snippet around error line
        $html .= $this->generateCodeSnippet($exception['file'], $exception['line']);

        // Previous exceptions
        if (!empty($exception['previous'])) {
            $html .= '<h3>Previous Exceptions</h3>';
            foreach ($exception['previous'] as $prev) {
                $html .= '<div class="stack-item">';
                $html .= '<div class="stack-file">' . htmlspecialchars($prev['class']) . '</div>';
                $html .= '<div>' . htmlspecialchars($prev['message']) . '</div>';
                $html .= '<div class="stack-file">' . htmlspecialchars($prev['file']) . ':' . $prev['line'] . '</div>';
                $html .= '</div>';
            }
        }
        $html .= '</div>';

        // Stack Trace Panel
        $html .= '<div id="stack-trace-panel" class="debug-panel">';
        $html .= '<h2>📚 Stack Trace</h2>';
        foreach ($exception['trace'] as $index => $trace) {
            $html .= '<div class="stack-item">';
            $html .= '<div class="stack-file">';
            $html .= '#' . $index . ' ';
            if (isset($trace['file'])) {
                $html .= htmlspecialchars($trace['file']);
                if (isset($trace['line'])) {
                    $html .= ':<span class="stack-line">' . $trace['line'] . '</span>';
                }
            } else {
                $html .= '[internal function]';
            }
            $html .= '</div>';

            if (isset($trace['class'])) {
                $html .= '<div class="stack-function">';
                $html .= htmlspecialchars($trace['class'] . $trace['type'] . $trace['function']) . '()';
                $html .= '</div>';
            } elseif (isset($trace['function'])) {
                $html .= '<div class="stack-function">' . htmlspecialchars($trace['function']) . '()</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        // Data Flow Panel
        $html .= '<div id="data-flow-panel" class="debug-panel">';
        $html .= '<h2>🔄 Data Flow Tracking</h2>';
        if (!empty($debugData['data_flow'])) {
            foreach ($debugData['data_flow'] as $flow) {
                $html .= '<div class="data-flow-item">';
                $html .= '<h4>' . htmlspecialchars($flow['component']) . '::' . htmlspecialchars($flow['method']) . '</h4>';
                $html .= '<p><strong>Timestamp:</strong> ' . date('H:i:s.u', $flow['timestamp']) . '</p>';
                $html .= '<p><strong>Memory:</strong> ' . $this->formatBytes($flow['memory']) . '</p>';
                if (!empty($flow['data'])) {
                    $html .= '<div class="collapsible">📄 View Data</div>';
                    $html .= '<div class="collapsible-content">';
                    $html .= '<div class="json-data">' . htmlspecialchars(json_encode($flow['data'], JSON_PRETTY_PRINT)) . '</div>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
        } else {
            $html .= '<p>No data flow information captured.</p>';
        }
        $html .= '</div>';

        // MVC Flow Panel
        $html .= '<div id="mvc-flow-panel" class="debug-panel">';
        $html .= '<h2>🏗️ MVC Flow Diagram</h2>';
        if (!empty($debugData['mvc_flow'])) {
            $html .= '<div class="mvc-flow">';
            foreach ($debugData['mvc_flow'] as $step) {
                $statusClass = $step['status'] === 'error' ? 'error' : ($step['status'] === 'warning' ? 'warning' : '');
                $html .= '<div class="mvc-step ' . $statusClass . '">';
                $html .= '<div class="status-indicator status-' . $step['status'] . '"></div>';
                $html .= '<h4>' . htmlspecialchars($step['stage']) . '</h4>';
                $html .= '<p>' . htmlspecialchars($step['component']) . '</p>';
                if ($step['error']) {
                    $html .= '<p style="color: #ef4444;">' . htmlspecialchars($step['error']) . '</p>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        } else {
            $html .= '<p>No MVC flow information captured.</p>';
        }
        $html .= '</div>';

        // Database Panel
        $html .= '<div id="database-panel" class="debug-panel">';
        $html .= '<h2>🗄️ Database Queries</h2>';
        if (!empty($debugData['queries'])) {
            foreach ($debugData['queries'] as $index => $query) {
                $html .= '<div class="query-item">';
                $html .= '<h4>Query #' . ($index + 1) . ' (' . number_format($query['execution_time'] * 1000, 2) . 'ms)</h4>';
                $html .= '<div class="query-sql">' . htmlspecialchars($query['sql']) . '</div>';
                if (!empty($query['bindings'])) {
                    $html .= '<p><strong>Bindings:</strong> ' . htmlspecialchars(json_encode($query['bindings'])) . '</p>';
                }
                $html .= '<p><strong>Result Count:</strong> ' . $query['result_count'] . '</p>';
                $html .= '</div>';
            }
        } else {
            $html .= '<p>No database queries executed.</p>';
        }
        $html .= '</div>';

        // Performance Panel
        $html .= '<div id="performance-panel" class="debug-panel">';
        $html .= '<h2>⚡ Performance Metrics</h2>';
        $html .= '<div class="performance-grid">';
        $html .= '<div class="performance-card">';
        $html .= '<div class="performance-value">' . number_format($debugData['performance']['execution_time'] * 1000, 2) . 'ms</div>';
        $html .= '<div>Execution Time</div>';
        $html .= '</div>';
        $html .= '<div class="performance-card">';
        $html .= '<div class="performance-value">' . $this->formatBytes($debugData['performance']['peak_memory']) . '</div>';
        $html .= '<div>Peak Memory</div>';
        $html .= '</div>';
        $html .= '<div class="performance-card">';
        $html .= '<div class="performance-value">' . count(get_included_files()) . '</div>';
        $html .= '<div>Included Files</div>';
        $html .= '</div>';
        $html .= '<div class="performance-card">';
        $html .= '<div class="performance-value">' . count($debugData['queries']) . '</div>';
        $html .= '<div>Database Queries</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        // Request Panel
        $html .= '<div id="request-panel" class="debug-panel">';
        $html .= '<h2>🌐 Request Information</h2>';
        $html .= '<div class="json-data">' . htmlspecialchars(json_encode($debugData['current_exception']['request_info'], JSON_PRETTY_PRINT)) . '</div>';
        $html .= '</div>';

        // Context Panel
        $html .= '<div id="context-panel" class="debug-panel">';
        $html .= '<h2>🔧 System Context</h2>';
        if (!empty($debugData['context'])) {
            $html .= '<div class="json-data">' . htmlspecialchars(json_encode($debugData['context'], JSON_PRETTY_PRINT)) . '</div>';
        } else {
            $html .= '<p>No context information available.</p>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate code snippet around error line
     */
    private function generateCodeSnippet($file, $errorLine, $contextLines = 10)
    {
        if (!file_exists($file)) {
            return '<p>File not found: ' . htmlspecialchars($file) . '</p>';
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $totalLines = count($lines);

        $startLine = max(1, $errorLine - $contextLines);
        $endLine = min($totalLines, $errorLine + $contextLines);

        $html = '<h3>Code Context</h3>';
        $html .= '<div class="code-snippet">';

        for ($i = $startLine; $i <= $endLine; $i++) {
            $lineClass = ($i == $errorLine) ? 'highlight-line' : '';
            $html .= '<div class="' . $lineClass . '">';
            $html .= '<span style="color: #64748b; margin-right: 15px;">' . str_pad($i, 4, ' ', STR_PAD_LEFT) . '</span>';
            $html .= htmlspecialchars($lines[$i - 1]);
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
