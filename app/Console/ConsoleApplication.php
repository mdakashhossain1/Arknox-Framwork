<?php

namespace App\Console;

/**
 * Console Application
 * 
 * Main console application handler for CLI commands
 */
class ConsoleApplication
{
    private $commands = [];
    
    public function __construct()
    {
        $this->registerCommands();
    }
    
    /**
     * Register all available commands
     */
    private function registerCommands()
    {
        $this->commands = [
            // Code generation commands
            'make:controller' => Commands\MakeControllerCommand::class,
            'make:model' => Commands\MakeModelCommand::class,
            'make:view' => Commands\MakeViewCommand::class,
            'make:middleware' => Commands\MakeMiddlewareCommand::class,

            // Package management commands
            'install' => Commands\InstallCommand::class,
            'remove' => Commands\RemoveCommand::class,
            'update' => Commands\UpdateCommand::class,
            'search' => Commands\SearchCommand::class,
            'package:list' => Commands\PackageListCommand::class,

            // Development commands
            'serve' => Commands\ServeCommand::class,
            'cache:clear' => Commands\CacheClearCommand::class,
            'route:list' => Commands\RouteListCommand::class,
            'migrate' => Commands\MigrateCommand::class,

            // Database commands
            'db:list' => Commands\DatabaseListCommand::class,
            'db:test' => Commands\DatabaseTestCommand::class,

            // Help commands
            'help' => Commands\HelpCommand::class,
            'list' => Commands\ListCommand::class,
        ];
    }
    
    /**
     * Run the console application
     */
    public function run($argv)
    {
        // Remove script name from arguments
        array_shift($argv);
        
        if (empty($argv)) {
            $this->showHelp();
            return;
        }
        
        $command = $argv[0];
        $arguments = array_slice($argv, 1);
        
        if (!isset($this->commands[$command])) {
            echo "Command '{$command}' not found.\n";
            echo "Run 'php console list' to see available commands.\n";
            return;
        }
        
        $commandClass = $this->commands[$command];
        $commandInstance = new $commandClass();
        $commandInstance->execute($arguments);
    }
    
    /**
     * Show help information
     */
    private function showHelp()
    {
        echo "\n";
        echo "MVC Framework Console\n";
        echo "====================\n\n";
        echo "Usage:\n";
        echo "  php console <command> [arguments]\n\n";
        echo "Available commands:\n\n";
        echo "Code Generation:\n";
        echo "  make:controller <name>    Create a new controller\n";
        echo "  make:model <name>         Create a new model\n";
        echo "  make:view <name>          Create a new view\n";
        echo "  make:middleware <name>    Create a new middleware\n\n";
        echo "Package Management:\n";
        echo "  install [package]         Install packages\n";
        echo "  remove <package>          Remove a package\n";
        echo "  update [package]          Update packages\n";
        echo "  search <term>             Search for packages\n";
        echo "  package:list              List installed packages\n\n";
        echo "Development:\n";
        echo "  serve                     Start development server\n";
        echo "  cache:clear               Clear application cache\n";
        echo "  route:list                List all registered routes\n";
        echo "  migrate                   Run database migrations\n\n";
        echo "Help:\n";
        echo "  list                      List all commands\n";
        echo "  help                      Show this help message\n";
        echo "\n";
    }
}
