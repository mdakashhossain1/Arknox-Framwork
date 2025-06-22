<?php

namespace App\Console\Commands;

use App\Core\Debug\DebugIntegration;
use App\Core\Debug\AdvancedErrorHandler;
use App\Core\Debug\DataFlowTracker;
use App\Core\Debug\RouteDebugger;
use App\Core\Debug\DatabaseDebugger;
use App\Core\Debug\MvcFlowVisualizer;

/**
 * Debug Report Command
 * 
 * Generates comprehensive debug reports
 */
class DebugReportCommand
{
    public function handle($args = [])
    {
        $format = $args[0] ?? 'console';
        $outputFile = $args[1] ?? null;

        $this->showHeader();
        
        if (!config('app.debug', false)) {
            echo "❌ Debug mode is disabled. Cannot generate debug report.\n\n";
            return;
        }

        switch ($format) {
            case 'json':
                $this->generateJsonReport($outputFile);
                break;
            case 'html':
                $this->generateHtmlReport($outputFile);
                break;
            case 'console':
            default:
                $this->generateConsoleReport();
                break;
        }
    }

    private function showHeader()
    {
        echo "\n";
        echo "📊 Arknox Framework - Debug Report Generator\n";
        echo str_repeat("=", 55) . "\n\n";
    }

    private function generateConsoleReport()
    {
        echo "📋 Console Debug Report\n";
        echo str_repeat("-", 30) . "\n\n";

        $this->showSystemInfo();
        $this->showDebugSummary();
        $this->showPerformanceMetrics();
        $this->showDatabaseStats();
        $this->showErrorSummary();
        $this->showRecommendations();
    }

    private function generateJsonReport($outputFile)
    {
        echo "📄 Generating JSON debug report...\n";

        $report = $this->collectAllDebugData();
        $json = json_encode($report, JSON_PRETTY_PRINT);

        if ($outputFile) {
            $filePath = $this->getOutputPath($outputFile, 'json');
            file_put_contents($filePath, $json);
            echo "✅ JSON report saved to: {$filePath}\n";
        } else {
            echo "\n" . $json . "\n";
        }
    }

    private function generateHtmlReport($outputFile)
    {
        echo "🌐 Generating HTML debug report...\n";

        $report = $this->collectAllDebugData();
        $html = $this->generateHtmlContent($report);

        if ($outputFile) {
            $filePath = $this->getOutputPath($outputFile, 'html');
            file_put_contents($filePath, $html);
            echo "✅ HTML report saved to: {$filePath}\n";
        } else {
            echo "❌ HTML format requires an output file. Please specify a filename.\n";
        }
    }

    private function collectAllDebugData()
    {
        $debugIntegration = DebugIntegration::getInstance();
        
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'system_info' => [
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'environment' => config('app.environment', 'unknown')
            ],
            'debug_summary' => $debugIntegration->getDebugSummary(),
            'error_handler' => AdvancedErrorHandler::getInstance()->getDebugData(),
            'data_flow' => DataFlowTracker::getInstance()->getDataFlow(),
            'routes' => RouteDebugger::getInstance()->getRouteDebugInfo(),
            'database' => DatabaseDebugger::getInstance()->getDatabaseDebugInfo(),
            'mvc_flow' => MvcFlowVisualizer::getInstance()->getFlowData()
        ];
    }

    private function showSystemInfo()
    {
        echo "🖥️ System Information:\n";
        echo "  PHP Version: " . PHP_VERSION . "\n";
        echo "  Memory Limit: " . ini_get('memory_limit') . "\n";
        echo "  Max Execution Time: " . ini_get('max_execution_time') . "s\n";
        echo "  Environment: " . config('app.environment', 'unknown') . "\n";
        echo "  Debug Mode: " . (config('app.debug', false) ? 'Enabled' : 'Disabled') . "\n";
        echo "\n";
    }

    private function showDebugSummary()
    {
        echo "📊 Debug Summary:\n";
        
        $debugIntegration = DebugIntegration::getInstance();
        $summary = $debugIntegration->getDebugSummary();

        if ($summary['enabled']) {
            echo "  Status: ✅ Active\n";
            echo "  MVC Flow: " . ucfirst($summary['mvc_flow']['status'] ?? 'unknown') . "\n";
            echo "  Completed Steps: " . ($summary['mvc_flow']['completed_steps'] ?? 0) . "/" . ($summary['mvc_flow']['total_steps'] ?? 0) . "\n";
            echo "  Errors: " . ($summary['errors'] ?? 0) . "\n";
            echo "  Exceptions: " . ($summary['exceptions'] ?? 0) . "\n";
        } else {
            echo "  Status: ❌ Inactive\n";
        }
        echo "\n";
    }

    private function showPerformanceMetrics()
    {
        echo "⚡ Performance Metrics:\n";
        
        $mvcFlow = MvcFlowVisualizer::getInstance()->getFlowSummary();
        
        echo "  Total Duration: " . number_format($mvcFlow['total_duration'] * 1000, 2) . "ms\n";
        echo "  Memory Usage: " . $this->formatBytes(memory_get_usage()) . "\n";
        echo "  Peak Memory: " . $this->formatBytes(memory_get_peak_usage()) . "\n";
        
        if ($mvcFlow['bottleneck_step']) {
            echo "  Bottleneck: " . $mvcFlow['bottleneck_step']['name'] . " (" . 
                 number_format($mvcFlow['bottleneck_step']['duration'] * 1000, 2) . "ms)\n";
        }
        echo "\n";
    }

    private function showDatabaseStats()
    {
        echo "🗄️ Database Statistics:\n";
        
        $dbStats = DatabaseDebugger::getInstance()->getDatabaseDebugInfo();
        $summary = $dbStats['summary'] ?? [];
        
        echo "  Total Queries: " . ($summary['total_queries'] ?? 0) . "\n";
        echo "  Total Time: " . number_format(($summary['total_execution_time'] ?? 0) * 1000, 2) . "ms\n";
        echo "  Average Time: " . number_format(($summary['average_query_time'] ?? 0) * 1000, 2) . "ms\n";
        echo "  Slow Queries: " . count($summary['performance_issues'] ?? []) . "\n";
        echo "\n";
    }

    private function showErrorSummary()
    {
        echo "🚨 Error Summary:\n";
        
        $errorData = AdvancedErrorHandler::getInstance()->getDebugData();
        
        echo "  Exceptions: " . count($errorData['exceptions'] ?? []) . "\n";
        echo "  PHP Errors: " . count($errorData['errors'] ?? []) . "\n";
        
        if (!empty($errorData['exceptions'])) {
            echo "  Latest Exception: " . ($errorData['exceptions'][0]['class'] ?? 'Unknown') . "\n";
        }
        echo "\n";
    }

    private function showRecommendations()
    {
        echo "💡 Recommendations:\n";
        
        $recommendations = [];
        
        // Check performance
        $executionTime = microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        if ($executionTime > 1.0) {
            $recommendations[] = "Consider optimizing slow operations (execution time: " . 
                               number_format($executionTime * 1000, 2) . "ms)";
        }
        
        // Check memory usage
        $memoryUsage = memory_get_peak_usage();
        if ($memoryUsage > 50 * 1024 * 1024) {
            $recommendations[] = "High memory usage detected (" . $this->formatBytes($memoryUsage) . ")";
        }
        
        // Check database queries
        $dbStats = DatabaseDebugger::getInstance()->getDatabaseDebugInfo();
        if (count($dbStats['queries'] ?? []) > 20) {
            $recommendations[] = "High number of database queries (" . count($dbStats['queries']) . ")";
        }
        
        if (empty($recommendations)) {
            echo "  ✅ No performance issues detected\n";
        } else {
            foreach ($recommendations as $recommendation) {
                echo "  ⚠️ {$recommendation}\n";
            }
        }
        echo "\n";
    }

    private function generateHtmlContent($report)
    {
        return '<!DOCTYPE html>
<html>
<head>
    <title>Arknox Framework Debug Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #1e293b; color: white; padding: 20px; border-radius: 8px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
        .metric { display: inline-block; margin: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🐛 Arknox Framework Debug Report</h1>
        <p>Generated on: ' . $report['timestamp'] . '</p>
    </div>
    
    <div class="section">
        <h2>System Information</h2>
        <div class="metric">PHP: ' . $report['system_info']['php_version'] . '</div>
        <div class="metric">Environment: ' . $report['system_info']['environment'] . '</div>
        <div class="metric">Memory Limit: ' . $report['system_info']['memory_limit'] . '</div>
    </div>
    
    <div class="section">
        <h2>Debug Data</h2>
        <pre>' . htmlspecialchars(json_encode($report, JSON_PRETTY_PRINT)) . '</pre>
    </div>
</body>
</html>';
    }

    private function getOutputPath($filename, $extension)
    {
        $logsDir = dirname(config('app.error_log_path', __DIR__ . '/../../../logs/error.log'));
        
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        return $logsDir . '/' . $filename . '_' . date('Y-m-d_H-i-s') . '.' . $extension;
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
        return 'Generate comprehensive debug reports (formats: console, json, html)';
    }
}
