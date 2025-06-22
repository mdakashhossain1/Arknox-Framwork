<?php
/**
 * Next-Generation Framework Bootstrap
 *
 * High-performance bootstrap with dependency injection,
 * service providers, and advanced optimization features
 */

// Load autoloader first
require_once __DIR__ . '/autoload.php';

use App\Core\Application;
use App\Core\Request;
use App\Core\Kernel;

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new application instance
| which serves as the "glue" for all the components and is the IoC container
| for the system binding all of the various parts.
|
*/

$app = new Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__FILE__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
    'App\Core\Contracts\Http\Kernel',
    'App\Core\Http\Kernel'
);

$app->singleton(
    'App\Core\Contracts\Console\Kernel',
    'App\Core\Console\Kernel'
);

$app->singleton(
    'App\Core\Contracts\Debug\ExceptionHandler',
    'App\Core\Exceptions\Handler'
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
