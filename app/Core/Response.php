<?php

namespace App\Core;

/**
 * HTTP Response Class
 * 
 * High-performance response handling with content negotiation,
 * caching headers, and optimized output delivery
 */
class Response
{
    protected $content = '';
    protected $statusCode = 200;
    protected $headers = [];
    protected $cookies = [];
    
    protected static $statusTexts = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    public function __construct($content = '', $status = 200, array $headers = [])
    {
        $this->setContent($content);
        $this->setStatusCode($status);
        $this->headers = $headers;
    }

    /**
     * Create a new response instance
     */
    public static function make($content = '', $status = 200, array $headers = [])
    {
        return new static($content, $status, $headers);
    }

    /**
     * Create JSON response
     */
    public static function json($data = [], $status = 200, array $headers = [])
    {
        $headers['Content-Type'] = 'application/json';
        
        return new static(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            $headers
        );
    }

    /**
     * Create redirect response
     */
    public static function redirect($url, $status = 302, array $headers = [])
    {
        $headers['Location'] = $url;
        return new static('', $status, $headers);
    }

    /**
     * Create view response
     */
    public static function view($view, array $data = [], $status = 200, array $headers = [])
    {
        $viewInstance = new View();
        $content = $viewInstance->render($view, $data);
        
        return new static($content, $status, $headers);
    }

    /**
     * Set response content
     */
    public function setContent($content)
    {
        $this->content = (string) $content;
        return $this;
    }

    /**
     * Get response content
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set status code
     */
    public function setStatusCode($code, $text = null)
    {
        $this->statusCode = (int) $code;
        
        if ($text === null && isset(static::$statusTexts[$code])) {
            $text = static::$statusTexts[$code];
        }
        
        return $this;
    }

    /**
     * Get status code
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Set header
     */
    public function header($key, $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Set multiple headers
     */
    public function withHeaders(array $headers)
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Get header
     */
    public function getHeader($key, $default = null)
    {
        return $this->headers[$key] ?? $default;
    }

    /**
     * Get all headers
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Set cookie
     */
    public function cookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = false, $httpOnly = true)
    {
        $this->cookies[] = [
            'name' => $name,
            'value' => $value,
            'expire' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httpOnly' => $httpOnly
        ];
        
        return $this;
    }

    /**
     * Set cache headers
     */
    public function cache($seconds)
    {
        $this->headers['Cache-Control'] = "public, max-age={$seconds}";
        $this->headers['Expires'] = gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT';
        return $this;
    }

    /**
     * Set no-cache headers
     */
    public function noCache()
    {
        $this->headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
        $this->headers['Pragma'] = 'no-cache';
        $this->headers['Expires'] = '0';
        return $this;
    }

    /**
     * Set ETag header
     */
    public function etag($etag)
    {
        $this->headers['ETag'] = '"' . $etag . '"';
        return $this;
    }

    /**
     * Set Last-Modified header
     */
    public function lastModified($timestamp)
    {
        $this->headers['Last-Modified'] = gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
        return $this;
    }

    /**
     * Send the response
     */
    public function send()
    {
        $this->sendHeaders();
        $this->sendCookies();
        $this->sendContent();
        
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        return $this;
    }

    /**
     * Send headers
     */
    protected function sendHeaders()
    {
        if (headers_sent()) {
            return;
        }

        // Send status line
        http_response_code($this->statusCode);

        // Send headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}", false);
        }
    }

    /**
     * Send cookies
     */
    protected function sendCookies()
    {
        foreach ($this->cookies as $cookie) {
            setcookie(
                $cookie['name'],
                $cookie['value'],
                $cookie['expire'],
                $cookie['path'],
                $cookie['domain'],
                $cookie['secure'],
                $cookie['httpOnly']
            );
        }
    }

    /**
     * Send content
     */
    protected function sendContent()
    {
        echo $this->content;
    }

    /**
     * Check if response is successful
     */
    public function isSuccessful()
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if response is a redirect
     */
    public function isRedirect()
    {
        return in_array($this->statusCode, [301, 302, 303, 307, 308]);
    }

    /**
     * Check if response is client error
     */
    public function isClientError()
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if response is server error
     */
    public function isServerError()
    {
        return $this->statusCode >= 500;
    }

    /**
     * Get response as string
     */
    public function __toString()
    {
        return $this->content;
    }
}
