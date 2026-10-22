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
    ->withMiddleware(function (Middleware $middleware) {
        // Global Middleware (Replaces $app->middleware([...]))
        $middleware->append([
            \App\Http\Middleware\HeaderUserAuth::class,
            \App\Http\Middleware\TrustProxies::class,
        ]);

        // Route Middleware Aliases (Replaces $app->routeMiddleware([...]))
        $middleware->alias([
            'public.api.auth' => \App\Http\Middleware\PublicAPIAuth::class,
            'can'  => \App\Http\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom exception handling configuration
    })->create();