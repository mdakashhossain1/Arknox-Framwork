<?php

namespace App\Core\Console;

/**
 * Arknox Console Application
 * 
 * Cross-platform CLI application for Arknox Framework
 */
class Application
{
    protected $commands = [];
    protected $name = 'Arknox Framework';
    protected $version = '1.0.0';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->detectPlatform();
    }

    /**
     * Register commands
     */
    public function registerCommands(array $commands)
    {
        foreach ($commands as $name => $class) {
            $this->commands[$name] = $class;
        }
    }

    /**
     * Auto-discover commands from directory
     */
    public function discoverCommands($directory)
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*Command.php');
        
        foreach ($files as $file) {
            $className = basename($file, '.php');
            $fullClassName = "App\\Console\\Commands\\{$className}";
            
            if (class_exists($fullClassName)) {
                // Extract command name from class name
                $commandName = $this->extractCommandName($className);
                $this->commands[$commandName] = $fullClassName;
            }
        }
    }

    /**
     * Run the application
     */
    public function run(array $argv)
    {
        array_shift($argv); // Remove script name

        if (empty($argv)) {
            $this->showHelp();
            return 0;
        }

        $commandName = array_shift($argv);

        // Handle built-in commands
        switch ($commandName) {
            case '--version':
            case '-V':
                $this->showVersion();
                return 0;
                
            case '--help':
            case '-h':
            case 'help':
                if (!empty($argv)) {
                    $this->showCommandHelp($argv[0]);
                } else {
                    $this->showHelp();
                }
                return 0;
                
            case 'list':
                $this->listCommands();
                return 0;
        }

        // Find and execute command
        if (!isset($this->commands[$commandName])) {
            $this->error("Command '{$commandName}' not found.");
            $this->suggestSimilarCommands($commandName);
            return 1;
        }

        try {
            $commandClass = $this->commands[$commandName];
            $command = new $commandClass();
            
            return $command->execute($argv);
            
        } catch (\Exception $e) {
            $this->error("Error executing command: " . $e->getMessage());
            
            if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                $this->line($e->getTraceAsString());
            }
            
            return 1;
        }
    }

    /**
     * Show application help
     */
    protected function showHelp()
    {
        $this->line($this->getHeader());
        $this->line('');
        $this->line('<yellow>Usage:</yellow>');
        $this->line('  arknox <command> [options] [arguments]');
        $this->line('');
        $this->line('<yellow>Options:</yellow>');
        $this->line('  -h, --help     Display help information');
        $this->line('  -V, --version  Display version information');
        $this->line('');
        $this->line('<yellow>Available commands:</yellow>');
        
        $categories = $this->categorizeCommands();
        
        foreach ($categories as $category => $commands) {
            if ($category !== 'misc') {
                $this->line("  <green>{$category}</green>");
            }
            
            foreach ($commands as $name => $class) {
                $description = $this->getCommandDescription($class);
                $this->line(sprintf('    %-20s %s', $name, $description));
            }
            
            $this->line('');
        }
    }

    /**
     * Show version information
     */
    protected function showVersion()
    {
        $this->line($this->getHeader());
        $this->line('');
        $this->line("Platform: " . $this->getPlatformInfo());
        $this->line("PHP Version: " . PHP_VERSION);
        $this->line("Memory Limit: " . ini_get('memory_limit'));
    }

    /**
     * List all commands
     */
    protected function listCommands()
    {
        $this->line('<yellow>Available commands:</yellow>');
        
        foreach ($this->commands as $name => $class) {
            $description = $this->getCommandDescription($class);
            $this->line(sprintf('  %-25s %s', $name, $description));
        }
    }

    /**
     * Show command help
     */
    protected function showCommandHelp($commandName)
    {
        if (!isset($this->commands[$commandName])) {
            $this->error("Command '{$commandName}' not found.");
            return;
        }

        $commandClass = $this->commands[$commandName];
        $command = new $commandClass();
        
        if (method_exists($command, 'getHelp')) {
            $this->line($command->getHelp());
        } else {
            $this->line("Help for command: {$commandName}");
            $this->line($this->getCommandDescription($commandClass));
        }
    }

    /**
     * Get application header
     */
    protected function getHeader()
    {
        return "<green>{$this->name}</green> <yellow>v{$this->version}</yellow>";
    }

    /**
     * Get platform information
     */
    protected function getPlatformInfo()
    {
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');
        return "{$os} ({$arch})";
    }

    /**
     * Detect platform and set appropriate settings
     */
    protected function detectPlatform()
    {
        // Set platform-specific configurations
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows-specific settings
            if (!defined('DIRECTORY_SEPARATOR')) {
                define('DIRECTORY_SEPARATOR', '\\');
            }
        } else {
            // Unix/Linux/macOS settings
            if (!defined('DIRECTORY_SEPARATOR')) {
                define('DIRECTORY_SEPARATOR', '/');
            }
        }
    }

    /**
     * Categorize commands
     */
    protected function categorizeCommands()
    {
        $categories = [
            'make' => [],
            'migrate' => [],
            'db' => [],
            'cache' => [],
            'queue' => [],
            'package' => [],
            'plugin' => [],
            'admin' => [],
            'misc' => []
        ];

        foreach ($this->commands as $name => $class) {
            $category = 'misc';
            
            if (strpos($name, 'make:') === 0) {
                $category = 'make';
            } elseif (strpos($name, 'migrate') === 0) {
                $category = 'migrate';
            } elseif (strpos($name, 'db:') === 0) {
                $category = 'db';
            } elseif (strpos($name, 'cache:') === 0) {
                $category = 'cache';
            } elseif (strpos($name, 'queue:') === 0) {
                $category = 'queue';
            } elseif (strpos($name, 'package:') === 0) {
                $category = 'package';
            } elseif (strpos($name, 'plugin:') === 0) {
                $category = 'plugin';
            } elseif (strpos($name, 'admin:') === 0) {
                $category = 'admin';
            }
            
            $categories[$category][$name] = $class;
        }

        return array_filter($categories);
    }

    /**
     * Extract command name from class name
     */
    protected function extractCommandName($className)
    {
        // Convert MakeModelCommand to make:model
        $name = str_replace('Command', '', $className);
        $name = preg_replace('/([A-Z])/', ':$1', $name);
        $name = strtolower(trim($name, ':'));
        return str_replace(':::', ':', $name);
    }

    /**
     * Get command description
     */
    protected function getCommandDescription($class)
    {
        if (class_exists($class)) {
            try {
                $reflection = new \ReflectionClass($class);

                // Skip abstract classes
                if ($reflection->isAbstract()) {
                    return 'No description available';
                }

                $instance = new $class();
                if (method_exists($instance, 'getDescription')) {
                    return $instance->getDescription();
                } elseif (property_exists($instance, 'description')) {
                    // Try to access via reflection for protected properties
                    try {
                        $reflection = new \ReflectionProperty($instance, 'description');
                        $reflection->setAccessible(true);
                        return $reflection->getValue($instance);
                    } catch (\Exception $e) {
                        // Fallback
                    }
                }
            } catch (\Exception $e) {
                // Fallback for any instantiation errors
            }
        }
        return 'No description available';
    }

    /**
     * Suggest similar commands
     */
    protected function suggestSimilarCommands($commandName)
    {
        $suggestions = [];
        
        foreach (array_keys($this->commands) as $command) {
            $similarity = similar_text($commandName, $command);
            if ($similarity > 3) {
                $suggestions[] = $command;
            }
        }

        if (!empty($suggestions)) {
            $this->line('');
            $this->line('Did you mean one of these?');
            foreach ($suggestions as $suggestion) {
                $this->line("  {$suggestion}");
            }
        }
    }

    /**
     * Output methods
     */
    protected function line($message = '')
    {
        echo $this->colorize($message) . "\n";
    }

    protected function error($message)
    {
        echo $this->colorize("<red>Error:</red> {$message}") . "\n";
    }

    protected function colorize($message)
    {
        // Simple color support for cross-platform compatibility
        $colors = [
            '<red>' => "\033[31m",
            '</red>' => "\033[0m",
            '<green>' => "\033[32m",
            '</green>' => "\033[0m",
            '<yellow>' => "\033[33m",
            '</yellow>' => "\033[0m",
            '<blue>' => "\033[34m",
            '</blue>' => "\033[0m",
        ];

        // Disable colors on Windows unless explicitly supported
        if (PHP_OS_FAMILY === 'Windows' && !getenv('ANSICON') && !getenv('ConEmuANSI')) {
            return strip_tags(str_replace(array_keys($colors), '', $message));
        }

        return str_replace(array_keys($colors), array_values($colors), $message);
    }
}
