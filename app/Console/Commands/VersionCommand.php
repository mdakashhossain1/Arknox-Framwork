<?php

namespace App\Console\Commands;

use App\Core\Console\Command;

/**
 * Version Command
 * 
 * Display Arknox Framework version information
 */
class VersionCommand extends Command
{
    protected $signature = 'version';
    protected $description = 'Display Arknox Framework version information';

    public function handle()
    {
        $this->displayHeader();
        $this->displaySystemInfo();
        $this->displayFrameworkInfo();
        $this->displayEnvironmentInfo();
        
        return 0;
    }

    /**
     * Display Arknox header
     */
    protected function displayHeader()
    {
        $this->line('');
        $this->line('<green>   ___         _                    </green>');
        $this->line('<green>  / _ \  _ __ | | __ _ __   _____  __</green>');
        $this->line('<green> / /_\ \| \'__|| |/ /| \'_ \ / _ \ \/ /</green>');
        $this->line('<green>/  _  \| |   |   < | | | | (_) >  < </green>');
        $this->line('<green>\_/ \_/|_|   |_|\_\|_| |_|\___/_/\_\</green>');
        $this->line('');
        $this->line('<yellow>The Most Advanced PHP Framework</yellow>');
        $this->line('');
    }

    /**
     * Display system information
     */
    protected function displaySystemInfo()
    {
        $this->line('<yellow>System Information:</yellow>');
        $this->line('  Platform: ' . $this->getPlatformInfo());
        $this->line('  PHP Version: ' . PHP_VERSION);
        $this->line('  Architecture: ' . php_uname('m'));
        $this->line('  OS: ' . php_uname('s') . ' ' . php_uname('r'));
        $this->line('');
    }

    /**
     * Display framework information
     */
    protected function displayFrameworkInfo()
    {
        $this->line('<yellow>Framework Information:</yellow>');
        $this->line('  Arknox Version: ' . $this->getFrameworkVersion());
        $this->line('  Installation Path: ' . $this->getInstallationPath());
        $this->line('  Environment: ' . $this->getEnvironment());
        $this->line('  Debug Mode: ' . ($this->isDebugMode() ? 'Enabled' : 'Disabled'));
        $this->line('');
    }

    /**
     * Display environment information
     */
    protected function displayEnvironmentInfo()
    {
        $this->line('<yellow>Environment:</yellow>');
        $this->line('  Memory Limit: ' . ini_get('memory_limit'));
        $this->line('  Max Execution Time: ' . ini_get('max_execution_time') . 's');
        $this->line('  Upload Max Size: ' . ini_get('upload_max_filesize'));
        $this->line('  Post Max Size: ' . ini_get('post_max_size'));
        
        // Check extensions
        $this->line('');
        $this->line('<yellow>Required Extensions:</yellow>');
        $this->checkExtension('PDO');
        $this->checkExtension('mbstring');
        $this->checkExtension('openssl');
        $this->checkExtension('json');
        $this->checkExtension('curl');
        
        $this->line('');
        $this->line('<yellow>Optional Extensions:</yellow>');
        $this->checkExtension('redis', false);
        $this->checkExtension('memcached', false);
        $this->checkExtension('imagick', false);
        $this->checkExtension('gd', false);
        $this->checkExtension('zip', false);
        $this->checkExtension('bcmath', false);
        $this->checkExtension('intl', false);
    }

    /**
     * Get platform information
     */
    protected function getPlatformInfo()
    {
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');
        
        $platforms = [
            'Windows' => '🪟 Windows',
            'Darwin' => '🍎 macOS',
            'Linux' => '🐧 Linux',
            'BSD' => '👹 BSD',
            'Solaris' => '☀️ Solaris'
        ];
        
        $platformIcon = $platforms[$os] ?? "🖥️ {$os}";
        
        return "{$platformIcon} ({$arch})";
    }

    /**
     * Get framework version
     */
    protected function getFrameworkVersion()
    {
        // Try to get version from composer.json
        $composerPath = defined('ARKNOX_ROOT') ? ARKNOX_ROOT . '/composer.json' : getcwd() . '/composer.json';
        
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            return $composer['version'] ?? '1.0.0';
        }
        
        return '1.0.0';
    }

    /**
     * Get installation path
     */
    protected function getInstallationPath()
    {
        return defined('ARKNOX_ROOT') ? ARKNOX_ROOT : getcwd();
    }

    /**
     * Get environment
     */
    protected function getEnvironment()
    {
        return $_ENV['APP_ENV'] ?? 'production';
    }

    /**
     * Check if debug mode is enabled
     */
    protected function isDebugMode()
    {
        return isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true';
    }

    /**
     * Check extension availability
     */
    protected function checkExtension($extension, $required = true)
    {
        $loaded = extension_loaded($extension);
        $status = $loaded ? '<green>✓</green>' : '<red>✗</red>';
        $type = $required ? 'Required' : 'Optional';
        
        $this->line("  {$status} {$extension} ({$type})");
        
        if ($required && !$loaded) {
            $this->line("    <red>Warning: {$extension} extension is required but not loaded</red>");
        }
    }
}
