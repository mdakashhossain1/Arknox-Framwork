<?php

namespace App\Core\Database;

use PDO;
use PDOException;

/**
 * Database Manager
 * 
 * Manages multiple database connections and provides connection pooling
 * Similar to Laravel's database manager
 */
class DatabaseManager
{
    private static $instance = null;
    private $connections = [];
    private $config = [];
    private $defaultConnection = 'mysql';
    
    private function __construct()
    {
        $this->loadConfig();
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load database configuration
     */
    private function loadConfig()
    {
        // Try new config first, then fall back to old config
        $newConfigFile = __DIR__ . '/../../../config/database_new.php';
        $oldConfigFile = __DIR__ . '/../../../config/database.php';

        if (file_exists($newConfigFile)) {
            $this->config = require $newConfigFile;
        } elseif (file_exists($oldConfigFile)) {
            // Convert old config format to new format
            $oldConfig = require $oldConfigFile;
            $this->config = [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'host' => $oldConfig['host'] ?? 'localhost',
                        'port' => 3306,
                        'database' => $oldConfig['dbname'] ?? 'diamond_max',
                        'username' => $oldConfig['username'] ?? 'root',
                        'password' => $oldConfig['password'] ?? '',
                        'charset' => $oldConfig['charset'] ?? 'utf8mb4',
                        'collation' => 'utf8mb4_unicode_ci',
                        'log_queries' => false,
                    ]
                ]
            ];
        } else {
            // Default configuration
            $this->config = [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'host' => 'localhost',
                        'port' => 3306,
                        'database' => 'diamond_max',
                        'username' => 'root',
                        'password' => '',
                        'charset' => 'utf8mb4',
                        'collation' => 'utf8mb4_unicode_ci',
                        'log_queries' => false,
                    ]
                ]
            ];
        }
    }
    
    /**
     * Get database connection
     */
    public function connection($name = null)
    {
        $name = $name ?: ($this->config['default'] ?? $this->defaultConnection);
        
        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->createConnection($name);
        }
        
        return $this->connections[$name];
    }
    
    /**
     * Create a new database connection
     */
    private function createConnection($name)
    {
        if (!isset($this->config['connections'][$name])) {
            throw new \Exception("Database connection [{$name}] not configured.");
        }
        
        $config = $this->config['connections'][$name];
        
        switch ($config['driver']) {
            case 'mysql':
                return $this->createMysqlConnection($config);
            case 'postgresql':
            case 'pgsql':
                return $this->createPostgresConnection($config);
            case 'sqlite':
                return $this->createSqliteConnection($config);
            case 'sqlserver':
                return $this->createSqlServerConnection($config);
            default:
                throw new \Exception("Unsupported database driver: {$config['driver']}");
        }
    }
    
    /**
     * Create MySQL connection
     */
    private function createMysqlConnection($config)
    {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']} COLLATE {$config['collation']}"
        ];
        
        return new Connection(new PDO($dsn, $config['username'], $config['password'], $options), $config);
    }
    
    /**
     * Create PostgreSQL connection
     */
    private function createPostgresConnection($config)
    {
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new Connection(new PDO($dsn, $config['username'], $config['password'], $options), $config);
    }
    
    /**
     * Create SQLite connection
     */
    private function createSqliteConnection($config)
    {
        $dsn = "sqlite:{$config['database']}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new Connection(new PDO($dsn, null, null, $options), $config);
    }
    
    /**
     * Create SQL Server connection
     */
    private function createSqlServerConnection($config)
    {
        $dsn = "sqlsrv:Server={$config['host']},{$config['port']};Database={$config['database']}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new Connection(new PDO($dsn, $config['username'], $config['password'], $options), $config);
    }
    
    /**
     * Set default connection
     */
    public function setDefaultConnection($name)
    {
        $this->defaultConnection = $name;
    }
    
    /**
     * Get default connection name
     */
    public function getDefaultConnection()
    {
        return $this->defaultConnection;
    }
    
    /**
     * Disconnect from a database
     */
    public function disconnect($name = null)
    {
        $name = $name ?: $this->defaultConnection;
        unset($this->connections[$name]);
    }
    
    /**
     * Disconnect from all databases
     */
    public function disconnectAll()
    {
        $this->connections = [];
    }
    
    /**
     * Get query builder for table
     */
    public function table($table, $connection = null)
    {
        return $this->connection($connection)->table($table);
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction($connection = null)
    {
        return $this->connection($connection)->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit($connection = null)
    {
        return $this->connection($connection)->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback($connection = null)
    {
        return $this->connection($connection)->rollback();
    }
}
