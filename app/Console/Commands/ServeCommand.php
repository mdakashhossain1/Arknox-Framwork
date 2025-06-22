<?php

namespace App\Console\Commands;

/**
 * Arknox Development Server Command
 *
 * Advanced development server with hot reloading, file watching,
 * and development tools integration for Arknox Framework
 */
class ServeCommand extends BaseCommand
{
    private $watchers = [];
    private $serverProcess = null;

    public function execute($arguments)
    {
        $host = '127.0.0.1';
        $port = 8000;
        $enableHotReload = false;
        $enableFileWatch = false;
        $enableDebug = false;

        // Parse arguments
        foreach ($arguments as $arg) {
            if (strpos($arg, '--host=') === 0) {
                $host = substr($arg, 7);
            } elseif (strpos($arg, '--port=') === 0) {
                $port = (int)substr($arg, 7);
            } elseif ($arg === '--hot') {
                $enableHotReload = true;
                $enableFileWatch = true;
            } elseif ($arg === '--watch') {
                $enableFileWatch = true;
            } elseif ($arg === '--debug') {
                $enableDebug = true;
            }
        }

        $this->info("🚀 Starting Arknox Development Server...");
        $this->info("📍 Server URL: http://{$host}:{$port}");

        if ($enableHotReload) {
            $this->info("🔥 Hot reloading: ENABLED");
        }

        if ($enableFileWatch) {
            $this->info("👀 File watching: ENABLED");
        }

        if ($enableDebug) {
            $this->info("🐛 Debug mode: ENABLED");
        }

        $this->info("⏹️  Press Ctrl+C to stop the server");
        $this->info("");

        try {
            // Start file watchers if enabled
            if ($enableFileWatch) {
                $this->startFileWatchers($enableHotReload);
            }

            // Create development router
            $this->createDevelopmentRouter($enableDebug);

            // Start the server
            $this->startServer($host, $port, $enableDebug);

        } catch (\Exception $e) {
            $this->error("❌ Server error: " . $e->getMessage());
            $this->shutdown();
        }
    }

    private function startFileWatchers($enableHotReload)
    {
        $this->info("🔍 Starting file watchers...");

        $watchPaths = [
            getcwd() . '/app',
            getcwd() . '/config',
            getcwd() . '/assets',
        ];

        foreach ($watchPaths as $path) {
            if (is_dir($path)) {
                $this->watchDirectory($path, $enableHotReload);
            }
        }
    }

    private function watchDirectory($path, $enableHotReload)
    {
        // Simple file watching implementation
        $lastModified = [];

        $watcher = function() use ($path, $enableHotReload, &$lastModified) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'css', 'js', 'html'])) {
                    $filePath = $file->getPathname();
                    $currentModified = $file->getMTime();

                    if (!isset($lastModified[$filePath])) {
                        $lastModified[$filePath] = $currentModified;
                        continue;
                    }

                    if ($currentModified > $lastModified[$filePath]) {
                        $this->info("📝 File changed: " . basename($filePath));
                        $lastModified[$filePath] = $currentModified;

                        if ($enableHotReload) {
                            $this->triggerHotReload($filePath);
                        }
                    }
                }
            }
        };

        $this->watchers[] = $watcher;
    }

    private function triggerHotReload($filePath)
    {
        // Clear OPCache if available
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($filePath, true);
        }

        // Clear framework caches
        $this->clearFrameworkCaches();

        $this->info("🔄 Hot reload triggered for: " . basename($filePath));
    }

    private function clearFrameworkCaches()
    {
        // Clear route cache
        $routeCacheFile = getcwd() . '/cache/routes.cache';
        if (file_exists($routeCacheFile)) {
            unlink($routeCacheFile);
        }

        // Clear config cache
        $configCacheFile = getcwd() . '/cache/config.cache';
        if (file_exists($configCacheFile)) {
            unlink($configCacheFile);
        }

        // Clear view cache
        $viewCacheDir = getcwd() . '/cache/views';
        if (is_dir($viewCacheDir)) {
            $this->clearDirectory($viewCacheDir);
        }
    }

    private function clearDirectory($dir)
    {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function createDevelopmentRouter($enableDebug)
    {
        $routerPath = getcwd() . '/dev_router.php';

        $routerContent = "<?php
/**
 * Development Router
 * Enhanced router for development server with debugging and hot reload support
 */

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set development environment
\$_ENV['APP_ENV'] = 'development';
\$_ENV['APP_DEBUG'] = " . ($enableDebug ? 'true' : 'false') . ";

// Handle static files
\$requestUri = \$_SERVER['REQUEST_URI'];
\$parsedUrl = parse_url(\$requestUri);
\$path = \$parsedUrl['path'];

// Serve static files directly
if (preg_match('/\\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$/', \$path)) {
    \$filePath = __DIR__ . \$path;
    if (file_exists(\$filePath)) {
        \$mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];

        \$extension = pathinfo(\$filePath, PATHINFO_EXTENSION);
        \$mimeType = \$mimeTypes[\$extension] ?? 'application/octet-stream';

        header('Content-Type: ' . \$mimeType);
        header('Cache-Control: public, max-age=3600');
        readfile(\$filePath);
        return;
    }
}

// Add development headers
header('X-Development-Server: true');
header('X-Hot-Reload: enabled');

// Load the main application
require_once __DIR__ . '/index.php';
";

        file_put_contents($routerPath, $routerContent);
        return $routerPath;
    }

    private function startServer($host, $port, $enableDebug)
    {
        $routerPath = getcwd() . '/dev_router.php';
        $documentRoot = getcwd();

        $command = "php -S {$host}:{$port} -t {$documentRoot} {$routerPath}";

        if ($enableDebug) {
            $this->info("🐛 Debug command: {$command}");
        }

        // Start file watching in background if watchers are set up
        if (!empty($this->watchers)) {
            $this->startBackgroundWatching();
        }

        // Start the server (this will block)
        $this->info("✅ Server started successfully!");
        passthru($command);
    }

    private function startBackgroundWatching()
    {
        // Fork a process for file watching (Unix-like systems only)
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();

            if ($pid == 0) {
                // Child process - run file watchers
                while (true) {
                    foreach ($this->watchers as $watcher) {
                        $watcher();
                    }
                    sleep(1); // Check every second
                }
                exit(0);
            }
        } else {
            // Fallback for systems without pcntl
            $this->warning("⚠️  File watching requires pcntl extension for optimal performance");
        }
    }

    public function shutdown()
    {
        $this->info("\n🛑 Shutting down development server...");

        // Clean up development router
        $routerPath = getcwd() . '/dev_router.php';
        if (file_exists($routerPath)) {
            unlink($routerPath);
        }

        // Kill server process if running
        if ($this->serverProcess) {
            proc_terminate($this->serverProcess);
        }

        $this->info("✅ Server stopped gracefully");
        exit(0);
    }
}
