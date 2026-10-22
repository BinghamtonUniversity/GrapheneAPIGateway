<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        'App\API' => 'App\Policies\APIPolicy',
        'App\Models\API' => 'App\Policies\APIPolicy',
        
        'App\APIInstance' => 'App\Policies\APIInstancePolicy',
        'App\Models\APIInstance' => 'App\Policies\APIInstancePolicy',
        
        'App\APIUser' => 'App\Policies\APIUserPolicy',
        'App\Models\APIUser' => 'App\Policies\APIUserPolicy',
        
        'App\APIVersion' => 'App\Policies\APIPolicy',
        'App\Models\APIVersion' => 'App\Policies\APIPolicy',
        
        'App\User' => 'App\Policies\UserPolicy',
        'App\Models\User' => 'App\Policies\UserPolicy',
        
        'App\Environment' => 'App\Policies\EnvironmentPolicy',
        'App\Models\Environment' => 'App\Policies\EnvironmentPolicy',
        
        'App\Resource' => 'App\Policies\ResourcePolicy',
        'App\Models\Resource' => 'App\Policies\ResourcePolicy',
        
        'App\Scheduler' => 'App\Policies\SchedulerPolicy',
        'App\Models\Scheduler' => 'App\Policies\SchedulerPolicy',
        
        'App\APIDeveloper' => 'App\Policies\APIDeveloperPolicy',
        'App\Models\APIDeveloper' => 'App\Policies\APIDeveloperPolicy',
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Auth::viaRequest('api', function ($request) {
            if ($token = $request->input('api_token')) {
                $userModel = class_exists(\App\Models\User::class) 
                    ? \App\Models\User::class 
                    : \App\User::class;

                return $userModel::where('api_token', $token)->first();
            }
        });
    }
}