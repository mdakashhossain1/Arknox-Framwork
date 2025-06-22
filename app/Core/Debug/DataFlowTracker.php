<?php

namespace App\Core\Debug;

/**
 * Data Flow Tracker
 * 
 * Tracks data flow through the MVC architecture to help debug
 * data display issues by showing how data moves from Model → Controller → View
 */
class DataFlowTracker
{
    private static $instance = null;
    private $enabled = false;
    private $dataFlow = [];
    private $currentRequest = [];
    private $dataTransformations = [];
    private $viewData = [];

    public function __construct()
    {
        $this->enabled = config('app.debug', false) && config('app.environment') !== 'production';
        
        if ($this->enabled) {
            $this->initializeTracking();
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
     * Initialize data tracking
     */
    private function initializeTracking()
    {
        $this->currentRequest = [
            'id' => uniqid('req_'),
            'timestamp' => microtime(true),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'input_data' => $this->captureInputData()
        ];
    }

    /**
     * Track data entering a controller
     */
    public function trackControllerInput($controller, $method, $data, $source = 'request')
    {
        if (!$this->enabled) return;

        $this->addDataFlowEntry([
            'stage' => 'controller_input',
            'component' => $controller,
            'method' => $method,
            'data' => $this->sanitizeData($data),
            'source' => $source,
            'data_size' => $this->calculateDataSize($data),
            'data_type' => gettype($data),
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ]);
    }

    /**
     * Track data being processed in controller
     */
    public function trackControllerProcessing($controller, $method, $inputData, $outputData, $operations = [])
    {
        if (!$this->enabled) return;

        $transformation = [
            'input' => $this->sanitizeData($inputData),
            'output' => $this->sanitizeData($outputData),
            'operations' => $operations,
            'data_changes' => $this->analyzeDataChanges($inputData, $outputData)
        ];

        $this->dataTransformations[] = $transformation;

        $this->addDataFlowEntry([
            'stage' => 'controller_processing',
            'component' => $controller,
            'method' => $method,
            'transformation' => $transformation,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage()
        ]);
    }

    /**
     * Track data going to model
     */
    public function trackModelInput($model, $method, $data, $queryType = null)
    {
        if (!$this->enabled) return;

        $this->addDataFlowEntry([
            'stage' => 'model_input',
            'component' => $model,
            'method' => $method,
            'data' => $this->sanitizeData($data),
            'query_type' => $queryType,
            'data_size' => $this->calculateDataSize($data),
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ]);
    }

    /**
     * Track data coming from model
     */
    public function trackModelOutput($model, $method, $data, $queryInfo = [])
    {
        if (!$this->enabled) return;

        $this->addDataFlowEntry([
            'stage' => 'model_output',
            'component' => $model,
            'method' => $method,
            'data' => $this->sanitizeData($data),
            'query_info' => $queryInfo,
            'data_size' => $this->calculateDataSize($data),
            'record_count' => $this->getRecordCount($data),
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage()
        ]);
    }

    /**
     * Track data being passed to view
     */
    public function trackViewInput($view, $data, $template = null)
    {
        if (!$this->enabled) return;

        $this->viewData[$view] = [
            'template' => $template,
            'data' => $this->sanitizeData($data),
            'data_keys' => is_array($data) ? array_keys($data) : [],
            'data_size' => $this->calculateDataSize($data),
            'timestamp' => microtime(true)
        ];

        $this->addDataFlowEntry([
            'stage' => 'view_input',
            'component' => $view,
            'template' => $template,
            'data' => $this->sanitizeData($data),
            'data_keys' => is_array($data) ? array_keys($data) : [],
            'data_size' => $this->calculateDataSize($data),
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage()
        ]);
    }

    /**
     * Track view rendering process
     */
    public function trackViewRendering($view, $template, $renderTime, $outputSize)
    {
        if (!$this->enabled) return;

        $this->addDataFlowEntry([
            'stage' => 'view_rendering',
            'component' => $view,
            'template' => $template,
            'render_time' => $renderTime,
            'output_size' => $outputSize,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage()
        ]);
    }

    /**
     * Track data transformation between components
     */
    public function trackDataTransformation($fromComponent, $toComponent, $inputData, $outputData, $transformationType = 'unknown')
    {
        if (!$this->enabled) return;

        $transformation = [
            'from' => $fromComponent,
            'to' => $toComponent,
            'type' => $transformationType,
            'input' => $this->sanitizeData($inputData),
            'output' => $this->sanitizeData($outputData),
            'changes' => $this->analyzeDataChanges($inputData, $outputData),
            'timestamp' => microtime(true)
        ];

        $this->dataTransformations[] = $transformation;

        $this->addDataFlowEntry([
            'stage' => 'data_transformation',
            'transformation' => $transformation,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage()
        ]);
    }

    /**
     * Add entry to data flow log
     */
    private function addDataFlowEntry($entry)
    {
        $entry['request_id'] = $this->currentRequest['id'];
        $entry['sequence'] = count($this->dataFlow) + 1;
        $this->dataFlow[] = $entry;

        // Also add to advanced error handler if available
        $errorHandler = AdvancedErrorHandler::getInstance();
        if ($errorHandler && $errorHandler->isEnabled()) {
            $errorHandler->trackDataFlow(
                $entry['component'] ?? 'unknown',
                $entry['method'] ?? 'unknown',
                $entry['data'] ?? [],
                $entry
            );
        }
    }

    /**
     * Capture input data from request
     */
    private function captureInputData()
    {
        return [
            'get' => $_GET,
            'post' => $_POST,
            'files' => $_FILES,
            'cookies' => $_COOKIE,
            'session' => $_SESSION ?? [],
            'headers' => getallheaders() ?: []
        ];
    }

    /**
     * Sanitize data for safe storage and display
     */
    private function sanitizeData($data)
    {
        if (is_string($data)) {
            return strlen($data) > 1000 ? substr($data, 0, 1000) . '... [truncated]' : $data;
        }
        
        if (is_array($data) || is_object($data)) {
            $serialized = json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
            return strlen($serialized) > 2000 ? 
                substr($serialized, 0, 2000) . '... [truncated]' : 
                json_decode($serialized, true);
        }
        
        return $data;
    }

    /**
     * Calculate data size
     */
    private function calculateDataSize($data)
    {
        if (is_string($data)) {
            return strlen($data);
        }
        
        return strlen(serialize($data));
    }

    /**
     * Get record count from data
     */
    private function getRecordCount($data)
    {
        if (is_array($data)) {
            return count($data);
        }
        
        if (is_object($data) && method_exists($data, 'count')) {
            return $data->count();
        }
        
        return is_object($data) ? 1 : 0;
    }

    /**
     * Analyze changes between input and output data
     */
    private function analyzeDataChanges($input, $output)
    {
        $changes = [
            'added_keys' => [],
            'removed_keys' => [],
            'modified_keys' => [],
            'type_changes' => [],
            'size_change' => 0
        ];

        if (is_array($input) && is_array($output)) {
            $inputKeys = array_keys($input);
            $outputKeys = array_keys($output);
            
            $changes['added_keys'] = array_diff($outputKeys, $inputKeys);
            $changes['removed_keys'] = array_diff($inputKeys, $outputKeys);
            
            foreach (array_intersect($inputKeys, $outputKeys) as $key) {
                if ($input[$key] !== $output[$key]) {
                    $changes['modified_keys'][] = $key;
                }
                
                if (gettype($input[$key]) !== gettype($output[$key])) {
                    $changes['type_changes'][$key] = [
                        'from' => gettype($input[$key]),
                        'to' => gettype($output[$key])
                    ];
                }
            }
        }

        $changes['size_change'] = $this->calculateDataSize($output) - $this->calculateDataSize($input);

        return $changes;
    }

    /**
     * Get all data flow information
     */
    public function getDataFlow()
    {
        return [
            'request' => $this->currentRequest,
            'flow' => $this->dataFlow,
            'transformations' => $this->dataTransformations,
            'view_data' => $this->viewData
        ];
    }

    /**
     * Get data flow summary
     */
    public function getDataFlowSummary()
    {
        $summary = [
            'total_steps' => count($this->dataFlow),
            'components_involved' => [],
            'data_transformations' => count($this->dataTransformations),
            'total_data_processed' => 0,
            'performance_metrics' => []
        ];

        foreach ($this->dataFlow as $entry) {
            if (isset($entry['component'])) {
                $summary['components_involved'][] = $entry['component'];
            }
            
            if (isset($entry['data_size'])) {
                $summary['total_data_processed'] += $entry['data_size'];
            }
        }

        $summary['components_involved'] = array_unique($summary['components_involved']);

        return $summary;
    }

    /**
     * Check if tracking is enabled
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Reset tracking data
     */
    public function reset()
    {
        $this->dataFlow = [];
        $this->dataTransformations = [];
        $this->viewData = [];
        $this->initializeTracking();
    }
}
