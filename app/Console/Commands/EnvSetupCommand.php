<?php

namespace App\Console\Commands;

use App\Core\Console\Command;

/**
 * Environment Setup Command
 * 
 * Cross-platform environment setup for Arknox Framework
 */
class EnvSetupCommand extends Command
{
    protected $signature = 'env:setup {--force}';
    protected $description = 'Setup environment configuration for Arknox Framework';

    public function handle()
    {
        $this->info('🚀 Setting up Arknox Framework environment...');
        $this->line('');

        // Check if .env already exists
        if ($this->envExists() && !$this->option('force')) {
            if (!$this->confirm('Environment file already exists. Do you want to overwrite it?')) {
                $this->info('Environment setup cancelled.');
                return 0;
            }
        }

        $this->createEnvironmentFile();
        $this->setupDirectories();
        $this->setPermissions();
        $this->displayNextSteps();

        return 0;
    }

    /**
     * Check if .env file exists
     */
    protected function envExists()
    {
        return file_exists($this->getEnvPath());
    }

    /**
     * Create environment file
     */
    protected function createEnvironmentFile()
    {
        $this->info('📝 Creating environment configuration...');

        $envContent = $this->generateEnvContent();
        $envPath = $this->getEnvPath();

        if (file_put_contents($envPath, $envContent)) {
            $this->success("Environment file created: {$envPath}");
        } else {
            $this->error("Failed to create environment file: {$envPath}");
            return false;
        }

        return true;
    }

    /**
     * Generate environment file content
     */
    protected function generateEnvContent()
    {
        $appKey = $this->generateAppKey();
        $platform = PHP_OS_FAMILY;

        return <<<ENV
# Arknox Framework Environment Configuration
# Generated on: {$this->getCurrentDateTime()}
# Platform: {$platform}

# Application
APP_NAME="Arknox Application"
APP_ENV=local
APP_KEY={$appKey}
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=arknox_app
DB_USERNAME=root
DB_PASSWORD=

# Cache Configuration
CACHE_DRIVER=file
CACHE_PREFIX=arknox

# Session Configuration
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false

# Queue Configuration
QUEUE_CONNECTION=sync

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=587
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=noreply@arknox.dev
MAIL_FROM_NAME="Arknox Application"

# File Storage
FILESYSTEM_DRIVER=local

# Security
BCRYPT_ROUNDS=10
HASH_DRIVER=bcrypt

# API Configuration
API_PREFIX=api
API_VERSION=v1
API_RATE_LIMIT=60

# Admin Configuration
ADMIN_PREFIX=admin
ADMIN_MIDDLEWARE=auth,admin

# Development
LOG_CHANNEL=stack
LOG_LEVEL=debug

# Cross-platform paths (automatically detected)
# Windows: Use backslashes or forward slashes
# macOS/Linux: Use forward slashes
STORAGE_PATH=storage
CACHE_PATH=storage/cache
LOGS_PATH=storage/logs
UPLOADS_PATH=storage/uploads

ENV;
    }

    /**
     * Setup required directories
     */
    protected function setupDirectories()
    {
        $this->info('📁 Creating required directories...');

        $directories = [
            'storage',
            'storage/cache',
            'storage/logs',
            'storage/uploads',
            'storage/sessions',
            'storage/views',
            'storage/framework',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'bootstrap/cache',
            'public/uploads'
        ];

        foreach ($directories as $dir) {
            $fullPath = $this->getBasePath($dir);
            
            if (!is_dir($fullPath)) {
                if (mkdir($fullPath, 0755, true)) {
                    $this->line("  ✓ Created: {$dir}");
                } else {
                    $this->error("  ✗ Failed to create: {$dir}");
                }
            } else {
                $this->line("  ✓ Exists: {$dir}");
            }
        }
    }

    /**
     * Set appropriate permissions
     */
    protected function setPermissions()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->info('🔒 Permissions setup (Windows detected - using default permissions)');
            return;
        }

        $this->info('🔒 Setting up permissions...');

        $writablePaths = [
            'storage',
            'bootstrap/cache',
            'public/uploads'
        ];

        foreach ($writablePaths as $path) {
            $fullPath = $this->getBasePath($path);
            
            if (is_dir($fullPath)) {
                if (chmod($fullPath, 0775)) {
                    $this->line("  ✓ Set permissions for: {$path}");
                } else {
                    $this->warn("  ⚠ Could not set permissions for: {$path}");
                }
            }
        }

        // Make CLI executable
        $cliPath = $this->getBasePath('arknox');
        if (file_exists($cliPath)) {
            chmod($cliPath, 0755);
            $this->line("  ✓ Made CLI executable: arknox");
        }
    }

    /**
     * Display next steps
     */
    protected function displayNextSteps()
    {
        $this->line('');
        $this->success('🎉 Environment setup completed successfully!');
        $this->line('');
        
        $this->info('📋 Next steps:');
        $this->line('');
        $this->line('1. Configure your database settings in .env file');
        $this->line('2. Create your database:');
        $this->line('   <green>arknox db:create</green>');
        $this->line('');
        $this->line('3. Run database migrations:');
        $this->line('   <green>arknox migrate</green>');
        $this->line('');
        $this->line('4. Start the development server:');
        $this->line('   <green>arknox serve</green>');
        $this->line('');
        
        if (PHP_OS_FAMILY === 'Darwin') {
            $this->info('🍎 macOS specific notes:');
            $this->line('- Make sure Homebrew is installed for easy package management');
            $this->line('- Consider using Laravel Valet for local development');
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $this->info('🪟 Windows specific notes:');
            $this->line('- Consider using XAMPP or WAMP for local development');
            $this->line('- Use Windows Terminal or PowerShell for better CLI experience');
        }
        
        $this->line('');
        $this->line('For more information, visit: <blue>https://docs.arknox.dev</blue>');
    }

    /**
     * Generate application key
     */
    protected function generateAppKey()
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    /**
     * Get current date time
     */
    protected function getCurrentDateTime()
    {
        return date('Y-m-d H:i:s T');
    }

    /**
     * Get environment file path
     */
    protected function getEnvPath()
    {
        return $this->getBasePath('.env');
    }

    /**
     * Get base path
     */
    protected function getBasePath($path = '')
    {
        $basePath = defined('ARKNOX_ROOT') ? ARKNOX_ROOT : getcwd();
        
        if (empty($path)) {
            return $basePath;
        }
        
        return $basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}
