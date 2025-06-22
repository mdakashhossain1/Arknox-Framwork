<?php

namespace App\Console\Commands;

/**
 * Make API Resource Command
 * 
 * Laravel-style command to generate complete API resources
 * with controller, model, migration, seeder, and routes
 */
class MakeResourceCommand extends BaseCommand
{
    protected $signature = 'make:resource {name} {--api} {--model} {--migration} {--seeder} {--all} {--force}';
    protected $description = 'Generate a complete API resource with controller, model, migration, and routes';

    public function execute($args = [])
    {
        if (empty($args)) {
            $this->error('❌ Resource name is required');
            $this->info('Usage: php console make:resource User --all');
            return false;
        }

        $name = $args[0];
        $options = $this->parseOptions($args);
        
        $this->info("🚀 Generating API Resource: {$name}");
        
        $generateAll = isset($options['all']);
        $force = isset($options['force']);

        try {
            // Generate API Controller
            if ($generateAll || isset($options['api'])) {
                $this->generateApiController($name, $force);
            }
            
            // Generate Model
            if ($generateAll || isset($options['model'])) {
                $this->generateModel($name, $force);
            }
            
            // Generate Migration
            if ($generateAll || isset($options['migration'])) {
                $this->generateMigration($name, $force);
            }
            
            // Generate Seeder
            if ($generateAll || isset($options['seeder'])) {
                $this->generateSeeder($name, $force);
            }
            
            // Generate Routes
            if ($generateAll) {
                $this->generateApiRoutes($name, $force);
            }

            $this->success("✅ API Resource '{$name}' generated successfully!");
            $this->info('');
            $this->info('📝 Next steps:');
            $this->info("1. Run: php console migrate");
            $this->info("2. Run: php console db:seed --class={$name}Seeder");
            $this->info("3. Test API endpoints:");
            $this->info("   GET    /api/{$this->pluralize(strtolower($name))}");
            $this->info("   POST   /api/{$this->pluralize(strtolower($name))}");
            $this->info("   GET    /api/{$this->pluralize(strtolower($name))}/{id}");
            $this->info("   PUT    /api/{$this->pluralize(strtolower($name))}/{id}");
            $this->info("   DELETE /api/{$this->pluralize(strtolower($name))}/{id}");

        } catch (\Exception $e) {
            $this->error('❌ Error generating resource: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    private function generateApiController($name, $force)
    {
        $controllerName = $name . 'Controller';
        $controllerPath = __DIR__ . '/../../Controllers/Api/' . $controllerName . '.php';
        
        if (file_exists($controllerPath) && !$force) {
            $this->warning("⚠️  {$controllerName} already exists. Use --force to overwrite.");
            return;
        }

        $template = $this->getApiControllerTemplate($name);
        
        if (!is_dir(dirname($controllerPath))) {
            mkdir(dirname($controllerPath), 0755, true);
        }
        
        file_put_contents($controllerPath, $template);
        $this->info("✓ Generated API Controller: {$controllerName}");
    }

    private function generateModel($name, $force)
    {
        $modelPath = __DIR__ . '/../../Models/' . $name . '.php';
        
        if (file_exists($modelPath) && !$force) {
            $this->warning("⚠️  {$name} model already exists. Use --force to overwrite.");
            return;
        }

        $template = $this->getModelTemplate($name);
        
        if (!is_dir(dirname($modelPath))) {
            mkdir(dirname($modelPath), 0755, true);
        }
        
        file_put_contents($modelPath, $template);
        $this->info("✓ Generated Model: {$name}");
    }

    private function generateMigration($name, $force)
    {
        $migrationDir = __DIR__ . '/../../../database/migrations';
        $timestamp = date('Y_m_d_His');
        $tableName = $this->pluralize(strtolower($name));
        $migrationPath = $migrationDir . '/' . $timestamp . '_create_' . $tableName . '_table.php';
        
        if (!is_dir($migrationDir)) {
            mkdir($migrationDir, 0755, true);
        }

        // Check if migration already exists
        $existingMigrations = glob($migrationDir . '/*_create_' . $tableName . '_table.php');
        if (!empty($existingMigrations) && !$force) {
            $this->warning("⚠️  Migration for {$tableName} already exists. Use --force to overwrite.");
            return;
        }

        $template = $this->getMigrationTemplate($name, $tableName);
        file_put_contents($migrationPath, $template);
        $this->info("✓ Generated Migration: create_{$tableName}_table");
    }

    private function generateSeeder($name, $force)
    {
        $seederDir = __DIR__ . '/../../../database/seeders';
        $seederName = $name . 'Seeder';
        $seederPath = $seederDir . '/' . $seederName . '.php';
        
        if (!is_dir($seederDir)) {
            mkdir($seederDir, 0755, true);
        }

        if (file_exists($seederPath) && !$force) {
            $this->warning("⚠️  {$seederName} already exists. Use --force to overwrite.");
            return;
        }

        $template = $this->getSeederTemplate($name);
        file_put_contents($seederPath, $template);
        $this->info("✓ Generated Seeder: {$seederName}");
    }

    private function generateApiRoutes($name, $force)
    {
        $routesFile = __DIR__ . '/../../../config/api_routes.php';
        $resourceName = strtolower($name);
        $pluralName = $this->pluralize($resourceName);
        
        $routes = $this->getApiRoutesTemplate($name, $pluralName);
        
        if (file_exists($routesFile)) {
            $currentRoutes = file_get_contents($routesFile);
            
            // Check if routes already exist
            if (strpos($currentRoutes, $name . 'Controller') !== false && !$force) {
                $this->warning("⚠️  API routes for {$name} may already exist. Use --force to overwrite.");
                return;
            }
            
            // Append routes
            $newRoutes = rtrim($currentRoutes, "\n];") . "\n\n    // {$name} API Routes\n" . $routes . "\n];";
            file_put_contents($routesFile, $newRoutes);
        } else {
            // Create new API routes file
            $template = "<?php\n\nreturn [\n    // {$name} API Routes\n" . $routes . "\n];";
            file_put_contents($routesFile, $template);
        }
        
        $this->info("✓ Generated API routes for {$name}");
    }

    private function getApiControllerTemplate($name)
    {
        $modelName = $name;
        $variableName = strtolower($name);
        $pluralName = $this->pluralize($variableName);

        return "<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\\{$modelName};

/**
 * {$name} API Controller
 * 
 * RESTful API controller for {$name} resource
 */
class {$name}Controller extends Controller
{
    /**
     * Display a listing of {$pluralName}
     */
    public function index(Request \$request)
    {
        try {
            \$page = \$request->input('page', 1);
            \$limit = \$request->input('limit', 15);
            \$offset = (\$page - 1) * \$limit;

            \${$pluralName} = {$modelName}::paginate(\$limit, \$offset);
            \$total = {$modelName}::count();

            return Response::json([
                'data' => \${$pluralName},
                'pagination' => [
                    'current_page' => \$page,
                    'per_page' => \$limit,
                    'total' => \$total,
                    'last_page' => ceil(\$total / \$limit)
                ]
            ]);
        } catch (\Exception \$e) {
            return Response::json(['error' => 'Failed to fetch {$pluralName}'], 500);
        }
    }

    /**
     * Store a newly created {$variableName}
     */
    public function store(Request \$request)
    {
        try {
            \$data = \$request->only([
                // Add your fillable fields here
                'name', 'email', 'description'
            ]);

            \${$variableName} = {$modelName}::create(\$data);

            return Response::json([
                'message' => '{$name} created successfully',
                'data' => \${$variableName}
            ], 201);
        } catch (\Exception \$e) {
            return Response::json(['error' => 'Failed to create {$variableName}'], 500);
        }
    }

    /**
     * Display the specified {$variableName}
     */
    public function show(Request \$request, \$id)
    {
        try {
            \${$variableName} = {$modelName}::find(\$id);

            if (!\${$variableName}) {
                return Response::json(['error' => '{$name} not found'], 404);
            }

            return Response::json(['data' => \${$variableName}]);
        } catch (\Exception \$e) {
            return Response::json(['error' => 'Failed to fetch {$variableName}'], 500);
        }
    }

    /**
     * Update the specified {$variableName}
     */
    public function update(Request \$request, \$id)
    {
        try {
            \${$variableName} = {$modelName}::find(\$id);

            if (!\${$variableName}) {
                return Response::json(['error' => '{$name} not found'], 404);
            }

            \$data = \$request->only([
                // Add your fillable fields here
                'name', 'email', 'description'
            ]);

            \${$variableName}->update(\$data);

            return Response::json([
                'message' => '{$name} updated successfully',
                'data' => \${$variableName}
            ]);
        } catch (\Exception \$e) {
            return Response::json(['error' => 'Failed to update {$variableName}'], 500);
        }
    }

    /**
     * Remove the specified {$variableName}
     */
    public function destroy(Request \$request, \$id)
    {
        try {
            \${$variableName} = {$modelName}::find(\$id);

            if (!\${$variableName}) {
                return Response::json(['error' => '{$name} not found'], 404);
            }

            \${$variableName}->delete();

            return Response::json(['message' => '{$name} deleted successfully']);
        } catch (\Exception \$e) {
            return Response::json(['error' => 'Failed to delete {$variableName}'], 500);
        }
    }
}";
    }

    private function getModelTemplate($name)
    {
        $tableName = $this->pluralize(strtolower($name));

        return "<?php

namespace App\Models;

use App\Core\Database\Model;

/**
 * {$name} Model
 */
class {$name} extends Model
{
    protected \$table = '{$tableName}';
    
    protected \$fillable = [
        'name',
        'email',
        'description',
        // Add your fillable fields here
    ];

    protected \$hidden = [
        'password',
        // Add fields to hide from JSON output
    ];

    protected \$casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        // Add field type casting here
    ];
}";
    }

    private function getMigrationTemplate($name, $tableName)
    {
        return "<?php

/**
 * Create {$tableName} table migration
 */
return [
    'up' => \"
        CREATE TABLE {$tableName} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    \",
    'down' => \"DROP TABLE IF EXISTS {$tableName}\"
];";
    }

    private function getSeederTemplate($name)
    {
        $tableName = $this->pluralize(strtolower($name));

        return "<?php

/**
 * {$name} Seeder
 */
class {$name}Seeder
{
    public function run()
    {
        \$db = \\App\\Core\\Database::getInstance();
        
        \$sampleData = [
            [
                'name' => 'Sample {$name} 1',
                'email' => 'sample1@example.com',
                'description' => 'This is a sample {$name}',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Sample {$name} 2',
                'email' => 'sample2@example.com',
                'description' => 'This is another sample {$name}',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];

        foreach (\$sampleData as \$data) {
            \$db->insert('{$tableName}', \$data);
        }

        echo \"Seeded {$tableName} table with sample data\\n\";
    }
}";
    }

    private function getApiRoutesTemplate($name, $pluralName)
    {
        return "    'GET /api/{$pluralName}' => 'Api\\{$name}Controller@index',
    'POST /api/{$pluralName}' => 'Api\\{$name}Controller@store',
    'GET /api/{$pluralName}/{id}' => 'Api\\{$name}Controller@show',
    'PUT /api/{$pluralName}/{id}' => 'Api\\{$name}Controller@update',
    'DELETE /api/{$pluralName}/{id}' => 'Api\\{$name}Controller@destroy',";
    }

    private function pluralize($word)
    {
        // Simple pluralization rules
        if (substr($word, -1) === 'y') {
            return substr($word, 0, -1) . 'ies';
        } elseif (in_array(substr($word, -1), ['s', 'x', 'z']) || in_array(substr($word, -2), ['ch', 'sh'])) {
            return $word . 'es';
        } else {
            return $word . 's';
        }
    }
}
