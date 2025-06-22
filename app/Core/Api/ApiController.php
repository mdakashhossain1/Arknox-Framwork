<?php

namespace App\Core\Api;

use App\Core\Controller;

/**
 * API Controller Base
 * 
 * API-first architecture with modern features
 */
abstract class ApiController extends Controller
{
    protected $statusCode = 200;
    protected $headers = [];

    /**
     * Return JSON response
     */
    protected function respond($data, $statusCode = null, $headers = [])
    {
        $statusCode = $statusCode ?: $this->statusCode;
        $headers = array_merge($this->headers, $headers);

        $response = [
            'data' => $data,
            'status' => $statusCode,
            'timestamp' => time()
        ];

        return $this->jsonResponse($response, $statusCode, $headers);
    }

    /**
     * Return success response
     */
    protected function respondWithSuccess($data = [], $message = 'Success')
    {
        return $this->respond([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Return error response
     */
    protected function respondWithError($message, $statusCode = 400, $errors = [])
    {
        return $this->respond([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }

    /**
     * Return validation error response
     */
    protected function respondWithValidationError($errors)
    {
        return $this->respondWithError('Validation failed', 422, $errors);
    }

    /**
     * Return not found response
     */
    protected function respondNotFound($message = 'Resource not found')
    {
        return $this->respondWithError($message, 404);
    }

    /**
     * Return unauthorized response
     */
    protected function respondUnauthorized($message = 'Unauthorized')
    {
        return $this->respondWithError($message, 401);
    }

    /**
     * Return forbidden response
     */
    protected function respondForbidden($message = 'Forbidden')
    {
        return $this->respondWithError($message, 403);
    }

    /**
     * Return internal server error response
     */
    protected function respondInternalError($message = 'Internal server error')
    {
        return $this->respondWithError($message, 500);
    }

    /**
     * Return paginated response
     */
    protected function respondWithPagination($data, $pagination)
    {
        return $this->respond([
            'data' => $data,
            'pagination' => $pagination
        ]);
    }

    /**
     * Set status code
     */
    protected function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Add header
     */
    protected function addHeader($key, $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Handle API exceptions
     */
    protected function handleException(\Exception $e)
    {
        if ($e instanceof ValidationException) {
            return $this->respondWithValidationError($e->getErrors());
        }

        if ($e instanceof NotFoundException) {
            return $this->respondNotFound($e->getMessage());
        }

        if ($e instanceof UnauthorizedException) {
            return $this->respondUnauthorized($e->getMessage());
        }

        if ($e instanceof ForbiddenException) {
            return $this->respondForbidden($e->getMessage());
        }

        // Log the error
        error_log($e->getMessage());

        return $this->respondInternalError();
    }

    /**
     * Validate request data
     */
    protected function validate($data, $rules)
    {
        $validator = new Validator($data, $rules);
        
        if ($validator->fails()) {
            throw new ValidationException($validator->getErrors());
        }

        return $validator->getValidData();
    }

    /**
     * Get authenticated user
     */
    protected function getAuthenticatedUser()
    {
        // Implementation depends on authentication system
        return $_SESSION['user'] ?? null;
    }

    /**
     * Require authentication
     */
    protected function requireAuth()
    {
        $user = $this->getAuthenticatedUser();
        
        if (!$user) {
            throw new UnauthorizedException();
        }

        return $user;
    }

    /**
     * Check permission
     */
    protected function checkPermission($permission)
    {
        $user = $this->requireAuth();
        
        if (!$user->hasPermission($permission)) {
            throw new ForbiddenException();
        }

        return true;
    }

    /**
     * Parse request filters
     */
    protected function parseFilters($request)
    {
        $filters = [];
        
        // Parse query parameters for filtering
        foreach ($request as $key => $value) {
            if (strpos($key, 'filter_') === 0) {
                $field = substr($key, 7);
                $filters[$field] = $value;
            }
        }

        return $filters;
    }

    /**
     * Parse request sorting
     */
    protected function parseSorting($request)
    {
        $sort = $request['sort'] ?? 'id';
        $direction = $request['direction'] ?? 'asc';

        return ['sort' => $sort, 'direction' => $direction];
    }

    /**
     * Parse pagination parameters
     */
    protected function parsePagination($request)
    {
        $page = max(1, (int)($request['page'] ?? 1));
        $perPage = min(100, max(1, (int)($request['per_page'] ?? 15)));

        return ['page' => $page, 'per_page' => $perPage];
    }

    /**
     * Apply filters to query
     */
    protected function applyFilters($query, $filters)
    {
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, 'LIKE', "%{$value}%");
            }
        }

        return $query;
    }

    /**
     * Apply sorting to query
     */
    protected function applySorting($query, $sorting)
    {
        return $query->orderBy($sorting['sort'], $sorting['direction']);
    }

    /**
     * Transform data for API response
     */
    protected function transform($data, $transformer = null)
    {
        if (!$transformer) {
            return $data;
        }

        if (is_array($data)) {
            return array_map([$transformer, 'transform'], $data);
        }

        return $transformer->transform($data);
    }
}
