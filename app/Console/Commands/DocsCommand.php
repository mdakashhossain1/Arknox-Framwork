<?php

namespace App\Console\Commands;

/**
 * API Documentation Generator Command
 * 
 * Automatically generates OpenAPI/Swagger documentation
 * from controller annotations and route definitions
 */
class DocsCommand extends BaseCommand
{
    private $outputDir;
    private $apiRoutes = [];
    private $controllers = [];

    public function __construct()
    {
        $this->outputDir = getcwd() . '/docs';
    }

    public function execute($arguments)
    {
        $command = $arguments[0] ?? 'generate';
        
        switch ($command) {
            case 'generate':
                return $this->generateDocs();
            case 'serve':
                return $this->serveDocs();
            case 'export':
                return $this->exportDocs($arguments);
            default:
                $this->showHelp();
                return false;
        }
    }

    private function showHelp()
    {
        $this->info("📚 API Documentation Commands:");
        $this->info("");
        $this->info("  generate             Generate API documentation");
        $this->info("  serve                Serve documentation on local server");
        $this->info("  export <format>      Export docs (json, yaml, html, postman)");
        $this->info("");
        $this->info("Examples:");
        $this->info("  php console docs generate");
        $this->info("  php console docs serve");
        $this->info("  php console docs export postman");
    }

    private function generateDocs()
    {
        $this->info("📚 Generating API Documentation...");

        // Create output directory
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }

        // Scan routes and controllers
        $this->scanRoutes();
        $this->scanControllers();

        // Generate OpenAPI specification
        $openApiSpec = $this->generateOpenApiSpec();

        // Save OpenAPI JSON
        file_put_contents($this->outputDir . '/openapi.json', json_encode($openApiSpec, JSON_PRETTY_PRINT));

        // Generate HTML documentation
        $this->generateHtmlDocs($openApiSpec);

        // Generate Postman collection
        $this->generatePostmanCollection($openApiSpec);

        $this->success("✅ Documentation generated successfully!");
        $this->info("📁 Output directory: {$this->outputDir}");
        $this->info("🌐 View docs: {$this->outputDir}/index.html");

        return true;
    }

    private function scanRoutes()
    {
        $routesFile = getcwd() . '/config/routes.php';
        $apiRoutesFile = getcwd() . '/config/api_routes.php';

        // Load regular routes
        if (file_exists($routesFile)) {
            $routes = require $routesFile;
            $this->processRoutes($routes, 'web');
        }

        // Load API routes
        if (file_exists($apiRoutesFile)) {
            $routes = require $apiRoutesFile;
            $this->processRoutes($routes, 'api');
        }

        $this->info("✓ Scanned " . count($this->apiRoutes) . " routes");
    }

    private function processRoutes($routes, $type)
    {
        foreach ($routes as $route => $handler) {
            [$method, $path] = explode(' ', $route, 2);
            
            $this->apiRoutes[] = [
                'method' => strtolower($method),
                'path' => $path,
                'handler' => $handler,
                'type' => $type
            ];
        }
    }

    private function scanControllers()
    {
        $controllersDir = getcwd() . '/app/Controllers';
        $this->scanDirectory($controllersDir);
        
        $this->info("✓ Scanned " . count($this->controllers) . " controllers");
    }

    private function scanDirectory($dir)
    {
        if (!is_dir($dir)) return;

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $filePath = $dir . '/' . $file;
            
            if (is_dir($filePath)) {
                $this->scanDirectory($filePath);
            } elseif (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $this->analyzeController($filePath);
            }
        }
    }

    private function analyzeController($filePath)
    {
        $content = file_get_contents($filePath);
        $className = $this->extractClassName($content);
        
        if (!$className) return;

        // Extract methods and their documentation
        $methods = $this->extractMethods($content);
        
        $this->controllers[$className] = [
            'file' => $filePath,
            'methods' => $methods
        ];
    }

    private function extractClassName($content)
    {
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractMethods($content)
    {
        $methods = [];
        
        // Extract public methods with their docblocks
        preg_match_all('/\/\*\*(.*?)\*\/\s*public\s+function\s+(\w+)\s*\([^)]*\)/s', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $docblock = $match[1];
            $methodName = $match[2];
            
            $methods[$methodName] = [
                'docblock' => $this->parseDocblock($docblock),
                'parameters' => $this->extractParameters($match[0])
            ];
        }
        
        return $methods;
    }

    private function parseDocblock($docblock)
    {
        $lines = explode("\n", $docblock);
        $parsed = [
            'summary' => '',
            'description' => '',
            'parameters' => [],
            'returns' => '',
            'tags' => []
        ];

        $currentSection = 'summary';
        
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B*");
            
            if (empty($line)) continue;
            
            if (strpos($line, '@') === 0) {
                $parts = explode(' ', $line, 3);
                $tag = substr($parts[0], 1);
                
                switch ($tag) {
                    case 'param':
                        $parsed['parameters'][] = [
                            'type' => $parts[1] ?? '',
                            'name' => $parts[2] ?? '',
                            'description' => $parts[3] ?? ''
                        ];
                        break;
                    case 'return':
                    case 'returns':
                        $parsed['returns'] = implode(' ', array_slice($parts, 1));
                        break;
                    default:
                        $parsed['tags'][$tag] = implode(' ', array_slice($parts, 1));
                }
            } else {
                if ($currentSection === 'summary' && empty($parsed['summary'])) {
                    $parsed['summary'] = $line;
                    $currentSection = 'description';
                } else {
                    $parsed['description'] .= $line . ' ';
                }
            }
        }
        
        $parsed['description'] = trim($parsed['description']);
        
        return $parsed;
    }

    private function extractParameters($methodSignature)
    {
        // Extract parameters from method signature
        if (preg_match('/\(([^)]*)\)/', $methodSignature, $matches)) {
            $params = $matches[1];
            // Parse parameters (simplified)
            return explode(',', $params);
        }
        return [];
    }

    private function generateOpenApiSpec()
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => config('app.app_name', 'MVC Framework API'),
                'description' => 'Auto-generated API documentation',
                'version' => '1.0.0'
            ],
            'servers' => [
                [
                    'url' => config('app.app_url', 'http://localhost'),
                    'description' => 'Development server'
                ]
            ],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT'
                    ]
                ]
            ]
        ];

        // Process routes
        foreach ($this->apiRoutes as $route) {
            $path = $this->convertPathToOpenApi($route['path']);
            $method = $route['method'];
            
            if (!isset($spec['paths'][$path])) {
                $spec['paths'][$path] = [];
            }

            $operation = $this->generateOperation($route);
            $spec['paths'][$path][$method] = $operation;
        }

        return $spec;
    }

    private function convertPathToOpenApi($path)
    {
        // Convert {id} to {id} (already OpenAPI format)
        return $path;
    }

    private function generateOperation($route)
    {
        $operation = [
            'summary' => $this->generateSummary($route),
            'description' => $this->generateDescription($route),
            'tags' => [$this->extractTag($route['handler'])],
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Add parameters for path variables
        if (preg_match_all('/\{(\w+)\}/', $route['path'], $matches)) {
            $operation['parameters'] = [];
            foreach ($matches[1] as $param) {
                $operation['parameters'][] = [
                    'name' => $param,
                    'in' => 'path',
                    'required' => true,
                    'schema' => [
                        'type' => 'string'
                    ]
                ];
            }
        }

        // Add request body for POST/PUT methods
        if (in_array($route['method'], ['post', 'put', 'patch'])) {
            $operation['requestBody'] = [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object'
                        ]
                    ]
                ]
            ];
        }

        // Add security for API routes
        if ($route['type'] === 'api') {
            $operation['security'] = [
                ['bearerAuth' => []]
            ];
        }

        return $operation;
    }

    private function generateSummary($route)
    {
        $action = $this->getActionFromHandler($route['handler']);
        $resource = $this->getResourceFromPath($route['path']);
        
        $actionMap = [
            'index' => "List {$resource}",
            'show' => "Get {$resource}",
            'store' => "Create {$resource}",
            'update' => "Update {$resource}",
            'destroy' => "Delete {$resource}"
        ];

        return $actionMap[$action] ?? ucfirst($route['method']) . ' ' . $route['path'];
    }

    private function generateDescription($route)
    {
        return "Auto-generated endpoint for {$route['handler']}";
    }

    private function extractTag($handler)
    {
        if (strpos($handler, '@') !== false) {
            [$controller, $method] = explode('@', $handler);
            return str_replace('Controller', '', basename($controller));
        }
        return 'Default';
    }

    private function getActionFromHandler($handler)
    {
        if (strpos($handler, '@') !== false) {
            [$controller, $method] = explode('@', $handler);
            return $method;
        }
        return 'unknown';
    }

    private function getResourceFromPath($path)
    {
        $segments = explode('/', trim($path, '/'));
        foreach ($segments as $segment) {
            if (!empty($segment) && strpos($segment, '{') === false) {
                return rtrim($segment, 's'); // Simple singularization
            }
        }
        return 'resource';
    }

    private function generateHtmlDocs($spec)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>' . $spec['info']['title'] . '</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@3.52.5/swagger-ui.css" />
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin:0; background: #fafafa; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@3.52.5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@3.52.5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: "./openapi.json",
                dom_id: "#swagger-ui",
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout"
            });
        };
    </script>
</body>
</html>';

        file_put_contents($this->outputDir . '/index.html', $html);
    }

    private function generatePostmanCollection($spec)
    {
        $collection = [
            'info' => [
                'name' => $spec['info']['title'],
                'description' => $spec['info']['description'],
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
            ],
            'item' => []
        ];

        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $item = [
                    'name' => $operation['summary'],
                    'request' => [
                        'method' => strtoupper($method),
                        'header' => [
                            [
                                'key' => 'Content-Type',
                                'value' => 'application/json'
                            ]
                        ],
                        'url' => [
                            'raw' => '{{base_url}}' . $path,
                            'host' => ['{{base_url}}'],
                            'path' => explode('/', trim($path, '/'))
                        ]
                    ]
                ];

                if (isset($operation['security'])) {
                    $item['request']['header'][] = [
                        'key' => 'Authorization',
                        'value' => 'Bearer {{token}}'
                    ];
                }

                $collection['item'][] = $item;
            }
        }

        file_put_contents($this->outputDir . '/postman_collection.json', json_encode($collection, JSON_PRETTY_PRINT));
    }

    private function serveDocs()
    {
        if (!file_exists($this->outputDir . '/index.html')) {
            $this->error("❌ Documentation not found. Run 'php console docs generate' first.");
            return false;
        }

        $this->info("🌐 Starting documentation server...");
        $this->info("📍 URL: http://localhost:8080");
        $this->info("⏹️  Press Ctrl+C to stop");

        $command = "php -S localhost:8080 -t {$this->outputDir}";
        passthru($command);

        return true;
    }

    private function exportDocs($arguments)
    {
        $format = $arguments[1] ?? 'json';
        
        if (!file_exists($this->outputDir . '/openapi.json')) {
            $this->error("❌ Documentation not found. Run 'php console docs generate' first.");
            return false;
        }

        $spec = json_decode(file_get_contents($this->outputDir . '/openapi.json'), true);

        switch ($format) {
            case 'yaml':
                // Convert to YAML (simplified)
                $yaml = $this->arrayToYaml($spec);
                file_put_contents($this->outputDir . '/openapi.yaml', $yaml);
                $this->success("✅ Exported to openapi.yaml");
                break;
            case 'postman':
                $this->info("✅ Postman collection already available at postman_collection.json");
                break;
            case 'html':
                $this->info("✅ HTML documentation already available at index.html");
                break;
            default:
                $this->info("✅ JSON documentation already available at openapi.json");
        }

        return true;
    }

    private function arrayToYaml($array, $indent = 0)
    {
        $yaml = '';
        $spaces = str_repeat('  ', $indent);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $yaml .= $spaces . $key . ":\n" . $this->arrayToYaml($value, $indent + 1);
            } else {
                $yaml .= $spaces . $key . ': ' . $value . "\n";
            }
        }

        return $yaml;
    }
}
