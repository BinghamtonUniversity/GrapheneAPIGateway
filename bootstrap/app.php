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
    ->withProviders([
        // Core Framework Service Providers
        \Illuminate\View\ViewServiceProvider::class,
        \Illuminate\Filesystem\FilesystemServiceProvider::class,
        \Illuminate\Translation\TranslationServiceProvider::class,
        \Illuminate\Validation\ValidationServiceProvider::class,

        // Application Service Providers
        \App\Providers\AppServiceProvider::class,
        \App\Providers\AuthServiceProvider::class,
        \App\Providers\EventServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Remove CSRF validation for stateless API calls
        $middleware->removeFromGroup('web', [
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);

        // Global Middleware
        $middleware->append([
            \App\Http\Middleware\HeaderUserAuth::class,
            \App\Http\Middleware\TrustProxies::class,
        ]);

        // Route Middleware Aliases
        $middleware->alias([
            'public.api.auth' => \App\Http\Middleware\PublicAPIAuth::class,
            'can'  => \App\Http\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom exception handling
    })->create();