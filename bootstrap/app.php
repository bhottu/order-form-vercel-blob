<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
         $exceptions->report(function (\Throwable $e): void {
        error_log('=== ORIGINAL LARAVEL EXCEPTION ===');
        error_log(get_class($e) . ': ' . $e->getMessage());
        error_log($e->getFile() . ':' . $e->getLine());
        error_log($e->getTraceAsString());
    });
    })->create();
