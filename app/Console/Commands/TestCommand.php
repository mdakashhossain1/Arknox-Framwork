<?php

namespace App\Console\Commands;

/**
 * Test Command
 * 
 * Enhanced test runner with coverage, parallel execution,
 * and comprehensive reporting
 */
class TestCommand extends BaseCommand
{
    private $phpunitPath;
    private $testsPath;
    private $coveragePath;

    public function __construct()
    {
        $this->phpunitPath = $this->findPhpUnit();
        $this->testsPath = getcwd() . '/tests';
        $this->coveragePath = getcwd() . '/coverage';
    }

    public function execute($arguments)
    {
        $command = $arguments[0] ?? 'run';
        
        switch ($command) {
            case 'run':
                return $this->runTests($arguments);
            case 'coverage':
                return $this->runWithCoverage($arguments);
            case 'unit':
                return $this->runUnitTests($arguments);
            case 'feature':
                return $this->runFeatureTests($arguments);
            case 'parallel':
                return $this->runParallelTests($arguments);
            case 'watch':
                return $this->watchTests($arguments);
            case 'setup':
                return $this->setupTesting();
            default:
                $this->showHelp();
                return false;
        }
    }

    private function showHelp()
    {
        $this->info("🧪 Testing Commands:");
        $this->info("");
        $this->info("  run [options]        Run all tests");
        $this->info("  coverage             Run tests with coverage report");
        $this->info("  unit                 Run unit tests only");
        $this->info("  feature              Run feature tests only");
        $this->info("  parallel             Run tests in parallel");
        $this->info("  watch                Watch files and re-run tests");
        $this->info("  setup                Setup testing environment");
        $this->info("");
        $this->info("Options:");
        $this->info("  --filter=<pattern>   Run tests matching pattern");
        $this->info("  --group=<group>      Run tests in specific group");
        $this->info("  --stop-on-failure    Stop on first failure");
        $this->info("  --verbose            Verbose output");
        $this->info("");
        $this->info("Examples:");
        $this->info("  php console test run --filter=UserTest");
        $this->info("  php console test coverage");
        $this->info("  php console test parallel --processes=4");
    }

    private function runTests($arguments)
    {
        $this->info("🧪 Running tests...");
        
        if (!$this->phpunitPath) {
            $this->error("❌ PHPUnit not found. Run 'php console test setup' first.");
            return false;
        }

        $options = $this->parseTestOptions($arguments);
        $command = $this->buildPhpUnitCommand($options);
        
        $this->info("📋 Command: {$command}");
        $this->info("");
        
        $startTime = microtime(true);
        $exitCode = $this->executeCommand($command);
        $duration = microtime(true) - $startTime;
        
        $this->info("");
        if ($exitCode === 0) {
            $this->success("✅ Tests completed successfully in " . number_format($duration, 2) . "s");
        } else {
            $this->error("❌ Tests failed with exit code: {$exitCode}");
        }
        
        return $exitCode === 0;
    }

    private function runWithCoverage($arguments)
    {
        $this->info("🧪 Running tests with coverage...");
        
        if (!extension_loaded('xdebug') && !extension_loaded('pcov')) {
            $this->warning("⚠️  No coverage driver available (xdebug or pcov required)");
        }
        
        $options = $this->parseTestOptions($arguments);
        $options['coverage'] = true;
        
        $command = $this->buildPhpUnitCommand($options);
        
        $this->info("📋 Command: {$command}");
        $this->info("");
        
        $startTime = microtime(true);
        $exitCode = $this->executeCommand($command);
        $duration = microtime(true) - $startTime;
        
        $this->info("");
        if ($exitCode === 0) {
            $this->success("✅ Tests with coverage completed in " . number_format($duration, 2) . "s");
            $this->info("📊 Coverage report: {$this->coveragePath}/index.html");
        } else {
            $this->error("❌ Tests failed with exit code: {$exitCode}");
        }
        
        return $exitCode === 0;
    }

    private function runUnitTests($arguments)
    {
        $this->info("🧪 Running unit tests...");
        
        $options = $this->parseTestOptions($arguments);
        $options['testsuite'] = 'Unit';
        
        return $this->runTestsWithOptions($options);
    }

    private function runFeatureTests($arguments)
    {
        $this->info("🧪 Running feature tests...");
        
        $options = $this->parseTestOptions($arguments);
        $options['testsuite'] = 'Feature';
        
        return $this->runTestsWithOptions($options);
    }

    private function runParallelTests($arguments)
    {
        $this->info("🧪 Running tests in parallel...");
        
        $processes = 4;
        foreach ($arguments as $arg) {
            if (strpos($arg, '--processes=') === 0) {
                $processes = (int) substr($arg, 12);
                break;
            }
        }
        
        $this->info("🔄 Using {$processes} parallel processes");
        
        $options = $this->parseTestOptions($arguments);
        $options['parallel'] = $processes;
        
        return $this->runTestsWithOptions($options);
    }

    private function watchTests($arguments)
    {
        $this->info("👀 Watching for file changes...");
        $this->info("⏹️  Press Ctrl+C to stop");
        
        $lastRun = 0;
        $watchPaths = [
            getcwd() . '/app',
            getcwd() . '/tests',
            getcwd() . '/config'
        ];
        
        while (true) {
            $latestChange = $this->getLatestFileChange($watchPaths);
            
            if ($latestChange > $lastRun) {
                $this->info("");
                $this->info("🔄 Files changed, running tests...");
                $this->runTests([]);
                $lastRun = time();
            }
            
            sleep(1);
        }
    }

    private function setupTesting()
    {
        $this->info("🔧 Setting up testing environment...");
        
        // Create test directories
        $directories = [
            $this->testsPath,
            $this->testsPath . '/Unit',
            $this->testsPath . '/Feature',
            $this->testsPath . '/Fixtures',
            $this->coveragePath
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                $this->info("✓ Created directory: {$dir}");
            }
        }
        
        // Create phpunit.xml
        $this->createPhpUnitConfig();
        
        // Create sample test files
        $this->createSampleTests();
        
        // Install PHPUnit if not available
        if (!$this->phpunitPath) {
            $this->installPhpUnit();
        }
        
        $this->success("✅ Testing environment setup complete!");
        $this->info("");
        $this->info("📝 Next steps:");
        $this->info("1. Run: php console test run");
        $this->info("2. Write your tests in the tests/ directory");
        $this->info("3. Run with coverage: php console test coverage");
        
        return true;
    }

    private function parseTestOptions($arguments)
    {
        $options = [];
        
        foreach ($arguments as $arg) {
            if (strpos($arg, '--filter=') === 0) {
                $options['filter'] = substr($arg, 9);
            } elseif (strpos($arg, '--group=') === 0) {
                $options['group'] = substr($arg, 8);
            } elseif ($arg === '--stop-on-failure') {
                $options['stop-on-failure'] = true;
            } elseif ($arg === '--verbose') {
                $options['verbose'] = true;
            }
        }
        
        return $options;
    }

    private function buildPhpUnitCommand($options)
    {
        $command = $this->phpunitPath;
        
        // Configuration file
        $configFile = getcwd() . '/phpunit.xml';
        if (file_exists($configFile)) {
            $command .= " --configuration {$configFile}";
        }
        
        // Test suite
        if (isset($options['testsuite'])) {
            $command .= " --testsuite {$options['testsuite']}";
        }
        
        // Filter
        if (isset($options['filter'])) {
            $command .= " --filter {$options['filter']}";
        }
        
        // Group
        if (isset($options['group'])) {
            $command .= " --group {$options['group']}";
        }
        
        // Stop on failure
        if (isset($options['stop-on-failure'])) {
            $command .= " --stop-on-failure";
        }
        
        // Verbose
        if (isset($options['verbose'])) {
            $command .= " --verbose";
        }
        
        // Coverage
        if (isset($options['coverage'])) {
            $command .= " --coverage-html {$this->coveragePath}";
            $command .= " --coverage-text";
        }
        
        // Parallel
        if (isset($options['parallel'])) {
            $command .= " --processes {$options['parallel']}";
        }
        
        return $command;
    }

    private function runTestsWithOptions($options)
    {
        $command = $this->buildPhpUnitCommand($options);
        
        $startTime = microtime(true);
        $exitCode = $this->executeCommand($command);
        $duration = microtime(true) - $startTime;
        
        $this->info("");
        if ($exitCode === 0) {
            $this->success("✅ Tests completed successfully in " . number_format($duration, 2) . "s");
        } else {
            $this->error("❌ Tests failed with exit code: {$exitCode}");
        }
        
        return $exitCode === 0;
    }

    private function executeCommand($command)
    {
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];
        
        $process = proc_open($command, $descriptorspec, $pipes);
        
        if (is_resource($process)) {
            fclose($pipes[0]);
            
            // Read output in real-time
            while (!feof($pipes[1])) {
                echo fgets($pipes[1]);
            }
            
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            return proc_close($process);
        }
        
        return 1;
    }

    private function findPhpUnit()
    {
        $paths = [
            getcwd() . '/vendor/bin/phpunit',
            getcwd() . '/vendor/bin/phpunit.phar',
            'phpunit',
            'phpunit.phar'
        ];
        
        foreach ($paths as $path) {
            if (is_executable($path) || $this->commandExists($path)) {
                return $path;
            }
        }
        
        return null;
    }

    private function commandExists($command)
    {
        $return = shell_exec(sprintf("which %s", escapeshellarg($command)));
        return !empty($return);
    }

    private function getLatestFileChange($paths)
    {
        $latest = 0;
        
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile() && in_array($file->getExtension(), ['php'])) {
                        $latest = max($latest, $file->getMTime());
                    }
                }
            }
        }
        
        return $latest;
    }

    private function createPhpUnitConfig()
    {
        $configPath = getcwd() . '/phpunit.xml';
        
        if (file_exists($configPath)) {
            return;
        }
        
        $config = '<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         cacheResultFile=".phpunit.result.cache"
         executionOrder="depends,defects"
         forceCoversAnnotation="false"
         beStrictAboutCoversAnnotation="true"
         beStrictAboutOutputDuringTests="true"
         beStrictAboutTodoAnnotatedTests="true"
         convertDeprecationsToExceptions="true"
         failOnRisky="true"
         failOnWarning="true"
         verbose="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
    <coverage cacheDirectory=".phpunit.cache/code-coverage"
              processUncoveredFiles="true">
        <include>
            <directory suffix=".php">./app</directory>
        </include>
        <exclude>
            <directory>./app/Console</directory>
            <file>./app/Core/helpers.php</file>
        </exclude>
    </coverage>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_DEBUG" value="true"/>
        <env name="DB_CONNECTION" value="testing"/>
    </php>
</phpunit>';
        
        file_put_contents($configPath, $config);
        $this->info("✓ Created phpunit.xml");
    }

    private function createSampleTests()
    {
        // Create bootstrap file
        $bootstrapPath = $this->testsPath . '/bootstrap.php';
        if (!file_exists($bootstrapPath)) {
            $bootstrap = '<?php

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/TestCase.php";

// Set testing environment
$_ENV["APP_ENV"] = "testing";
$_ENV["APP_DEBUG"] = true;
';
            file_put_contents($bootstrapPath, $bootstrap);
            $this->info("✓ Created tests/bootstrap.php");
        }
        
        // Create sample unit test
        $unitTestPath = $this->testsPath . '/Unit/ExampleTest.php';
        if (!file_exists($unitTestPath)) {
            $unitTest = '<?php

namespace Tests\Unit;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_basic_assertion()
    {
        $this->assertTrue(true);
    }
    
    public function test_application_returns_successful_response()
    {
        $response = $this->get("/");
        
        $response->assertStatus(200);
    }
}';
            file_put_contents($unitTestPath, $unitTest);
            $this->info("✓ Created tests/Unit/ExampleTest.php");
        }
        
        // Create sample feature test
        $featureTestPath = $this->testsPath . '/Feature/ExampleTest.php';
        if (!file_exists($featureTestPath)) {
            $featureTest = '<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\RefreshDatabase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_homepage_loads()
    {
        $response = $this->get("/");
        
        $response->assertStatus(200);
        $response->assertSee("Welcome");
    }
    
    public function test_api_returns_json()
    {
        $response = $this->json("GET", "/api/status");
        
        $response->assertStatus(200);
        $response->assertJson([
            "status" => "ok"
        ]);
    }
}';
            file_put_contents($featureTestPath, $featureTest);
            $this->info("✓ Created tests/Feature/ExampleTest.php");
        }
    }

    private function installPhpUnit()
    {
        $this->info("📦 Installing PHPUnit...");
        
        $composerPath = $this->findComposer();
        if ($composerPath) {
            $command = "{$composerPath} require --dev phpunit/phpunit";
            $this->executeCommand($command);
            $this->phpunitPath = $this->findPhpUnit();
        } else {
            $this->warning("⚠️  Composer not found. Please install PHPUnit manually.");
        }
    }

    private function findComposer()
    {
        $paths = [
            getcwd() . '/composer.phar',
            'composer',
            'composer.phar'
        ];
        
        foreach ($paths as $path) {
            if (is_executable($path) || $this->commandExists($path)) {
                return $path;
            }
        }
        
        return null;
    }
}
