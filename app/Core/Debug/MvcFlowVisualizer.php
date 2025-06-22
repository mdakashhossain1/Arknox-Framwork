<?php

namespace App\Core\Debug;

/**
 * MVC Flow Visualizer
 * 
 * Creates an interactive visual representation of the complete request lifecycle
 * showing which components are working correctly and where problems occur
 */
class MvcFlowVisualizer
{
    private static $instance = null;
    private $enabled = false;
    private $flowSteps = [];
    private $currentStep = 0;
    private $requestId;
    private $startTime;

    // Flow step statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS = 'success';
    const STATUS_WARNING = 'warning';
    const STATUS_ERROR = 'error';

    public function __construct()
    {
        $this->enabled = config('app.debug', false) && config('app.environment') !== 'production';
        
        if ($this->enabled) {
            $this->initializeFlow();
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
     * Initialize MVC flow tracking
     */
    private function initializeFlow()
    {
        $this->requestId = uniqid('flow_');
        $this->startTime = microtime(true);
        
        // Define the standard MVC flow steps
        $this->flowSteps = [
            'request_received' => [
                'id' => 'request_received',
                'name' => 'Request Received',
                'description' => 'HTTP request received by the framework',
                'component' => 'Framework',
                'status' => self::STATUS_PENDING,
                'start_time' => null,
                'end_time' => null,
                'duration' => null,
                'memory_usage' => null,
                'data' => [],
                'errors' => [],
                'warnings' => []
            ],
            'middleware_processing' => [
                'id' => 'middleware_processing',
                'name' => 'Middleware Processing',
                'description' => 'Request processed through middleware stack',
                'component' => 'Middleware',
                'status' => self::STATUS_PENDING,
                'start_time' => null,
                'end_time' => null,
                'duration' => null,
                'memory_usage' => null,
                'data' => [],
                'errors' => [],
                'warnings' => []
            ],
            'route_matching' => [
                'id' => 'route_matching',
                'name' => 'Route Matching',
                'description' => 'Finding the appropriate route for the request',
                'component' => 'Router',
                'status' => self::STATUS_PENDING,
                'start_time' => null,
                'end_time' => null,
                'duration' => null,
                'memory_usage' => null,
                'data' => [],
                'errors' => [],
                'warnings' => []
            ],
            'controller_instantiation' => [
                'id' => 'controller_instantiation',
                'name' => 'Controller Instantiation',
                'description' => 'Creating controller instance and preparing for execution',
                'component' => 'Controller',
                'status' => self::STATUS_PENDING,
                'start_time' => null,
                'end_time' => null,
                'duration' => null,
                'memory_usage' => null,
                'data' => [],
                'errors' => [],
                'warnings' => []
            ],
            'model_operations' => [
                'id' => 'model_operations',
                'name' => 'Model Operations',
                'description' => 'Database queries and model operations',
                'component' => 'Model',
                'status' => self::STATUS_PENDING,
                'start_time' => null,
                'end_time' => null,
                'duration' => null,
                'memory_usage' => null,
                'data' => [],
                'errors' => [],
                'warnings' => []
            ],
            'controller_execution' => [
                'id' => 'controller_execution',
                'name' => 'Controller Execution',
                'description' => 'Controller method execution and business logic',
                'component' => 'Controller',
                'status' => self::STATUS_PENDING,
                'start_time' => null,
                'end_time' => null,
                'duration' => null,
                'memory_usage' => null,
                'data' => [],
                'errors' => [],
                'warnings' => []
            ],
            'view_rendering' => [
                'id' => 'view_rendering',
                'name' => 'View Rendering',
                'description' => 'Template rendering and response preparation',
                'component' => 'View',
                'status' => self::STATUS_PENDING,
                'start_time' => null,
                'end_time' => null,
                'duration' => null,
                'memory_usage' => null,
                'data' => [],
                'errors' => [],
                'warnings' => []
            ],
            'response_sent' => [
                'id' => 'response_sent',
                'name' => 'Response Sent',
                'description' => 'HTTP response sent to client',
                'component' => 'Framework',
                'status' => self::STATUS_PENDING,
                'start_time' => null,
                'end_time' => null,
                'duration' => null,
                'memory_usage' => null,
                'data' => [],
                'errors' => [],
                'warnings' => []
            ]
        ];
    }

    /**
     * Start tracking a flow step
     */
    public function startStep($stepId, $data = [])
    {
        if (!$this->enabled || !isset($this->flowSteps[$stepId])) {
            return;
        }

        $this->flowSteps[$stepId]['status'] = self::STATUS_PROCESSING;
        $this->flowSteps[$stepId]['start_time'] = microtime(true);
        $this->flowSteps[$stepId]['memory_usage'] = memory_get_usage();
        $this->flowSteps[$stepId]['data'] = array_merge($this->flowSteps[$stepId]['data'], $data);

        // Track in advanced error handler
        $this->trackInErrorHandler($stepId, self::STATUS_PROCESSING, $data);
    }

    /**
     * Complete a flow step successfully
     */
    public function completeStep($stepId, $data = [])
    {
        if (!$this->enabled || !isset($this->flowSteps[$stepId])) {
            return;
        }

        $endTime = microtime(true);
        $this->flowSteps[$stepId]['status'] = self::STATUS_SUCCESS;
        $this->flowSteps[$stepId]['end_time'] = $endTime;
        $this->flowSteps[$stepId]['duration'] = $endTime - ($this->flowSteps[$stepId]['start_time'] ?: $endTime);
        $this->flowSteps[$stepId]['data'] = array_merge($this->flowSteps[$stepId]['data'], $data);

        // Track in advanced error handler
        $this->trackInErrorHandler($stepId, self::STATUS_SUCCESS, $data);
    }

    /**
     * Mark a flow step as having an error
     */
    public function errorStep($stepId, $error, $data = [])
    {
        if (!$this->enabled || !isset($this->flowSteps[$stepId])) {
            return;
        }

        $endTime = microtime(true);
        $this->flowSteps[$stepId]['status'] = self::STATUS_ERROR;
        $this->flowSteps[$stepId]['end_time'] = $endTime;
        $this->flowSteps[$stepId]['duration'] = $endTime - ($this->flowSteps[$stepId]['start_time'] ?: $endTime);
        $this->flowSteps[$stepId]['errors'][] = $error;
        $this->flowSteps[$stepId]['data'] = array_merge($this->flowSteps[$stepId]['data'], $data);

        // Track in advanced error handler
        $this->trackInErrorHandler($stepId, self::STATUS_ERROR, $data, $error);
    }

    /**
     * Mark a flow step as having a warning
     */
    public function warningStep($stepId, $warning, $data = [])
    {
        if (!$this->enabled || !isset($this->flowSteps[$stepId])) {
            return;
        }

        if ($this->flowSteps[$stepId]['status'] === self::STATUS_PENDING) {
            $this->flowSteps[$stepId]['status'] = self::STATUS_WARNING;
        }
        
        $this->flowSteps[$stepId]['warnings'][] = $warning;
        $this->flowSteps[$stepId]['data'] = array_merge($this->flowSteps[$stepId]['data'], $data);

        // Track in advanced error handler
        $this->trackInErrorHandler($stepId, self::STATUS_WARNING, $data, null, $warning);
    }

    /**
     * Generate visual flow diagram HTML
     */
    public function generateFlowDiagram()
    {
        if (!$this->enabled) {
            return '<p>MVC Flow visualization is disabled.</p>';
        }

        $html = '<div class="mvc-flow-diagram">';
        $html .= '<h3>🏗️ MVC Request Flow</h3>';
        $html .= '<div class="flow-timeline">';

        foreach ($this->flowSteps as $step) {
            $statusClass = $this->getStatusClass($step['status']);
            $statusIcon = $this->getStatusIcon($step['status']);
            
            $html .= '<div class="flow-step ' . $statusClass . '" data-step="' . $step['id'] . '">';
            $html .= '<div class="step-header">';
            $html .= '<div class="step-icon">' . $statusIcon . '</div>';
            $html .= '<div class="step-info">';
            $html .= '<h4>' . htmlspecialchars($step['name']) . '</h4>';
            $html .= '<p>' . htmlspecialchars($step['description']) . '</p>';
            $html .= '<span class="component-badge">' . htmlspecialchars($step['component']) . '</span>';
            $html .= '</div>';
            $html .= '<div class="step-metrics">';
            
            if ($step['duration'] !== null) {
                $html .= '<span class="metric">⏱️ ' . number_format($step['duration'] * 1000, 2) . 'ms</span>';
            }
            
            if ($step['memory_usage'] !== null) {
                $html .= '<span class="metric">🧠 ' . $this->formatBytes($step['memory_usage']) . '</span>';
            }
            
            $html .= '</div>';
            $html .= '</div>';

            // Show errors and warnings
            if (!empty($step['errors']) || !empty($step['warnings'])) {
                $html .= '<div class="step-issues">';
                
                foreach ($step['errors'] as $error) {
                    $html .= '<div class="issue error">❌ ' . htmlspecialchars($error) . '</div>';
                }
                
                foreach ($step['warnings'] as $warning) {
                    $html .= '<div class="issue warning">⚠️ ' . htmlspecialchars($warning) . '</div>';
                }
                
                $html .= '</div>';
            }

            // Show step data if available
            if (!empty($step['data'])) {
                $html .= '<div class="step-data collapsible-trigger" onclick="toggleStepData(\'' . $step['id'] . '\')">';
                $html .= '📊 View Step Data';
                $html .= '</div>';
                $html .= '<div class="step-data-content" id="data-' . $step['id'] . '" style="display: none;">';
                $html .= '<pre>' . htmlspecialchars(json_encode($step['data'], JSON_PRETTY_PRINT)) . '</pre>';
                $html .= '</div>';
            }

            $html .= '</div>';

            // Add connector arrow (except for last step)
            if ($step['id'] !== 'response_sent') {
                $html .= '<div class="flow-connector">⬇️</div>';
            }
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get CSS class for step status
     */
    private function getStatusClass($status)
    {
        switch ($status) {
            case self::STATUS_SUCCESS:
                return 'step-success';
            case self::STATUS_ERROR:
                return 'step-error';
            case self::STATUS_WARNING:
                return 'step-warning';
            case self::STATUS_PROCESSING:
                return 'step-processing';
            default:
                return 'step-pending';
        }
    }

    /**
     * Get icon for step status
     */
    private function getStatusIcon($status)
    {
        switch ($status) {
            case self::STATUS_SUCCESS:
                return '✅';
            case self::STATUS_ERROR:
                return '❌';
            case self::STATUS_WARNING:
                return '⚠️';
            case self::STATUS_PROCESSING:
                return '🔄';
            default:
                return '⏳';
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Track in advanced error handler
     */
    private function trackInErrorHandler($stepId, $status, $data, $error = null, $warning = null)
    {
        $errorHandler = AdvancedErrorHandler::getInstance();
        if ($errorHandler && $errorHandler->isEnabled()) {
            $errorHandler->trackMvcFlow(
                $stepId,
                $this->flowSteps[$stepId]['component'],
                $status,
                $data,
                $error ?: $warning
            );
        }
    }

    /**
     * Get flow summary
     */
    public function getFlowSummary()
    {
        $summary = [
            'total_steps' => count($this->flowSteps),
            'completed_steps' => 0,
            'error_steps' => 0,
            'warning_steps' => 0,
            'total_duration' => 0,
            'bottleneck_step' => null,
            'status' => 'unknown'
        ];

        $slowestStep = null;
        $slowestDuration = 0;

        foreach ($this->flowSteps as $step) {
            if ($step['status'] === self::STATUS_SUCCESS) {
                $summary['completed_steps']++;
            } elseif ($step['status'] === self::STATUS_ERROR) {
                $summary['error_steps']++;
            } elseif ($step['status'] === self::STATUS_WARNING) {
                $summary['warning_steps']++;
            }

            if ($step['duration'] !== null) {
                $summary['total_duration'] += $step['duration'];
                
                if ($step['duration'] > $slowestDuration) {
                    $slowestDuration = $step['duration'];
                    $slowestStep = $step;
                }
            }
        }

        $summary['bottleneck_step'] = $slowestStep;

        // Determine overall status
        if ($summary['error_steps'] > 0) {
            $summary['status'] = 'error';
        } elseif ($summary['warning_steps'] > 0) {
            $summary['status'] = 'warning';
        } elseif ($summary['completed_steps'] === $summary['total_steps']) {
            $summary['status'] = 'success';
        } else {
            $summary['status'] = 'processing';
        }

        return $summary;
    }

    /**
     * Get all flow data
     */
    public function getFlowData()
    {
        return [
            'request_id' => $this->requestId,
            'start_time' => $this->startTime,
            'steps' => $this->flowSteps,
            'summary' => $this->getFlowSummary()
        ];
    }

    /**
     * Check if visualization is enabled
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Reset flow data
     */
    public function reset()
    {
        $this->initializeFlow();
    }
}
