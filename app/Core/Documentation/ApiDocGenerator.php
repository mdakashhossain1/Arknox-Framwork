<?php

namespace App\Core\Documentation;

/**
 * API Documentation Generator
 * 
 * Automatic API documentation generation
 */
class ApiDocGenerator
{
    protected $routes = [];
    protected $models = [];
    protected $controllers = [];

    /**
     * Generate API documentation
     */
    public function generate($outputPath = 'docs/api')
    {
        $this->discoverRoutes();
        $this->discoverModels();
        $this->discoverControllers();

        $documentation = [
            'info' => $this->getApiInfo(),
            'servers' => $this->getServers(),
            'paths' => $this->generatePaths(),
            'components' => $this->generateComponents()
        ];

        // Generate OpenAPI/Swagger documentation
        $this->generateOpenApiDoc($documentation, $outputPath);
        
        // Generate HTML documentation
        $this->generateHtmlDoc($documentation, $outputPath);

        return $documentation;
    }

    /**
     * Get API information
     */
    protected function getApiInfo()
    {
        return [
            'title' => 'Framework API',
            'description' => 'Auto-generated API documentation',
            'version' => '1.0.0',
            'contact' => [
                'name' => 'API Support',
                'email' => 'support@example.com'
            ]
        ];
    }

    /**
     * Get servers configuration
     */
    protected function getServers()
    {
        return [
            [
                'url' => 'http://localhost:8000/api',
                'description' => 'Development server'
            ],
            [
                'url' => 'https://api.example.com',
                'description' => 'Production server'
            ]
        ];
    }

    /**
     * Discover routes
     */
    protected function discoverRoutes()
    {
        // This would scan route files and extract route definitions
        $this->routes = [
            'GET /users' => [
                'controller' => 'UserController@index',
                'description' => 'Get all users',
                'parameters' => [
                    'page' => ['type' => 'integer', 'description' => 'Page number'],
                    'per_page' => ['type' => 'integer', 'description' => 'Items per page']
                ],
                'responses' => [
                    '200' => ['description' => 'Success', 'schema' => 'UserCollection']
                ]
            ],
            'POST /users' => [
                'controller' => 'UserController@store',
                'description' => 'Create a new user',
                'requestBody' => 'UserRequest',
                'responses' => [
                    '201' => ['description' => 'User created', 'schema' => 'User'],
                    '422' => ['description' => 'Validation error']
                ]
            ],
            'GET /users/{id}' => [
                'controller' => 'UserController@show',
                'description' => 'Get user by ID',
                'parameters' => [
                    'id' => ['type' => 'integer', 'description' => 'User ID', 'required' => true]
                ],
                'responses' => [
                    '200' => ['description' => 'Success', 'schema' => 'User'],
                    '404' => ['description' => 'User not found']
                ]
            ]
        ];
    }

    /**
     * Discover models
     */
    protected function discoverModels()
    {
        // This would scan model files and extract model definitions
        $this->models = [
            'User' => [
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'User ID'],
                    'name' => ['type' => 'string', 'description' => 'User name'],
                    'email' => ['type' => 'string', 'format' => 'email', 'description' => 'User email'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time']
                ],
                'required' => ['name', 'email']
            ],
            'Product' => [
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Product ID'],
                    'name' => ['type' => 'string', 'description' => 'Product name'],
                    'price' => ['type' => 'number', 'format' => 'decimal', 'description' => 'Product price'],
                    'description' => ['type' => 'string', 'description' => 'Product description'],
                    'category_id' => ['type' => 'integer', 'description' => 'Category ID'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time']
                ],
                'required' => ['name', 'price']
            ]
        ];
    }

    /**
     * Discover controllers
     */
    protected function discoverControllers()
    {
        // This would scan controller files and extract method documentation
        $this->controllers = [];
    }

    /**
     * Generate paths documentation
     */
    protected function generatePaths()
    {
        $paths = [];

        foreach ($this->routes as $route => $config) {
            list($method, $path) = explode(' ', $route, 2);
            $method = strtolower($method);

            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }

            $paths[$path][$method] = [
                'summary' => $config['description'],
                'description' => $config['description'],
                'parameters' => $this->formatParameters($config['parameters'] ?? []),
                'responses' => $this->formatResponses($config['responses'] ?? [])
            ];

            if (isset($config['requestBody'])) {
                $paths[$path][$method]['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => "#/components/schemas/{$config['requestBody']}"]
                        ]
                    ]
                ];
            }
        }

        return $paths;
    }

    /**
     * Generate components documentation
     */
    protected function generateComponents()
    {
        $schemas = [];

        foreach ($this->models as $model => $config) {
            $schemas[$model] = [
                'type' => 'object',
                'properties' => $config['properties'],
                'required' => $config['required'] ?? []
            ];

            // Generate collection schema
            $schemas["{$model}Collection"] = [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => "#/components/schemas/{$model}"]
                    ],
                    'pagination' => ['$ref' => '#/components/schemas/Pagination']
                ]
            ];

            // Generate request schema
            $schemas["{$model}Request"] = [
                'type' => 'object',
                'properties' => array_filter($config['properties'], function($key) {
                    return !in_array($key, ['id', 'created_at', 'updated_at']);
                }, ARRAY_FILTER_USE_KEY),
                'required' => $config['required'] ?? []
            ];
        }

        // Add common schemas
        $schemas['Pagination'] = [
            'type' => 'object',
            'properties' => [
                'current_page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
                'total' => ['type' => 'integer'],
                'last_page' => ['type' => 'integer']
            ]
        ];

        $schemas['Error'] = [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean', 'example' => false],
                'message' => ['type' => 'string'],
                'errors' => ['type' => 'object']
            ]
        ];

        return ['schemas' => $schemas];
    }

    /**
     * Format parameters for OpenAPI
     */
    protected function formatParameters($parameters)
    {
        $formatted = [];

        foreach ($parameters as $name => $config) {
            $formatted[] = [
                'name' => $name,
                'in' => $config['in'] ?? 'query',
                'description' => $config['description'] ?? '',
                'required' => $config['required'] ?? false,
                'schema' => [
                    'type' => $config['type'] ?? 'string'
                ]
            ];
        }

        return $formatted;
    }

    /**
     * Format responses for OpenAPI
     */
    protected function formatResponses($responses)
    {
        $formatted = [];

        foreach ($responses as $code => $config) {
            $formatted[$code] = [
                'description' => $config['description']
            ];

            if (isset($config['schema'])) {
                $formatted[$code]['content'] = [
                    'application/json' => [
                        'schema' => ['$ref' => "#/components/schemas/{$config['schema']}"]
                    ]
                ];
            }
        }

        return $formatted;
    }

    /**
     * Generate OpenAPI documentation file
     */
    protected function generateOpenApiDoc($documentation, $outputPath)
    {
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        $openApiDoc = [
            'openapi' => '3.0.0',
            'info' => $documentation['info'],
            'servers' => $documentation['servers'],
            'paths' => $documentation['paths'],
            'components' => $documentation['components']
        ];

        file_put_contents(
            $outputPath . '/openapi.json',
            json_encode($openApiDoc, JSON_PRETTY_PRINT)
        );

        file_put_contents(
            $outputPath . '/openapi.yaml',
            yaml_emit($openApiDoc)
        );
    }

    /**
     * Generate HTML documentation
     */
    protected function generateHtmlDoc($documentation, $outputPath)
    {
        $html = $this->generateHtmlTemplate($documentation);
        file_put_contents($outputPath . '/index.html', $html);
    }

    /**
     * Generate HTML template
     */
    protected function generateHtmlTemplate($documentation)
    {
        return '<!DOCTYPE html>
<html>
<head>
    <title>API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@3.52.5/swagger-ui.css">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@3.52.5/swagger-ui-bundle.js"></script>
    <script>
        SwaggerUIBundle({
            url: "./openapi.json",
            dom_id: "#swagger-ui",
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIBundle.presets.standalone
            ]
        });
    </script>
</body>
</html>';
    }
}
