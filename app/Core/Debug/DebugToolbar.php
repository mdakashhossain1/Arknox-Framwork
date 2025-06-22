<?php

namespace App\Core\Debug;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Cache;

/**
 * Debug Toolbar
 * 
 * Laravel Debugbar-style debug toolbar with performance metrics,
 * database queries, cache operations, and system information
 */
class DebugToolbar
{
    private static $instance = null;
    private $startTime;
    private $startMemory;
    private $queries = [];
    private $cacheOperations = [];
    private $logs = [];
    private $timeline = [];
    private $enabled = false;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
        $this->enabled = config('app.debug', false) && config('app.environment') !== 'production';
        
        if ($this->enabled) {
            $this->registerCollectors();
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
     * Register data collectors
     */
    private function registerCollectors()
    {
        // Database query collector
        $this->registerDatabaseCollector();
        
        // Cache operation collector
        $this->registerCacheCollector();
        
        // Error and log collector
        $this->registerLogCollector();
    }

    private function registerDatabaseCollector()
    {
        // Hook into database operations
        $originalQuery = Database::class . '::query';
        
        // This would require modifying the Database class to support hooks
        // For now, we'll collect queries manually when they're executed
    }

    private function registerCacheCollector()
    {
        // Hook into cache operations
        // Similar to database collector
    }

    private function registerLogCollector()
    {
        // Hook into logging system
        set_error_handler([$this, 'errorHandler']);
    }

    /**
     * Add database query to collection
     */
    public function addQuery($sql, $bindings = [], $time = 0)
    {
        if (!$this->enabled) return;

        $this->queries[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => $time,
            'timestamp' => microtime(true),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];
    }

    /**
     * Add cache operation to collection
     */
    public function addCacheOperation($operation, $key, $value = null, $time = 0)
    {
        if (!$this->enabled) return;

        $this->cacheOperations[] = [
            'operation' => $operation,
            'key' => $key,
            'value' => $value,
            'time' => $time,
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Add log entry
     */
    public function addLog($level, $message, $context = [])
    {
        if (!$this->enabled) return;

        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => microtime(true),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ];
    }

    /**
     * Add timeline event
     */
    public function addTimelineEvent($name, $start = null, $end = null)
    {
        if (!$this->enabled) return;

        $this->timeline[] = [
            'name' => $name,
            'start' => $start ?: microtime(true),
            'end' => $end,
            'duration' => $end ? ($end - ($start ?: $this->startTime)) : null
        ];
    }

    /**
     * Error handler for collecting errors
     */
    public function errorHandler($severity, $message, $file, $line)
    {
        $this->addLog('error', $message, [
            'severity' => $severity,
            'file' => $file,
            'line' => $line
        ]);

        // Call the original error handler
        return false;
    }

    /**
     * Exception handler for collecting exceptions
     */
    public function exceptionHandler(\Throwable $exception)
    {
        $this->addLog('exception', $exception->getMessage(), [
            'type' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace(),
            'previous' => $exception->getPrevious() ? [
                'message' => $exception->getPrevious()->getMessage(),
                'file' => $exception->getPrevious()->getFile(),
                'line' => $exception->getPrevious()->getLine()
            ] : null
        ]);
    }

    /**
     * Inject debug toolbar into response
     */
    public function injectToolbar(Response $response)
    {
        if (!$this->enabled) {
            return $response;
        }

        $content = $response->getContent();
        
        // Only inject into HTML responses
        if (strpos($response->getHeader('Content-Type', ''), 'text/html') === false) {
            return $response;
        }

        $toolbar = $this->renderToolbar();
        
        // Inject before closing body tag
        if (strpos($content, '</body>') !== false) {
            $content = str_replace('</body>', $toolbar . '</body>', $content);
            $response->setContent($content);
        }

        return $response;
    }

    /**
     * Render the debug toolbar HTML
     */
    private function renderToolbar()
    {
        $data = $this->collectData();
        
        return '
        <div id="debug-toolbar" style="position: fixed; bottom: 0; left: 0; right: 0; background: #2d3748; color: white; font-family: monospace; font-size: 12px; z-index: 999999; border-top: 3px solid #4299e1;">
            <div style="display: flex; align-items: center; padding: 8px 16px; background: #1a202c;">
                <div style="flex: 1; display: flex; gap: 20px;">
                    <div class="debug-tab" data-tab="performance" style="cursor: pointer; padding: 4px 8px; border-radius: 4px; background: #4299e1;">
                        ⚡ ' . number_format($data['performance']['execution_time'] * 1000, 2) . 'ms
                    </div>
                    <div class="debug-tab" data-tab="memory" style="cursor: pointer; padding: 4px 8px; border-radius: 4px;">
                        🧠 ' . $this->formatBytes($data['performance']['memory_usage']) . '
                    </div>
                    <div class="debug-tab" data-tab="database" style="cursor: pointer; padding: 4px 8px; border-radius: 4px;">
                        🗄️ ' . count($data['database']['queries']) . ' queries (' . number_format($data['database']['total_time'] * 1000, 2) . 'ms)
                    </div>
                    <div class="debug-tab" data-tab="cache" style="cursor: pointer; padding: 4px 8px; border-radius: 4px;">
                        💾 ' . count($data['cache']['operations']) . ' cache ops
                    </div>
                    <div class="debug-tab" data-tab="logs" style="cursor: pointer; padding: 4px 8px; border-radius: 4px;">
                        📝 ' . count($data['logs']) . ' logs
                    </div>
                    <div class="debug-tab" data-tab="request" style="cursor: pointer; padding: 4px 8px; border-radius: 4px;">
                        🌐 Request
                    </div>
                </div>
                <div style="cursor: pointer;" onclick="document.getElementById(\'debug-toolbar\').style.display=\'none\'">
                    ❌
                </div>
            </div>
            <div id="debug-content" style="max-height: 400px; overflow-y: auto; padding: 16px; background: #2d3748; display: none;">
                ' . $this->renderTabContent($data) . '
            </div>
        </div>
        
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const tabs = document.querySelectorAll(".debug-tab");
            const content = document.getElementById("debug-content");
            
            tabs.forEach(tab => {
                tab.addEventListener("click", function() {
                    // Remove active class from all tabs
                    tabs.forEach(t => t.style.background = "transparent");
                    
                    // Add active class to clicked tab
                    this.style.background = "#4299e1";
                    
                    // Show content
                    content.style.display = "block";
                    
                    // Update content based on tab
                    const tabName = this.getAttribute("data-tab");
                    const tabContent = document.getElementById("debug-" + tabName);
                    
                    // Hide all tab contents
                    document.querySelectorAll(".debug-tab-content").forEach(tc => tc.style.display = "none");
                    
                    // Show selected tab content
                    if (tabContent) {
                        tabContent.style.display = "block";
                    }
                });
            });
        });
        </script>';
    }

    /**
     * Render tab content
     */
    private function renderTabContent($data)
    {
        $html = '';
        
        // Performance tab
        $html .= '<div id="debug-performance" class="debug-tab-content" style="display: block;">';
        $html .= '<h3>Performance Metrics</h3>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr><td style="padding: 4px; border-bottom: 1px solid #4a5568;">Execution Time:</td><td style="padding: 4px; border-bottom: 1px solid #4a5568;">' . number_format($data['performance']['execution_time'] * 1000, 2) . 'ms</td></tr>';
        $html .= '<tr><td style="padding: 4px; border-bottom: 1px solid #4a5568;">Memory Usage:</td><td style="padding: 4px; border-bottom: 1px solid #4a5568;">' . $this->formatBytes($data['performance']['memory_usage']) . '</td></tr>';
        $html .= '<tr><td style="padding: 4px; border-bottom: 1px solid #4a5568;">Peak Memory:</td><td style="padding: 4px; border-bottom: 1px solid #4a5568;">' . $this->formatBytes($data['performance']['peak_memory']) . '</td></tr>';
        $html .= '<tr><td style="padding: 4px; border-bottom: 1px solid #4a5568;">Included Files:</td><td style="padding: 4px; border-bottom: 1px solid #4a5568;">' . count(get_included_files()) . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';
        
        // Database tab
        $html .= '<div id="debug-database" class="debug-tab-content" style="display: none;">';
        $html .= '<h3>Database Queries (' . count($data['database']['queries']) . ')</h3>';
        foreach ($data['database']['queries'] as $i => $query) {
            $html .= '<div style="margin-bottom: 16px; padding: 8px; background: #1a202c; border-radius: 4px;">';
            $html .= '<div style="color: #4299e1; font-weight: bold;">Query #' . ($i + 1) . ' (' . number_format($query['time'] * 1000, 2) . 'ms)</div>';
            $html .= '<pre style="margin: 8px 0; white-space: pre-wrap;">' . htmlspecialchars($query['sql']) . '</pre>';
            if (!empty($query['bindings'])) {
                $html .= '<div style="color: #68d391;">Bindings: ' . htmlspecialchars(json_encode($query['bindings'])) . '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        
        // Cache tab
        $html .= '<div id="debug-cache" class="debug-tab-content" style="display: none;">';
        $html .= '<h3>Cache Operations (' . count($data['cache']['operations']) . ')</h3>';
        foreach ($data['cache']['operations'] as $i => $op) {
            $html .= '<div style="margin-bottom: 8px; padding: 8px; background: #1a202c; border-radius: 4px;">';
            $html .= '<span style="color: #4299e1;">' . strtoupper($op['operation']) . '</span> ';
            $html .= '<span style="color: #68d391;">' . htmlspecialchars($op['key']) . '</span>';
            if ($op['time'] > 0) {
                $html .= ' <span style="color: #fbb6ce;">(' . number_format($op['time'] * 1000, 2) . 'ms)</span>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        
        // Logs tab
        $html .= '<div id="debug-logs" class="debug-tab-content" style="display: none;">';
        $html .= '<h3>Logs (' . count($data['logs']) . ')</h3>';
        foreach ($data['logs'] as $log) {
            $levelColors = [
                'error' => '#f56565',
                'warning' => '#ed8936',
                'info' => '#4299e1',
                'debug' => '#68d391'
            ];
            $color = $levelColors[$log['level']] ?? '#a0aec0';
            
            $html .= '<div style="margin-bottom: 8px; padding: 8px; background: #1a202c; border-radius: 4px;">';
            $html .= '<span style="color: ' . $color . '; font-weight: bold;">[' . strtoupper($log['level']) . ']</span> ';
            $html .= htmlspecialchars($log['message']);
            $html .= '</div>';
        }
        $html .= '</div>';
        
        // Request tab
        $html .= '<div id="debug-request" class="debug-tab-content" style="display: none;">';
        $html .= '<h3>Request Information</h3>';
        $html .= '<h4>Server Variables</h4>';
        $html .= '<pre style="background: #1a202c; padding: 8px; border-radius: 4px; overflow-x: auto;">';
        $html .= htmlspecialchars(json_encode($_SERVER, JSON_PRETTY_PRINT));
        $html .= '</pre>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Collect all debug data
     */
    private function collectData()
    {
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        return [
            'performance' => [
                'execution_time' => $endTime - $this->startTime,
                'memory_usage' => $endMemory - $this->startMemory,
                'peak_memory' => memory_get_peak_usage(),
                'included_files' => count(get_included_files())
            ],
            'database' => [
                'queries' => $this->queries,
                'total_time' => array_sum(array_column($this->queries, 'time'))
            ],
            'cache' => [
                'operations' => $this->cacheOperations
            ],
            'logs' => $this->logs,
            'timeline' => $this->timeline
        ];
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
