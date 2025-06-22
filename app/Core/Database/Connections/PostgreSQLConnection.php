<?php

namespace App\Core\Database\Connections;

use App\Core\Database\Connection;
use App\Core\Database\QueryBuilder;

/**
 * PostgreSQL Database Connection
 * 
 * PostgreSQL-specific database connection implementation
 */
class PostgreSQLConnection extends Connection
{
    protected $driver = 'postgresql';

    /**
     * Create PDO connection
     */
    protected function createPdoConnection()
    {
        $dsn = "pgsql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']}";
        
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false
        ];

        return new \PDO($dsn, $this->config['username'], $this->config['password'], $options);
    }

    /**
     * Get PostgreSQL-specific query builder
     */
    public function table($table)
    {
        return new PostgreSQLQueryBuilder($this, $table);
    }

    /**
     * Get PostgreSQL version
     */
    public function getVersion()
    {
        $result = $this->raw('SELECT version()');
        return $result[0]['version'] ?? 'Unknown';
    }

    /**
     * Get table information
     */
    public function getTableInfo($table)
    {
        $sql = "SELECT * FROM information_schema.tables WHERE table_name = ? AND table_schema = 'public'";
        $result = $this->raw($sql, [$table]);
        return $result[0] ?? null;
    }

    /**
     * Get column information
     */
    public function getColumns($table)
    {
        $sql = "SELECT column_name, data_type, is_nullable, column_default 
                FROM information_schema.columns 
                WHERE table_name = ? AND table_schema = 'public'
                ORDER BY ordinal_position";
        return $this->raw($sql, [$table]);
    }

    /**
     * Get indexes for a table
     */
    public function getIndexes($table)
    {
        $sql = "SELECT indexname, indexdef 
                FROM pg_indexes 
                WHERE tablename = ? AND schemaname = 'public'";
        return $this->raw($sql, [$table]);
    }

    /**
     * Check if table exists
     */
    public function tableExists($table)
    {
        $sql = "SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_name = ?
                )";
        $result = $this->raw($sql, [$table]);
        return $result[0]['exists'] ?? false;
    }

    /**
     * Create database
     */
    public function createDatabase($name, $encoding = 'UTF8')
    {
        $sql = "CREATE DATABASE \"{$name}\" WITH ENCODING '{$encoding}'";
        return $this->raw($sql);
    }

    /**
     * Drop database
     */
    public function dropDatabase($name)
    {
        $sql = "DROP DATABASE IF EXISTS \"{$name}\"";
        return $this->raw($sql);
    }

    /**
     * Get database size
     */
    public function getDatabaseSize($database = null)
    {
        $database = $database ?: $this->config['database'];
        
        $sql = "SELECT pg_size_pretty(pg_database_size(?)) as size_pretty,
                       pg_database_size(?) as size_bytes";
        
        $result = $this->raw($sql, [$database, $database]);
        return $result[0] ?? null;
    }

    /**
     * Vacuum table
     */
    public function vacuumTable($table)
    {
        $sql = "VACUUM \"{$table}\"";
        return $this->raw($sql);
    }

    /**
     * Analyze table
     */
    public function analyzeTable($table)
    {
        $sql = "ANALYZE \"{$table}\"";
        return $this->raw($sql);
    }

    /**
     * Get PostgreSQL-specific configuration
     */
    protected function getDefaultConfig()
    {
        return array_merge(parent::getDefaultConfig(), [
            'port' => 5432,
            'charset' => 'utf8',
            'schema' => 'public'
        ]);
    }
}

/**
 * PostgreSQL Query Builder
 */
class PostgreSQLQueryBuilder extends QueryBuilder
{
    /**
     * Add PostgreSQL-specific LIMIT clause
     */
    public function limit($count, $offset = 0)
    {
        $this->limitCount = $count;
        $this->offsetCount = $offset;
        return $this;
    }

    /**
     * Add full-text search using PostgreSQL
     */
    public function fullTextSearch($columns, $query, $language = 'english')
    {
        if (is_array($columns)) {
            $columns = implode(" || ' ' || ", array_map(function($col) {
                return "\"{$col}\"";
            }, $columns));
        } else {
            $columns = "\"{$columns}\"";
        }

        $this->wheres[] = [
            'type' => 'raw',
            'sql' => "to_tsvector('{$language}', {$columns}) @@ plainto_tsquery('{$language}', ?)",
            'bindings' => [$query]
        ];

        return $this;
    }

    /**
     * Add PostgreSQL-specific JSON operations
     */
    public function whereJson($column, $path, $operator, $value)
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => "\"{$column}\"->>{$path} {$operator} ?",
            'bindings' => [$value]
        ];

        return $this;
    }

    /**
     * Add JSONB operations
     */
    public function whereJsonb($column, $path, $operator, $value)
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => "\"{$column}\"->>'{$path}' {$operator} ?",
            'bindings' => [$value]
        ];

        return $this;
    }

    /**
     * Build PostgreSQL-specific SELECT query
     */
    protected function buildSelectQuery()
    {
        $sql = parent::buildSelectQuery();
        
        // Add PostgreSQL-specific LIMIT/OFFSET
        if ($this->limitCount !== null) {
            $sql .= " LIMIT {$this->limitCount}";
            
            if ($this->offsetCount > 0) {
                $sql .= " OFFSET {$this->offsetCount}";
            }
        }

        return $sql;
    }

    /**
     * Get PostgreSQL-specific data types for schema
     */
    public function getDataTypes()
    {
        return [
            'string' => 'VARCHAR',
            'text' => 'TEXT',
            'integer' => 'INTEGER',
            'bigint' => 'BIGINT',
            'decimal' => 'DECIMAL',
            'float' => 'REAL',
            'double' => 'DOUBLE PRECISION',
            'boolean' => 'BOOLEAN',
            'date' => 'DATE',
            'datetime' => 'TIMESTAMP',
            'timestamp' => 'TIMESTAMP WITH TIME ZONE',
            'time' => 'TIME',
            'json' => 'JSON',
            'jsonb' => 'JSONB',
            'binary' => 'BYTEA',
            'uuid' => 'UUID'
        ];
    }
}
