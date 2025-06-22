<?php

namespace App\Console\Commands;

use App\Core\Package\PackageManager;

/**
 * Remove Command
 * 
 * Remove packages from the project
 */
class RemoveCommand extends BaseCommand
{
    private $packageManager;
    
    public function __construct()
    {
        $this->packageManager = new PackageManager();
    }
    
    public function execute($arguments)
    {
        if (empty($arguments)) {
            $this->error("Package name is required.");
            $this->info("Usage: php console remove <package-name>");
            return;
        }
        
        $packageName = $arguments[0];
        
        $this->info("Removing package: {$packageName}");
        
        // Confirm removal
        if (!$this->confirm("Are you sure you want to remove {$packageName}?", false)) {
            $this->info("Package removal cancelled.");
            return;
        }
        
        try {
            if ($this->packageManager->remove($packageName)) {
                $this->success("Package {$packageName} removed successfully!");
                $this->generateAutoloader();
            } else {
                $this->error("Failed to remove package {$packageName}");
            }
        } catch (\Exception $e) {
            $this->error("Removal failed: " . $e->getMessage());
        }
    }
    
    /**
     * Generate autoloader
     */
    private function generateAutoloader()
    {
        $this->info("Updating autoloader...");
        
        $vendorDir = getcwd() . '/vendor';
        $autoloadFile = $vendorDir . '/autoload.php';
        
        $autoloadContent = $this->getAutoloaderContent();
        
        if (file_put_contents($autoloadFile, $autoloadContent)) {
            $this->success("Autoloader updated successfully!");
        } else {
            $this->warning("Failed to update autoloader");
        }
    }
    
    /**
     * Get autoloader content
     */
    private function getAutoloaderContent()
    {
        return "<?php
/**
 * MVC Framework Package Autoloader
 * 
 * Generated automatically by the package manager
 */

// Load framework autoloader first
require_once __DIR__ . '/../autoload.php';

// Load package autoloaders
\$vendorDir = __DIR__;
\$packages = glob(\$vendorDir . '/*/autoload.php');

foreach (\$packages as \$packageAutoload) {
    if (file_exists(\$packageAutoload)) {
        require_once \$packageAutoload;
    }
}

// Package registry
\$packageRegistry = [];

// Scan for packages
\$packageDirs = glob(\$vendorDir . '/*', GLOB_ONLYDIR);
foreach (\$packageDirs as \$packageDir) {
    \$packageJsonFile = \$packageDir . '/package.json';
    if (file_exists(\$packageJsonFile)) {
        \$packageInfo = json_decode(file_get_contents(\$packageJsonFile), true);
        if (\$packageInfo) {
            \$packageRegistry[basename(\$packageDir)] = \$packageInfo;
        }
    }
}

// Make package registry available globally
\$GLOBALS['mvc_packages'] = \$packageRegistry;

/**
 * Get installed package information
 */
function mvc_get_package(\$name) {
    return \$GLOBALS['mvc_packages'][\$name] ?? null;
}

/**
 * Get all installed packages
 */
function mvc_get_packages() {
    return \$GLOBALS['mvc_packages'] ?? [];
}

/**
 * Check if package is installed
 */
function mvc_has_package(\$name) {
    return isset(\$GLOBALS['mvc_packages'][\$name]);
}
";
    }
}
