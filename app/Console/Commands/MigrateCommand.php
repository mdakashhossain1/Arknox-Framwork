<?php

namespace App\Console\Commands;

use App\Core\Database;
use App\Core\Database\Migration;
use App\Core\Database\Schema;

/**
 * Database Migration Command
 *
 * Laravel-style migration management with rollback support
 */
class MigrateCommand extends BaseCommand
{
    private $db;
    private $migrationsPath;
    private $migrationsTable = 'migrations';

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->migrationsPath = getcwd() . '/database/migrations';
    }

    public function execute($arguments)
    {
        $command = $arguments[0] ?? 'run';

        switch ($command) {
            case 'run':
            case 'up':
                return $this->runMigrations();
            case 'rollback':
                return $this->rollbackMigrations($arguments);
            case 'reset':
                return $this->resetMigrations();
            case 'refresh':
                return $this->refreshMigrations();
            case 'status':
                return $this->showStatus();
            case 'make':
                return $this->makeMigration($arguments);
            default:
                $this->showHelp();
                return false;
        }
    }

    private function showHelp()
    {
        $this->info("🗄️  Database Migration Commands:");
        $this->info("");
        $this->info("  run                  Run pending migrations");
        $this->info("  rollback [--step=N]  Rollback migrations (default: 1 step)");
        $this->info("  reset                Rollback all migrations");
        $this->info("  refresh              Reset and re-run all migrations");
        $this->info("  status               Show migration status");
        $this->info("  make <name>          Create a new migration");
        $this->info("");
        $this->info("Examples:");
        $this->info("  php console migrate run");
        $this->info("  php console migrate rollback --step=3");
        $this->info("  php console migrate make create_users_table");
    }

    private function runMigrations()
    {
        $this->info("🗄️  Running database migrations...");

        // Ensure migrations table exists
        $this->createMigrationsTable();

        // Get pending migrations
        $pending = $this->getPendingMigrations();

        if (empty($pending)) {
            $this->info("✅ No pending migrations");
            return true;
        }

        $this->info("Found " . count($pending) . " pending migrations:");

        $migrated = 0;
        foreach ($pending as $migration) {
            $this->info("  Migrating: {$migration}");

            try {
                $this->runMigration($migration);
                $this->recordMigration($migration);
                $migrated++;
                $this->success("  ✅ Migrated: {$migration}");
            } catch (\Exception $e) {
                $this->error("  ❌ Failed: {$migration}");
                $this->error("     Error: " . $e->getMessage());
                break;
            }
        }

        $this->info("");
        $this->success("🎉 Migrated {$migrated} migrations successfully!");
        return true;
    }

    private function rollbackMigrations($arguments)
    {
        $steps = 1;

        // Parse step argument
        foreach ($arguments as $arg) {
            if (strpos($arg, '--step=') === 0) {
                $steps = (int) substr($arg, 7);
                break;
            }
        }

        $this->info("🔄 Rolling back {$steps} migration(s)...");

        // Get migrations to rollback
        $toRollback = $this->getMigrationsToRollback($steps);

        if (empty($toRollback)) {
            $this->info("✅ No migrations to rollback");
            return true;
        }

        $rolledBack = 0;
        foreach ($toRollback as $migration) {
            $this->info("  Rolling back: {$migration}");

            try {
                $this->rollbackMigration($migration);
                $this->removeMigrationRecord($migration);
                $rolledBack++;
                $this->success("  ✅ Rolled back: {$migration}");
            } catch (\Exception $e) {
                $this->error("  ❌ Failed: {$migration}");
                $this->error("     Error: " . $e->getMessage());
                break;
            }
        }

        $this->info("");
        $this->success("🎉 Rolled back {$rolledBack} migrations successfully!");
        return true;
    }

    private function createMigrationsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `migration` varchar(255) NOT NULL,
            `batch` int(11) NOT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->statement($sql);
    }

    private function getPendingMigrations()
    {
        $allMigrations = $this->getAllMigrationFiles();
        $runMigrations = $this->getRunMigrations();

        return array_diff($allMigrations, $runMigrations);
    }

    private function getAllMigrationFiles()
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = scandir($this->migrationsPath);
        $migrations = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $migrations[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        sort($migrations);
        return $migrations;
    }

    private function getRunMigrations()
    {
        try {
            $result = $this->db->select("SELECT migration FROM `{$this->migrationsTable}` ORDER BY batch, id");
            return array_column($result, 'migration');
        } catch (\Exception $e) {
            return [];
        }
    }

    private function runMigration($migration)
    {
        $filepath = $this->migrationsPath . '/' . $migration . '.php';

        if (!file_exists($filepath)) {
            throw new \Exception("Migration file not found: {$filepath}");
        }

        $migrationData = require $filepath;

        if (isset($migrationData['up'])) {
            // Simple SQL migration
            $this->db->statement($migrationData['up']);
        } else {
            // Class-based migration
            $className = $this->getMigrationClassName($migration);
            if (class_exists($className)) {
                $instance = new $className();
                $instance->up();
            } else {
                throw new \Exception("Migration class not found: {$className}");
            }
        }
    }

    private function recordMigration($migration)
    {
        $batch = $this->getNextBatchNumber();

        $this->db->insert($this->migrationsTable, [
            'migration' => $migration,
            'batch' => $batch,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function getNextBatchNumber()
    {
        try {
            $result = $this->db->select("SELECT MAX(batch) as max_batch FROM `{$this->migrationsTable}`");
            return ($result[0]['max_batch'] ?? 0) + 1;
        } catch (\Exception $e) {
            return 1;
        }
    }

    private function getMigrationClassName($migration)
    {
        // Convert migration filename to class name
        $parts = explode('_', $migration);
        array_shift($parts); // Remove timestamp

        $className = '';
        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        return $className;
    }
}
