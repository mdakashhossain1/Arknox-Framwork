<?php
/**
 * Routes Configuration
 *
 * This file defines the application routes and their corresponding controllers.
 * Add your routes here following the pattern: 'METHOD /path' => 'Controller@method'
 */

return [
    // Welcome page routes
    'GET /' => 'HomeController@index',
    'GET /info' => 'HomeController@info',

    // Documentation routes
    'GET /docs' => 'DocumentationController@index',
    'GET /docs/search' => 'DocumentationController@search',
    'GET /docs/{section}' => 'DocumentationController@section',

    // Example routes
    'GET /posts' => 'PostController@index',
    'GET /posts/{id}' => 'PostController@show',
    'POST /posts' => 'PostController@store',
    'PUT /posts/{id}' => 'PostController@update',
    'DELETE /posts/{id}' => 'PostController@destroy',

    // API routes
    'GET /api/posts' => 'Api\\PostController@index',
    'GET /api/posts/{id}' => 'Api\\PostController@show',
    'POST /api/posts' => 'Api\\PostController@store',
    'PUT /api/posts/{id}' => 'Api\\PostController@update',
    'DELETE /api/posts/{id}' => 'Api\\PostController@destroy',

    // Health check and system info
    'GET /health' => function() {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'framework' => 'Arknox Framework',
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ]);
    },

    'GET /api/health' => function() {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'healthy',
            'checks' => [
                'database' => 'ok',
                'cache' => 'ok',
                'storage' => 'ok'
            ],
            'timestamp' => date('c'),
            'uptime' => time() - $_SERVER['REQUEST_TIME']
        ]);
    },
];
