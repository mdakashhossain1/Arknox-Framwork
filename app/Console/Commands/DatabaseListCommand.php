<?php

namespace App\Console\Commands;

use App\Core\Database\DB;

/**
 * Database List Command
 * 
 * List database connections and tables
 */
class DatabaseListCommand extends BaseCommand
{
    public function execute($arguments)
    {
        $action = $arguments[0] ?? 'connections';
        
        switch ($action) {
            case 'connections':
                $this->listConnections();
                break;
            case 'tables':
                $connection = $arguments[1] ?? null;
                $this->listTables($connection);
                break;
            case 'info':
                $table = $arguments[1] ?? null;
                $connection = $arguments[2] ?? null;
                $this->showTableInfo($table, $connection);
                break;
            default:
                $this->showHelp();
        }
    }
    
    /**
     * List available database connections
     */
    private function listConnections()
    {
        $this->info("Available Database Connections:");
        echo "\n";
        
        $config = require __DIR__ . '/../../../config/database_new.php';
        $connections = $config['connections'];
        $default = $config['default'];
        
        printf("%-15s %-12s %-20s %-15s\n", "Name", "Driver", "Host/Database", "Status");
        echo str_repeat("-", 70) . "\n";
        
        foreach ($connections as $name => $connection) {
            $isDefault = $name === $default ? " (default)" : "";
            $status = $this->testConnection($name) ? "✓ Connected" : "✗ Failed";
            
            $hostDb = $connection['driver'] === 'sqlite' 
                ? basename($connection['database']) 
                : ($connection['host'] ?? 'N/A');
            
            printf("%-15s %-12s %-20s %-15s%s\n", 
                $name, 
                $connection['driver'], 
                $hostDb, 
                $status,
                $isDefault
            );
        }
        
        echo "\n";
        $this->info("Use 'php console db:list tables [connection]' to list tables");
        $this->info("Use 'php console db:list info <table> [connection]' to show table info");
    }
    
    /**
     * List tables in a connection
     */
    private function listTables($connection = null)
    {
        try {
            $this->info("Tables in " . ($connection ?: 'default') . " connection:");
            echo "\n";
            
            $tables = DB::getTables($connection);
            
            if (empty($tables)) {
                $this->warning("No tables found.");
                return;
            }
            
            $count = 0;
            foreach ($tables as $table) {
                $tableName = is_array($table) ? reset($table) : $table;
                echo "  " . (++$count) . ". {$tableName}\n";
            }
            
            echo "\n";
            $this->success("Found " . count($tables) . " table(s)");
            
        } catch (\Exception $e) {
            $this->error("Failed to list tables: " . $e->getMessage());
        }
    }
    
    /**
     * Show table information
     */
    private function showTableInfo($table, $connection = null)
    {
        if (!$table) {
            $this->error("Table name is required.");
            $this->info("Usage: php console db:list info <table> [connection]");
            return;
        }
        
        try {
            $this->info("Table Information: {$table}");
            echo "\n";
            
            $columns = DB::getTableInfo($table, $connection);
            
            if (empty($columns)) {
                $this->warning("No column information found for table: {$table}");
                return;
            }
            
            // Different databases return different column structures
            $driver = DB::getDriverName($connection);
            
            switch ($driver) {
                case 'mysql':
                    $this->showMysqlTableInfo($columns);
                    break;
                case 'postgresql':
                case 'pgsql':
                    $this->showPostgresTableInfo($columns);
                    break;
                case 'sqlite':
                    $this->showSqliteTableInfo($columns);
                    break;
                default:
                    $this->showGenericTableInfo($columns);
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to get table info: " . $e->getMessage());
        }
    }
    
    /**
     * Show MySQL table info
     */
    private function showMysqlTableInfo($columns)
    {
        printf("%-20s %-15s %-10s %-10s %-15s\n", "Column", "Type", "Null", "Key", "Extra");
        echo str_repeat("-", 75) . "\n";
        
        foreach ($columns as $column) {
            printf("%-20s %-15s %-10s %-10s %-15s\n",
                $column['Field'],
                $column['Type'],
                $column['Null'],
                $column['Key'],
                $column['Extra']
            );
        }
    }
    
    /**
     * Show PostgreSQL table info
     */
    private function showPostgresTableInfo($columns)
    {
        printf("%-20s %-15s %-10s\n", "Column", "Type", "Nullable");
        echo str_repeat("-", 50) . "\n";
        
        foreach ($columns as $column) {
            printf("%-20s %-15s %-10s\n",
                $column['column_name'],
                $column['data_type'],
                $column['is_nullable']
            );
        }
    }
    
    /**
     * Show SQLite table info
     */
    private function showSqliteTableInfo($columns)
    {
        printf("%-20s %-15s %-10s %-10s\n", "Column", "Type", "Not Null", "Primary Key");
        echo str_repeat("-", 60) . "\n";
        
        foreach ($columns as $column) {
            printf("%-20s %-15s %-10s %-10s\n",
                $column['name'],
                $column['type'],
                $column['notnull'] ? 'YES' : 'NO',
                $column['pk'] ? 'YES' : 'NO'
            );
        }
    }
    
    /**
     * Show generic table info
     */
    private function showGenericTableInfo($columns)
    {
        foreach ($columns as $i => $column) {
            echo "Column " . ($i + 1) . ":\n";
            foreach ($column as $key => $value) {
                echo "  {$key}: {$value}\n";
            }
            echo "\n";
        }
    }
    
    /**
     * Test database connection
     */
    private function testConnection($name)
    {
        try {
            DB::connection($name)->scalar('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Show help
     */
    private function showHelp()
    {
        echo "\n";
        $this->info("Database List Command");
        echo "\n";
        echo "Usage:\n";
        echo "  php console db:list connections              List all connections\n";
        echo "  php console db:list tables [connection]     List tables\n";
        echo "  php console db:list info <table> [conn]     Show table info\n";
        echo "\n";
        echo "Examples:\n";
        echo "  php console db:list connections\n";
        echo "  php console db:list tables mysql\n";
        echo "  php console db:list info users\n";
        echo "  php console db:list info users pgsql\n";
        echo "\n";
    }
}
