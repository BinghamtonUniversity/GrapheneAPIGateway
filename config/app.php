<?php

return [
    'name' => env('APP_NAME', 'Graphene API Gateway'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'America/New_York'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'resources' => [],
    'options' => [],
    'args' => [],
    'auth' => [
        'user' => env('AUTH_USER', 'graphene_proxyserver'),
        'password' => env('AUTH_PASSWORD', 'graphene_proxyserver'),
    ],
    'locale' => env('LOCALE', 'en'),
    'key' => env('APP_KEY', ''),
    'cipher' => 'AES-256-CBC',
    'server_name' => env('SITE_SERVER_NAME', 'dataproxy'),
];