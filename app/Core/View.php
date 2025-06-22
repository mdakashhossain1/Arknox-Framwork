<?php

namespace App\Core;

use App\Core\Template\TwigEngine;

/**
 * View Class
 *
 * Handles view rendering and template management.
 * Supports both PHP and Twig templates with automatic detection.
 */
class View
{
    private $viewsPath;
    private $data = [];
    private $twigEngine;
    private $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/app.php';
        $this->viewsPath = $this->config['views_path'];

        // Initialize Twig engine if enabled
        if ($this->isTwigEnabled()) {
            $this->twigEngine = new TwigEngine($this->config);
        }
    }

    /**
     * Render a view template
     * Supports both PHP and Twig templates with automatic detection
     */
    public function render($template, $data = [])
    {
        $this->data = array_merge($this->data, $data);

        // Check if Twig template exists first
        if ($this->isTwigEnabled() && $this->twigTemplateExists($template)) {
            return $this->renderTwigTemplate($template, $this->data);
        }

        // Fall back to PHP template
        return $this->renderPhpTemplate($template, $this->data);
    }

    /**
     * Render PHP template
     */
    private function renderPhpTemplate($template, $data = [])
    {
        $templatePath = $this->viewsPath . str_replace('.', '/', $template) . '.php';

        if (!file_exists($templatePath)) {
            throw new \Exception("View template not found: {$template}");
        }

        // Extract data to variables
        extract($data);

        // Start output buffering
        ob_start();

        // Include the template
        include $templatePath;

        // Get the content and clean the buffer
        $content = ob_get_clean();

        return $content;
    }

    /**
     * Render Twig template
     */
    private function renderTwigTemplate($template, $data = [])
    {
        return $this->twigEngine->render($template, $data);
    }

    /**
     * Render view with layout
     */
    public function renderWithLayout($template, $data = [], $layout = 'main')
    {
        $content = $this->render($template, $data);
        
        return $this->render("layouts/{$layout}", array_merge($data, [
            'content' => $content
        ]));
    }

    /**
     * Include a partial view
     */
    public function partial($template, $data = [])
    {
        return $this->render($template, $data);
    }

    /**
     * Include a component
     */
    public function component($component, $data = [])
    {
        return $this->render("components/{$component}", $data);
    }

    /**
     * Set global view data
     */
    public function share($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
            // Also share with Twig engine if available
            if ($this->twigEngine) {
                $this->twigEngine->share($key);
            }
        } else {
            $this->data[$key] = $value;
            // Also share with Twig engine if available
            if ($this->twigEngine) {
                $this->twigEngine->share($key, $value);
            }
        }
    }

    /**
     * Escape HTML output
     */
    public function escape($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate URL
     */
    public function url($path = '')
    {
        $config = require __DIR__ . '/../../config/app.php';
        $baseUrl = rtrim($config['app_url'], '/');
        $path = ltrim($path, '/');
        
        return $baseUrl . '/' . $path;
    }

    /**
     * Generate asset URL
     */
    public function asset($path)
    {
        $config = require __DIR__ . '/../../config/app.php';
        $baseUrl = rtrim($config['app_url'], '/');
        $assetsPath = trim($config['assets_path'], '/');
        $path = ltrim($path, '/');
        
        return $baseUrl . '/' . $assetsPath . '/' . $path;
    }

    /**
     * Include CSS file
     */
    public function css($file)
    {
        return '<link rel="stylesheet" href="' . $this->asset("css/{$file}") . '">';
    }

    /**
     * Include JS file
     */
    public function js($file)
    {
        return '<script src="' . $this->asset("js/{$file}") . '"></script>';
    }

    /**
     * Generate CSRF token field
     */
    public function csrfField()
    {
        $session = new Session();
        $token = $session->get('_token');
        
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            $session->set('_token', $token);
        }
        
        return '<input type="hidden" name="_token" value="' . $token . '">';
    }

    /**
     * Format date
     */
    public function formatDate($date, $format = 'Y-m-d H:i:s')
    {
        if (empty($date)) {
            return 'N/A';
        }
        
        return date($format, strtotime($date));
    }

    /**
     * Format currency
     */
    public function formatCurrency($amount, $currency = '$')
    {
        if (is_null($amount) || $amount === '') {
            return 'N/A';
        }
        
        return $currency . number_format((float)$amount, 2);
    }

    /**
     * Truncate text
     */
    public function truncate($text, $length = 100, $suffix = '...')
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . $suffix;
    }

    /**
     * Check if Twig is enabled
     */
    private function isTwigEnabled(): bool
    {
        return isset($this->config['twig_enabled']) ? $this->config['twig_enabled'] : true;
    }

    /**
     * Check if Twig template exists
     */
    private function twigTemplateExists($template): bool
    {
        if (!$this->twigEngine) {
            return false;
        }

        return $this->twigEngine->exists($template);
    }

    /**
     * Get Twig engine instance
     */
    public function getTwigEngine(): ?TwigEngine
    {
        return $this->twigEngine;
    }

    /**
     * Force render with specific engine
     */
    public function renderWith($engine, $template, $data = [])
    {
        $this->data = array_merge($this->data, $data);

        if ($engine === 'twig' && $this->twigEngine) {
            return $this->renderTwigTemplate($template, $this->data);
        } elseif ($engine === 'php') {
            return $this->renderPhpTemplate($template, $this->data);
        }

        throw new \Exception("Invalid template engine: {$engine}");
    }
}
