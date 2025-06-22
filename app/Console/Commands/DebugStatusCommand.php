<?php

namespace App\Console\Commands;

use App\Core\Debug\DebugIntegration;
use App\Core\Debug\AdvancedErrorHandler;
use App\Core\Debug\DataFlowTracker;
use App\Core\Debug\RouteDebugger;
use App\Core\Debug\DatabaseDebugger;
use App\Core\Debug\MvcFlowVisualizer;

/**
 * Debug Status Command
 * 
 * Shows the current status of the debugging system
 */
class DebugStatusCommand
{
    public function handle($args = [])
    {
        $this->showHeader();
        $this->showDebugStatus();
        $this->showComponentStatus();
        $this->showConfiguration();
        $this->showStatistics();
    }

    private function showHeader()
    {
        echo "\n";
        echo "🐛 Arknox Framework - Advanced Debug System Status\n";
        echo str_repeat("=", 60) . "\n\n";
    }

    private function showDebugStatus()
    {
        $debugEnabled = config('app.debug', false);
        $environment = config('app.environment', 'unknown');
        
        echo "📊 System Status:\n";
        echo "  Debug Mode: " . ($debugEnabled ? "✅ Enabled" : "❌ Disabled") . "\n";
        echo "  Environment: " . ucfirst($environment) . "\n";
        echo "  PHP Version: " . PHP_VERSION . "\n";
        echo "  Memory Limit: " . ini_get('memory_limit') . "\n";
        echo "  Max Execution Time: " . ini_get('max_execution_time') . "s\n";
        echo "\n";
    }

    private function showComponentStatus()
    {
        echo "🔧 Debug Components:\n";
        
        $components = [
            'Error Handler' => AdvancedErrorHandler::getInstance()->isEnabled(),
            'Data Flow Tracker' => DataFlowTracker::getInstance()->isEnabled(),
            'Route Debugger' => RouteDebugger::getInstance()->isEnabled(),
            'Database Debugger' => DatabaseDebugger::getInstance()->isEnabled(),
            'MVC Flow Visualizer' => MvcFlowVisualizer::getInstance()->isEnabled(),
            'Debug Integration' => DebugIntegration::getInstance()->isEnabled()
        ];

        foreach ($components as $name => $enabled) {
            $status = $enabled ? "✅ Active" : "❌ Inactive";
            echo "  {$name}: {$status}\n";
        }
        echo "\n";
    }

    private function showConfiguration()
    {
        echo "⚙️ Configuration:\n";
        
        $configs = [
            'Interface Enabled' => config('app.debug_interface_enabled', true),
            'Data Flow Tracking' => config('app.debug_data_flow_tracking', true),
            'Route Tracking' => config('app.debug_route_tracking', true),
            'Database Tracking' => config('app.debug_database_tracking', true),
            'MVC Flow Visualization' => config('app.debug_mvc_flow_visualization', true),
            'Performance Monitoring' => config('app.debug_performance_monitoring', true),
            'Error Context Capture' => config('app.debug_error_context_capture', true),
            'Max Query Log' => config('app.debug_max_query_log', 100),
            'Slow Query Threshold' => config('app.debug_slow_query_threshold', 0.1) . 's',
            'Memory Threshold' => $this->formatBytes(config('app.debug_memory_threshold', 50 * 1024 * 1024)),
            'Execution Time Threshold' => config('app.debug_execution_time_threshold', 1.0) . 's'
        ];

        foreach ($configs as $name => $value) {
            if (is_bool($value)) {
                $value = $value ? "✅ Enabled" : "❌ Disabled";
            }
            echo "  {$name}: {$value}\n";
        }
        echo "\n";
    }

    private function showStatistics()
    {
        if (!config('app.debug', false)) {
            echo "📈 Statistics: Debug mode is disabled\n\n";
            return;
        }

        echo "📈 Current Session Statistics:\n";
        
        $debugIntegration = DebugIntegration::getInstance();
        $summary = $debugIntegration->getDebugSummary();

        if ($summary['enabled']) {
            echo "  MVC Flow Status: " . ucfirst($summary['mvc_flow']['status'] ?? 'unknown') . "\n";
            echo "  Completed Steps: " . ($summary['mvc_flow']['completed_steps'] ?? 0) . "/" . ($summary['mvc_flow']['total_steps'] ?? 0) . "\n";
            echo "  Database Queries: " . ($summary['database_stats']['total_queries'] ?? 0) . "\n";
            echo "  Data Flow Steps: " . ($summary['data_flow']['total_steps'] ?? 0) . "\n";
            echo "  Errors: " . ($summary['errors'] ?? 0) . "\n";
            echo "  Exceptions: " . ($summary['exceptions'] ?? 0) . "\n";
        } else {
            echo "  No statistics available (debug integration not active)\n";
        }
        echo "\n";
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    public function getDescription()
    {
        return 'Show the current status of the advanced debugging system';
    }
}
