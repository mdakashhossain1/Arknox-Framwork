<?php

namespace App\Console\Commands;

use App\Core\Console\Command;

/**
 * Package Install Command
 * 
 * Laravel-style package installation command
 */
class PackageInstallCommand extends Command
{
    protected $signature = 'package:install {package} {--dev} {--version=}';
    protected $description = 'Install a package via Composer';

    public function handle()
    {
        $package = $this->argument('package');
        $isDev = $this->option('dev');
        $version = $this->option('version');

        $this->info("Installing package: {$package}");

        // Build composer command
        $command = 'composer require ';
        
        if ($isDev) {
            $command .= '--dev ';
        }

        $command .= $package;

        if ($version) {
            $command .= ":{$version}";
        }

        // Execute composer command
        $this->line("Executing: {$command}");
        
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            $this->success("Package {$package} installed successfully!");
            
            // Check for service providers or config files
            $this->checkForServiceProviders($package);
            $this->checkForConfigFiles($package);
            
        } else {
            $this->error("Failed to install package {$package}");
            foreach ($output as $line) {
                $this->line($line);
            }
        }

        return $returnCode;
    }

    protected function checkForServiceProviders($package)
    {
        $vendorPath = "vendor/{$package}";
        
        if (!is_dir($vendorPath)) {
            return;
        }

        // Look for service provider files
        $serviceProviders = glob("{$vendorPath}/src/*ServiceProvider.php");
        
        if (!empty($serviceProviders)) {
            $this->info("Service providers found:");
            foreach ($serviceProviders as $provider) {
                $className = basename($provider, '.php');
                $this->line("  - {$className}");
            }
            
            $this->warn("Don't forget to register service providers in your application!");
        }
    }

    protected function checkForConfigFiles($package)
    {
        $vendorPath = "vendor/{$package}";
        
        if (!is_dir($vendorPath)) {
            return;
        }

        // Look for config files
        $configFiles = glob("{$vendorPath}/config/*.php");
        
        if (!empty($configFiles)) {
            $this->info("Configuration files found:");
            foreach ($configFiles as $config) {
                $configName = basename($config, '.php');
                $this->line("  - {$configName}.php");
            }
            
            if ($this->confirm("Would you like to publish configuration files?")) {
                $this->publishConfigFiles($package, $configFiles);
            }
        }
    }

    protected function publishConfigFiles($package, $configFiles)
    {
        $configDir = 'config';
        
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        foreach ($configFiles as $configFile) {
            $configName = basename($configFile);
            $destination = "{$configDir}/{$configName}";
            
            if (file_exists($destination)) {
                if (!$this->confirm("Config file {$configName} already exists. Overwrite?")) {
                    continue;
                }
            }
            
            if (copy($configFile, $destination)) {
                $this->success("Published: {$configName}");
            } else {
                $this->error("Failed to publish: {$configName}");
            }
        }
    }
}
