<?php

namespace App\Core\Console;

/**
 * Base Console Command Class
 * 
 * Abstract base class for all Arknox console commands
 * Provides Laravel-style command functionality
 */
abstract class Command
{
    /**
     * The command signature
     */
    protected $signature = '';
    
    /**
     * The command description
     */
    protected $description = '';
    
    /**
     * Execute the command
     */
    abstract public function handle();
    
    /**
     * Execute the command (compatibility method)
     */
    public function execute($arguments = [])
    {
        return $this->handle();
    }
    
    /**
     * Write a string as information output
     */
    protected function info($string)
    {
        $this->line($string, 'info');
    }
    
    /**
     * Write a string as standard output
     */
    protected function line($string, $style = null)
    {
        $styled = $this->formatOutput($string, $style);
        echo $styled . "\n";
    }
    
    /**
     * Write a string as comment output
     */
    protected function comment($string)
    {
        $this->line($string, 'comment');
    }
    
    /**
     * Write a string as question output
     */
    protected function question($string)
    {
        $this->line($string, 'question');
    }
    
    /**
     * Write a string as error output
     */
    protected function error($string)
    {
        $this->line($string, 'error');
    }
    
    /**
     * Write a string as warning output
     */
    protected function warn($string)
    {
        $this->line($string, 'warning');
    }
    
    /**
     * Write a string as alert output
     */
    protected function alert($string)
    {
        $this->line($string, 'alert');
    }
    
    /**
     * Format output with colors
     */
    protected function formatOutput($string, $style = null)
    {
        // Handle inline color tags like <green>text</green>
        $string = preg_replace_callback('/<(\w+)>(.*?)<\/\1>/', function($matches) {
            $color = $matches[1];
            $text = $matches[2];
            return $this->colorize($text, $color);
        }, $string);
        
        // Apply overall style
        if ($style) {
            return $this->colorize($string, $style);
        }
        
        return $string;
    }
    
    /**
     * Colorize text
     */
    protected function colorize($text, $color)
    {
        $colors = [
            'black' => '0;30',
            'dark_gray' => '1;30',
            'blue' => '0;34',
            'light_blue' => '1;34',
            'green' => '0;32',
            'light_green' => '1;32',
            'cyan' => '0;36',
            'light_cyan' => '1;36',
            'red' => '0;31',
            'light_red' => '1;31',
            'purple' => '0;35',
            'light_purple' => '1;35',
            'brown' => '0;33',
            'yellow' => '1;33',
            'light_gray' => '0;37',
            'white' => '1;37',
            
            // Style aliases
            'info' => '0;34',      // blue
            'comment' => '0;33',   // brown/yellow
            'question' => '0;35',  // purple
            'error' => '0;31',     // red
            'warning' => '1;33',   // yellow
            'alert' => '1;31',     // light red
        ];
        
        if (isset($colors[$color])) {
            return "\033[" . $colors[$color] . "m" . $text . "\033[0m";
        }
        
        return $text;
    }
    
    /**
     * Prompt the user for input
     */
    protected function ask($question, $default = null)
    {
        $prompt = $question;
        if ($default !== null) {
            $prompt .= " [" . $default . "]";
        }
        $prompt .= ": ";
        
        echo $prompt;
        $input = trim(fgets(STDIN));
        
        return empty($input) ? $default : $input;
    }
    
    /**
     * Prompt the user for input with validation
     */
    protected function askWithValidation($question, $validator, $attempts = 3)
    {
        for ($i = 0; $i < $attempts; $i++) {
            $answer = $this->ask($question);
            
            if ($validator($answer)) {
                return $answer;
            }
            
            $this->error("Invalid input. Please try again.");
        }
        
        throw new \RuntimeException("Maximum attempts exceeded.");
    }
    
    /**
     * Confirm a question with the user
     */
    protected function confirm($question, $default = false)
    {
        $prompt = $question . " (yes/no)";
        if ($default) {
            $prompt .= " [yes]";
        } else {
            $prompt .= " [no]";
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
     * Prompt the user to select from a list of options
     */
    protected function choice($question, array $choices, $default = null)
    {
        $this->line($question);
        
        foreach ($choices as $key => $choice) {
            $this->line("  [" . $key . "] " . $choice);
        }
        
        $answer = $this->ask("Please select an option", $default);
        
        if (isset($choices[$answer])) {
            return $answer;
        }
        
        $this->error("Invalid choice.");
        return $this->choice($question, $choices, $default);
    }
    
    /**
     * Display a table
     */
    protected function table(array $headers, array $rows)
    {
        // Calculate column widths
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }
        
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], strlen($cell));
            }
        }
        
        // Display headers
        $this->line('');
        $headerLine = '| ';
        foreach ($headers as $i => $header) {
            $headerLine .= str_pad($header, $widths[$i]) . ' | ';
        }
        $this->line($headerLine);
        
        // Display separator
        $separator = '|';
        foreach ($widths as $width) {
            $separator .= str_repeat('-', $width + 2) . '|';
        }
        $this->line($separator);
        
        // Display rows
        foreach ($rows as $row) {
            $rowLine = '| ';
            foreach ($row as $i => $cell) {
                $rowLine .= str_pad($cell, $widths[$i]) . ' | ';
            }
            $this->line($rowLine);
        }
        $this->line('');
    }
    
    /**
     * Get the command signature
     */
    public function getSignature()
    {
        return $this->signature;
    }
    
    /**
     * Get the command description
     */
    public function getDescription()
    {
        return $this->description;
    }
}
