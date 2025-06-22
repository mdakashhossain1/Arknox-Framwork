<?php

namespace App\Core\Debug;

/**
 * Database Debugger
 * 
 * Enhanced database debugging that shows SQL queries, results, data transformations,
 * model relationships, and performance metrics for database operations
 */
class DatabaseDebugger
{
    private static $instance = null;
    private $enabled = false;
    private $queries = [];
    private $modelOperations = [];
    private $relationships = [];
    private $transactions = [];
    private $connectionInfo = [];
    private $queryStats = [];

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
     * Initialize database tracking
     */
    private function initializeTracking()
    {
        $this->queryStats = [
            'total_queries' => 0,
            'total_time' => 0,
            'slow_queries' => 0,
            'failed_queries' => 0,
            'duplicate_queries' => 0,
            'n_plus_one_detected' => 0
        ];
    }

    /**
     * Track SQL query execution
     */
    public function trackQuery($sql, $bindings = [], $executionTime = 0, $result = null, $error = null)
    {
        if (!$this->enabled) return;

        $queryId = uniqid('query_');
        $timestamp = microtime(true);

        $queryData = [
            'id' => $queryId,
            'sql' => $sql,
            'bindings' => $bindings,
            'execution_time' => $executionTime,
            'result' => $this->analyzeQueryResult($result),
            'error' => $error,
            'timestamp' => $timestamp,
            'memory_usage' => memory_get_usage(),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
            'query_type' => $this->detectQueryType($sql),
            'affected_tables' => $this->extractTables($sql),
            'query_hash' => md5($sql),
            'is_slow' => $executionTime > 0.1, // 100ms threshold
            'is_duplicate' => $this->isDuplicateQuery($sql)
        ];

        $this->queries[] = $queryData;
        $this->updateQueryStats($queryData);

        // Detect N+1 queries
        $this->detectNPlusOneQueries($queryData);

        // Track in advanced error handler
        $this->trackInErrorHandler($queryData);

        return $queryId;
    }

    /**
     * Track model operation
     */
    public function trackModelOperation($model, $operation, $data = [], $queryId = null, $relationships = [])
    {
        if (!$this->enabled) return;

        $operationData = [
            'id' => uniqid('model_'),
            'model' => $model,
            'operation' => $operation,
            'data' => $this->sanitizeData($data),
            'query_id' => $queryId,
            'relationships' => $relationships,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];

        $this->modelOperations[] = $operationData;

        // Track relationships
        if (!empty($relationships)) {
            $this->trackRelationships($model, $relationships);
        }
    }

    /**
     * Track model relationships
     */
    public function trackRelationships($model, $relationships)
    {
        if (!$this->enabled) return;

        foreach ($relationships as $relationship) {
            $relationshipData = [
                'parent_model' => $model,
                'relationship_type' => $relationship['type'] ?? 'unknown',
                'related_model' => $relationship['model'] ?? 'unknown',
                'foreign_key' => $relationship['foreign_key'] ?? null,
                'local_key' => $relationship['local_key'] ?? null,
                'pivot_table' => $relationship['pivot_table'] ?? null,
                'loaded_count' => $relationship['count'] ?? 0,
                'timestamp' => microtime(true)
            ];

            $this->relationships[] = $relationshipData;
        }
    }

    /**
     * Track database transaction
     */
    public function trackTransaction($action, $transactionId = null, $savepoint = null)
    {
        if (!$this->enabled) return;

        if ($action === 'begin') {
            $transactionId = uniqid('txn_');
        }

        $transactionData = [
            'id' => $transactionId,
            'action' => $action, // begin, commit, rollback, savepoint
            'savepoint' => $savepoint,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];

        $this->transactions[] = $transactionData;

        return $transactionId;
    }

    /**
     * Track database connection information
     */
    public function trackConnection($connectionName, $config, $status = 'connected')
    {
        if (!$this->enabled) return;

        $this->connectionInfo[$connectionName] = [
            'name' => $connectionName,
            'driver' => $config['driver'] ?? 'unknown',
            'host' => $config['host'] ?? 'unknown',
            'database' => $config['database'] ?? 'unknown',
            'status' => $status,
            'connected_at' => microtime(true),
            'query_count' => 0,
            'total_time' => 0
        ];
    }

    /**
     * Analyze query result
     */
    private function analyzeQueryResult($result)
    {
        if ($result === null) {
            return ['type' => 'null', 'count' => 0];
        }

        if (is_bool($result)) {
            return ['type' => 'boolean', 'value' => $result];
        }

        if (is_numeric($result)) {
            return ['type' => 'numeric', 'value' => $result];
        }

        if (is_array($result)) {
            return [
                'type' => 'array',
                'count' => count($result),
                'sample' => count($result) > 0 ? $this->sanitizeData(array_slice($result, 0, 3)) : []
            ];
        }

        if (is_object($result)) {
            return [
                'type' => 'object',
                'class' => get_class($result),
                'properties' => $this->sanitizeData(get_object_vars($result))
            ];
        }

        return ['type' => gettype($result), 'value' => $this->sanitizeData($result)];
    }

    /**
     * Detect query type
     */
    private function detectQueryType($sql)
    {
        $sql = trim(strtoupper($sql));
        
        if (strpos($sql, 'SELECT') === 0) return 'SELECT';
        if (strpos($sql, 'INSERT') === 0) return 'INSERT';
        if (strpos($sql, 'UPDATE') === 0) return 'UPDATE';
        if (strpos($sql, 'DELETE') === 0) return 'DELETE';
        if (strpos($sql, 'CREATE') === 0) return 'CREATE';
        if (strpos($sql, 'ALTER') === 0) return 'ALTER';
        if (strpos($sql, 'DROP') === 0) return 'DROP';
        if (strpos($sql, 'SHOW') === 0) return 'SHOW';
        if (strpos($sql, 'DESCRIBE') === 0) return 'DESCRIBE';
        
        return 'OTHER';
    }

    /**
     * Extract table names from SQL
     */
    private function extractTables($sql)
    {
        $tables = [];
        
        // Simple regex to extract table names (this could be enhanced)
        preg_match_all('/(?:FROM|JOIN|INTO|UPDATE)\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?/i', $sql, $matches);
        
        if (!empty($matches[1])) {
            $tables = array_unique($matches[1]);
        }

        return $tables;
    }

    /**
     * Check if query is duplicate
     */
    private function isDuplicateQuery($sql)
    {
        $hash = md5($sql);
        
        foreach ($this->queries as $query) {
            if ($query['query_hash'] === $hash) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect N+1 query problems
     */
    private function detectNPlusOneQueries($queryData)
    {
        $sql = $queryData['sql'];
        $queryType = $queryData['query_type'];
        
        if ($queryType !== 'SELECT') {
            return;
        }

        // Look for similar queries executed in quick succession
        $recentQueries = array_slice($this->queries, -10); // Last 10 queries
        $similarQueries = 0;

        foreach ($recentQueries as $recentQuery) {
            if ($recentQuery['query_type'] === 'SELECT' && 
                $this->queriesAreSimilar($sql, $recentQuery['sql'])) {
                $similarQueries++;
            }
        }

        if ($similarQueries >= 3) {
            $this->queryStats['n_plus_one_detected']++;
            $queryData['n_plus_one_warning'] = true;
        }
    }

    /**
     * Check if two queries are similar (potential N+1)
     */
    private function queriesAreSimilar($sql1, $sql2)
    {
        // Remove WHERE clauses and compare structure
        $pattern1 = preg_replace('/WHERE\s+.*/i', '', $sql1);
        $pattern2 = preg_replace('/WHERE\s+.*/i', '', $sql2);
        
        return trim($pattern1) === trim($pattern2);
    }

    /**
     * Update query statistics
     */
    private function updateQueryStats($queryData)
    {
        $this->queryStats['total_queries']++;
        $this->queryStats['total_time'] += $queryData['execution_time'];

        if ($queryData['is_slow']) {
            $this->queryStats['slow_queries']++;
        }

        if ($queryData['error']) {
            $this->queryStats['failed_queries']++;
        }

        if ($queryData['is_duplicate']) {
            $this->queryStats['duplicate_queries']++;
        }
    }

    /**
     * Get comprehensive database debug information
     */
    public function getDatabaseDebugInfo()
    {
        return [
            'queries' => $this->queries,
            'model_operations' => $this->modelOperations,
            'relationships' => $this->relationships,
            'transactions' => $this->transactions,
            'connections' => $this->connectionInfo,
            'statistics' => $this->queryStats,
            'summary' => $this->generateDatabaseSummary()
        ];
    }

    /**
     * Generate database debugging summary
     */
    private function generateDatabaseSummary()
    {
        $summary = [
            'total_queries' => count($this->queries),
            'total_execution_time' => array_sum(array_column($this->queries, 'execution_time')),
            'average_query_time' => 0,
            'slowest_query' => null,
            'most_frequent_table' => null,
            'query_types' => [],
            'performance_issues' => []
        ];

        if ($summary['total_queries'] > 0) {
            $summary['average_query_time'] = $summary['total_execution_time'] / $summary['total_queries'];
            
            // Find slowest query
            $slowest = null;
            foreach ($this->queries as $query) {
                if ($slowest === null || $query['execution_time'] > $slowest['execution_time']) {
                    $slowest = $query;
                }
            }
            $summary['slowest_query'] = $slowest;

            // Count query types
            foreach ($this->queries as $query) {
                $type = $query['query_type'];
                $summary['query_types'][$type] = ($summary['query_types'][$type] ?? 0) + 1;
            }

            // Find most frequent table
            $tableCounts = [];
            foreach ($this->queries as $query) {
                foreach ($query['affected_tables'] as $table) {
                    $tableCounts[$table] = ($tableCounts[$table] ?? 0) + 1;
                }
            }
            if (!empty($tableCounts)) {
                $summary['most_frequent_table'] = array_keys($tableCounts, max($tableCounts))[0];
            }
        }

        // Performance issues
        if ($this->queryStats['slow_queries'] > 0) {
            $summary['performance_issues'][] = $this->queryStats['slow_queries'] . ' slow queries detected';
        }
        if ($this->queryStats['duplicate_queries'] > 0) {
            $summary['performance_issues'][] = $this->queryStats['duplicate_queries'] . ' duplicate queries detected';
        }
        if ($this->queryStats['n_plus_one_detected'] > 0) {
            $summary['performance_issues'][] = $this->queryStats['n_plus_one_detected'] . ' potential N+1 query issues detected';
        }

        return $summary;
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
    private function trackInErrorHandler($queryData)
    {
        $errorHandler = AdvancedErrorHandler::getInstance();
        if ($errorHandler && $errorHandler->isEnabled()) {
            $errorHandler->addQuery(
                $queryData['sql'],
                $queryData['bindings'],
                $queryData['execution_time'],
                $queryData['result']
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
     * Reset database debugging data
     */
    public function reset()
    {
        $this->queries = [];
        $this->modelOperations = [];
        $this->relationships = [];
        $this->transactions = [];
        $this->initializeTracking();
    }
}
