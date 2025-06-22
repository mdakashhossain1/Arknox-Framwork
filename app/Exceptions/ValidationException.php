<?php

namespace App\Exceptions;

/**
 * Validation Exception
 * 
 * Thrown when validation fails for user input.
 */
class ValidationException extends BaseException
{
    protected $statusCode = 422;
    protected $errorCode = 'VALIDATION_ERROR';
    protected $errors = [];
    protected $redirectUrl;

    public function __construct($message = 'Validation failed', array $errors = [], $redirectUrl = null)
    {
        parent::__construct($message);
        $this->errors = $errors;
        $this->redirectUrl = $redirectUrl;
    }

    /**
     * Get validation errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Set validation errors
     */
    public function setErrors(array $errors)
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * Add validation error
     */
    public function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
        return $this;
    }

    /**
     * Get redirect URL
     */
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    /**
     * Set redirect URL
     */
    public function setRedirectUrl($url)
    {
        $this->redirectUrl = $url;
        return $this;
    }

    /**
     * Check if has errors for field
     */
    public function hasError($field)
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * Get errors for specific field
     */
    public function getFieldErrors($field)
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get first error for field
     */
    public function getFirstError($field)
    {
        $errors = $this->getFieldErrors($field);
        return !empty($errors) ? $errors[0] : null;
    }

    /**
     * Get all errors as flat array
     */
    public function getFlatErrors()
    {
        $flat = [];
        foreach ($this->errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $flat[] = $error;
            }
        }
        return $flat;
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
            'errors' => $this->getErrors(),
            'redirect_url' => $this->getRedirectUrl()
        ];
    }

    /**
     * Create from validator errors
     */
    public static function fromValidator(array $errors, $message = 'Validation failed', $redirectUrl = null)
    {
        return new static($message, $errors, $redirectUrl);
    }

    /**
     * Create for single field
     */
    public static function forField($field, $message, $redirectUrl = null)
    {
        return new static('Validation failed', [$field => [$message]], $redirectUrl);
    }

    /**
     * Create for required field
     */
    public static function required($field, $redirectUrl = null)
    {
        $message = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        return static::forField($field, $message, $redirectUrl);
    }

    /**
     * Create for invalid field
     */
    public static function invalid($field, $redirectUrl = null)
    {
        $message = ucfirst(str_replace('_', ' ', $field)) . ' is invalid';
        return static::forField($field, $message, $redirectUrl);
    }
}
