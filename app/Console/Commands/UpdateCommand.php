<?php

namespace App\Console\Commands;

use App\Core\Package\PackageManager;

/**
 * Update Command
 * 
 * Update packages to their latest versions
 */
class UpdateCommand extends BaseCommand
{
    private $packageManager;
    
    public function __construct()
    {
        $this->packageManager = new PackageManager();
    }
    
    public function execute($arguments)
    {
        if (empty($arguments)) {
            // Update all packages
            $this->updateAll();
            return;
        }
        
        $packageName = $arguments[0];
        
        // Update specific package
        $this->updatePackage($packageName);
    }
    
    /**
     * Update all packages
     */
    private function updateAll()
    {
        $this->info("Updating all packages...");
        
        try {
            if ($this->packageManager->update()) {
                $this->success("All packages updated successfully!");
                $this->generateAutoloader();
            } else {
                $this->error("Failed to update packages.");
            }
        } catch (\Exception $e) {
            $this->error("Update failed: " . $e->getMessage());
        }
    }
    
    /**
     * Update specific package
     */
    private function updatePackage($packageName)
    {
        $this->info("Updating package: {$packageName}");
        
        try {
            // Remove and reinstall the package
            $this->packageManager->remove($packageName);
            
            if ($this->packageManager->install($packageName)) {
                $this->success("Package {$packageName} updated successfully!");
                $this->generateAutoloader();
            } else {
                $this->error("Failed to update package {$packageName}");
            }
        } catch (\Exception $e) {
            $this->error("Update failed: " . $e->getMessage());
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
        
        if (!is_dir($vendorDir)) {
            mkdir($vendorDir, 0755, true);
        }
        
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
