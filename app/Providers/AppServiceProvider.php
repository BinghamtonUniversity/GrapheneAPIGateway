<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Rebind CSRF middleware classes in IoC container to our dummy no-op middleware
        $this->app->bind(
            PreventRequestForgery::class,
            \App\Http\Middleware\DisableCsrfMiddleware::class
        );

        $this->app->bind(
            ValidateCsrfToken::class,
            \App\Http\Middleware\DisableCsrfMiddleware::class
        );

        // Bind custom Exception Handler
        $this->app->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            \App\Exceptions\Handler::class
        );

        // Bind custom Console Kernel
        $this->app->singleton(
            \Illuminate\Contracts\Console\Kernel::class,
            \App\Console\Kernel::class
        );

        // Replicates conditional OCI8 registration
        if (class_exists(\Yajra\Oci8\Oci8ServiceProvider::class)) {
            $this->app->register(\Yajra\Oci8\Oci8ServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}