<?php
/**
 * Next-Generation Framework Entry Point
 *
 * High-performance entry point with advanced request handling,
 * middleware processing, and optimized response delivery
 */

use App\Core\Request;
use App\Core\Response;

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists(__DIR__.'/storage/framework/maintenance.php')) {
    require __DIR__.'/storage/framework/maintenance.php';
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require_once __DIR__.'/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/bootstrap.php';

try {
    // Create request instance
    $request = Request::capture();

    // Handle the request through the application
    $response = $app->handle($request);

    // Send the response
    $response->send();

    // Terminate the application
    $app->terminate($request, $response);

} catch (Throwable $e) {
    // Handle uncaught exceptions
    $handler = $app->make('App\Core\Contracts\Debug\ExceptionHandler');
    $handler->report($e);
    $handler->render($request ?? null, $e)->send();
}
