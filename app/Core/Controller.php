<?php

namespace App\Core;

/**
 * Base Controller Class
 * 
 * Provides common functionality for all controller classes including
 * view rendering, request handling, and response management.
 */
abstract class Controller
{
    protected $view;
    protected $request;
    protected $session;
    protected $config;
    protected $logger;

    public function __construct()
    {
        $this->view = new View();
        $this->request = new Request();
        $this->session = new Session();
        $this->config = require __DIR__ . '/../../config/app.php';
        $this->logger = new Logger();

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Render a view with data
     */
    protected function render($template, $data = [])
    {
        return $this->view->render($template, $data);
    }

    /**
     * Render a view with layout
     */
    protected function renderWithLayout($template, $data = [], $layout = 'main')
    {
        return $this->view->renderWithLayout($template, $data, $layout);
    }

    /**
     * Return JSON response
     */
    protected function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Redirect to another URL
     */
    protected function redirect($url, $statusCode = 302)
    {
        // Handle relative URLs by adding base path
        if (strpos($url, '/') === 0 && strpos($url, 'http') !== 0) {
            $url = $this->getBaseUrl() . $url;
        }

        http_response_code($statusCode);
        header("Location: {$url}");
        exit;
    }

    /**
     * Get the base URL for the application
     */
    protected function getBaseUrl()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $basePath = dirname($scriptName);

        // Remove trailing slash if not root
        if ($basePath !== '/') {
            $basePath = rtrim($basePath, '/');
        }

        return $protocol . '://' . $host . $basePath;
    }

    /**
     * Redirect back to previous page
     */
    protected function redirectBack()
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard';
        $this->redirect($referer);
    }

    /**
     * Get request input
     */
    protected function input($key = null, $default = null)
    {
        return $this->request->input($key, $default);
    }

    /**
     * Get all request input
     */
    protected function all()
    {
        return $this->request->all();
    }

    /**
     * Check if request has input
     */
    protected function has($key)
    {
        return $this->request->has($key);
    }

    /**
     * Get uploaded file
     */
    protected function file($key)
    {
        return $this->request->file($key);
    }

    /**
     * Validate request input
     */
    protected function validate($rules, $redirectUrl = null)
    {
        $validator = new Validator();
        if (!$validator->validate($this->all(), $rules)) {
            if ($this->request->isAjax()) {
                $this->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->getErrors()
                ], 422);
            } else {
                $this->flash('error', $validator->getFirstError());
                $this->flash('old_input', $this->all());
                $this->redirect($redirectUrl ?: $this->request->referer());
            }
        }
        return true;
    }

    /**
     * Validate and throw exception if fails
     */
    protected function validateOrFail($rules, $redirectUrl = null)
    {
        $validator = new Validator();
        $validator->validateOrFail($this->all(), $rules, $redirectUrl);
        return true;
    }

    /**
     * Set flash message
     */
    protected function flash($key, $message)
    {
        $this->session->flash($key, $message);
    }

    /**
     * Get flash message
     */
    protected function getFlash($key)
    {
        return $this->session->getFlash($key);
    }

    /**
     * Check if user is authenticated
     */
    protected function isAuthenticated()
    {
        return $this->session->has('admin_id');
    }

    /**
     * Get authenticated user ID
     */
    protected function getAuthUserId()
    {
        return $this->session->get('admin_id');
    }

    /**
     * Require authentication
     */
    protected function requireAuth()
    {
        if (!$this->isAuthenticated()) {
            if ($this->request->isAjax()) {
                $this->json([
                    'success' => false,
                    'message' => 'Authentication required',
                    'redirect' => $this->getBaseUrl() . '/auth/login'
                ], 401);
            } else {
                $this->flash('error', 'Please login to access this page.');
                $this->redirect('/auth/login');
            }
        }
    }

    /**
     * Generate CSRF token
     */
    protected function generateCsrfToken()
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set('_token', $token);
        return $token;
    }

    /**
     * Verify CSRF token
     */
    protected function verifyCsrfToken($token)
    {
        $sessionToken = $this->session->get('_token');
        return hash_equals($sessionToken, $token);
    }

    /**
     * Handle 404 error
     */
    protected function notFound()
    {
        http_response_code(404);
        $this->render('errors/404');
        exit;
    }

    /**
     * Handle 500 error
     */
    protected function serverError($message = 'Internal Server Error')
    {
        http_response_code(500);
        error_log("Server Error: " . $message);
        $this->render('errors/500', ['message' => $message]);
        exit;
    }

    /**
     * Log activity
     */
    protected function log($message, $level = 'info', array $context = [])
    {
        $context['user_id'] = $this->getAuthUserId();
        $context['controller'] = get_class($this);

        $this->logger->log($level, $message, $context);
    }

    /**
     * Handle exceptions gracefully
     */
    protected function handleException(\Exception $exception, $defaultMessage = 'An error occurred')
    {
        $this->logger->error('Controller exception: ' . $exception->getMessage(), [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);

        if ($this->request->isAjax()) {
            $message = $this->config['debug'] ? $exception->getMessage() : $defaultMessage;
            $this->json([
                'success' => false,
                'message' => $message
            ], 500);
        } else {
            $this->flash('error', $defaultMessage);
            $this->redirectBack();
        }
    }

    /**
     * Try to execute a callback with error handling
     */
    protected function tryExecute(callable $callback, $defaultMessage = 'Operation failed')
    {
        try {
            return $callback();
        } catch (\Exception $e) {
            $this->handleException($e, $defaultMessage);
            return false;
        }
    }

    /**
     * Abort with error
     */
    protected function abort($statusCode, $message = null)
    {
        http_response_code($statusCode);

        if ($this->request->isAjax()) {
            $this->json([
                'success' => false,
                'message' => $message ?: "Error {$statusCode}",
                'status_code' => $statusCode
            ], $statusCode);
        } else {
            $errorTemplate = "errors/{$statusCode}";
            if ($this->view->exists($errorTemplate)) {
                echo $this->render($errorTemplate, [
                    'message' => $message,
                    'status_code' => $statusCode
                ]);
            } else {
                echo $this->render('errors/500', [
                    'message' => $message ?: "Error {$statusCode}",
                    'status_code' => $statusCode
                ]);
            }
        }
        exit;
    }

    /**
     * Get old input for form repopulation
     */
    protected function old($key = null, $default = null)
    {
        $oldInput = $this->getFlash('old_input') ?: [];

        if ($key === null) {
            return $oldInput;
        }

        return $oldInput[$key] ?? $default;
    }

    /**
     * Check if request is AJAX
     */
    protected function isAjax()
    {
        return $this->request->isAjax();
    }

    /**
     * Get validation errors for display
     */
    protected function getValidationErrors()
    {
        return $this->getFlash('validation_errors') ?: [];
    }

    /**
     * Set validation errors
     */
    protected function setValidationErrors(array $errors)
    {
        $this->flash('validation_errors', $errors);
    }
}
