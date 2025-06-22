<?php

namespace App\Core\Debug;

use App\Core\Request;
use App\Core\Response;

/**
 * Advanced Debug Interface
 * 
 * Comprehensive, user-friendly debugging interface that displays all debugging
 * information directly on the webpage in an organized, intuitive format
 * that surpasses Laravel's error pages
 */
class AdvancedDebugInterface
{
    private static $instance = null;
    private $enabled = false;
    private $errorHandler;
    private $dataFlowTracker;
    private $routeDebugger;
    private $databaseDebugger;
    private $mvcFlowVisualizer;

    public function __construct()
    {
        $this->enabled = config('app.debug', false) && config('app.environment') !== 'production';
        
        if ($this->enabled) {
            $this->initializeComponents();
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
    private function initializeComponents()
    {
        $this->errorHandler = AdvancedErrorHandler::getInstance();
        $this->dataFlowTracker = DataFlowTracker::getInstance();
        $this->routeDebugger = RouteDebugger::getInstance();
        $this->databaseDebugger = DatabaseDebugger::getInstance();
        $this->mvcFlowVisualizer = MvcFlowVisualizer::getInstance();
    }

    /**
     * Inject debug interface into response
     */
    public function injectDebugInterface(Response $response)
    {
        if (!$this->enabled) {
            return $response;
        }

        $content = $response->getContent();
        
        // Only inject into HTML responses
        if (strpos($response->getHeader('Content-Type', ''), 'text/html') === false) {
            return $response;
        }

        $debugInterface = $this->generateDebugInterface();
        
        // Inject before closing body tag
        if (strpos($content, '</body>') !== false) {
            $content = str_replace('</body>', $debugInterface . '</body>', $content);
            $response->setContent($content);
        }

        return $response;
    }

    /**
     * Generate complete debug interface HTML
     */
    private function generateDebugInterface()
    {
        $debugData = $this->collectAllDebugData();
        
        return '
        <div id="arknox-debug-interface" style="position: fixed; bottom: 0; left: 0; right: 0; z-index: 999999; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
            ' . $this->generateDebugToolbar($debugData) . '
            ' . $this->generateDebugPanel($debugData) . '
            ' . $this->generateDebugStyles() . '
            ' . $this->generateDebugScripts() . '
        </div>';
    }

    /**
     * Generate debug toolbar
     */
    private function generateDebugToolbar($debugData)
    {
        $summary = $debugData['summary'];
        
        return '
        <div id="debug-toolbar" class="debug-toolbar">
            <div class="toolbar-brand">
                <span class="brand-icon">🐛</span>
                <span class="brand-text">Arknox Debug</span>
            </div>
            
            <div class="toolbar-metrics">
                <div class="metric-item ' . ($summary['status'] === 'error' ? 'metric-error' : ($summary['status'] === 'warning' ? 'metric-warning' : 'metric-success')) . '">
                    <span class="metric-icon">' . $this->getStatusIcon($summary['status']) . '</span>
                    <span class="metric-label">Status</span>
                </div>
                
                <div class="metric-item" onclick="showDebugPanel(\'performance\')">
                    <span class="metric-value">' . number_format($summary['execution_time'] * 1000, 1) . 'ms</span>
                    <span class="metric-label">Time</span>
                </div>
                
                <div class="metric-item" onclick="showDebugPanel(\'performance\')">
                    <span class="metric-value">' . $this->formatBytes($summary['memory_usage']) . '</span>
                    <span class="metric-label">Memory</span>
                </div>
                
                <div class="metric-item" onclick="showDebugPanel(\'database\')">
                    <span class="metric-value">' . $summary['query_count'] . '</span>
                    <span class="metric-label">Queries</span>
                </div>
                
                <div class="metric-item" onclick="showDebugPanel(\'mvc-flow\')">
                    <span class="metric-value">' . $summary['mvc_steps_completed'] . '/' . $summary['mvc_total_steps'] . '</span>
                    <span class="metric-label">MVC Steps</span>
                </div>
            </div>
            
            <div class="toolbar-actions">
                <button class="action-btn" onclick="toggleDebugPanel()" title="Toggle Debug Panel">
                    <span id="panel-toggle-icon">📊</span>
                </button>
                <button class="action-btn" onclick="exportDebugData()" title="Export Debug Data">
                    💾
                </button>
                <button class="action-btn" onclick="closeDebugInterface()" title="Close Debug Interface">
                    ❌
                </button>
            </div>
        </div>';
    }

    /**
     * Generate debug panel
     */
    private function generateDebugPanel($debugData)
    {
        return '
        <div id="debug-panel" class="debug-panel" style="display: none;">
            <div class="panel-header">
                <div class="panel-tabs">
                    <button class="tab-btn active" onclick="showDebugTab(\'overview\')">📋 Overview</button>
                    <button class="tab-btn" onclick="showDebugTab(\'mvc-flow\')">🏗️ MVC Flow</button>
                    <button class="tab-btn" onclick="showDebugTab(\'data-flow\')">🔄 Data Flow</button>
                    <button class="tab-btn" onclick="showDebugTab(\'database\')">🗄️ Database</button>
                    <button class="tab-btn" onclick="showDebugTab(\'routes\')">🛣️ Routes</button>
                    <button class="tab-btn" onclick="showDebugTab(\'performance\')">⚡ Performance</button>
                    <button class="tab-btn" onclick="showDebugTab(\'errors\')">🚨 Errors</button>
                    <button class="tab-btn" onclick="showDebugTab(\'request\')">🌐 Request</button>
                </div>
            </div>
            
            <div class="panel-content">
                ' . $this->generateOverviewTab($debugData) . '
                ' . $this->generateMvcFlowTab($debugData) . '
                ' . $this->generateDataFlowTab($debugData) . '
                ' . $this->generateDatabaseTab($debugData) . '
                ' . $this->generateRoutesTab($debugData) . '
                ' . $this->generatePerformanceTab($debugData) . '
                ' . $this->generateErrorsTab($debugData) . '
                ' . $this->generateRequestTab($debugData) . '
            </div>
        </div>';
    }

    /**
     * Generate overview tab
     */
    private function generateOverviewTab($debugData)
    {
        $summary = $debugData['summary'];
        
        return '
        <div id="tab-overview" class="tab-content active">
            <h2>🔍 Debug Overview</h2>
            
            <div class="overview-grid">
                <div class="overview-card">
                    <h3>Request Summary</h3>
                    <p><strong>URL:</strong> ' . htmlspecialchars($debugData['request']['url']) . '</p>
                    <p><strong>Method:</strong> ' . htmlspecialchars($debugData['request']['method']) . '</p>
                    <p><strong>Status:</strong> <span class="status-' . $summary['status'] . '">' . ucfirst($summary['status']) . '</span></p>
                </div>
                
                <div class="overview-card">
                    <h3>Performance Metrics</h3>
                    <p><strong>Execution Time:</strong> ' . number_format($summary['execution_time'] * 1000, 2) . 'ms</p>
                    <p><strong>Memory Usage:</strong> ' . $this->formatBytes($summary['memory_usage']) . '</p>
                    <p><strong>Peak Memory:</strong> ' . $this->formatBytes($summary['peak_memory']) . '</p>
                </div>
                
                <div class="overview-card">
                    <h3>Database Activity</h3>
                    <p><strong>Total Queries:</strong> ' . $summary['query_count'] . '</p>
                    <p><strong>Query Time:</strong> ' . number_format($summary['query_time'] * 1000, 2) . 'ms</p>
                    <p><strong>Slow Queries:</strong> ' . $summary['slow_queries'] . '</p>
                </div>
                
                <div class="overview-card">
                    <h3>MVC Flow</h3>
                    <p><strong>Completed Steps:</strong> ' . $summary['mvc_steps_completed'] . '/' . $summary['mvc_total_steps'] . '</p>
                    <p><strong>Errors:</strong> ' . $summary['mvc_errors'] . '</p>
                    <p><strong>Warnings:</strong> ' . $summary['mvc_warnings'] . '</p>
                </div>
            </div>
            
            ' . ($summary['issues'] ? '<div class="issues-section">
                <h3>⚠️ Issues Detected</h3>
                <ul class="issues-list">
                    ' . implode('', array_map(function($issue) {
                        return '<li class="issue-' . $issue['type'] . '">' . htmlspecialchars($issue['message']) . '</li>';
                    }, $summary['issues'])) . '
                </ul>
            </div>' : '') . '
        </div>';
    }

    /**
     * Generate MVC flow tab
     */
    private function generateMvcFlowTab($debugData)
    {
        return '
        <div id="tab-mvc-flow" class="tab-content">
            ' . $this->mvcFlowVisualizer->generateFlowDiagram() . '
        </div>';
    }

    /**
     * Generate data flow tab
     */
    private function generateDataFlowTab($debugData)
    {
        $dataFlow = $debugData['data_flow'];
        
        $html = '
        <div id="tab-data-flow" class="tab-content">
            <h2>🔄 Data Flow Analysis</h2>';
            
        if (!empty($dataFlow['flow'])) {
            $html .= '<div class="data-flow-timeline">';
            foreach ($dataFlow['flow'] as $index => $flow) {
                $html .= '
                <div class="data-flow-step">
                    <div class="step-number">' . ($index + 1) . '</div>
                    <div class="step-details">
                        <h4>' . htmlspecialchars($flow['component']) . '::' . htmlspecialchars($flow['method']) . '</h4>
                        <p><strong>Stage:</strong> ' . htmlspecialchars($flow['stage']) . '</p>
                        <p><strong>Time:</strong> ' . date('H:i:s.u', $flow['timestamp']) . '</p>
                        <p><strong>Memory:</strong> ' . $this->formatBytes($flow['memory']) . '</p>
                        
                        <div class="collapsible-section">
                            <button class="collapsible-trigger" onclick="toggleCollapsible(this)">📊 View Data</button>
                            <div class="collapsible-content">
                                <pre>' . htmlspecialchars(json_encode($flow['data'], JSON_PRETTY_PRINT)) . '</pre>
                            </div>
                        </div>
                    </div>
                </div>';
            }
            $html .= '</div>';
        } else {
            $html .= '<p>No data flow information captured.</p>';
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Generate database tab
     */
    private function generateDatabaseTab($debugData)
    {
        $database = $debugData['database'];
        
        $html = '
        <div id="tab-database" class="tab-content">
            <h2>🗄️ Database Operations</h2>';
            
        if (!empty($database['queries'])) {
            $html .= '<div class="database-summary">
                <p><strong>Total Queries:</strong> ' . count($database['queries']) . '</p>
                <p><strong>Total Time:</strong> ' . number_format($database['summary']['total_execution_time'] * 1000, 2) . 'ms</p>
                <p><strong>Average Time:</strong> ' . number_format($database['summary']['average_query_time'] * 1000, 2) . 'ms</p>
            </div>';
            
            $html .= '<div class="queries-list">';
            foreach ($database['queries'] as $index => $query) {
                $html .= '
                <div class="query-item ' . ($query['is_slow'] ? 'query-slow' : '') . '">
                    <div class="query-header">
                        <span class="query-number">#' . ($index + 1) . '</span>
                        <span class="query-type">' . $query['query_type'] . '</span>
                        <span class="query-time">' . number_format($query['execution_time'] * 1000, 2) . 'ms</span>
                    </div>
                    <div class="query-sql">
                        <pre>' . htmlspecialchars($query['sql']) . '</pre>
                    </div>
                    ' . (!empty($query['bindings']) ? '<div class="query-bindings">
                        <strong>Bindings:</strong> ' . htmlspecialchars(json_encode($query['bindings'])) . '
                    </div>' : '') . '
                </div>';
            }
            $html .= '</div>';
        } else {
            $html .= '<p>No database queries executed.</p>';
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Generate routes tab
     */
    private function generateRoutesTab($debugData)
    {
        $routes = $debugData['routes'];
        
        return '
        <div id="tab-routes" class="tab-content">
            <h2>🛣️ Route Information</h2>
            
            <div class="route-info">
                <h3>Matched Route</h3>
                <p><strong>Pattern:</strong> ' . htmlspecialchars($routes['route_info']['route_pattern'] ?? 'N/A') . '</p>
                <p><strong>Controller:</strong> ' . htmlspecialchars($routes['route_info']['controller'] ?? 'N/A') . '</p>
                <p><strong>Method:</strong> ' . htmlspecialchars($routes['route_info']['method'] ?? 'N/A') . '</p>
                
                ' . (!empty($routes['route_info']['route_params']) ? '<div class="route-params">
                    <h4>Route Parameters</h4>
                    <pre>' . htmlspecialchars(json_encode($routes['route_info']['route_params'], JSON_PRETTY_PRINT)) . '</pre>
                </div>' : '') . '
                
                ' . (!empty($routes['middleware_execution']) ? '<div class="middleware-info">
                    <h4>Middleware Execution</h4>
                    <div class="middleware-list">
                        ' . implode('', array_map(function($middleware) {
                            return '<div class="middleware-item status-' . $middleware['status'] . '">
                                <span class="middleware-name">' . htmlspecialchars($middleware['middleware']) . '</span>
                                <span class="middleware-time">' . number_format($middleware['execution_time'] * 1000, 2) . 'ms</span>
                            </div>';
                        }, $routes['middleware_execution'])) . '
                    </div>
                </div>' : '') . '
            </div>
        </div>';
    }

    /**
     * Generate performance tab
     */
    private function generatePerformanceTab($debugData)
    {
        $performance = $debugData['performance'];
        
        return '
        <div id="tab-performance" class="tab-content">
            <h2>⚡ Performance Analysis</h2>
            
            <div class="performance-metrics">
                <div class="metric-card">
                    <h3>Execution Time</h3>
                    <div class="metric-value">' . number_format($performance['execution_time'] * 1000, 2) . 'ms</div>
                </div>
                
                <div class="metric-card">
                    <h3>Memory Usage</h3>
                    <div class="metric-value">' . $this->formatBytes($performance['memory_usage']) . '</div>
                </div>
                
                <div class="metric-card">
                    <h3>Peak Memory</h3>
                    <div class="metric-value">' . $this->formatBytes($performance['peak_memory']) . '</div>
                </div>
                
                <div class="metric-card">
                    <h3>Included Files</h3>
                    <div class="metric-value">' . $performance['included_files'] . '</div>
                </div>
            </div>
            
            ' . (!empty($performance['bottlenecks']) ? '<div class="bottlenecks-section">
                <h3>Performance Bottlenecks</h3>
                <ul class="bottlenecks-list">
                    ' . implode('', array_map(function($bottleneck) {
                        return '<li>' . htmlspecialchars($bottleneck) . '</li>';
                    }, $performance['bottlenecks'])) . '
                </ul>
            </div>' : '') . '
        </div>';
    }

    /**
     * Generate errors tab
     */
    private function generateErrorsTab($debugData)
    {
        $errors = $debugData['errors'];
        
        $html = '
        <div id="tab-errors" class="tab-content">
            <h2>🚨 Errors and Exceptions</h2>';
            
        if (!empty($errors['exceptions']) || !empty($errors['errors'])) {
            if (!empty($errors['exceptions'])) {
                $html .= '<h3>Exceptions</h3>';
                foreach ($errors['exceptions'] as $exception) {
                    $html .= '
                    <div class="error-item exception">
                        <h4>' . htmlspecialchars($exception['class']) . '</h4>
                        <p>' . htmlspecialchars($exception['message']) . '</p>
                        <p><strong>File:</strong> ' . htmlspecialchars($exception['file']) . ':' . $exception['line'] . '</p>
                    </div>';
                }
            }
            
            if (!empty($errors['errors'])) {
                $html .= '<h3>PHP Errors</h3>';
                foreach ($errors['errors'] as $error) {
                    $html .= '
                    <div class="error-item error">
                        <h4>' . htmlspecialchars($error['severity_name']) . '</h4>
                        <p>' . htmlspecialchars($error['message']) . '</p>
                        <p><strong>File:</strong> ' . htmlspecialchars($error['file']) . ':' . $error['line'] . '</p>
                    </div>';
                }
            }
        } else {
            $html .= '<p>No errors or exceptions occurred.</p>';
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Generate request tab
     */
    private function generateRequestTab($debugData)
    {
        $request = $debugData['request'];
        
        return '
        <div id="tab-request" class="tab-content">
            <h2>🌐 Request Information</h2>
            
            <div class="request-details">
                <h3>Request Details</h3>
                <p><strong>URL:</strong> ' . htmlspecialchars($request['url']) . '</p>
                <p><strong>Method:</strong> ' . htmlspecialchars($request['method']) . '</p>
                <p><strong>User Agent:</strong> ' . htmlspecialchars($request['user_agent']) . '</p>
                <p><strong>IP Address:</strong> ' . htmlspecialchars($request['ip']) . '</p>
                
                <div class="collapsible-section">
                    <button class="collapsible-trigger" onclick="toggleCollapsible(this)">📊 View Full Request Data</button>
                    <div class="collapsible-content">
                        <pre>' . htmlspecialchars(json_encode($request, JSON_PRETTY_PRINT)) . '</pre>
                    </div>
                </div>
            </div>
        </div>';
    }

    /**
     * Collect all debug data from various components
     */
    private function collectAllDebugData()
    {
        $data = [
            'request' => $this->getRequestInfo(),
            'errors' => $this->errorHandler->getDebugData(),
            'data_flow' => $this->dataFlowTracker->getDataFlow(),
            'routes' => $this->routeDebugger->getRouteDebugInfo(),
            'database' => $this->databaseDebugger->getDatabaseDebugInfo(),
            'mvc_flow' => $this->mvcFlowVisualizer->getFlowData(),
            'performance' => $this->getPerformanceData(),
            'summary' => []
        ];

        $data['summary'] = $this->generateSummary($data);

        return $data;
    }

    /**
     * Get request information
     */
    private function getRequestInfo()
    {
        return [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'timestamp' => microtime(true),
            'headers' => getallheaders() ?: [],
            'get_data' => $_GET,
            'post_data' => $_POST,
            'session_data' => $_SESSION ?? []
        ];
    }

    /**
     * Get performance data
     */
    private function getPerformanceData()
    {
        return [
            'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)),
            'memory_usage' => memory_get_usage(),
            'peak_memory' => memory_get_peak_usage(),
            'included_files' => count(get_included_files()),
            'bottlenecks' => $this->identifyBottlenecks()
        ];
    }

    /**
     * Identify performance bottlenecks
     */
    private function identifyBottlenecks()
    {
        $bottlenecks = [];

        // Check database performance
        $dbData = $this->databaseDebugger->getDatabaseDebugInfo();
        if (!empty($dbData['statistics']['slow_queries'])) {
            $bottlenecks[] = $dbData['statistics']['slow_queries'] . ' slow database queries detected';
        }

        // Check memory usage
        $memoryUsage = memory_get_peak_usage();
        if ($memoryUsage > 50 * 1024 * 1024) { // 50MB
            $bottlenecks[] = 'High memory usage: ' . $this->formatBytes($memoryUsage);
        }

        // Check execution time
        $executionTime = microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        if ($executionTime > 1.0) { // 1 second
            $bottlenecks[] = 'Slow execution time: ' . number_format($executionTime * 1000, 2) . 'ms';
        }

        return $bottlenecks;
    }

    /**
     * Generate comprehensive summary
     */
    private function generateSummary($data)
    {
        $summary = [
            'status' => 'success',
            'execution_time' => $data['performance']['execution_time'],
            'memory_usage' => $data['performance']['memory_usage'],
            'peak_memory' => $data['performance']['peak_memory'],
            'query_count' => count($data['database']['queries'] ?? []),
            'query_time' => array_sum(array_column($data['database']['queries'] ?? [], 'execution_time')),
            'slow_queries' => count(array_filter($data['database']['queries'] ?? [], function($q) { return $q['is_slow'] ?? false; })),
            'mvc_steps_completed' => 0,
            'mvc_total_steps' => 0,
            'mvc_errors' => 0,
            'mvc_warnings' => 0,
            'issues' => []
        ];

        // MVC flow summary
        if (!empty($data['mvc_flow']['summary'])) {
            $mvcSummary = $data['mvc_flow']['summary'];
            $summary['mvc_steps_completed'] = $mvcSummary['completed_steps'];
            $summary['mvc_total_steps'] = $mvcSummary['total_steps'];
            $summary['mvc_errors'] = $mvcSummary['error_steps'];
            $summary['mvc_warnings'] = $mvcSummary['warning_steps'];

            if ($mvcSummary['status'] === 'error') {
                $summary['status'] = 'error';
            } elseif ($mvcSummary['status'] === 'warning' && $summary['status'] !== 'error') {
                $summary['status'] = 'warning';
            }
        }

        // Check for errors
        if (!empty($data['errors']['exceptions']) || !empty($data['errors']['errors'])) {
            $summary['status'] = 'error';
        }

        // Identify issues
        if ($summary['slow_queries'] > 0) {
            $summary['issues'][] = ['type' => 'warning', 'message' => $summary['slow_queries'] . ' slow database queries'];
        }

        if ($summary['execution_time'] > 1.0) {
            $summary['issues'][] = ['type' => 'warning', 'message' => 'Slow page load time: ' . number_format($summary['execution_time'] * 1000, 2) . 'ms'];
        }

        if ($summary['memory_usage'] > 50 * 1024 * 1024) {
            $summary['issues'][] = ['type' => 'warning', 'message' => 'High memory usage: ' . $this->formatBytes($summary['memory_usage'])];
        }

        return $summary;
    }

    /**
     * Get status icon
     */
    private function getStatusIcon($status)
    {
        switch ($status) {
            case 'success':
                return '✅';
            case 'error':
                return '❌';
            case 'warning':
                return '⚠️';
            default:
                return '❓';
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 1)
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Generate debug styles
     */
    private function generateDebugStyles()
    {
        return '
        <style>
            #arknox-debug-interface * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            .debug-toolbar {
                background: linear-gradient(135deg, #1e293b, #334155);
                color: #e2e8f0;
                padding: 12px 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                border-top: 3px solid #3b82f6;
                box-shadow: 0 -5px 20px rgba(0,0,0,0.3);
                font-size: 14px;
            }

            .toolbar-brand {
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: bold;
            }

            .brand-icon {
                font-size: 18px;
            }

            .toolbar-metrics {
                display: flex;
                gap: 20px;
                align-items: center;
            }

            .metric-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                cursor: pointer;
                padding: 8px 12px;
                border-radius: 6px;
                transition: background-color 0.2s;
            }

            .metric-item:hover {
                background: rgba(255,255,255,0.1);
            }

            .metric-value {
                font-weight: bold;
                font-size: 16px;
            }

            .metric-label {
                font-size: 11px;
                opacity: 0.8;
                margin-top: 2px;
            }

            .metric-success { color: #10b981; }
            .metric-warning { color: #f59e0b; }
            .metric-error { color: #ef4444; }

            .toolbar-actions {
                display: flex;
                gap: 8px;
            }

            .action-btn {
                background: rgba(255,255,255,0.1);
                border: none;
                color: #e2e8f0;
                padding: 8px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                transition: background-color 0.2s;
            }

            .action-btn:hover {
                background: rgba(255,255,255,0.2);
            }

            .debug-panel {
                background: #0f172a;
                color: #e2e8f0;
                max-height: 70vh;
                overflow: hidden;
                border-top: 1px solid #334155;
            }

            .panel-header {
                background: #1e293b;
                padding: 0;
                border-bottom: 1px solid #334155;
            }

            .panel-tabs {
                display: flex;
                overflow-x: auto;
            }

            .tab-btn {
                background: transparent;
                border: none;
                color: #94a3b8;
                padding: 12px 20px;
                cursor: pointer;
                border-bottom: 3px solid transparent;
                white-space: nowrap;
                transition: all 0.2s;
            }

            .tab-btn:hover {
                color: #e2e8f0;
                background: rgba(255,255,255,0.05);
            }

            .tab-btn.active {
                color: #3b82f6;
                border-bottom-color: #3b82f6;
                background: rgba(59,130,246,0.1);
            }

            .panel-content {
                padding: 20px;
                overflow-y: auto;
                max-height: calc(70vh - 60px);
            }

            .tab-content {
                display: none;
            }

            .tab-content.active {
                display: block;
            }

            .overview-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }

            .overview-card {
                background: #1e293b;
                padding: 20px;
                border-radius: 8px;
                border-left: 4px solid #3b82f6;
            }

            .overview-card h3 {
                margin-bottom: 15px;
                color: #3b82f6;
            }

            .status-success { color: #10b981; }
            .status-warning { color: #f59e0b; }
            .status-error { color: #ef4444; }

            .issues-section {
                background: #1e293b;
                padding: 20px;
                border-radius: 8px;
                border-left: 4px solid #f59e0b;
            }

            .issues-list {
                list-style: none;
                margin-top: 10px;
            }

            .issue-warning {
                color: #f59e0b;
                margin-bottom: 5px;
            }

            .issue-error {
                color: #ef4444;
                margin-bottom: 5px;
            }

            .collapsible-section {
                margin-top: 15px;
            }

            .collapsible-trigger {
                background: #374151;
                border: none;
                color: #e2e8f0;
                padding: 8px 12px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
            }

            .collapsible-trigger:hover {
                background: #4b5563;
            }

            .collapsible-content {
                display: none;
                margin-top: 10px;
                background: #111827;
                padding: 15px;
                border-radius: 4px;
                overflow-x: auto;
            }

            .collapsible-content.open {
                display: block;
            }

            .collapsible-content pre {
                font-family: "Courier New", monospace;
                font-size: 12px;
                line-height: 1.4;
                white-space: pre-wrap;
            }

            .data-flow-timeline {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }

            .data-flow-step {
                display: flex;
                align-items: flex-start;
                gap: 15px;
                background: #1e293b;
                padding: 15px;
                border-radius: 8px;
                border-left: 4px solid #10b981;
            }

            .step-number {
                background: #3b82f6;
                color: white;
                width: 30px;
                height: 30px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 12px;
                flex-shrink: 0;
            }

            .step-details h4 {
                color: #3b82f6;
                margin-bottom: 8px;
            }

            .queries-list {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }

            .query-item {
                background: #1e293b;
                padding: 15px;
                border-radius: 8px;
                border-left: 4px solid #8b5cf6;
            }

            .query-slow {
                border-left-color: #ef4444;
            }

            .query-header {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-bottom: 10px;
            }

            .query-number {
                background: #374151;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
            }

            .query-type {
                background: #8b5cf6;
                color: white;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
            }

            .query-time {
                color: #f59e0b;
                font-weight: bold;
                font-size: 12px;
            }

            .query-sql pre {
                background: #111827;
                padding: 10px;
                border-radius: 4px;
                font-family: "Courier New", monospace;
                font-size: 12px;
                overflow-x: auto;
            }

            .performance-metrics {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }

            .metric-card {
                background: #1e293b;
                padding: 20px;
                border-radius: 8px;
                text-align: center;
                border-left: 4px solid #3b82f6;
            }

            .metric-card h3 {
                margin-bottom: 10px;
                color: #94a3b8;
                font-size: 14px;
            }

            .metric-card .metric-value {
                font-size: 24px;
                font-weight: bold;
                color: #3b82f6;
            }

            .error-item {
                background: #1e293b;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 15px;
                border-left: 4px solid #ef4444;
            }

            .error-item h4 {
                color: #ef4444;
                margin-bottom: 8px;
            }
        </style>';
    }

    /**
     * Generate debug scripts
     */
    private function generateDebugScripts()
    {
        return '
        <script>
            function toggleDebugPanel() {
                const panel = document.getElementById("debug-panel");
                const icon = document.getElementById("panel-toggle-icon");

                if (panel.style.display === "none" || panel.style.display === "") {
                    panel.style.display = "block";
                    icon.textContent = "📈";
                } else {
                    panel.style.display = "none";
                    icon.textContent = "📊";
                }
            }

            function showDebugPanel(tabName) {
                document.getElementById("debug-panel").style.display = "block";
                showDebugTab(tabName);
            }

            function showDebugTab(tabName) {
                // Hide all tab contents
                document.querySelectorAll(".tab-content").forEach(tab => {
                    tab.classList.remove("active");
                });

                // Remove active class from all tab buttons
                document.querySelectorAll(".tab-btn").forEach(btn => {
                    btn.classList.remove("active");
                });

                // Show selected tab content
                document.getElementById("tab-" + tabName).classList.add("active");

                // Add active class to clicked tab button
                event.target.classList.add("active");
            }

            function toggleCollapsible(trigger) {
                const content = trigger.nextElementSibling;
                content.classList.toggle("open");
            }

            function exportDebugData() {
                const debugData = ' . json_encode($this->collectAllDebugData()) . ';
                const blob = new Blob([JSON.stringify(debugData, null, 2)], {type: "application/json"});
                const url = URL.createObjectURL(blob);
                const a = document.createElement("a");
                a.href = url;
                a.download = "arknox-debug-" + new Date().toISOString() + ".json";
                a.click();
                URL.revokeObjectURL(url);
            }

            function closeDebugInterface() {
                document.getElementById("arknox-debug-interface").style.display = "none";
            }

            // Initialize collapsible sections
            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".collapsible-trigger").forEach(trigger => {
                    trigger.addEventListener("click", function() {
                        toggleCollapsible(this);
                    });
                });
            });
        </script>';
    }

    /**
     * Check if debugging is enabled
     */
    public function isEnabled()
    {
        return $this->enabled;
    }
}
