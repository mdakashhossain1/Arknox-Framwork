<?php

namespace App\Exceptions;

/**
 * Database Exception
 * 
 * Thrown when database operations fail.
 */
class DatabaseException extends BaseException
{
    protected $statusCode = 500;
    protected $errorCode = 'DATABASE_ERROR';
    protected $query;
    protected $bindings;

    public function __construct($message = 'Database error occurred', $query = null, array $bindings = [], $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->query = $query;
        $this->bindings = $bindings;
    }

    /**
     * Get the SQL query that caused the error
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Get the query bindings
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * Set query information
     */
    public function setQueryInfo($query, array $bindings = [])
    {
        $this->query = $query;
        $this->bindings = $bindings;
        return $this;
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray()
    {
        $array = parent::toArray();
        
        // Only include query info in debug mode
        $config = require __DIR__ . '/../../config/app.php';
        if ($config['debug']) {
            $array['query'] = $this->query;
            $array['bindings'] = $this->bindings;
        }
        
        return $array;
    }

    /**
     * Create for connection failure
     */
    public static function connectionFailed($details = null)
    {
        $message = 'Failed to connect to database';
        if ($details) {
            $message .= ": {$details}";
        }
        return new static($message);
    }

    /**
     * Create for query execution failure
     */
    public static function queryFailed($query, array $bindings = [], $error = null)
    {
        $message = 'Database query failed';
        if ($error) {
            $message .= ": {$error}";
        }
        return new static($message, $query, $bindings);
    }

    /**
     * Create for transaction failure
     */
    public static function transactionFailed($operation = null)
    {
        $message = $operation ? 
            "Transaction failed during {$operation}" : 
            'Database transaction failed';
        return new static($message);
    }

    /**
     * Create for constraint violation
     */
    public static function constraintViolation($constraint = null)
    {
        $message = $constraint ? 
            "Database constraint violation: {$constraint}" : 
            'Database constraint violation';
        return new static($message);
    }

    /**
     * Create for duplicate entry
     */
    public static function duplicateEntry($field = null)
    {
        $message = $field ? 
            "Duplicate entry for {$field}" : 
            'Duplicate entry detected';
        return new static($message);
    }

    /**
     * Create for record not found
     */
    public static function recordNotFound($table = null, $id = null)
    {
        if ($table && $id) {
            $message = "Record not found in {$table} with ID {$id}";
        } elseif ($table) {
            $message = "Record not found in {$table}";
        } else {
            $message = 'Record not found';
        }
        return new static($message);
    }

    /**
     * Create for table not found
     */
    public static function tableNotFound($table)
    {
        return new static("Table '{$table}' does not exist");
    }

    /**
     * Create for column not found
     */
    public static function columnNotFound($column, $table = null)
    {
        $message = $table ? 
            "Column '{$column}' not found in table '{$table}'" : 
            "Column '{$column}' not found";
        return new static($message);
    }
}
