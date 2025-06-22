<?php

namespace App\Console\Commands;

/**
 * Route List Command
 * 
 * Display all registered routes
 */
class RouteListCommand extends BaseCommand
{
    public function execute($arguments)
    {
        $this->info("Loading routes...");
        
        // Load routes configuration
        $routes = require 'config/routes.php';
        
        if (empty($routes)) {
            $this->warning("No routes found.");
            return;
        }
        
        $this->info("Registered Routes:");
        echo "\n";
        
        // Table header
        printf("%-10s %-30s %-30s\n", "Method", "URI", "Action");
        echo str_repeat("-", 72) . "\n";
        
        foreach ($routes as $route => $action) {
            // Parse route
            $parts = explode(' ', $route, 2);
            $method = $parts[0] ?? 'GET';
            $uri = $parts[1] ?? $route;
            
            // Handle closure actions
            if (is_callable($action)) {
                $actionString = 'Closure';
            } else {
                $actionString = $action;
            }
            
            printf("%-10s %-30s %-30s\n", $method, $uri, $actionString);
        }
        
        echo "\n";
        $this->success("Total routes: " . count($routes));
    }
}
