<?php

namespace App\Console\Commands;

/**
 * List Command
 * 
 * List all available commands
 */
class ListCommand extends BaseCommand
{
    public function execute($arguments)
    {
        echo "\n";
        echo "\033[32mMVC Framework Console\033[0m\n";
        echo str_repeat("=", 30) . "\n\n";
        
        echo "\033[33mAvailable Commands:\033[0m\n\n";
        
        $commands = [
            'make:controller' => 'Create a new controller class',
            'make:model' => 'Create a new model class',
            'make:view' => 'Create a new view file',
            'make:middleware' => 'Create a new middleware class',
            'install' => 'Install packages',
            'remove' => 'Remove a package',
            'update' => 'Update packages',
            'search' => 'Search for packages',
            'package:list' => 'List installed packages',
            'serve' => 'Start the development server',
            'cache:clear' => 'Clear the application cache',
            'route:list' => 'List all registered routes',
            'migrate' => 'Run database migrations',
            'list' => 'Show this command list',
            'help' => 'Show help information',
        ];
        
        foreach ($commands as $command => $description) {
            printf("  \033[32m%-20s\033[0m %s\n", $command, $description);
        }
        
        echo "\n";
        echo "\033[33mUsage:\033[0m\n";
        echo "  php console <command> [arguments]\n\n";
        
        echo "\033[33mExamples:\033[0m\n";
        echo "  php console make:controller UserController\n";
        echo "  php console make:model User\n";
        echo "  php console make:view users/index\n";
        echo "  php console install monolog/monolog\n";
        echo "  php console search logging\n";
        echo "  php console serve --port=8080\n";
        echo "  php console cache:clear\n";
        echo "\n";
    }
}
