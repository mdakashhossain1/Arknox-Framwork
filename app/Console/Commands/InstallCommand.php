<?php

namespace App\Console\Commands;

use App\Core\Package\PackageManager;

/**
 * Install Command
 * 
 * Install packages and dependencies
 */
class InstallCommand extends BaseCommand
{
    private $packageManager;
    
    public function __construct()
    {
        $this->packageManager = new PackageManager();
    }
    
    public function execute($arguments)
    {
        if (empty($arguments)) {
            // Install all packages from packages.json
            $this->installAll();
            return;
        }
        
        $packageName = $arguments[0];
        $options = $this->parseOptions($arguments);
        
        // Check for specific commands
        if ($packageName === 'all') {
            $this->installAll();
            return;
        }
        
        // Install specific package
        $this->installPackage($packageName, $options);
    }
    
    /**
     * Install all packages
     */
    private function installAll()
    {
        $this->info("Installing all packages from packages.json...");
        
        try {
            if ($this->packageManager->installAll()) {
                $this->success("All packages installed successfully!");
                $this->generateAutoloader();
            } else {
                $this->error("Failed to install packages.");
            }
        } catch (\Exception $e) {
            $this->error("Installation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Install specific package
     */
    private function installPackage($packageName, $options)
    {
        $version = $options['version'] ?? null;
        $dev = $options['dev'] ?? false;
        
        $this->info("Installing package: {$packageName}");
        
        if ($version) {
            $this->info("Version: {$version}");
        }
        
        if ($dev) {
            $this->info("Installing as development dependency");
        }
        
        try {
            if ($this->packageManager->install($packageName, $version, $dev)) {
                $this->success("Package {$packageName} installed successfully!");
                $this->generateAutoloader();
                $this->showPackageInfo($packageName);
            } else {
                $this->error("Failed to install package {$packageName}");
            }
        } catch (\Exception $e) {
            $this->error("Installation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Parse command options
     */
    private function parseOptions($arguments)
    {
        $options = [];
        
        foreach ($arguments as $arg) {
            if (strpos($arg, '--version=') === 0) {
                $options['version'] = substr($arg, 10);
            } elseif ($arg === '--dev') {
                $options['dev'] = true;
            } elseif (strpos($arg, '--') === 0) {
                // Handle other options
                $option = substr($arg, 2);
                if (strpos($option, '=') !== false) {
                    list($key, $value) = explode('=', $option, 2);
                    $options[$key] = $value;
                } else {
                    $options[$option] = true;
                }
            }
        }
        
        return $options;
    }
    
    /**
     * Generate autoloader
     */
    private function generateAutoloader()
    {
        $this->info("Generating autoloader...");
        
        $vendorDir = getcwd() . '/vendor';
        $autoloadFile = $vendorDir . '/autoload.php';
        
        if (!is_dir($vendorDir)) {
            mkdir($vendorDir, 0755, true);
        }
        
        $autoloadContent = $this->getAutoloaderContent();
        
        if (file_put_contents($autoloadFile, $autoloadContent)) {
            $this->success("Autoloader generated successfully!");
        } else {
            $this->warning("Failed to generate autoloader");
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
    
    /**
     * Show package information
     */
    private function showPackageInfo($packageName)
    {
        $vendorDir = getcwd() . '/vendor';
        $packageDir = $vendorDir . '/' . $packageName;
        $packageJsonFile = $packageDir . '/package.json';
        
        if (file_exists($packageJsonFile)) {
            $packageInfo = json_decode(file_get_contents($packageJsonFile), true);
            
            if ($packageInfo) {
                echo "\n";
                $this->info("Package Information:");
                echo "  Name: " . ($packageInfo['name'] ?? 'Unknown') . "\n";
                echo "  Version: " . ($packageInfo['version'] ?? 'Unknown') . "\n";
                echo "  Description: " . ($packageInfo['description'] ?? 'No description') . "\n";
                echo "  Installed: " . ($packageInfo['installed_at'] ?? 'Unknown') . "\n";
                echo "\n";
            }
        }
    }
}
