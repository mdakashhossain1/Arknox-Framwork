<?php

namespace App\Core;

/**
 * Request Class
 * 
 * Handles HTTP request data and provides convenient methods
 * for accessing input, files, and request information.
 */
class Request
{
    private $input = [];
    private $files = [];
    private $headers = [];
    private $server = [];
    private static $instance = null;

    public function __construct()
    {
        $this->input = array_merge($_GET, $_POST);
        $this->files = $_FILES;
        $this->server = $_SERVER;
        $this->headers = $this->parseHeaders();
    }

    /**
     * Create a new request instance from globals
     */
    public static function capture()
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * Parse headers from server variables
     */
    private function parseHeaders()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($header)] = $value;
            }
        }
        return $headers;
    }

    /**
     * Get input value
     */
    public function input($key = null, $default = null)
    {
        if ($key === null) {
            return $this->input;
        }

        return $this->input[$key] ?? $default;
    }

    /**
     * Get all input
     */
    public function all()
    {
        return $this->input;
    }

    /**
     * Check if input exists
     */
    public function has($key)
    {
        return isset($this->input[$key]);
    }

    /**
     * Get uploaded file
     */
    public function file($key)
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Check if file was uploaded
     */
    public function hasFile($key)
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }

    /**
     * Get request method
     */
    public function method()
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Check if request is POST
     */
    public function isPost()
    {
        return $this->method() === 'POST';
    }

    /**
     * Check if request is GET
     */
    public function isGet()
    {
        return $this->method() === 'GET';
    }

    /**
     * Check if request is AJAX
     */
    public function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get request URI
     */
    public function uri()
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Get request path
     */
    public function path()
    {
        $uri = $this->uri();
        return parse_url($uri, PHP_URL_PATH);
    }

    /**
     * Get query string
     */
    public function query()
    {
        return $_SERVER['QUERY_STRING'] ?? '';
    }

    /**
     * Get user IP address
     */
    public function ip()
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
               $_SERVER['HTTP_X_REAL_IP'] ?? 
               $_SERVER['REMOTE_ADDR'] ?? 
               '0.0.0.0';
    }

    /**
     * Get user agent
     */
    public function userAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Get referer
     */
    public function referer()
    {
        return $_SERVER['HTTP_REFERER'] ?? '';
    }

    /**
     * Sanitize input
     */
    public function sanitize($key, $filter = FILTER_SANITIZE_STRING)
    {
        $value = $this->input($key);
        
        if ($value === null) {
            return null;
        }

        return filter_var($value, $filter);
    }

    /**
     * Validate email
     */
    public function email($key)
    {
        $value = $this->input($key);
        return filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Validate integer
     */
    public function int($key, $default = 0)
    {
        $value = $this->input($key, $default);
        return filter_var($value, FILTER_VALIDATE_INT) ?: $default;
    }

    /**
     * Validate float
     */
    public function float($key, $default = 0.0)
    {
        $value = $this->input($key, $default);
        return filter_var($value, FILTER_VALIDATE_FLOAT) ?: $default;
    }

    /**
     * Get header value
     */
    public function header($key, $default = null)
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    /**
     * Get all headers
     */
    public function headers()
    {
        return $this->headers;
    }

    /**
     * Check if request expects JSON
     */
    public function expectsJson()
    {
        return $this->isAjax() || $this->wantsJson();
    }

    /**
     * Check if request wants JSON response
     */
    public function wantsJson()
    {
        $acceptable = $this->header('accept', '');
        return strpos($acceptable, 'application/json') !== false;
    }

    /**
     * Check if request is secure (HTTPS)
     */
    public function isSecure()
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    /**
     * Get the full URL
     */
    public function url()
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $this->uri();
        return "{$scheme}://{$host}{$uri}";
    }

    /**
     * Get only specified input keys
     */
    public function only($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $result = [];

        foreach ($keys as $key) {
            if ($this->has($key)) {
                $result[$key] = $this->input($key);
            }
        }

        return $result;
    }

    /**
     * Get all input except specified keys
     */
    public function except($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $result = $this->all();

        foreach ($keys as $key) {
            unset($result[$key]);
        }

        return $result;
    }

    /**
     * Get bearer token from Authorization header
     */
    public function bearerToken()
    {
        $header = $this->header('authorization', '');
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get JSON payload
     */
    public function json($key = null, $default = null)
    {
        static $json = null;

        if ($json === null) {
            $input = file_get_contents('php://input');
            $json = json_decode($input, true) ?: [];
        }

        if ($key === null) {
            return $json;
        }

        return $json[$key] ?? $default;
    }

    /**
     * Merge additional input
     */
    public function merge(array $input)
    {
        $this->input = array_merge($this->input, $input);
        return $this;
    }

    /**
     * Replace input
     */
    public function replace(array $input)
    {
        $this->input = $input;
        return $this;
    }
}
