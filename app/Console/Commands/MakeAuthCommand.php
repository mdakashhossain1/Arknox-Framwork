<?php

namespace App\Console\Commands;

/**
 * Make Authentication System Command
 * 
 * Laravel-style command to generate complete authentication system
 * with controllers, views, middleware, and routes
 */
class MakeAuthCommand extends BaseCommand
{
    protected $signature = 'make:auth {--views} {--api} {--force}';
    protected $description = 'Generate complete authentication system with controllers, views, and routes';

    public function execute($args = [])
    {
        $this->info('🔐 Generating Authentication System...');
        
        $options = $this->parseOptions($args);
        $includeViews = isset($options['views']) || !isset($options['api']);
        $apiOnly = isset($options['api']);
        $force = isset($options['force']);

        try {
            // Generate Auth Controller
            $this->generateAuthController($apiOnly, $force);
            
            // Generate Auth Middleware
            $this->generateAuthMiddleware($force);
            
            // Generate User Model (if not exists)
            $this->generateUserModel($force);
            
            // Generate Views (if requested)
            if ($includeViews && !$apiOnly) {
                $this->generateAuthViews($force);
            }
            
            // Generate Routes
            $this->generateAuthRoutes($apiOnly, $force);
            
            // Generate Migration
            $this->generateUserMigration($force);
            
            // Generate Seeder
            $this->generateUserSeeder($force);

            $this->success('✅ Authentication system generated successfully!');
            $this->info('');
            $this->info('📝 Next steps:');
            $this->info('1. Run: php console migrate');
            $this->info('2. Run: php console db:seed --class=UserSeeder');
            if ($includeViews) {
                $this->info('3. Visit: /login to test authentication');
            }
            if ($apiOnly) {
                $this->info('3. Use POST /api/auth/login for API authentication');
            }

        } catch (\Exception $e) {
            $this->error('❌ Error generating authentication system: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    private function generateAuthController($apiOnly, $force)
    {
        $controllerPath = __DIR__ . '/../../Controllers/AuthController.php';
        
        if (file_exists($controllerPath) && !$force) {
            $this->warning('⚠️  AuthController already exists. Use --force to overwrite.');
            return;
        }

        $template = $apiOnly ? $this->getApiAuthControllerTemplate() : $this->getWebAuthControllerTemplate();
        
        if (!is_dir(dirname($controllerPath))) {
            mkdir(dirname($controllerPath), 0755, true);
        }
        
        file_put_contents($controllerPath, $template);
        $this->info('✓ Generated AuthController');
    }

    private function generateAuthMiddleware($force)
    {
        $middlewarePath = __DIR__ . '/../../Middleware/AuthMiddleware.php';
        
        if (file_exists($middlewarePath) && !$force) {
            $this->warning('⚠️  AuthMiddleware already exists. Use --force to overwrite.');
            return;
        }

        $template = $this->getAuthMiddlewareTemplate();
        
        if (!is_dir(dirname($middlewarePath))) {
            mkdir(dirname($middlewarePath), 0755, true);
        }
        
        file_put_contents($middlewarePath, $template);
        $this->info('✓ Generated AuthMiddleware');
    }

    private function generateUserModel($force)
    {
        $modelPath = __DIR__ . '/../../Models/User.php';
        
        if (file_exists($modelPath) && !$force) {
            $this->warning('⚠️  User model already exists. Use --force to overwrite.');
            return;
        }

        $template = $this->getUserModelTemplate();
        
        if (!is_dir(dirname($modelPath))) {
            mkdir(dirname($modelPath), 0755, true);
        }
        
        file_put_contents($modelPath, $template);
        $this->info('✓ Generated User model');
    }

    private function generateAuthViews($force)
    {
        $viewsDir = __DIR__ . '/../../Views/auth';
        
        if (!is_dir($viewsDir)) {
            mkdir($viewsDir, 0755, true);
        }

        $views = [
            'login.php' => $this->getLoginViewTemplate(),
            'register.php' => $this->getRegisterViewTemplate(),
            'forgot-password.php' => $this->getForgotPasswordViewTemplate(),
            'reset-password.php' => $this->getResetPasswordViewTemplate(),
        ];

        foreach ($views as $filename => $template) {
            $viewPath = $viewsDir . '/' . $filename;
            
            if (file_exists($viewPath) && !$force) {
                $this->warning("⚠️  View {$filename} already exists. Use --force to overwrite.");
                continue;
            }
            
            file_put_contents($viewPath, $template);
            $this->info("✓ Generated view: {$filename}");
        }
    }

    private function generateAuthRoutes($apiOnly, $force)
    {
        $routesFile = __DIR__ . '/../../../config/routes.php';
        $authRoutes = $apiOnly ? $this->getApiAuthRoutes() : $this->getWebAuthRoutes();
        
        if (file_exists($routesFile)) {
            $currentRoutes = file_get_contents($routesFile);
            
            // Check if auth routes already exist
            if (strpos($currentRoutes, 'AuthController') !== false && !$force) {
                $this->warning('⚠️  Auth routes may already exist. Use --force to overwrite.');
                return;
            }
            
            // Append auth routes
            $newRoutes = rtrim($currentRoutes, "\n];") . "\n\n    // Authentication Routes\n" . $authRoutes . "\n];";
            file_put_contents($routesFile, $newRoutes);
        } else {
            // Create new routes file
            $template = "<?php\n\nreturn [\n    // Authentication Routes\n" . $authRoutes . "\n];";
            file_put_contents($routesFile, $template);
        }
        
        $this->info('✓ Generated auth routes');
    }

    private function generateUserMigration($force)
    {
        $migrationDir = __DIR__ . '/../../../database/migrations';
        $timestamp = date('Y_m_d_His');
        $migrationPath = $migrationDir . '/' . $timestamp . '_create_users_table.php';
        
        if (!is_dir($migrationDir)) {
            mkdir($migrationDir, 0755, true);
        }

        // Check if users migration already exists
        $existingMigrations = glob($migrationDir . '/*_create_users_table.php');
        if (!empty($existingMigrations) && !$force) {
            $this->warning('⚠️  Users migration already exists. Use --force to overwrite.');
            return;
        }

        $template = $this->getUserMigrationTemplate();
        file_put_contents($migrationPath, $template);
        $this->info('✓ Generated users migration');
    }

    private function generateUserSeeder($force)
    {
        $seederDir = __DIR__ . '/../../../database/seeders';
        $seederPath = $seederDir . '/UserSeeder.php';
        
        if (!is_dir($seederDir)) {
            mkdir($seederDir, 0755, true);
        }

        if (file_exists($seederPath) && !$force) {
            $this->warning('⚠️  UserSeeder already exists. Use --force to overwrite.');
            return;
        }

        $template = $this->getUserSeederTemplate();
        file_put_contents($seederPath, $template);
        $this->info('✓ Generated UserSeeder');
    }

    // Template methods would be implemented here...
    private function getApiAuthControllerTemplate() { return '<?php /* API Auth Controller Template */'; }
    private function getWebAuthControllerTemplate() { return '<?php /* Web Auth Controller Template */'; }
    private function getAuthMiddlewareTemplate() { return '<?php /* Auth Middleware Template */'; }
    private function getUserModelTemplate() { return '<?php /* User Model Template */'; }
    private function getLoginViewTemplate() { return '<!-- Login View Template -->'; }
    private function getRegisterViewTemplate() { return '<!-- Register View Template -->'; }
    private function getForgotPasswordViewTemplate() { return '<!-- Forgot Password View Template -->'; }
    private function getResetPasswordViewTemplate() { return '<!-- Reset Password View Template -->'; }
    private function getApiAuthRoutes() { return "    'POST /api/auth/login' => 'AuthController@apiLogin',\n    'POST /api/auth/logout' => 'AuthController@apiLogout',\n    'POST /api/auth/refresh' => 'AuthController@refresh',"; }
    private function getWebAuthRoutes() { return "    'GET /login' => 'AuthController@showLogin',\n    'POST /login' => 'AuthController@login',\n    'GET /register' => 'AuthController@showRegister',\n    'POST /register' => 'AuthController@register',\n    'POST /logout' => 'AuthController@logout',"; }
    private function getUserMigrationTemplate() { return '<?php /* User Migration Template */'; }
    private function getUserSeederTemplate() { return '<?php /* User Seeder Template */'; }
}
