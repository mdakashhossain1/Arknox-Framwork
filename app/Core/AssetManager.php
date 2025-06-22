<?php

namespace App\Core;

/**
 * Asset Manager
 * 
 * Manages CSS and JavaScript assets with minification,
 * concatenation, and caching for optimal performance.
 */
class AssetManager
{
    private $assets = [
        'css' => [],
        'js' => []
    ];
    private $cache;
    private $config;
    private $assetPath;
    private $publicPath;

    public function __construct()
    {
        $this->cache = Cache::getInstance();
        $this->config = require __DIR__ . '/../../config/app.php';
        $this->assetPath = __DIR__ . '/../../assets';
        $this->publicPath = __DIR__ . '/../../public/assets';
        
        // Ensure public assets directory exists
        if (!is_dir($this->publicPath)) {
            mkdir($this->publicPath, 0755, true);
        }
    }

    /**
     * Add CSS file
     */
    public function addCss($file, $priority = 10, $media = 'all')
    {
        $this->assets['css'][] = [
            'file' => $file,
            'priority' => $priority,
            'media' => $media,
            'type' => 'file'
        ];
        
        return $this;
    }

    /**
     * Add inline CSS
     */
    public function addInlineCss($css, $priority = 10)
    {
        $this->assets['css'][] = [
            'content' => $css,
            'priority' => $priority,
            'type' => 'inline'
        ];
        
        return $this;
    }

    /**
     * Add JavaScript file
     */
    public function addJs($file, $priority = 10, $defer = false, $async = false)
    {
        $this->assets['js'][] = [
            'file' => $file,
            'priority' => $priority,
            'defer' => $defer,
            'async' => $async,
            'type' => 'file'
        ];
        
        return $this;
    }

    /**
     * Add inline JavaScript
     */
    public function addInlineJs($js, $priority = 10)
    {
        $this->assets['js'][] = [
            'content' => $js,
            'priority' => $priority,
            'type' => 'inline'
        ];
        
        return $this;
    }

    /**
     * Render CSS assets
     */
    public function renderCss($minify = true, $combine = true)
    {
        $cssAssets = $this->assets['css'];
        
        // Sort by priority
        usort($cssAssets, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        $output = '';

        if ($combine && $this->config['asset_optimization']) {
            $output .= $this->renderCombinedCss($cssAssets, $minify);
        } else {
            $output .= $this->renderIndividualCss($cssAssets, $minify);
        }

        return $output;
    }

    /**
     * Render JavaScript assets
     */
    public function renderJs($minify = true, $combine = true)
    {
        $jsAssets = $this->assets['js'];
        
        // Sort by priority
        usort($jsAssets, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        $output = '';

        if ($combine && $this->config['asset_optimization']) {
            $output .= $this->renderCombinedJs($jsAssets, $minify);
        } else {
            $output .= $this->renderIndividualJs($jsAssets, $minify);
        }

        return $output;
    }

    /**
     * Render combined CSS
     */
    private function renderCombinedCss($assets, $minify)
    {
        $cacheKey = 'combined_css_' . md5(serialize($assets) . ($minify ? '1' : '0'));
        $cachedUrl = $this->cache->get($cacheKey);

        if ($cachedUrl && file_exists($this->publicPath . '/' . $cachedUrl)) {
            return '<link rel="stylesheet" href="' . $this->getAssetUrl($cachedUrl) . '">' . "\n";
        }

        $combinedCss = '';
        $inlineStyles = '';

        foreach ($assets as $asset) {
            if ($asset['type'] === 'file') {
                $filePath = $this->resolveAssetPath($asset['file']);
                if (file_exists($filePath)) {
                    $css = file_get_contents($filePath);
                    $css = $this->processCssUrls($css, dirname($asset['file']));
                    $combinedCss .= $css . "\n";
                }
            } else {
                $inlineStyles .= $asset['content'] . "\n";
            }
        }

        if ($minify) {
            $optimizer = new PerformanceOptimizer();
            $combinedCss = $optimizer->minifyCss($combinedCss);
            $inlineStyles = $optimizer->minifyCss($inlineStyles);
        }

        $output = '';

        // Save combined CSS file
        if (!empty($combinedCss)) {
            $filename = 'combined_' . md5($combinedCss) . '.css';
            $filePath = $this->publicPath . '/' . $filename;
            file_put_contents($filePath, $combinedCss);
            
            $this->cache->set($cacheKey, $filename, 86400); // Cache for 24 hours
            $output .= '<link rel="stylesheet" href="' . $this->getAssetUrl($filename) . '">' . "\n";
        }

        // Add inline styles
        if (!empty($inlineStyles)) {
            $output .= '<style>' . $inlineStyles . '</style>' . "\n";
        }

        return $output;
    }

    /**
     * Render combined JavaScript
     */
    private function renderCombinedJs($assets, $minify)
    {
        $cacheKey = 'combined_js_' . md5(serialize($assets) . ($minify ? '1' : '0'));
        $cachedUrl = $this->cache->get($cacheKey);

        if ($cachedUrl && file_exists($this->publicPath . '/' . $cachedUrl)) {
            return '<script src="' . $this->getAssetUrl($cachedUrl) . '"></script>' . "\n";
        }

        $combinedJs = '';
        $inlineScripts = '';

        foreach ($assets as $asset) {
            if ($asset['type'] === 'file') {
                $filePath = $this->resolveAssetPath($asset['file']);
                if (file_exists($filePath)) {
                    $js = file_get_contents($filePath);
                    $combinedJs .= $js . ";\n";
                }
            } else {
                $inlineScripts .= $asset['content'] . "\n";
            }
        }

        if ($minify) {
            $optimizer = new PerformanceOptimizer();
            $combinedJs = $optimizer->minifyJs($combinedJs);
            $inlineScripts = $optimizer->minifyJs($inlineScripts);
        }

        $output = '';

        // Save combined JS file
        if (!empty($combinedJs)) {
            $filename = 'combined_' . md5($combinedJs) . '.js';
            $filePath = $this->publicPath . '/' . $filename;
            file_put_contents($filePath, $combinedJs);
            
            $this->cache->set($cacheKey, $filename, 86400); // Cache for 24 hours
            $output .= '<script src="' . $this->getAssetUrl($filename) . '"></script>' . "\n";
        }

        // Add inline scripts
        if (!empty($inlineScripts)) {
            $output .= '<script>' . $inlineScripts . '</script>' . "\n";
        }

        return $output;
    }

    /**
     * Render individual CSS files
     */
    private function renderIndividualCss($assets, $minify)
    {
        $output = '';

        foreach ($assets as $asset) {
            if ($asset['type'] === 'file') {
                $url = $this->getOptimizedAssetUrl($asset['file'], 'css', $minify);
                $media = $asset['media'] ?? 'all';
                $output .= '<link rel="stylesheet" href="' . $url . '" media="' . $media . '">' . "\n";
            } else {
                $css = $minify ? (new PerformanceOptimizer())->minifyCss($asset['content']) : $asset['content'];
                $output .= '<style>' . $css . '</style>' . "\n";
            }
        }

        return $output;
    }

    /**
     * Render individual JavaScript files
     */
    private function renderIndividualJs($assets, $minify)
    {
        $output = '';

        foreach ($assets as $asset) {
            if ($asset['type'] === 'file') {
                $url = $this->getOptimizedAssetUrl($asset['file'], 'js', $minify);
                $attributes = '';
                
                if ($asset['defer']) $attributes .= ' defer';
                if ($asset['async']) $attributes .= ' async';
                
                $output .= '<script src="' . $url . '"' . $attributes . '></script>' . "\n";
            } else {
                $js = $minify ? (new PerformanceOptimizer())->minifyJs($asset['content']) : $asset['content'];
                $output .= '<script>' . $js . '</script>' . "\n";
            }
        }

        return $output;
    }

    /**
     * Get optimized asset URL
     */
    private function getOptimizedAssetUrl($file, $type, $minify)
    {
        if (!$minify || !$this->config['asset_optimization']) {
            return $this->getAssetUrl($file);
        }

        $cacheKey = 'optimized_' . $type . '_' . md5($file);
        $cachedUrl = $this->cache->get($cacheKey);

        if ($cachedUrl && file_exists($this->publicPath . '/' . $cachedUrl)) {
            return $this->getAssetUrl($cachedUrl);
        }

        $sourcePath = $this->resolveAssetPath($file);
        if (!file_exists($sourcePath)) {
            return $this->getAssetUrl($file);
        }

        $content = file_get_contents($sourcePath);
        $optimizer = new PerformanceOptimizer();

        if ($type === 'css') {
            $content = $optimizer->minifyCss($content);
            $content = $this->processCssUrls($content, dirname($file));
        } else {
            $content = $optimizer->minifyJs($content);
        }

        $filename = 'optimized_' . md5($content) . '.' . $type;
        $filePath = $this->publicPath . '/' . $filename;
        file_put_contents($filePath, $content);

        $this->cache->set($cacheKey, $filename, 86400); // Cache for 24 hours
        return $this->getAssetUrl($filename);
    }

    /**
     * Process CSS URLs to make them relative to the combined file
     */
    private function processCssUrls($css, $originalDir)
    {
        return preg_replace_callback('/url\(["\']?([^"\']+)["\']?\)/', function($matches) use ($originalDir) {
            $url = $matches[1];
            
            // Skip absolute URLs and data URLs
            if (preg_match('/^(https?:\/\/|data:)/', $url)) {
                return $matches[0];
            }
            
            // Make relative URLs absolute
            if (!preg_match('/^\//', $url)) {
                $url = $originalDir . '/' . $url;
            }
            
            return 'url(' . $url . ')';
        }, $css);
    }

    /**
     * Resolve asset path
     */
    private function resolveAssetPath($file)
    {
        // Check if it's already an absolute path
        if (file_exists($file)) {
            return $file;
        }

        // Check in assets directory
        $assetPath = $this->assetPath . '/' . ltrim($file, '/');
        if (file_exists($assetPath)) {
            return $assetPath;
        }

        // Check in public directory
        $publicPath = __DIR__ . '/../../' . ltrim($file, '/');
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        return $file;
    }

    /**
     * Get asset URL
     */
    private function getAssetUrl($file)
    {
        $baseUrl = $this->config['asset_url'] ?? '/assets';
        return rtrim($baseUrl, '/') . '/' . ltrim($file, '/');
    }

    /**
     * Clear asset cache
     */
    public function clearCache()
    {
        // Clear cached combined files
        $files = glob($this->publicPath . '/combined_*.{css,js}', GLOB_BRACE);
        $files = array_merge($files, glob($this->publicPath . '/optimized_*.{css,js}', GLOB_BRACE));
        
        $cleared = 0;
        foreach ($files as $file) {
            if (unlink($file)) {
                $cleared++;
            }
        }

        // Clear cache entries
        $this->cache->invalidatePattern('combined_*');
        $this->cache->invalidatePattern('optimized_*');

        return $cleared;
    }

    /**
     * Get asset statistics
     */
    public function getStats()
    {
        $cssFiles = glob($this->publicPath . '/*.css');
        $jsFiles = glob($this->publicPath . '/*.js');
        
        $totalSize = 0;
        foreach (array_merge($cssFiles, $jsFiles) as $file) {
            $totalSize += filesize($file);
        }

        return [
            'css_files' => count($cssFiles),
            'js_files' => count($jsFiles),
            'total_files' => count($cssFiles) + count($jsFiles),
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'optimization_enabled' => $this->config['asset_optimization'] ?? false
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Static helper to get asset manager instance
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
