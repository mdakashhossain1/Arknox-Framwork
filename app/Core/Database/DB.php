<?php

namespace App\Core\Database;

/**
 * Database Facade
 * 
 * Static interface for database operations
 * Similar to Laravel's DB facade
 */
class DB
{
    private static $manager = null;
    
    /**
     * Get database manager instance
     */
    private static function getManager()
    {
        if (self::$manager === null) {
            self::$manager = DatabaseManager::getInstance();
        }
        return self::$manager;
    }
    
    /**
     * Get a database connection
     */
    public static function connection($name = null)
    {
        return self::getManager()->connection($name);
    }
    
    /**
     * Get query builder for table
     */
    public static function table($table, $connection = null)
    {
        return self::getManager()->table($table, $connection);
    }
    
    /**
     * Execute a select query
     */
    public static function select($sql, $bindings = [], $connection = null)
    {
        return self::connection($connection)->select($sql, $bindings);
    }
    
    /**
     * Execute a select query and return first result
     */
    public static function selectOne($sql, $bindings = [], $connection = null)
    {
        return self::connection($connection)->selectOne($sql, $bindings);
    }
    
    /**
     * Execute a select query and return single value
     */
    public static function scalar($sql, $bindings = [], $connection = null)
    {
        return self::connection($connection)->scalar($sql, $bindings);
    }
    
    /**
     * Execute an insert statement
     */
    public static function insert($sql, $bindings = [], $connection = null)
    {
        return self::connection($connection)->insert($sql, $bindings);
    }
    
    /**
     * Execute an update statement
     */
    public static function update($sql, $bindings = [], $connection = null)
    {
        return self::connection($connection)->update($sql, $bindings);
    }
    
    /**
     * Execute a delete statement
     */
    public static function delete($sql, $bindings = [], $connection = null)
    {
        return self::connection($connection)->delete($sql, $bindings);
    }
    
    /**
     * Execute a statement
     */
    public static function statement($sql, $bindings = [], $connection = null)
    {
        return self::connection($connection)->statement($sql, $bindings);
    }
    
    /**
     * Begin transaction
     */
    public static function beginTransaction($connection = null)
    {
        return self::connection($connection)->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public static function commit($connection = null)
    {
        return self::connection($connection)->commit();
    }
    
    /**
     * Rollback transaction
     */
    public static function rollback($connection = null)
    {
        return self::connection($connection)->rollback();
    }
    
    /**
     * Execute a transaction
     */
    public static function transaction(callable $callback, $connection = null)
    {
        $conn = self::connection($connection);
        
        $conn->beginTransaction();
        
        try {
            $result = $callback($conn);
            $conn->commit();
            return $result;
        } catch (\Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Get table information
     */
    public static function getTableInfo($table, $connection = null)
    {
        return self::connection($connection)->getTableInfo($table);
    }
    
    /**
     * Get list of tables
     */
    public static function getTables($connection = null)
    {
        return self::connection($connection)->getTables();
    }
    
    /**
     * Enable query logging
     */
    public static function enableQueryLog($connection = null)
    {
        return self::connection($connection)->enableQueryLog();
    }
    
    /**
     * Disable query logging
     */
    public static function disableQueryLog($connection = null)
    {
        return self::connection($connection)->disableQueryLog();
    }
    
    /**
     * Get query log
     */
    public static function getQueryLog($connection = null)
    {
        return self::connection($connection)->getQueryLog();
    }
    
    /**
     * Clear query log
     */
    public static function flushQueryLog($connection = null)
    {
        return self::connection($connection)->flushQueryLog();
    }
    
    /**
     * Set default connection
     */
    public static function setDefaultConnection($name)
    {
        return self::getManager()->setDefaultConnection($name);
    }
    
    /**
     * Get default connection name
     */
    public static function getDefaultConnection()
    {
        return self::getManager()->getDefaultConnection();
    }
    
    /**
     * Disconnect from database
     */
    public static function disconnect($name = null)
    {
        return self::getManager()->disconnect($name);
    }
    
    /**
     * Disconnect from all databases
     */
    public static function disconnectAll()
    {
        return self::getManager()->disconnectAll();
    }
    
    /**
     * Raw SQL expression
     */
    public static function raw($expression)
    {
        return "RAW:{$expression}";
    }
    
    /**
     * Get database driver name
     */
    public static function getDriverName($connection = null)
    {
        return self::connection($connection)->getDriverName();
    }
    
    /**
     * Get database name
     */
    public static function getDatabaseName($connection = null)
    {
        return self::connection($connection)->getDatabaseName();
    }
}
