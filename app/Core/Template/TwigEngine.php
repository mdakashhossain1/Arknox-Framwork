<?php

namespace App\Core\Template;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;
use App\Core\Session;

/**
 * Twig Template Engine
 * 
 * Provides Twig template engine integration for the Arknox Framework
 * with custom functions and filters for framework-specific functionality.
 */
class TwigEngine
{
    private Environment $twig;
    private array $config;
    private array $globalData = [];
    private string $viewsPath;
    private string $cachePath;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->viewsPath = $this->config['views_path'];
        $this->cachePath = $this->config['cache_path'];
        
        $this->initializeTwig();
        $this->registerCustomFunctions();
        $this->registerCustomFilters();
    }

    /**
     * Get default Twig configuration
     */
    private function getDefaultConfig(): array
    {
        $appConfig = require __DIR__ . '/../../../config/app.php';
        
        return [
            'views_path' => $appConfig['views_path'] ?? __DIR__ . '/../../Views/',
            'cache_path' => __DIR__ . '/../../../cache/twig/',
            'debug' => $appConfig['debug'] ?? false,
            'auto_reload' => $appConfig['debug'] ?? false,
            'strict_variables' => false,
            'autoescape' => 'html',
            'charset' => 'UTF-8',
        ];
    }

    /**
     * Initialize Twig environment
     */
    private function initializeTwig(): void
    {
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }

        // Setup filesystem loader with multiple template paths
        $loader = new FilesystemLoader();
        
        // Add main views path
        $loader->addPath($this->viewsPath);
        
        // Add twig-specific views path if it exists
        $twigViewsPath = $this->viewsPath . 'twig/';
        if (is_dir($twigViewsPath)) {
            $loader->addPath($twigViewsPath, 'twig');
        }

        // Configure Twig environment
        $this->twig = new Environment($loader, [
            'cache' => $this->config['debug'] ? false : $this->cachePath,
            'debug' => $this->config['debug'],
            'auto_reload' => $this->config['auto_reload'],
            'strict_variables' => $this->config['strict_variables'],
            'autoescape' => $this->config['autoescape'],
            'charset' => $this->config['charset'],
        ]);

        // Add debug extension if debug mode is enabled
        if ($this->config['debug']) {
            $this->twig->addExtension(new DebugExtension());
        }
    }

    /**
     * Register custom Twig functions
     */
    private function registerCustomFunctions(): void
    {
        // URL generation function
        $this->twig->addFunction(new TwigFunction('url', function ($path = '') {
            return $this->generateUrl($path);
        }));

        // Asset URL function
        $this->twig->addFunction(new TwigFunction('asset', function ($path) {
            return $this->generateAssetUrl($path);
        }));

        // CSS include function
        $this->twig->addFunction(new TwigFunction('css', function ($file) {
            return '<link rel="stylesheet" href="' . $this->generateAssetUrl("css/{$file}") . '">';
        }, ['is_safe' => ['html']]));

        // JavaScript include function
        $this->twig->addFunction(new TwigFunction('js', function ($file) {
            return '<script src="' . $this->generateAssetUrl("js/{$file}") . '"></script>';
        }, ['is_safe' => ['html']]));

        // CSRF token function
        $this->twig->addFunction(new TwigFunction('csrf_token', function () {
            $session = new Session();
            return $session->getCsrfToken();
        }));

        // CSRF field function
        $this->twig->addFunction(new TwigFunction('csrf_field', function () {
            $session = new Session();
            $token = $session->getCsrfToken();
            return '<input type="hidden" name="_token" value="' . $token . '">';
        }, ['is_safe' => ['html']]));

        // Config function
        $this->twig->addFunction(new TwigFunction('config', function ($key, $default = null) {
            return $this->getConfigValue($key, $default);
        }));

        // Session function
        $this->twig->addFunction(new TwigFunction('session', function ($key = null, $default = null) {
            $session = new Session();
            if ($key === null) {
                return $session;
            }
            return $session->get($key, $default);
        }));

        // Flash messages function
        $this->twig->addFunction(new TwigFunction('flash', function ($key = null) {
            $session = new Session();
            if ($key === null) {
                return $session->getFlashData();
            }
            return $session->getFlash($key);
        }));

        // Include function for partials
        $this->twig->addFunction(new TwigFunction('include_partial', function ($template, $data = []) {
            return $this->render($template, array_merge($this->globalData, $data));
        }, ['is_safe' => ['html']]));

        // Route function (if routing is available)
        $this->twig->addFunction(new TwigFunction('route', function ($name, $params = []) {
            // This would integrate with your routing system
            return $this->generateRoute($name, $params);
        }));

        // Old function for backwards compatibility
        $this->twig->addFunction(new TwigFunction('old', function ($key, $default = null) {
            $session = new Session();
            return $session->get('_old_input.' . $key, $default);
        }));

        // Errors function for form validation
        $this->twig->addFunction(new TwigFunction('errors', function ($key = null) {
            $session = new Session();
            $errors = $session->getFlash('errors') ?? [];
            if ($key === null) {
                return $errors;
            }
            return $errors[$key] ?? [];
        }));

        // Auth functions (if authentication is available)
        $this->twig->addFunction(new TwigFunction('auth_user', function () {
            $session = new Session();
            return $session->get('user');
        }));

        $this->twig->addFunction(new TwigFunction('auth_check', function () {
            $session = new Session();
            return $session->has('user');
        }));

        // Environment function
        $this->twig->addFunction(new TwigFunction('env', function ($key, $default = null) {
            return $_ENV[$key] ?? $default;
        }));
    }

    /**
     * Register custom Twig filters
     */
    private function registerCustomFilters(): void
    {
        // Currency formatting filter
        $this->twig->addFilter(new TwigFilter('currency', function ($amount, $currency = '$') {
            if (is_null($amount) || $amount === '') {
                return 'N/A';
            }
            return $currency . number_format((float)$amount, 2);
        }));

        // Text truncation filter
        $this->twig->addFilter(new TwigFilter('truncate', function ($text, $length = 100, $suffix = '...') {
            if (strlen($text) <= $length) {
                return $text;
            }
            return substr($text, 0, $length) . $suffix;
        }));

        // Date formatting filter
        $this->twig->addFilter(new TwigFilter('date_format', function ($date, $format = 'Y-m-d H:i:s') {
            if (is_string($date)) {
                $date = new \DateTime($date);
            }
            return $date instanceof \DateTime ? $date->format($format) : '';
        }));

        // Slug filter
        $this->twig->addFilter(new TwigFilter('slug', function ($text) {
            return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
        }));
    }

    /**
     * Render a template
     */
    public function render(string $template, array $data = []): string
    {
        // Merge global data with template data
        $templateData = array_merge($this->globalData, $data);
        
        // Add .twig extension if not present
        if (!str_ends_with($template, '.twig')) {
            $template .= '.twig';
        }

        try {
            return $this->twig->render($template, $templateData);
        } catch (\Twig\Error\LoaderError $e) {
            throw new \Exception("Twig template not found: {$template}");
        } catch (\Twig\Error\RuntimeError $e) {
            throw new \Exception("Twig runtime error: " . $e->getMessage());
        } catch (\Twig\Error\SyntaxError $e) {
            throw new \Exception("Twig syntax error in {$template}: " . $e->getMessage());
        }
    }

    /**
     * Check if a template exists
     */
    public function exists(string $template): bool
    {
        if (!str_ends_with($template, '.twig')) {
            $template .= '.twig';
        }
        
        return $this->twig->getLoader()->exists($template);
    }

    /**
     * Set global template data
     */
    public function share($key, $value = null): void
    {
        if (is_array($key)) {
            $this->globalData = array_merge($this->globalData, $key);
        } else {
            $this->globalData[$key] = $value;
        }
    }

    /**
     * Get Twig environment instance
     */
    public function getTwig(): Environment
    {
        return $this->twig;
    }

    /**
     * Generate URL
     */
    private function generateUrl(string $path = ''): string
    {
        $config = require __DIR__ . '/../../../config/app.php';
        $baseUrl = rtrim($config['app_url'], '/');
        $path = ltrim($path, '/');
        
        return $baseUrl . '/' . $path;
    }

    /**
     * Generate asset URL
     */
    private function generateAssetUrl(string $path): string
    {
        $config = require __DIR__ . '/../../../config/app.php';
        $baseUrl = rtrim($config['app_url'], '/');
        $assetsPath = trim($config['assets_path'], '/');
        $path = ltrim($path, '/');
        
        return $baseUrl . '/' . $assetsPath . '/' . $path;
    }

    /**
     * Get configuration value
     */
    private function getConfigValue(string $key, $default = null)
    {
        $config = require __DIR__ . '/../../../config/app.php';
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (is_array($value) && array_key_exists($k, $value)) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }

    /**
     * Generate route URL (placeholder for routing integration)
     */
    private function generateRoute(string $name, array $params = []): string
    {
        // This is a placeholder implementation
        // In a real application, this would integrate with your routing system
        $baseUrl = $this->generateUrl();

        // Simple route generation - you can enhance this based on your routing system
        $route = $name;
        foreach ($params as $key => $value) {
            $route = str_replace('{' . $key . '}', $value, $route);
        }

        return $baseUrl . '/' . ltrim($route, '/');
    }
}
