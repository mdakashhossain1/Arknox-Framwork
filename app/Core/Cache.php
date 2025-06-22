<?php

namespace App\Core;

/**
 * Cache Manager
 * 
 * Provides file-based caching with automatic expiration,
 * cache invalidation, and performance optimization.
 */
class Cache
{
    private $cacheDir;
    private $defaultTtl;
    private $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/app.php';
        $this->cacheDir = $this->config['cache_path'] ?? __DIR__ . '/../../cache';
        $this->defaultTtl = $this->config['cache_ttl'] ?? 3600; // 1 hour default
        
        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Get cached value
     */
    public function get($key, $default = null)
    {
        $cacheFile = $this->getCacheFile($key);
        
        if (!file_exists($cacheFile)) {
            return $default;
        }

        $data = json_decode(file_get_contents($cacheFile), true);
        
        if (!$data || !isset($data['expires']) || $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * Set cached value
     */
    public function set($key, $value, $ttl = null)
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $cacheFile = $this->getCacheFile($key);
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];

        return file_put_contents($cacheFile, json_encode($data)) !== false;
    }

    /**
     * Check if key exists and is not expired
     */
    public function has($key)
    {
        return $this->get($key) !== null;
    }

    /**
     * Delete cached value
     */
    public function delete($key)
    {
        $cacheFile = $this->getCacheFile($key);
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return true;
    }

    /**
     * Clear all cache
     */
    public function clear()
    {
        $files = glob($this->cacheDir . '/*.cache');
        $cleared = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $cleared++;
            }
        }
        
        return $cleared;
    }

    /**
     * Get or set cached value (cache-aside pattern)
     */
    public function remember($key, $callback, $ttl = null)
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    /**
     * Get multiple cached values
     */
    public function getMultiple(array $keys, $default = null)
    {
        $result = [];
        
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        
        return $result;
    }

    /**
     * Set multiple cached values
     */
    public function setMultiple(array $values, $ttl = null)
    {
        $success = true;
        
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Delete multiple cached values
     */
    public function deleteMultiple(array $keys)
    {
        $success = true;
        
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Increment cached numeric value
     */
    public function increment($key, $step = 1, $ttl = null)
    {
        $value = (int) $this->get($key, 0);
        $newValue = $value + $step;
        $this->set($key, $newValue, $ttl);
        
        return $newValue;
    }

    /**
     * Decrement cached numeric value
     */
    public function decrement($key, $step = 1, $ttl = null)
    {
        return $this->increment($key, -$step, $ttl);
    }

    /**
     * Cache database query results
     */
    public function cacheQuery($sql, $bindings, $callback, $ttl = null)
    {
        $key = 'query_' . md5($sql . serialize($bindings));
        
        return $this->remember($key, $callback, $ttl ?? 300); // 5 minutes default for queries
    }

    /**
     * Cache view rendering
     */
    public function cacheView($template, $data, $callback, $ttl = null)
    {
        $key = 'view_' . md5($template . serialize($data));
        
        return $this->remember($key, $callback, $ttl ?? 1800); // 30 minutes default for views
    }

    /**
     * Cache API responses
     */
    public function cacheApi($endpoint, $params, $callback, $ttl = null)
    {
        $key = 'api_' . md5($endpoint . serialize($params));
        
        return $this->remember($key, $callback, $ttl ?? 600); // 10 minutes default for API
    }

    /**
     * Invalidate cache by pattern
     */
    public function invalidatePattern($pattern)
    {
        $files = glob($this->cacheDir . '/*.cache');
        $invalidated = 0;
        
        foreach ($files as $file) {
            $filename = basename($file, '.cache');
            if (fnmatch($pattern, $filename)) {
                if (unlink($file)) {
                    $invalidated++;
                }
            }
        }
        
        return $invalidated;
    }

    /**
     * Clean expired cache entries
     */
    public function cleanExpired()
    {
        $files = glob($this->cacheDir . '/*.cache');
        $cleaned = 0;
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            
            if (!$data || !isset($data['expires']) || $data['expires'] < time()) {
                if (unlink($file)) {
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }

    /**
     * Get cache statistics
     */
    public function getStats()
    {
        $files = glob($this->cacheDir . '/*.cache');
        $totalSize = 0;
        $totalFiles = count($files);
        $expiredFiles = 0;
        $oldestFile = null;
        $newestFile = null;
        
        foreach ($files as $file) {
            $size = filesize($file);
            $totalSize += $size;
            
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['expires']) && $data['expires'] < time()) {
                $expiredFiles++;
            }
            
            $mtime = filemtime($file);
            if ($oldestFile === null || $mtime < $oldestFile) {
                $oldestFile = $mtime;
            }
            if ($newestFile === null || $mtime > $newestFile) {
                $newestFile = $mtime;
            }
        }
        
        return [
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'expired_files' => $expiredFiles,
            'hit_ratio' => $this->getHitRatio(),
            'oldest_file' => $oldestFile ? date('Y-m-d H:i:s', $oldestFile) : null,
            'newest_file' => $newestFile ? date('Y-m-d H:i:s', $newestFile) : null,
            'cache_dir' => $this->cacheDir
        ];
    }

    /**
     * Get cache file path
     */
    private function getCacheFile($key)
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->cacheDir . '/' . $safeKey . '.cache';
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
     * Get cache hit ratio (simplified)
     */
    private function getHitRatio()
    {
        // This is a simplified implementation
        // In production, you'd track hits/misses more accurately
        $statsFile = $this->cacheDir . '/stats.json';
        
        if (file_exists($statsFile)) {
            $stats = json_decode(file_get_contents($statsFile), true);
            if ($stats && isset($stats['hits']) && isset($stats['misses'])) {
                $total = $stats['hits'] + $stats['misses'];
                return $total > 0 ? round(($stats['hits'] / $total) * 100, 2) : 0;
            }
        }
        
        return 0;
    }

    /**
     * Track cache hit
     */
    public function trackHit()
    {
        $this->updateStats('hits');
    }

    /**
     * Track cache miss
     */
    public function trackMiss()
    {
        $this->updateStats('misses');
    }

    /**
     * Update cache statistics
     */
    private function updateStats($type)
    {
        $statsFile = $this->cacheDir . '/stats.json';
        $stats = ['hits' => 0, 'misses' => 0];
        
        if (file_exists($statsFile)) {
            $existingStats = json_decode(file_get_contents($statsFile), true);
            if ($existingStats) {
                $stats = array_merge($stats, $existingStats);
            }
        }
        
        $stats[$type]++;
        file_put_contents($statsFile, json_encode($stats));
    }

    /**
     * Static helper to get cache instance
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
