<?php

namespace App\Console\Commands;

use App\Core\Debug\DebugIntegration;

/**
 * Debug Clear Command
 * 
 * Clears all debug data and resets the debugging system
 */
class DebugClearCommand
{
    public function handle($args = [])
    {
        $this->showHeader();
        
        if (!config('app.debug', false)) {
            echo "❌ Debug mode is disabled. Cannot clear debug data.\n\n";
            return;
        }

        $this->clearDebugData();
        $this->clearLogFiles();
        $this->showSuccess();
    }

    private function showHeader()
    {
        echo "\n";
        echo "🧹 Arknox Framework - Clear Debug Data\n";
        echo str_repeat("=", 50) . "\n\n";
    }

    private function clearDebugData()
    {
        echo "🔄 Clearing debug data...\n";
        
        try {
            $debugIntegration = DebugIntegration::getInstance();
            $debugIntegration->reset();
            echo "  ✅ Debug integration data cleared\n";
        } catch (\Exception $e) {
            echo "  ❌ Failed to clear debug integration data: " . $e->getMessage() . "\n";
        }
    }

    private function clearLogFiles()
    {
        echo "🗂️ Clearing log files...\n";
        
        $logPath = config('app.error_log_path', __DIR__ . '/../../../logs/error.log');
        $logDir = dirname($logPath);
        
        if (!is_dir($logDir)) {
            echo "  ⚠️ Log directory does not exist: {$logDir}\n";
            return;
        }

        $logFiles = glob($logDir . '/*.log');
        $clearedCount = 0;

        foreach ($logFiles as $logFile) {
            if (is_writable($logFile)) {
                file_put_contents($logFile, '');
                $clearedCount++;
                echo "  ✅ Cleared: " . basename($logFile) . "\n";
            } else {
                echo "  ❌ Cannot clear (not writable): " . basename($logFile) . "\n";
            }
        }

        if ($clearedCount === 0) {
            echo "  ℹ️ No log files found to clear\n";
        } else {
            echo "  📊 Cleared {$clearedCount} log file(s)\n";
        }
    }

    private function showSuccess()
    {
        echo "\n";
        echo "✅ Debug data clearing completed!\n";
        echo "\n";
        echo "📝 Summary:\n";
        echo "  - Debug component data reset\n";
        echo "  - Log files cleared\n";
        echo "  - System ready for fresh debugging session\n";
        echo "\n";
        echo "💡 Tip: Use 'arknox debug:status' to check the current debug system status\n";
        echo "\n";
    }

    public function getDescription()
    {
        return 'Clear all debug data and reset the debugging system';
    }
}
