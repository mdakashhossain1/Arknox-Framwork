<?php

namespace App\Core\Database;

use PDO;
use PDOStatement;

/**
 * Database Connection
 * 
 * Wraps PDO connection with additional functionality
 */
class Connection
{
    protected $pdo;
    protected $config;
    private $queryLog = [];
    private $logQueries = false;
    
    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->logQueries = $config['log_queries'] ?? false;
    }
    
    /**
     * Get the PDO instance
     */
    public function getPdo()
    {
        return $this->pdo;
    }
    
    /**
     * Get connection config
     */
    public function getConfig()
    {
        return $this->config;
    }
    
    /**
     * Execute a query and return the statement
     */
    public function query($sql, $bindings = [])
    {
        $start = microtime(true);
        
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($bindings);
            
            if ($this->logQueries) {
                $this->logQuery($sql, $bindings, microtime(true) - $start);
            }
            
            return $statement;
        } catch (\PDOException $e) {
            throw new \Exception("Database query failed: " . $e->getMessage() . " SQL: " . $sql);
        }
    }
    
    /**
     * Execute a select query and return all results
     */
    public function select($sql, $bindings = [])
    {
        $statement = $this->query($sql, $bindings);
        return $statement->fetchAll();
    }
    
    /**
     * Execute a select query and return first result
     */
    public function selectOne($sql, $bindings = [])
    {
        $statement = $this->query($sql, $bindings);
        return $statement->fetch();
    }
    
    /**
     * Execute a select query and return single value
     */
    public function scalar($sql, $bindings = [])
    {
        $statement = $this->query($sql, $bindings);
        return $statement->fetchColumn();
    }
    
    /**
     * Execute an insert, update, or delete statement
     */
    public function statement($sql, $bindings = [])
    {
        $statement = $this->query($sql, $bindings);
        return $statement->rowCount();
    }
    
    /**
     * Execute an insert statement and return the last insert ID
     */
    public function insert($sql, $bindings = [])
    {
        $this->statement($sql, $bindings);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Execute an update statement
     */
    public function update($sql, $bindings = [])
    {
        return $this->statement($sql, $bindings);
    }
    
    /**
     * Execute a delete statement
     */
    public function delete($sql, $bindings = [])
    {
        return $this->statement($sql, $bindings);
    }
    
    /**
     * Get query builder for table
     */
    public function table($table)
    {
        return new QueryBuilder($this, $table);
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit()
    {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback()
    {
        return $this->pdo->rollBack();
    }
    
    /**
     * Check if in transaction
     */
    public function inTransaction()
    {
        return $this->pdo->inTransaction();
    }
    
    /**
     * Get database driver name
     */
    public function getDriverName()
    {
        return $this->config['driver'];
    }
    
    /**
     * Get database name
     */
    public function getDatabaseName()
    {
        return $this->config['database'];
    }
    
    /**
     * Log a query
     */
    private function logQuery($sql, $bindings, $time)
    {
        $this->queryLog[] = [
            'query' => $sql,
            'bindings' => $bindings,
            'time' => $time,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get query log
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }
    
    /**
     * Clear query log
     */
    public function flushQueryLog()
    {
        $this->queryLog = [];
    }
    
    /**
     * Enable query logging
     */
    public function enableQueryLog()
    {
        $this->logQueries = true;
    }
    
    /**
     * Disable query logging
     */
    public function disableQueryLog()
    {
        $this->logQueries = false;
    }
    
    /**
     * Get table information
     */
    public function getTableInfo($table)
    {
        switch ($this->getDriverName()) {
            case 'mysql':
                return $this->select("DESCRIBE `{$table}`");
            case 'postgresql':
            case 'pgsql':
                return $this->select("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = ?", [$table]);
            case 'sqlite':
                return $this->select("PRAGMA table_info(`{$table}`)");
            case 'sqlserver':
                return $this->select("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?", [$table]);
            default:
                throw new \Exception("Table info not supported for driver: " . $this->getDriverName());
        }
    }
    
    /**
     * Get list of tables
     */
    public function getTables()
    {
        switch ($this->getDriverName()) {
            case 'mysql':
                return $this->select("SHOW TABLES");
            case 'postgresql':
            case 'pgsql':
                return $this->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
            case 'sqlite':
                return $this->select("SELECT name FROM sqlite_master WHERE type='table'");
            case 'sqlserver':
                return $this->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
            default:
                throw new \Exception("Table listing not supported for driver: " . $this->getDriverName());
        }
    }
}
