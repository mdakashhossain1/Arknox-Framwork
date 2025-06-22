<?php

namespace App\Console\Commands;

use App\Core\Package\PackageManager;

/**
 * Package List Command
 * 
 * List all installed packages
 */
class PackageListCommand extends BaseCommand
{
    private $packageManager;
    
    public function __construct()
    {
        $this->packageManager = new PackageManager();
    }
    
    public function execute($arguments)
    {
        $this->info("Listing installed packages...");
        
        try {
            $packages = $this->packageManager->listPackages();
            
            if (empty($packages)) {
                $this->warning("No packages installed.");
                $this->info("Run 'php console install <package-name>' to install packages.");
                return;
            }
            
            $this->displayPackages($packages);
            
        } catch (\Exception $e) {
            $this->error("Failed to list packages: " . $e->getMessage());
        }
    }
    
    /**
     * Display packages in a formatted table
     */
    private function displayPackages($packages)
    {
        echo "\n";
        $this->info("Installed Packages:");
        echo "\n";
        
        // Table header
        printf("%-30s %-15s %-15s\n", "Package", "Version", "Type");
        echo str_repeat("-", 62) . "\n";
        
        // Group packages by type
        $production = [];
        $development = [];
        
        foreach ($packages as $package) {
            if ($package['type'] === 'development') {
                $development[] = $package;
            } else {
                $production[] = $package;
            }
        }
        
        // Display production packages
        if (!empty($production)) {
            foreach ($production as $package) {
                printf("%-30s %-15s %-15s\n", 
                    $package['name'], 
                    $package['version'], 
                    'production'
                );
            }
        }
        
        // Display development packages
        if (!empty($development)) {
            if (!empty($production)) {
                echo str_repeat("-", 62) . "\n";
            }
            
            foreach ($development as $package) {
                printf("%-30s %-15s %-15s\n", 
                    $package['name'], 
                    $package['version'], 
                    'development'
                );
            }
        }
        
        echo "\n";
        $this->success("Total packages: " . count($packages));
        
        // Show package details if requested
        if (in_array('--details', $GLOBALS['argv'] ?? [])) {
            $this->showPackageDetails($packages);
        } else {
            echo "\n";
            $this->info("Use --details flag to see more information about each package.");
        }
    }
    
    /**
     * Show detailed package information
     */
    private function showPackageDetails($packages)
    {
        echo "\n";
        $this->info("Package Details:");
        echo "\n";
        
        $vendorDir = getcwd() . '/vendor';
        
        foreach ($packages as $package) {
            $packageDir = $vendorDir . '/' . $package['name'];
            $packageJsonFile = $packageDir . '/package.json';
            
            echo "Package: " . $package['name'] . "\n";
            echo "  Version: " . $package['version'] . "\n";
            echo "  Type: " . $package['type'] . "\n";
            
            if (file_exists($packageJsonFile)) {
                $packageInfo = json_decode(file_get_contents($packageJsonFile), true);
                if ($packageInfo) {
                    echo "  Description: " . ($packageInfo['description'] ?? 'No description') . "\n";
                    echo "  Installed: " . ($packageInfo['installed_at'] ?? 'Unknown') . "\n";
                }
            }
            
            echo "  Location: " . $packageDir . "\n";
            echo "\n";
        }
    }
}
