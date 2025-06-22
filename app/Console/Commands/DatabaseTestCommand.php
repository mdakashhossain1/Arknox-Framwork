<?php

namespace App\Console\Commands;

use App\Core\Database\DB;

/**
 * Database Test Command
 * 
 * Test database connections and run sample queries
 */
class DatabaseTestCommand extends BaseCommand
{
    public function execute($arguments)
    {
        $connection = $arguments[0] ?? null;
        
        if ($connection) {
            $this->testConnection($connection);
        } else {
            $this->testAllConnections();
        }
    }
    
    /**
     * Test all database connections
     */
    private function testAllConnections()
    {
        $this->info("Testing all database connections...");
        echo "\n";
        
        $config = require __DIR__ . '/../../../config/database_new.php';
        $connections = $config['connections'];
        
        $results = [];
        
        foreach ($connections as $name => $connection) {
            $this->info("Testing connection: {$name}");
            
            try {
                $start = microtime(true);
                $result = DB::connection($name)->scalar('SELECT 1 as test');
                $time = round((microtime(true) - $start) * 1000, 2);
                
                if ($result == 1) {
                    $this->success("✓ {$name} - Connected successfully ({$time}ms)");
                    $results[$name] = ['status' => 'success', 'time' => $time];
                } else {
                    $this->error("✗ {$name} - Unexpected result");
                    $results[$name] = ['status' => 'error', 'error' => 'Unexpected result'];
                }
                
            } catch (\Exception $e) {
                $this->error("✗ {$name} - " . $e->getMessage());
                $results[$name] = ['status' => 'error', 'error' => $e->getMessage()];
            }
        }
        
        echo "\n";
        $this->showSummary($results);
    }
    
    /**
     * Test specific database connection
     */
    private function testConnection($connectionName)
    {
        $this->info("Testing database connection: {$connectionName}");
        echo "\n";
        
        try {
            // Test basic connection
            $this->info("1. Testing basic connection...");
            $start = microtime(true);
            $result = DB::connection($connectionName)->scalar('SELECT 1 as test');
            $time = round((microtime(true) - $start) * 1000, 2);
            
            if ($result == 1) {
                $this->success("   ✓ Basic connection successful ({$time}ms)");
            } else {
                $this->error("   ✗ Basic connection failed - unexpected result");
                return;
            }
            
            // Test database info
            $this->info("2. Testing database information...");
            $dbName = DB::getDatabaseName($connectionName);
            $driver = DB::getDriverName($connectionName);
            $this->success("   ✓ Database: {$dbName} (Driver: {$driver})");
            
            // Test table listing
            $this->info("3. Testing table listing...");
            $tables = DB::getTables($connectionName);
            $tableCount = count($tables);
            $this->success("   ✓ Found {$tableCount} table(s)");
            
            // Test query builder
            $this->info("4. Testing query builder...");
            if ($tableCount > 0) {
                $firstTable = is_array($tables[0]) ? reset($tables[0]) : $tables[0];
                $count = DB::table($firstTable, $connectionName)->count();
                $this->success("   ✓ Query builder works - {$firstTable} has {$count} record(s)");
            } else {
                $this->warning("   ! No tables to test query builder");
            }
            
            // Test transaction
            $this->info("5. Testing transactions...");
            DB::transaction(function() {
                // Empty transaction for testing
            }, $connectionName);
            $this->success("   ✓ Transaction support working");
            
            echo "\n";
            $this->success("All tests passed for connection: {$connectionName}");
            
        } catch (\Exception $e) {
            echo "\n";
            $this->error("Connection test failed: " . $e->getMessage());
            
            // Show additional debug info
            echo "\n";
            $this->info("Debug Information:");
            echo "  Error: " . $e->getMessage() . "\n";
            echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        }
    }
    
    /**
     * Show test summary
     */
    private function showSummary($results)
    {
        $successful = array_filter($results, function($result) {
            return $result['status'] === 'success';
        });
        
        $failed = array_filter($results, function($result) {
            return $result['status'] === 'error';
        });
        
        $this->info("Test Summary:");
        echo "  Total connections: " . count($results) . "\n";
        echo "  Successful: " . count($successful) . "\n";
        echo "  Failed: " . count($failed) . "\n";
        
        if (!empty($successful)) {
            echo "\n";
            $this->success("Successful connections:");
            foreach ($successful as $name => $result) {
                echo "  ✓ {$name} ({$result['time']}ms)\n";
            }
        }
        
        if (!empty($failed)) {
            echo "\n";
            $this->error("Failed connections:");
            foreach ($failed as $name => $result) {
                echo "  ✗ {$name} - " . $result['error'] . "\n";
            }
        }
        
        echo "\n";
        if (count($failed) === 0) {
            $this->success("All database connections are working properly!");
        } else {
            $this->warning("Some database connections have issues. Check configuration.");
        }
    }
}
