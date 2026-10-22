<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PublicAPIAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $username = $request->getUser() ?? $request->header('PHP_AUTH_USER');
        $password = $request->getPassword() ?? $request->header('PHP_AUTH_PW');

        $loggedIn = ($username === config('app.auth.user') && $password === config('app.auth.password'));

        if (!$loggedIn) {
            return response('Invalid credentials.', 401, ['WWW-Authenticate' => 'Basic']);
        }

        return $next($request);
    }
}