<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DisableCsrfMiddleware
{
    /**
     * Pass every request directly through without CSRF checks.
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}