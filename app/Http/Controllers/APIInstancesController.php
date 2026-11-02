<?php

namespace App\Http\Controllers;

use App\Models\APIInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class APIInstancesController extends Controller
{
    public function __construct() {
    }
    
    public function browse() {
        $user = Auth::user();

        if ($user && $user->admin) {
            return APIInstance::whereHas('environment', function($query) {
                $query->where('server_name', config('app.server_name'));
            })
                ->orderby('environment_id')
                ->with(['api' => function ($query) {
                    $query->where('api_type', 'php');
                }])
                ->with('environment')
                ->orderby('name')
                ->get();
        }

        return APIInstance::whereHas('environment', function($query) {
            $query->where('server_name', config('app.server_name'));
        })->whereHas('api', function ($query) use ($user) {
            $query->where('api_type', 'php')->where(function ($q) use ($user) {
                $q->where('user_id', $user->id ?? null)
                  ->orWhereHas('developers', function ($devQuery) use ($user) {
                      $devQuery->where('users.id', $user->id ?? null);
                  });
            });
        })->with('environment')->orderby('name')->get();
    }   

    public function read($api_instance_id) {
        $api_instance = APIInstance::where('id', $api_instance_id)
            ->with('api')
            ->with(['environment' => function($query) {
                $query->where('server_name', config('app.server_name'));
            }])
            ->first();

        if (!is_null($api_instance)) {
            $api_instance->api_version = $api_instance->find_version();
            return $api_instance;
        }

        return response('api_instance not found', 404);
    }   

    public function edit(Request $request, $api_instance_id)
    {
        $api_instance = APIInstance::where('id', $api_instance_id)->first();
        if (!is_null($api_instance)) {
            $data = $request->all();
            if (($request->api_version_id ?? null) == -1 || ($request->api_version_id ?? null) == '') {
                $data['api_version_id'] = null;
            }
            $api_instance->update($data);
            return APIInstance::where('id', '=', $api_instance->id)->first();
        }

        return response('api_instance not found', 404);
    }

    public function add(Request $request)
    {
        $api_instance = new APIInstance($request->all());
        $api_instance->api_version_id = null;
        $api_instance->save();
        return $api_instance;
    }

    public function delete($api_instance_id)
    {
        if (APIInstance::where('id', $api_instance_id)->delete()) {
            return [true];
        }
        return response('api_instance not found', 404);
    }
}