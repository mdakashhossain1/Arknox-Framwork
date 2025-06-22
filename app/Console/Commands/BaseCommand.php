<?php

namespace App\Console\Commands;

/**
 * Base Command Class
 * 
 * Abstract base class for all console commands
 */
abstract class BaseCommand
{
    /**
     * Execute the command
     */
    abstract public function execute($arguments);
    
    /**
     * Output success message
     */
    protected function success($message)
    {
        echo "\033[32m✓ {$message}\033[0m\n";
    }
    
    /**
     * Output error message
     */
    protected function error($message)
    {
        echo "\033[31m✗ {$message}\033[0m\n";
    }
    
    /**
     * Output info message
     */
    protected function info($message)
    {
        echo "\033[34mℹ {$message}\033[0m\n";
    }
    
    /**
     * Output warning message
     */
    protected function warning($message)
    {
        echo "\033[33m⚠ {$message}\033[0m\n";
    }
    
    /**
     * Ask user for input
     */
    protected function ask($question, $default = null)
    {
        $prompt = $question;
        if ($default) {
            $prompt .= " [{$default}]";
        }
        $prompt .= ": ";
        
        echo $prompt;
        $input = trim(fgets(STDIN));
        
        return empty($input) ? $default : $input;
    }
    
    /**
     * Ask user for confirmation
     */
    protected function confirm($question, $default = false)
    {
        $prompt = $question . " (y/n)";
        if ($default) {
            $prompt .= " [y]";
        } else {
            $prompt .= " [n]";
        }
        $prompt .= ": ";
        
        echo $prompt;
        $input = trim(fgets(STDIN));
        
        if (empty($input)) {
            return $default;
        }
        
        return in_array(strtolower($input), ['y', 'yes', '1', 'true']);
    }
    
    /**
     * Create directory if it doesn't exist
     */
    protected function ensureDirectoryExists($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
            return true;
        }
        return false;
    }
    
    /**
     * Get stub content
     */
    protected function getStub($stubName)
    {
        $stubPath = __DIR__ . '/stubs/' . $stubName . '.stub';
        if (file_exists($stubPath)) {
            return file_get_contents($stubPath);
        }
        return null;
    }
    
    /**
     * Replace placeholders in stub content
     */
    protected function replacePlaceholders($content, $replacements)
    {
        foreach ($replacements as $placeholder => $value) {
            $content = str_replace('{{' . $placeholder . '}}', $value, $content);
        }
        return $content;
    }
}
