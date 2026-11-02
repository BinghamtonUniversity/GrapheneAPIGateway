<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Graphene API Gateway initialized.');
})->purpose('Display startup message');