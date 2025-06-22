<?php

namespace App\Core;

use App\Exceptions\DatabaseException;

use PDO;
use PDOException;

/**
 * Database Connection Manager
 * 
 * Handles database connections and provides a singleton pattern
 * for database access throughout the application.
 */
class Database
{
    private static $instance = null;
    private $connection = null;
    private $config = [];
    private $logger;
    private $cache;
    private $performanceOptimizer;
    private $queryCount = 0;

    private function __construct()
    {
        $this->config = require __DIR__ . '/../../config/database.php';

        // Initialize logger if available
        if (class_exists('App\Core\Logger')) {
            $this->logger = new Logger();
        }

        // Initialize cache and performance optimizer if available
        if (class_exists('App\Core\Cache')) {
            $this->cache = Cache::getInstance();
        }

        if (class_exists('App\Core\PerformanceOptimizer')) {
            $this->performanceOptimizer = PerformanceOptimizer::getInstance();
        }

        $this->connect();
    }

    /**
     * Get database instance (Singleton pattern)
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     */
    public function getConnection()
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Establish database connection
     */
    private function connect()
    {
        try {
            // Handle both simple and complex config structures
            $config = $this->config;
            if (isset($this->config['connections'])) {
                $config = $this->config['connections'][$this->config['default']];
            }

            $driver = $config['driver'] ?? 'mysql';
            $dsn = sprintf(
                "%s:host=%s;dbname=%s;charset=%s",
                $driver,
                $config['host'],
                $config['database'],
                $config['charset']
            );

            $this->connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options'] ?? []
            );

        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new \Exception("Database connection failed. Please check your configuration.");
        }
    }

    /**
     * Execute a query and return results
     */
    public function query($sql, $params = [])
    {
        $startTime = microtime(true);
        $this->queryCount++;

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            $duration = microtime(true) - $startTime;

            // Log query performance if optimizer is available
            if ($this->performanceOptimizer) {
                $this->performanceOptimizer->logQuery($sql, $params, $duration);
            }

            // Log slow queries if logger is available
            if ($duration > 0.1 && $this->logger) { // 100ms threshold
                $this->logger->warning("Slow query detected", [
                    'sql' => $sql,
                    'params' => $params,
                    'duration' => $duration
                ]);
            }

            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage() . " SQL: " . $sql);
            throw new \Exception("Database query failed: " . $e->getMessage());
        }
    }

    /**
     * Get single record
     */
    public function fetch($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Get all records
     */
    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Get single value
     */
    public function fetchColumn($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Insert record and return last insert ID
     */
    public function insert($sql, $params = [])
    {
        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }

    /**
     * Update/Delete and return affected rows
     */
    public function execute($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit()
    {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback()
    {
        return $this->connection->rollback();
    }

    /**
     * Check if in transaction
     */
    public function inTransaction()
    {
        return $this->connection->inTransaction();
    }

    /**
     * Cached fetch - get single record with caching
     */
    public function cachedFetch($sql, $params = [], $ttl = 300)
    {
        if ($this->cache) {
            return $this->cache->cacheQuery($sql, $params, function() use ($sql, $params) {
                return $this->fetch($sql, $params);
            }, $ttl);
        }

        return $this->fetch($sql, $params);
    }

    /**
     * Cached fetch all - get all records with caching
     */
    public function cachedFetchAll($sql, $params = [], $ttl = 300)
    {
        if ($this->cache) {
            return $this->cache->cacheQuery($sql, $params, function() use ($sql, $params) {
                return $this->fetchAll($sql, $params);
            }, $ttl);
        }

        return $this->fetchAll($sql, $params);
    }

    /**
     * Cached fetch column - get single value with caching
     */
    public function cachedFetchColumn($sql, $params = [], $ttl = 300)
    {
        if ($this->cache) {
            return $this->cache->cacheQuery($sql, $params, function() use ($sql, $params) {
                return $this->fetchColumn($sql, $params);
            }, $ttl);
        }

        return $this->fetchColumn($sql, $params);
    }

    /**
     * Get query count for performance monitoring
     */
    public function getQueryCount()
    {
        return $this->queryCount;
    }

    /**
     * Reset query count
     */
    public function resetQueryCount()
    {
        $this->queryCount = 0;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {}
}
