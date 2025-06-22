<?php

namespace App\Core;

/**
 * Performance Optimizer
 * 
 * Provides performance monitoring, optimization techniques,
 * and resource management for the application.
 */
class PerformanceOptimizer
{
    private $startTime;
    private $startMemory;
    private $checkpoints = [];
    private $queries = [];
    private $cache;
    private $config;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        $this->cache = Cache::getInstance();
        $this->config = require __DIR__ . '/../../config/app.php';
    }

    /**
     * Start performance monitoring
     */
    public function start($label = 'main')
    {
        $this->checkpoints[$label] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'end_time' => null,
            'end_memory' => null,
            'duration' => null,
            'memory_used' => null
        ];
    }

    /**
     * End performance monitoring
     */
    public function end($label = 'main')
    {
        if (!isset($this->checkpoints[$label])) {
            return false;
        }

        $checkpoint = &$this->checkpoints[$label];
        $checkpoint['end_time'] = microtime(true);
        $checkpoint['end_memory'] = memory_get_usage(true);
        $checkpoint['duration'] = $checkpoint['end_time'] - $checkpoint['start_time'];
        $checkpoint['memory_used'] = $checkpoint['end_memory'] - $checkpoint['start_memory'];

        return $checkpoint;
    }

    /**
     * Add checkpoint for performance measurement
     */
    public function checkpoint($label)
    {
        $this->checkpoints[$label] = [
            'time' => microtime(true),
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }

    /**
     * Log database query for analysis
     */
    public function logQuery($sql, $bindings = [], $duration = null)
    {
        $this->queries[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'duration' => $duration,
            'memory' => memory_get_usage(true),
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Optimize database queries
     */
    public function optimizeQueries()
    {
        $optimizations = [];

        foreach ($this->queries as $query) {
            // Check for N+1 queries
            if ($this->isNPlusOneQuery($query['sql'])) {
                $optimizations[] = [
                    'type' => 'n_plus_one',
                    'query' => $query['sql'],
                    'suggestion' => 'Consider using JOIN or eager loading'
                ];
            }

            // Check for missing WHERE clauses
            if ($this->isMissingWhereClause($query['sql'])) {
                $optimizations[] = [
                    'type' => 'missing_where',
                    'query' => $query['sql'],
                    'suggestion' => 'Add WHERE clause to limit results'
                ];
            }

            // Check for slow queries
            if ($query['duration'] && $query['duration'] > 0.1) { // 100ms threshold
                $optimizations[] = [
                    'type' => 'slow_query',
                    'query' => $query['sql'],
                    'duration' => $query['duration'],
                    'suggestion' => 'Consider adding indexes or optimizing query'
                ];
            }
        }

        return $optimizations;
    }

    /**
     * Compress output for better performance
     */
    public function enableCompression()
    {
        if (!headers_sent() && extension_loaded('zlib')) {
            if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
                ob_start('ob_gzhandler');
                return true;
            }
        }
        return false;
    }

    /**
     * Set performance headers
     */
    public function setPerformanceHeaders()
    {
        if (!headers_sent()) {
            // Enable browser caching for static assets
            $cacheTime = 86400; // 24 hours
            header("Cache-Control: public, max-age={$cacheTime}");
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
            
            // Enable keep-alive
            header('Connection: keep-alive');
            
            // Optimize for mobile
            header('Vary: Accept-Encoding, User-Agent');
        }
    }

    /**
     * Optimize images on-the-fly
     */
    public function optimizeImage($imagePath, $quality = 85, $maxWidth = 1920, $maxHeight = 1080)
    {
        if (!file_exists($imagePath)) {
            return false;
        }

        $cacheKey = 'optimized_image_' . md5($imagePath . $quality . $maxWidth . $maxHeight);
        $cachedPath = $this->cache->get($cacheKey);

        if ($cachedPath && file_exists($cachedPath)) {
            return $cachedPath;
        }

        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return false;
        }

        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];

        // Calculate new dimensions
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        if ($ratio >= 1) {
            return $imagePath; // No optimization needed
        }

        $newWidth = round($originalWidth * $ratio);
        $newHeight = round($originalHeight * $ratio);

        // Create optimized image
        $optimizedPath = $this->createOptimizedImage($imagePath, $newWidth, $newHeight, $quality, $mimeType);
        
        if ($optimizedPath) {
            $this->cache->set($cacheKey, $optimizedPath, 86400); // Cache for 24 hours
            return $optimizedPath;
        }

        return $imagePath;
    }

    /**
     * Minify CSS content
     */
    public function minifyCss($css)
    {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove unnecessary whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove unnecessary semicolons and spaces
        $css = str_replace(['; ', ' {', '{ ', ' }', '} ', ': ', ' :'], [';', '{', '{', '}', '}', ':', ':'], $css);
        
        return trim($css);
    }

    /**
     * Minify JavaScript content
     */
    public function minifyJs($js)
    {
        // Remove single-line comments (but preserve URLs)
        $js = preg_replace('/(?<!:)\/\/.*$/m', '', $js);
        
        // Remove multi-line comments
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // Remove unnecessary whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Remove spaces around operators
        $js = preg_replace('/\s*([{}();,=+\-*\/])\s*/', '$1', $js);
        
        return trim($js);
    }

    /**
     * Lazy load resources
     */
    public function generateLazyLoadHtml($src, $alt = '', $class = '', $placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')
    {
        return sprintf(
            '<img src="%s" data-src="%s" alt="%s" class="lazy-load %s" loading="lazy">',
            htmlspecialchars($placeholder),
            htmlspecialchars($src),
            htmlspecialchars($alt),
            htmlspecialchars($class)
        );
    }

    /**
     * Get performance report
     */
    public function getPerformanceReport()
    {
        $totalTime = microtime(true) - $this->startTime;
        $totalMemory = memory_get_usage(true) - $this->startMemory;
        $peakMemory = memory_get_peak_usage(true);

        return [
            'execution_time' => round($totalTime * 1000, 2), // milliseconds
            'memory_used' => $this->formatBytes($totalMemory),
            'peak_memory' => $this->formatBytes($peakMemory),
            'queries_count' => count($this->queries),
            'slow_queries' => $this->getSlowQueries(),
            'checkpoints' => $this->checkpoints,
            'optimizations' => $this->optimizeQueries(),
            'cache_stats' => $this->cache->getStats()
        ];
    }

    /**
     * Get slow queries
     */
    private function getSlowQueries()
    {
        return array_filter($this->queries, function($query) {
            return $query['duration'] && $query['duration'] > 0.1; // 100ms threshold
        });
    }

    /**
     * Check if query is N+1 pattern
     */
    private function isNPlusOneQuery($sql)
    {
        // Simple heuristic: SELECT queries with WHERE id = ? pattern
        return preg_match('/SELECT.*WHERE.*id\s*=\s*\?/i', $sql);
    }

    /**
     * Check if query is missing WHERE clause
     */
    private function isMissingWhereClause($sql)
    {
        // Check for SELECT/UPDATE/DELETE without WHERE
        return preg_match('/^(SELECT|UPDATE|DELETE)(?!.*WHERE)/i', trim($sql));
    }

    /**
     * Create optimized image
     */
    private function createOptimizedImage($sourcePath, $newWidth, $newHeight, $quality, $mimeType)
    {
        $optimizedDir = dirname($sourcePath) . '/optimized';
        if (!is_dir($optimizedDir)) {
            mkdir($optimizedDir, 0755, true);
        }

        $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $optimizedPath = $optimizedDir . '/' . $filename . '_' . $newWidth . 'x' . $newHeight . '.' . $extension;

        // Create image resource based on type
        switch ($mimeType) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }

        if (!$source) {
            return false;
        }

        // Create new image
        $optimized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($optimized, false);
            imagesavealpha($optimized, true);
            $transparent = imagecolorallocatealpha($optimized, 255, 255, 255, 127);
            imagefill($optimized, 0, 0, $transparent);
        }

        // Resize image
        imagecopyresampled($optimized, $source, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($source), imagesy($source));

        // Save optimized image
        $success = false;
        switch ($mimeType) {
            case 'image/jpeg':
                $success = imagejpeg($optimized, $optimizedPath, $quality);
                break;
            case 'image/png':
                $success = imagepng($optimized, $optimizedPath, round(9 * (100 - $quality) / 100));
                break;
            case 'image/gif':
                $success = imagegif($optimized, $optimizedPath);
                break;
        }

        // Clean up
        imagedestroy($source);
        imagedestroy($optimized);

        return $success ? $optimizedPath : false;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Enable performance monitoring in debug mode
     */
    public function enableDebugMode()
    {
        if ($this->config['debug']) {
            register_shutdown_function(function() {
                $report = $this->getPerformanceReport();
                
                if ($this->config['performance_debug']) {
                    echo "<!-- Performance Report: " . json_encode($report) . " -->";
                }
            });
        }
    }

    /**
     * Static helper to get optimizer instance
     */
    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new static();
        }
        return $instance;
    }
}
