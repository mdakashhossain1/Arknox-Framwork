<?php

namespace App\Core;

use App\Exceptions\ValidationException;
use App\Exceptions\AuthenticationException;
use App\Exceptions\DatabaseException;
use App\Exceptions\FileUploadException;

/**
 * Global Error Handler
 * 
 * Handles all uncaught exceptions and errors in the application.
 */
class ErrorHandler
{
    private $config;
    private $logger;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/app.php';
        $this->logger = new Logger();
        
        // Register error handlers
        $this->register();
    }

    /**
     * Register error and exception handlers
     */
    public function register()
    {
        // Set error reporting level
        if ($this->config['debug']) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
            ini_set('display_errors', 0);
        }

        // Register handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Handle PHP errors
     */
    public function handleError($severity, $message, $file, $line)
    {
        // Don't handle suppressed errors
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $errorType = $this->getErrorType($severity);
        $context = [
            'file' => $file,
            'line' => $line,
            'severity' => $severity,
            'type' => $errorType
        ];

        $this->logger->error("PHP Error [{$errorType}]: {$message}", $context);

        // Convert errors to exceptions in debug mode
        if ($this->config['debug'] && $severity & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }

        return true;
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleException($exception)
    {
        $this->logException($exception);

        // Prevent infinite loops
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Handle different exception types
        if ($exception instanceof ValidationException) {
            $this->handleValidationException($exception);
        } elseif ($exception instanceof AuthenticationException) {
            $this->handleAuthenticationException($exception);
        } elseif ($exception instanceof DatabaseException) {
            $this->handleDatabaseException($exception);
        } elseif ($exception instanceof FileUploadException) {
            $this->handleFileUploadException($exception);
        } else {
            $this->handleGenericException($exception);
        }
    }

    /**
     * Handle fatal errors during shutdown
     */
    public function handleShutdown()
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $exception = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            
            $this->handleException($exception);
        }
    }

    /**
     * Handle validation exceptions
     */
    private function handleValidationException(ValidationException $exception)
    {
        if ($this->isAjaxRequest()) {
            $this->sendJsonError([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $exception->getErrors(),
                'error_code' => 'VALIDATION_ERROR'
            ], 422);
        } else {
            $this->redirectWithError($exception->getMessage(), $exception->getRedirectUrl());
        }
    }

    /**
     * Handle authentication exceptions
     */
    private function handleAuthenticationException(AuthenticationException $exception)
    {
        if ($this->isAjaxRequest()) {
            $this->sendJsonError([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => 'AUTH_ERROR',
                'redirect' => '/login'
            ], 401);
        } else {
            $this->redirectToLogin($exception->getMessage());
        }
    }

    /**
     * Handle database exceptions
     */
    private function handleDatabaseException(DatabaseException $exception)
    {
        $message = $this->config['debug'] ? 
            $exception->getMessage() : 
            'A database error occurred. Please try again later.';

        if ($this->isAjaxRequest()) {
            $this->sendJsonError([
                'success' => false,
                'message' => $message,
                'error_code' => 'DATABASE_ERROR'
            ], 500);
        } else {
            $this->showErrorPage('Database Error', $message, 500);
        }
    }

    /**
     * Handle file upload exceptions
     */
    private function handleFileUploadException(FileUploadException $exception)
    {
        if ($this->isAjaxRequest()) {
            $this->sendJsonError([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => 'UPLOAD_ERROR'
            ], 400);
        } else {
            $this->redirectWithError($exception->getMessage());
        }
    }

    /**
     * Handle generic exceptions
     */
    private function handleGenericException(\Throwable $exception)
    {
        $message = $this->config['debug'] ? 
            $exception->getMessage() : 
            'An unexpected error occurred. Please try again later.';

        $statusCode = method_exists($exception, 'getStatusCode') ? 
            $exception->getStatusCode() : 500;

        if ($this->isAjaxRequest()) {
            $this->sendJsonError([
                'success' => false,
                'message' => $message,
                'error_code' => 'INTERNAL_ERROR'
            ], $statusCode);
        } else {
            $this->showErrorPage('Application Error', $message, $statusCode);
        }
    }

    /**
     * Log exception details
     */
    private function logException(\Throwable $exception)
    {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'user_id' => $_SESSION['admin_id'] ?? null
        ];

        $this->logger->error("Uncaught Exception: " . $exception->getMessage(), $context);
    }

    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Send JSON error response
     */
    private function sendJsonError($data, $statusCode = 500)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Redirect with error message
     */
    private function redirectWithError($message, $url = null)
    {
        $session = new Session();
        $session->flash('error', $message);
        
        $redirectUrl = $url ?: ($_SERVER['HTTP_REFERER'] ?? '/dashboard');
        header("Location: {$redirectUrl}");
        exit;
    }

    /**
     * Redirect to login page
     */
    private function redirectToLogin($message = null)
    {
        $session = new Session();
        if ($message) {
            $session->flash('error', $message);
        }
        
        header('Location: /login');
        exit;
    }

    /**
     * Show error page
     */
    private function showErrorPage($title, $message, $statusCode = 500)
    {
        http_response_code($statusCode);
        
        // Try to load error template
        $errorTemplate = __DIR__ . '/../Views/errors/' . $statusCode . '.php';
        if (file_exists($errorTemplate)) {
            $view = new View();
            echo $view->render("errors/{$statusCode}", [
                'title' => $title,
                'message' => $message,
                'status_code' => $statusCode
            ]);
        } else {
            // Fallback to simple HTML
            echo $this->getSimpleErrorPage($title, $message, $statusCode);
        }
        exit;
    }

    /**
     * Get simple error page HTML
     */
    private function getSimpleErrorPage($title, $message, $statusCode)
    {
        return "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$title} - Diamond Max Admin</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #e74c3c; margin-bottom: 20px; }
        p { color: #666; line-height: 1.6; margin-bottom: 20px; }
        .error-code { font-size: 3em; color: #bdc3c7; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='error-code'>{$statusCode}</div>
        <h1>{$title}</h1>
        <p>{$message}</p>
        <a href='/dashboard' class='btn'>Go to Dashboard</a>
    </div>
</body>
</html>";
    }

    /**
     * Get error type from severity
     */
    private function getErrorType($severity)
    {
        $types = [
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Standards',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];

        return $types[$severity] ?? 'Unknown Error';
    }

    /**
     * Get client IP address
     */
    private function getClientIp()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return '0.0.0.0';
    }
}
