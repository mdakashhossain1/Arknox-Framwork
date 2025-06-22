<?php

namespace App\Core\Database\Connections;

use App\Core\Database\Connection;
use App\Core\Database\QueryBuilder;

/**
 * MySQL Database Connection
 * 
 * MySQL-specific database connection implementation
 */
class MySQLConnection extends Connection
{
    protected $driver = 'mysql';

    /**
     * Create PDO connection
     */
    protected function createPdoConnection()
    {
        $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}";
        
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']} COLLATE {$this->config['collation']}"
        ];

        return new \PDO($dsn, $this->config['username'], $this->config['password'], $options);
    }

    /**
     * Get MySQL-specific query builder
     */
    public function table($table)
    {
        return new MySQLQueryBuilder($this, $table);
    }

    /**
     * Get MySQL version
     */
    public function getVersion()
    {
        $result = $this->raw('SELECT VERSION() as version');
        return $result[0]['version'] ?? 'Unknown';
    }

    /**
     * Get table information
     */
    public function getTableInfo($table)
    {
        $sql = "SHOW TABLE STATUS LIKE ?";
        $result = $this->raw($sql, [$table]);
        return $result[0] ?? null;
    }

    /**
     * Get column information
     */
    public function getColumns($table)
    {
        $sql = "SHOW COLUMNS FROM `{$table}`";
        return $this->raw($sql);
    }

    /**
     * Get indexes for a table
     */
    public function getIndexes($table)
    {
        $sql = "SHOW INDEXES FROM `{$table}`";
        return $this->raw($sql);
    }

    /**
     * Check if table exists
     */
    public function tableExists($table)
    {
        $sql = "SHOW TABLES LIKE ?";
        $result = $this->raw($sql, [$table]);
        return !empty($result);
    }

    /**
     * Create database
     */
    public function createDatabase($name, $charset = 'utf8mb4', $collation = 'utf8mb4_unicode_ci')
    {
        $sql = "CREATE DATABASE `{$name}` CHARACTER SET {$charset} COLLATE {$collation}";
        return $this->raw($sql);
    }

    /**
     * Drop database
     */
    public function dropDatabase($name)
    {
        $sql = "DROP DATABASE IF EXISTS `{$name}`";
        return $this->raw($sql);
    }

    /**
     * Get database size
     */
    public function getDatabaseSize($database = null)
    {
        $database = $database ?: $this->config['database'];
        
        $sql = "SELECT 
                    SUM(data_length + index_length) as size_bytes,
                    SUM(data_length) as data_bytes,
                    SUM(index_length) as index_bytes
                FROM information_schema.TABLES 
                WHERE table_schema = ?";
        
        $result = $this->raw($sql, [$database]);
        return $result[0] ?? null;
    }

    /**
     * Optimize table
     */
    public function optimizeTable($table)
    {
        $sql = "OPTIMIZE TABLE `{$table}`";
        return $this->raw($sql);
    }

    /**
     * Analyze table
     */
    public function analyzeTable($table)
    {
        $sql = "ANALYZE TABLE `{$table}`";
        return $this->raw($sql);
    }

    /**
     * Get MySQL-specific configuration
     */
    protected function getDefaultConfig()
    {
        return array_merge(parent::getDefaultConfig(), [
            'port' => 3306,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => true,
            'engine' => 'InnoDB'
        ]);
    }
}

/**
 * MySQL Query Builder
 */
class MySQLQueryBuilder extends QueryBuilder
{
    /**
     * Add MySQL-specific LIMIT clause
     */
    public function limit($count, $offset = 0)
    {
        $this->limitCount = $count;
        $this->offsetCount = $offset;
        return $this;
    }

    /**
     * Add FULLTEXT search
     */
    public function fullTextSearch($columns, $query)
    {
        if (is_array($columns)) {
            $columns = implode(',', array_map(function($col) {
                return "`{$col}`";
            }, $columns));
        } else {
            $columns = "`{$columns}`";
        }

        $this->wheres[] = [
            'type' => 'raw',
            'sql' => "MATCH({$columns}) AGAINST(? IN BOOLEAN MODE)",
            'bindings' => [$query]
        ];

        return $this;
    }

    /**
     * Add MySQL-specific JSON operations
     */
    public function whereJson($column, $path, $operator, $value)
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => "JSON_EXTRACT(`{$column}`, ?) {$operator} ?",
            'bindings' => [$path, $value]
        ];

        return $this;
    }

    /**
     * Build MySQL-specific SELECT query
     */
    protected function buildSelectQuery()
    {
        $sql = parent::buildSelectQuery();
        
        // Add MySQL-specific LIMIT/OFFSET
        if ($this->limitCount !== null) {
            $sql .= " LIMIT {$this->limitCount}";
            
            if ($this->offsetCount > 0) {
                $sql .= " OFFSET {$this->offsetCount}";
            }
        }

        return $sql;
    }

    /**
     * Get MySQL-specific data types for schema
     */
    public function getDataTypes()
    {
        return [
            'string' => 'VARCHAR',
            'text' => 'TEXT',
            'integer' => 'INT',
            'bigint' => 'BIGINT',
            'decimal' => 'DECIMAL',
            'float' => 'FLOAT',
            'double' => 'DOUBLE',
            'boolean' => 'TINYINT(1)',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'timestamp' => 'TIMESTAMP',
            'time' => 'TIME',
            'json' => 'JSON',
            'binary' => 'BLOB'
        ];
    }
}
