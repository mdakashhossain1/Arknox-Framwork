<?php
/**
 * Database Configuration
 * 
 * Configure your database connections here
 * Supports multiple database connections like Laravel
 */

return [
    // Default database connection
    'default' => env('DB_CONNECTION', 'mysql'),
    
    // Database connections
    'connections' => [
        
        // MySQL Connection
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'mvc_framework'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_PREFIX', ''),
            'strict' => true,
            'engine' => null,
            'log_queries' => env('DB_LOG_QUERIES', false),
        ],
        
        // PostgreSQL Connection
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('PGSQL_HOST', 'localhost'),
            'port' => env('PGSQL_PORT', 5432),
            'database' => env('PGSQL_DATABASE', 'mvc_framework'),
            'username' => env('PGSQL_USERNAME', 'postgres'),
            'password' => env('PGSQL_PASSWORD', ''),
            'charset' => env('PGSQL_CHARSET', 'utf8'),
            'prefix' => env('PGSQL_PREFIX', ''),
            'schema' => env('PGSQL_SCHEMA', 'public'),
            'log_queries' => env('PGSQL_LOG_QUERIES', false),
        ],
        
        // SQLite Connection
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('SQLITE_DATABASE', database_path('database.sqlite')),
            'prefix' => env('SQLITE_PREFIX', ''),
            'foreign_key_constraints' => env('SQLITE_FOREIGN_KEYS', true),
            'log_queries' => env('SQLITE_LOG_QUERIES', false),
        ],
        
        // SQL Server Connection
        'sqlserver' => [
            'driver' => 'sqlserver',
            'host' => env('SQLSERVER_HOST', 'localhost'),
            'port' => env('SQLSERVER_PORT', 1433),
            'database' => env('SQLSERVER_DATABASE', 'mvc_framework'),
            'username' => env('SQLSERVER_USERNAME', 'sa'),
            'password' => env('SQLSERVER_PASSWORD', ''),
            'charset' => env('SQLSERVER_CHARSET', 'utf8'),
            'prefix' => env('SQLSERVER_PREFIX', ''),
            'log_queries' => env('SQLSERVER_LOG_QUERIES', false),
        ],
        
        // Testing Connection (SQLite in memory)
        'testing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'log_queries' => false,
        ],
        
    ],
    
    // Migration settings
    'migrations' => [
        'table' => 'migrations',
        'path' => 'database/migrations',
    ],
    
    // Redis configuration (for caching/sessions)
    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),
        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],
        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],
        'session' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_SESSION_DB', 2),
        ],
    ],
];

/**
 * Helper function to get database path
 */
function database_path($path = '')
{
    return __DIR__ . '/../database/' . $path;
}


