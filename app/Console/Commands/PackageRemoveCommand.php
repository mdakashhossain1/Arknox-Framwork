<?php

namespace App\Console\Commands;

use App\Core\Console\Command;

/**
 * Package Remove Command
 * 
 * Laravel-style package removal command
 */
class PackageRemoveCommand extends Command
{
    protected $signature = 'package:remove {package} {--dev}';
    protected $description = 'Remove a package via Composer';

    public function handle()
    {
        $package = $this->argument('package');
        $isDev = $this->option('dev');

        $this->info("Removing package: {$package}");

        // Build composer command
        $command = 'composer remove ';
        
        if ($isDev) {
            $command .= '--dev ';
        }

        $command .= $package;

        // Execute composer command
        $this->line("Executing: {$command}");
        
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            $this->success("Package {$package} removed successfully!");
            
            // Check for leftover config files
            $this->checkForLeftoverFiles($package);
            
        } else {
            $this->error("Failed to remove package {$package}");
            foreach ($output as $line) {
                $this->line($line);
            }
        }

        return $returnCode;
    }

    protected function checkForLeftoverFiles($package)
    {
        $configDir = 'config';
        $packageName = basename($package);
        
        // Look for config files that might belong to this package
        $possibleConfigs = [
            "{$configDir}/{$packageName}.php",
            "{$configDir}/" . str_replace('/', '_', $package) . ".php"
        ];

        $foundConfigs = [];
        foreach ($possibleConfigs as $config) {
            if (file_exists($config)) {
                $foundConfigs[] = $config;
            }
        }

        if (!empty($foundConfigs)) {
            $this->warn("Found configuration files that may belong to this package:");
            foreach ($foundConfigs as $config) {
                $this->line("  - {$config}");
            }
            
            if ($this->confirm("Would you like to remove these configuration files?")) {
                foreach ($foundConfigs as $config) {
                    if (unlink($config)) {
                        $this->success("Removed: {$config}");
                    } else {
                        $this->error("Failed to remove: {$config}");
                    }
                }
            }
        }
    }
}
