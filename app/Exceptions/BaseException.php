<?php

namespace App\Exceptions;

/**
 * Base Exception Class
 * 
 * Base class for all custom application exceptions.
 */
class BaseException extends \Exception
{
    protected $statusCode = 500;
    protected $errorCode = 'INTERNAL_ERROR';
    protected $context = [];

    public function __construct($message = '', $code = 0, \Exception $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get HTTP status code
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Get error code
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Get context data
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Set context data
     */
    public function setContext(array $context)
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Add context data
     */
    public function addContext($key, $value)
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray()
    {
        return [
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->getErrorCode(),
            'status_code' => $this->getStatusCode(),
            'context' => $this->getContext()
        ];
    }
}
