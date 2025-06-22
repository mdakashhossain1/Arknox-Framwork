<?php

namespace App\Console\Commands;

/**
 * Help Command
 * 
 * Show help information
 */
class HelpCommand extends BaseCommand
{
    public function execute($arguments)
    {
        echo "\n";
        echo "\033[32m███╗   ███╗██╗   ██╗ ██████╗\033[0m\n";
        echo "\033[32m████╗ ████║██║   ██║██╔════╝\033[0m\n";
        echo "\033[32m██╔████╔██║██║   ██║██║     \033[0m\n";
        echo "\033[32m██║╚██╔╝██║╚██╗ ██╔╝██║     \033[0m\n";
        echo "\033[32m██║ ╚═╝ ██║ ╚████╔╝ ╚██████╗\033[0m\n";
        echo "\033[32m╚═╝     ╚═╝  ╚═══╝   ╚═════╝\033[0m\n";
        echo "\n";
        echo "\033[33mMVC Framework Console Tool\033[0m\n";
        echo "Version 1.0.0\n\n";
        
        echo "\033[33mDESCRIPTION:\033[0m\n";
        echo "  The MVC Framework console tool provides various commands to help you\n";
        echo "  develop your application faster. You can generate controllers, models,\n";
        echo "  views, middleware, and perform various maintenance tasks.\n\n";
        
        echo "\033[33mUSAGE:\033[0m\n";
        echo "  php console <command> [options] [arguments]\n\n";
        
        echo "\033[33mGLOBAL OPTIONS:\033[0m\n";
        echo "  -h, --help     Display help information\n";
        echo "  -v, --version  Display version information\n\n";
        
        echo "\033[33mAVAILABLE COMMANDS:\033[0m\n\n";
        
        echo "\033[36m  make\033[0m\n";
        echo "    make:controller    Create a new controller class\n";
        echo "    make:model         Create a new model class\n";
        echo "    make:view          Create a new view file\n";
        echo "    make:middleware    Create a new middleware class\n\n";
        
        echo "\033[36m  cache\033[0m\n";
        echo "    cache:clear        Clear the application cache\n\n";
        
        echo "\033[36m  route\033[0m\n";
        echo "    route:list         List all registered routes\n\n";
        
        echo "\033[36m  server\033[0m\n";
        echo "    serve              Start the development server\n\n";
        
        echo "\033[36m  database\033[0m\n";
        echo "    migrate            Run database migrations\n\n";
        
        echo "\033[33mEXAMPLES:\033[0m\n";
        echo "  # Create a new controller\n";
        echo "  php console make:controller PostController\n\n";
        echo "  # Create a new model\n";
        echo "  php console make:model Post\n\n";
        echo "  # Create a new view\n";
        echo "  php console make:view posts/index\n\n";
        echo "  # Start development server on custom port\n";
        echo "  php console serve --port=8080\n\n";
        echo "  # Clear application cache\n";
        echo "  php console cache:clear\n\n";
        
        echo "For more information about a specific command, use:\n";
        echo "  php console <command> --help\n\n";
    }
}
