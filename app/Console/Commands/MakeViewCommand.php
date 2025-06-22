<?php

namespace App\Console\Commands;

/**
 * Make View Command
 * 
 * Creates a new view file
 */
class MakeViewCommand extends BaseCommand
{
    public function execute($arguments)
    {
        if (empty($arguments)) {
            $this->error("View name is required.");
            $this->info("Usage: php console make:view <view/name>");
            return;
        }
        
        $viewName = $arguments[0];
        $viewPath = "app/Views/{$viewName}.php";
        
        // Create directory if it doesn't exist
        $viewDir = dirname($viewPath);
        if (!is_dir($viewDir)) {
            if (!mkdir($viewDir, 0755, true)) {
                $this->error("Failed to create directory: {$viewDir}");
                return;
            }
        }
        
        // Check if view already exists
        if (file_exists($viewPath)) {
            $this->error("View {$viewName} already exists!");
            return;
        }
        
        // Create view content
        $content = $this->getViewTemplate($viewName);
        
        // Write view file
        if (file_put_contents($viewPath, $content)) {
            $this->success("View {$viewName} created successfully!");
            $this->info("Location: {$viewPath}");
        } else {
            $this->error("Failed to create view {$viewName}");
        }
    }
    
    private function getViewTemplate($viewName)
    {
        $title = ucwords(str_replace(['/', '_', '-'], ' ', $viewName));
        
        return "<?php
/**
 * {$title} View
 * 
 * Generated view file
 */
?>

<div class=\"container\">
    <div class=\"row\">
        <div class=\"col-12\">
            <h1><?= htmlspecialchars(\$title ?? '{$title}') ?></h1>
            
            <div class=\"content\">
                <p>Welcome to the {$title} page!</p>
                
                <!-- Add your content here -->
                
            </div>
        </div>
    </div>
</div>

<style>
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.row {
    display: flex;
    flex-wrap: wrap;
    margin: -10px;
}

.col-12 {
    flex: 0 0 100%;
    padding: 10px;
}

h1 {
    color: #333;
    margin-bottom: 20px;
}

.content {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}
</style>";
    }
}
