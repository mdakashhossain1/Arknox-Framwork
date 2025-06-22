<?php

namespace App\Console\Commands;

/**
 * Cache Clear Command
 * 
 * Clear application cache
 */
class CacheClearCommand extends BaseCommand
{
    public function execute($arguments)
    {
        $cacheDir = 'cache';
        
        if (!is_dir($cacheDir)) {
            $this->warning("Cache directory does not exist.");
            return;
        }
        
        $this->info("Clearing application cache...");
        
        $files = glob($cacheDir . '/*');
        $clearedCount = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if (unlink($file)) {
                    $clearedCount++;
                }
            }
        }
        
        if ($clearedCount > 0) {
            $this->success("Cache cleared successfully! Removed {$clearedCount} file(s).");
        } else {
            $this->info("Cache was already empty.");
        }
    }
}
