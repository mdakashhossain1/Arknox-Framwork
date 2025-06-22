<?php

namespace App\Core\Package;

/**
 * Package Manager
 * 
 * Manages package installation, removal, and dependencies
 * Similar to Composer but integrated with the MVC framework
 */
class PackageManager
{
    private $packagesFile;
    private $vendorDir;
    private $lockFile;
    private $repositories;
    
    public function __construct()
    {
        $this->packagesFile = getcwd() . '/packages.json';
        $this->vendorDir = getcwd() . '/vendor';
        $this->lockFile = getcwd() . '/packages.lock';
        $this->repositories = [
            'https://packagist.org',
            'https://github.com'
        ];
        
        $this->ensureDirectories();
    }
    
    /**
     * Install a package
     */
    public function install($packageName, $version = null, $dev = false)
    {
        $this->output("Installing package: {$packageName}");
        
        // Load current packages.json
        $config = $this->loadPackagesConfig();
        
        // Add package to config
        $section = $dev ? 'require-dev' : 'require';
        $config[$section][$packageName] = $version ?: '*';
        
        // Save updated config
        $this->savePackagesConfig($config);
        
        // Download and install package
        return $this->downloadPackage($packageName, $version);
    }
    
    /**
     * Remove a package
     */
    public function remove($packageName)
    {
        $this->output("Removing package: {$packageName}");
        
        $config = $this->loadPackagesConfig();
        
        // Remove from both require and require-dev
        unset($config['require'][$packageName]);
        unset($config['require-dev'][$packageName]);
        
        $this->savePackagesConfig($config);
        
        // Remove package directory
        $packageDir = $this->vendorDir . '/' . $packageName;
        if (is_dir($packageDir)) {
            $this->removeDirectory($packageDir);
        }
        
        return true;
    }
    
    /**
     * Install all packages from packages.json
     */
    public function installAll()
    {
        $this->output("Installing all packages...");
        
        $config = $this->loadPackagesConfig();
        $installed = 0;
        
        // Install regular dependencies
        if (isset($config['require'])) {
            foreach ($config['require'] as $package => $version) {
                if ($package !== 'php') {
                    if ($this->downloadPackage($package, $version)) {
                        $installed++;
                    }
                }
            }
        }
        
        // Install dev dependencies
        if (isset($config['require-dev'])) {
            foreach ($config['require-dev'] as $package => $version) {
                if ($this->downloadPackage($package, $version)) {
                    $installed++;
                }
            }
        }
        
        $this->output("Installed {$installed} packages successfully!");
        return true;
    }
    
    /**
     * Update all packages
     */
    public function update()
    {
        $this->output("Updating all packages...");
        
        // Remove vendor directory
        if (is_dir($this->vendorDir)) {
            $this->removeDirectory($this->vendorDir);
        }
        
        // Reinstall all packages
        return $this->installAll();
    }
    
    /**
     * List installed packages
     */
    public function listPackages()
    {
        $config = $this->loadPackagesConfig();
        $packages = [];
        
        if (isset($config['require'])) {
            foreach ($config['require'] as $package => $version) {
                if ($package !== 'php') {
                    $packages[] = [
                        'name' => $package,
                        'version' => $version,
                        'type' => 'production'
                    ];
                }
            }
        }
        
        if (isset($config['require-dev'])) {
            foreach ($config['require-dev'] as $package => $version) {
                $packages[] = [
                    'name' => $package,
                    'version' => $version,
                    'type' => 'development'
                ];
            }
        }
        
        return $packages;
    }
    
    /**
     * Download and install a package
     */
    private function downloadPackage($packageName, $version = null)
    {
        $this->output("  - Downloading {$packageName}...");
        
        // Create package directory
        $packageDir = $this->vendorDir . '/' . $packageName;
        if (!is_dir($packageDir)) {
            mkdir($packageDir, 0755, true);
        }
        
        // For demo purposes, create a simple package structure
        // In a real implementation, this would download from repositories
        $this->createDemoPackage($packageDir, $packageName, $version);
        
        $this->output("  ✓ {$packageName} installed successfully");
        return true;
    }
    
    /**
     * Create a demo package (placeholder for real package download)
     */
    private function createDemoPackage($packageDir, $packageName, $version)
    {
        // Create package info file
        $packageInfo = [
            'name' => $packageName,
            'version' => $version ?: '1.0.0',
            'description' => "Package {$packageName}",
            'installed_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($packageDir . '/package.json', json_encode($packageInfo, JSON_PRETTY_PRINT));
        
        // Create a simple autoload file
        $autoloadContent = "<?php\n// Autoload for {$packageName}\n// Package installed at: " . date('Y-m-d H:i:s') . "\n";
        file_put_contents($packageDir . '/autoload.php', $autoloadContent);
        
        // Create README
        $readmeContent = "# {$packageName}\n\nVersion: " . ($version ?: '1.0.0') . "\nInstalled: " . date('Y-m-d H:i:s') . "\n";
        file_put_contents($packageDir . '/README.md', $readmeContent);
    }
    
    /**
     * Load packages.json configuration
     */
    private function loadPackagesConfig()
    {
        if (!file_exists($this->packagesFile)) {
            return $this->getDefaultConfig();
        }
        
        $content = file_get_contents($this->packagesFile);
        return json_decode($content, true) ?: $this->getDefaultConfig();
    }
    
    /**
     * Save packages.json configuration
     */
    private function savePackagesConfig($config)
    {
        $content = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return file_put_contents($this->packagesFile, $content);
    }
    
    /**
     * Get default configuration
     */
    private function getDefaultConfig()
    {
        return [
            'name' => 'mvc-framework',
            'description' => 'A modern PHP MVC framework',
            'version' => '1.0.0',
            'require' => [
                'php' => '>=7.4'
            ],
            'require-dev' => [],
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'app/'
                ]
            ]
        ];
    }
    
    /**
     * Ensure required directories exist
     */
    private function ensureDirectories()
    {
        if (!is_dir($this->vendorDir)) {
            mkdir($this->vendorDir, 0755, true);
        }
    }
    
    /**
     * Remove directory recursively
     */
    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
    
    /**
     * Output message
     */
    private function output($message)
    {
        echo $message . "\n";
    }
}
