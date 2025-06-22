<?php

namespace App\Console\Commands;

/**
 * Make Middleware Command
 * 
 * Creates a new middleware class
 */
class MakeMiddlewareCommand extends BaseCommand
{
    public function execute($arguments)
    {
        if (empty($arguments)) {
            $this->error("Middleware name is required.");
            $this->info("Usage: php console make:middleware <MiddlewareName>");
            return;
        }
        
        $middlewareName = $arguments[0];
        
        // Ensure middleware name ends with 'Middleware'
        if (!str_ends_with($middlewareName, 'Middleware')) {
            $middlewareName .= 'Middleware';
        }
        
        $middlewarePath = "app/Middleware/{$middlewareName}.php";
        
        // Check if middleware already exists
        if (file_exists($middlewarePath)) {
            $this->error("Middleware {$middlewareName} already exists!");
            return;
        }
        
        // Create middleware content
        $content = $this->getMiddlewareTemplate($middlewareName);
        
        // Write middleware file
        if (file_put_contents($middlewarePath, $content)) {
            $this->success("Middleware {$middlewareName} created successfully!");
            $this->info("Location: {$middlewarePath}");
        } else {
            $this->error("Failed to create middleware {$middlewareName}");
        }
    }
    
    private function getMiddlewareTemplate($middlewareName)
    {
        return "<?php

namespace App\Middleware;

/**
 * {$middlewareName}
 * 
 * Generated middleware class
 */
class {$middlewareName}
{
    /**
     * Handle the request
     */
    public function handle(\$request, \$next)
    {
        // Add your middleware logic here
        // This runs before the controller
        
        // Example: Check if user is authenticated
        // if (!\$this->isAuthenticated()) {
        //     return redirect('/login');
        // }
        
        // Continue to the next middleware or controller
        \$response = \$next(\$request);
        
        // Add any post-processing logic here
        // This runs after the controller
        
        return \$response;
    }
    
    /**
     * Example helper method
     */
    private function isAuthenticated()
    {
        // Add your authentication logic
        return isset(\$_SESSION['user_id']);
    }
}";
    }
}
