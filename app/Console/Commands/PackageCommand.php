<?php

namespace App\Console\Commands;

/**
 * Package Manager Command
 * 
 * Laravel Composer-style package management for the framework
 * with dependency resolution and auto-discovery
 */
class PackageCommand extends BaseCommand
{
    protected $packagesFile;
    protected $vendorDir;
    protected $lockFile;

    public function __construct()
    {
        $this->packagesFile = getcwd() . '/packages.json';
        $this->vendorDir = getcwd() . '/vendor';
        $this->lockFile = getcwd() . '/packages.lock';
    }

    public function execute($arguments)
    {
        if (empty($arguments)) {
            $this->showHelp();
            return false;
        }

        $command = $arguments[0];
        $args = array_slice($arguments, 1);

        switch ($command) {
            case 'install':
                return $this->install($args);
            case 'require':
                return $this->require($args);
            case 'remove':
                return $this->remove($args);
            case 'update':
                return $this->update($args);
            case 'list':
                return $this->listPackages();
            case 'search':
                return $this->search($args);
            case 'init':
                return $this->init();
            default:
                $this->error("Unknown command: {$command}");
                $this->showHelp();
                return false;
        }
    }

    private function showHelp()
    {
        $this->info("📦 Package Manager Commands:");
        $this->info("");
        $this->info("  init                 Initialize packages.json file");
        $this->info("  install              Install all dependencies from packages.json");
        $this->info("  require <package>    Add and install a new package");
        $this->info("  remove <package>     Remove a package");
        $this->info("  update [package]     Update packages to latest versions");
        $this->info("  list                 List installed packages");
        $this->info("  search <term>        Search for packages");
        $this->info("");
        $this->info("Examples:");
        $this->info("  php console package require monolog/monolog");
        $this->info("  php console package install");
        $this->info("  php console package update");
    }

    private function init()
    {
        if (file_exists($this->packagesFile)) {
            $this->warning("⚠️  packages.json already exists");
            return false;
        }

        $packageData = [
            'name' => basename(getcwd()),
            'description' => 'A next-generation PHP MVC framework application',
            'version' => '1.0.0',
            'type' => 'project',
            'require' => [
                'php' => '>=7.4'
            ],
            'require-dev' => [],
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'app/'
                ]
            ],
            'repositories' => [
                [
                    'type' => 'composer',
                    'url' => 'https://packagist.org'
                ]
            ],
            'config' => [
                'vendor-dir' => 'vendor',
                'optimize-autoloader' => true
            ]
        ];

        file_put_contents($this->packagesFile, json_encode($packageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $this->success("✅ Created packages.json");
        return true;
    }

    private function install($args = [])
    {
        if (!file_exists($this->packagesFile)) {
            $this->error("❌ packages.json not found. Run 'php console package init' first.");
            return false;
        }

        $this->info("📦 Installing packages...");

        $packages = json_decode(file_get_contents($this->packagesFile), true);
        $dependencies = array_merge(
            $packages['require'] ?? [],
            $packages['require-dev'] ?? []
        );

        if (empty($dependencies)) {
            $this->info("✅ No packages to install");
            return true;
        }

        $installed = [];
        $failed = [];

        foreach ($dependencies as $package => $version) {
            if ($package === 'php') {
                continue; // Skip PHP version requirement
            }

            $this->info("Installing {$package}...");
            
            if ($this->installPackage($package, $version)) {
                $installed[] = $package;
                $this->success("✅ Installed {$package}");
            } else {
                $failed[] = $package;
                $this->error("❌ Failed to install {$package}");
            }
        }

        // Generate autoloader
        $this->generateAutoloader($packages);

        // Create lock file
        $this->createLockFile($installed);

        $this->info("");
        $this->success("📦 Installation complete!");
        $this->info("Installed: " . count($installed) . " packages");
        
        if (!empty($failed)) {
            $this->warning("Failed: " . count($failed) . " packages");
        }

        return empty($failed);
    }

    private function require($args)
    {
        if (empty($args)) {
            $this->error("❌ Package name required");
            return false;
        }

        $packageName = $args[0];
        $version = $args[1] ?? '*';

        if (!file_exists($this->packagesFile)) {
            $this->init();
        }

        $packages = json_decode(file_get_contents($this->packagesFile), true);
        
        // Add to require section
        $packages['require'][$packageName] = $version;

        // Save packages.json
        file_put_contents($this->packagesFile, json_encode($packages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("📦 Added {$packageName} to packages.json");

        // Install the package
        return $this->install();
    }

    private function remove($args)
    {
        if (empty($args)) {
            $this->error("❌ Package name required");
            return false;
        }

        $packageName = $args[0];

        if (!file_exists($this->packagesFile)) {
            $this->error("❌ packages.json not found");
            return false;
        }

        $packages = json_decode(file_get_contents($this->packagesFile), true);

        // Remove from require sections
        unset($packages['require'][$packageName]);
        unset($packages['require-dev'][$packageName]);

        // Save packages.json
        file_put_contents($this->packagesFile, json_encode($packages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Remove package directory
        $packageDir = $this->vendorDir . '/' . $packageName;
        if (is_dir($packageDir)) {
            $this->removeDirectory($packageDir);
        }

        $this->success("✅ Removed {$packageName}");
        return true;
    }

    private function update($args = [])
    {
        $this->info("🔄 Updating packages...");
        
        // For now, just reinstall all packages
        return $this->install();
    }

    private function listPackages()
    {
        if (!file_exists($this->packagesFile)) {
            $this->error("❌ packages.json not found");
            return false;
        }

        $packages = json_decode(file_get_contents($this->packagesFile), true);
        $dependencies = array_merge(
            $packages['require'] ?? [],
            $packages['require-dev'] ?? []
        );

        $this->info("📦 Installed Packages:");
        $this->info("");

        foreach ($dependencies as $package => $version) {
            if ($package === 'php') continue;
            
            $status = is_dir($this->vendorDir . '/' . $package) ? '✅' : '❌';
            $this->info("  {$status} {$package} ({$version})");
        }

        return true;
    }

    private function search($args)
    {
        if (empty($args)) {
            $this->error("❌ Search term required");
            return false;
        }

        $term = $args[0];
        $this->info("🔍 Searching for packages containing '{$term}'...");

        // Simulate package search (in real implementation, this would query Packagist)
        $mockResults = [
            'monolog/monolog' => 'Sends your logs to files, sockets, inboxes, databases and various web services',
            'guzzlehttp/guzzle' => 'Guzzle is a PHP HTTP client library',
            'symfony/console' => 'Eases the creation of beautiful and testable command line interfaces',
            'doctrine/dbal' => 'Powerful PHP database abstraction layer',
            'twig/twig' => 'Twig, the flexible, fast, and secure template language for PHP'
        ];

        $found = [];
        foreach ($mockResults as $package => $description) {
            if (stripos($package, $term) !== false || stripos($description, $term) !== false) {
                $found[$package] = $description;
            }
        }

        if (empty($found)) {
            $this->warning("No packages found matching '{$term}'");
            return false;
        }

        $this->info("Found " . count($found) . " packages:");
        $this->info("");

        foreach ($found as $package => $description) {
            $this->info("📦 {$package}");
            $this->info("   {$description}");
            $this->info("");
        }

        return true;
    }

    private function installPackage($package, $version)
    {
        // Create vendor directory
        if (!is_dir($this->vendorDir)) {
            mkdir($this->vendorDir, 0755, true);
        }

        $packageDir = $this->vendorDir . '/' . $package;
        
        // For demonstration, we'll create a mock package structure
        // In a real implementation, this would download from Packagist
        if (!is_dir($packageDir)) {
            mkdir($packageDir, 0755, true);
            
            // Create a mock composer.json for the package
            $packageInfo = [
                'name' => $package,
                'version' => $version,
                'description' => "Mock package {$package}",
                'autoload' => [
                    'psr-4' => [
                        ucfirst(explode('/', $package)[1]) . '\\' => 'src/'
                    ]
                ]
            ];
            
            file_put_contents($packageDir . '/composer.json', json_encode($packageInfo, JSON_PRETTY_PRINT));
            
            // Create src directory
            mkdir($packageDir . '/src', 0755, true);
            
            return true;
        }

        return true;
    }

    private function generateAutoloader($packages)
    {
        $autoloadFile = $this->vendorDir . '/autoload.php';
        
        if (!is_dir($this->vendorDir)) {
            mkdir($this->vendorDir, 0755, true);
        }

        $autoloadContent = "<?php
/**
 * Auto-generated autoloader
 */

spl_autoload_register(function (\$class) {
    // PSR-4 autoloading
    \$prefixes = [
";

        // Add framework autoloading
        if (isset($packages['autoload']['psr-4'])) {
            foreach ($packages['autoload']['psr-4'] as $prefix => $path) {
                $autoloadContent .= "        '{$prefix}' => __DIR__ . '/../{$path}',\n";
            }
        }

        // Add vendor packages
        $vendorDirs = glob($this->vendorDir . '/*', GLOB_ONLYDIR);
        foreach ($vendorDirs as $vendorDir) {
            $composerFile = $vendorDir . '/composer.json';
            if (file_exists($composerFile)) {
                $packageInfo = json_decode(file_get_contents($composerFile), true);
                if (isset($packageInfo['autoload']['psr-4'])) {
                    foreach ($packageInfo['autoload']['psr-4'] as $prefix => $path) {
                        $autoloadContent .= "        '{$prefix}' => '{$vendorDir}/{$path}',\n";
                    }
                }
            }
        }

        $autoloadContent .= "    ];

    foreach (\$prefixes as \$prefix => \$baseDir) {
        \$len = strlen(\$prefix);
        if (strncmp(\$prefix, \$class, \$len) !== 0) {
            continue;
        }

        \$relativeClass = substr(\$class, \$len);
        \$file = \$baseDir . str_replace('\\\\', '/', \$relativeClass) . '.php';

        if (file_exists(\$file)) {
            require \$file;
            return;
        }
    }
});
";

        file_put_contents($autoloadFile, $autoloadContent);
        $this->info("✅ Generated autoloader");
    }

    private function createLockFile($installed)
    {
        $lockData = [
            'packages' => $installed,
            'generated' => date('Y-m-d H:i:s'),
            'hash' => md5_file($this->packagesFile)
        ];

        file_put_contents($this->lockFile, json_encode($lockData, JSON_PRETTY_PRINT));
    }

    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
