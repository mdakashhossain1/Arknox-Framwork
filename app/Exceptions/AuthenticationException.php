<?php

namespace App\Exceptions;

/**
 * Authentication Exception
 * 
 * Thrown when authentication fails or access is denied.
 */
class AuthenticationException extends BaseException
{
    protected $statusCode = 401;
    protected $errorCode = 'AUTH_ERROR';

    public function __construct($message = 'Authentication required', $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create for invalid credentials
     */
    public static function invalidCredentials()
    {
        return new static('Invalid credentials provided');
    }

    /**
     * Create for session expired
     */
    public static function sessionExpired()
    {
        return new static('Your session has expired. Please login again.');
    }

    /**
     * Create for access denied
     */
    public static function accessDenied($resource = null)
    {
        $message = $resource ? 
            "Access denied to {$resource}" : 
            'Access denied';
        return new static($message);
    }

    /**
     * Create for account locked
     */
    public static function accountLocked()
    {
        return new static('Account is locked due to too many failed login attempts');
    }

    /**
     * Create for insufficient permissions
     */
    public static function insufficientPermissions($permission = null)
    {
        $message = $permission ? 
            "Insufficient permissions: {$permission} required" : 
            'Insufficient permissions';
        return new static($message);
    }

    /**
     * Create for token mismatch
     */
    public static function tokenMismatch()
    {
        return new static('Security token mismatch. Please refresh and try again.');
    }
}
