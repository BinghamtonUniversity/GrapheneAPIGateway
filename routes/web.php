<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\APIUsersController;
use App\Http\Controllers\EnvironmentsController;
use App\Http\Controllers\APIsController;
use App\Http\Controllers\APIVersionsController;
use App\Http\Controllers\APIInstancesController;
use App\Http\Controllers\ResourcesController;
use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\ExecController;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return 'Welcome!';
});

Route::prefix('api')->middleware('public.api.auth')->group(function () {

    // API Users
    Route::get('/api_users', [APIUsersController::class, 'browse'])->middleware('can:viewAny,App\Models\APIUser');
    Route::get('/api_users/{apiuser_id}', [APIUsersController::class, 'read'])->middleware('can:view,App\Models\APIUser');
    Route::get('/api_users/{apiuser_id}/decrypted_secret', [APIUsersController::class, 'decrypted_app_secret'])->middleware('can:getDecryptedSecret,App\Models\APIUser');
    Route::put('/api_users/{apiuser_id}', [APIUsersController::class, 'edit'])->middleware('can:manage,App\Models\APIUser');
    Route::post('/api_users', [APIUsersController::class, 'add'])->middleware('can:manage,App\Models\APIUser');
    Route::delete('/api_users/{apiuser_id}', [APIUsersController::class, 'delete'])->middleware('can:delete,App\Models\APIUser');

    // Environments
    Route::get('/environments', [EnvironmentsController::class, 'browse'])->middleware('can:viewAny,App\Models\Environment');
    Route::get('/environments/{environment_id}', [EnvironmentsController::class, 'read'])->middleware('can:viewAny,App\Models\Environment');
    Route::put('/environments/{environment_id}', [EnvironmentsController::class, 'edit'])->middleware('can:manage,environment_id');
    Route::post('/environments', [EnvironmentsController::class, 'add'])->middleware('can:create,App\Models\Environment');
    Route::delete('/environments/{environment_id}', [EnvironmentsController::class, 'delete'])->middleware('can:manage,environment_id');
    Route::get('/environments/{environment_id}/api_users', [EnvironmentsController::class, 'api_users'])->middleware('can:viewAny,App\Models\APIUser');

    // APIs
    Route::get('/apis', [APIsController::class, 'browse'])->middleware('can:viewAny,App\Models\API');
    Route::get('/apis/{api_id}', [APIsController::class, 'read'])->middleware('can:view,api_id');
    Route::get('/apis/{api_id}/versions/latest', [APIsController::class, 'latest_version'])->middleware('can:view,api_id');
    Route::get('/apis/{api_id}/versions', [APIsController::class, 'versions'])->middleware('can:view,api_id');
    Route::put('/apis/{api_id}', [APIsController::class, 'edit'])->middleware('can:manage,api_id');
    Route::put('/apis/{api_id}/publish', [APIsController::class, 'publish'])->middleware('can:manage,api_id');
    Route::put('/apis/{api_id}/code', [APIsController::class, 'code'])->middleware('can:manage,api_id');
    Route::post('/apis', [APIsController::class, 'add'])->middleware('can:create,App\Models\API');
    Route::delete('/apis/{api_id}', [APIsController::class, 'delete'])->middleware('can:manage,api_id');

    // API Developers
    Route::get('/apis/{api_id}/developers', [APIsController::class, 'browseDevelopers'])->middleware('can:viewAny,App\Models\API');
    Route::post('/apis/{api_id}/developers/{user_id}', [APIsController::class, 'addDeveloper'])->middleware('can:manage_developers,api_id');
    Route::delete('/apis/{api_id}/developers/{user_id}', [APIsController::class, 'deleteDeveloper'])->middleware('can:manage_developers,api_id');

    // API Versions
    Route::get('/api_versions', [APIVersionsController::class, 'browse'])->middleware('can:viewAny,App\Models\APIVersion');
    Route::get('/api_versions/{api_version_id}', [APIVersionsController::class, 'read'])->middleware('can:viewAny,App\Models\APIVersion');
    Route::put('/api_versions/{api_version_id}', [APIVersionsController::class, 'edit'])->middleware('can:view_manage,api_version_id');
    Route::post('/api_versions', [APIVersionsController::class, 'add']);
    Route::delete('/api_versions/{api_version_id}', [APIVersionsController::class, 'delete'])->middleware('can:view_manage,api_version_id');
    
    // API Instances
    Route::get('/api_instances', [APIInstancesController::class, 'browse'])->middleware('can:viewAny,App\Models\APIInstance');
    Route::get('/api_instances/{api_instance_id}', [APIInstancesController::class, 'read'])->middleware('can:view,api_instance_id');
    Route::put('/api_instances/{api_instance_id}', [APIInstancesController::class, 'edit'])->middleware('can:manage,api_instance_id');
    Route::post('/api_instances', [APIInstancesController::class, 'add'])->middleware('can:create,App\Models\APIInstance');
    Route::delete('/api_instances/{api_instance_id}', [APIInstancesController::class, 'delete'])->middleware('can:manage,api_instance_id');
    
    // Resources
    Route::get('/resources', [ResourcesController::class, 'browse'])->middleware('can:viewAny,App\Models\Resource');
    Route::get('/resources/type/{type}', [ResourcesController::class, 'browse_by_type'])->middleware('can:viewAny,App\Models\Resource');
    Route::get('/resources/{resource_id}', [ResourcesController::class, 'read'])->middleware('can:viewAny,App\Models\Resource');
    Route::put('/resources/{resource_id}', [ResourcesController::class, 'edit'])->middleware('can:manage,App\Models\Resource');
    Route::post('/resources', [ResourcesController::class, 'add'])->middleware('can:manage,App\Models\Resource');
    Route::delete('/resources/{resource_id}', [ResourcesController::class, 'delete'])->middleware('can:manage,App\Models\Resource');

    // Scheduler
    Route::get('/scheduler', [SchedulerController::class, 'browse'])->middleware('can:viewAny,App\Models\Scheduler');
    Route::get('/scheduler/{scheduler_id}', [SchedulerController::class, 'read'])->middleware('can:viewAny,App\Models\Scheduler');
    Route::get('/scheduler/{scheduler_id}/run', [SchedulerController::class, 'run'])->middleware('can:manage,App\Models\Scheduler');
    Route::get('/scheduler/{scheduler_id}/last_response', [SchedulerController::class, 'read_last_response'])->middleware('can:viewAny,App\Models\Scheduler');
    Route::put('/scheduler/{scheduler_id}', [SchedulerController::class, 'edit'])->middleware('can:manage,App\Models\Scheduler');
    Route::post('/scheduler', [SchedulerController::class, 'add'])->middleware('can:manage,App\Models\Scheduler');
    Route::delete('/scheduler/{scheduler_id}', [SchedulerController::class, 'delete'])->middleware('can:delete,App\Models\Scheduler');

    // Users
    Route::get('/users', [UsersController::class, 'browse'])->middleware('can:viewAny,App\Models\User');
    Route::get('/users/{user_id}', [UsersController::class, 'read'])->middleware('can:view,user_id');
    Route::put('/users/{user_id}', [UsersController::class, 'edit'])->middleware('can:manage,user_id');
    Route::post('/users', [UsersController::class, 'add'])->middleware('can:manage,App\Models\User');
    Route::delete('/users/{user_id}', [UsersController::class, 'delete'])->middleware('can:delete,user_id');

    // Activity Log
    Route::get('/activity_log', [ActivityLogController::class, 'browse'])->middleware('can:viewAny,App\Models\ActivityLog');

    // API Docs
    Route::get('/api_docs/{api_instance_id}', [DocumentationController::class, 'fetch']);
});

// Documentation Route (Single Slug)
Route::get('/{slug}', [DocumentationController::class, 'docs']);

// Catch-all Dynamic Proxy Route (Matches /{slug} with optional wildcard sub-paths for all HTTP methods)
Route::match(['GET', 'POST', 'PUT', 'DELETE'], '/{slug}/{any?}', [ExecController::class, 'exec'])
    ->where('any', '.*');