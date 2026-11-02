<?php

namespace App\Http\Controllers;

use App\Models\API;
use App\Models\APIInstance;
use App\Models\APIVersion;
use App\Models\APIUser;
use App\Models\Resource;
use App\Libraries\Router;
use App\Libraries\MySQLDB;
use App\Libraries\OracleDB;
use App\Libraries\ValidateUser;
use App\Libraries\ExecAPI;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;
use ErrorException;

class ExecController extends Controller
{
    public function __construct() {
    }

    private function validate_user($api_instance, Request$request) {
        $user_id_arr = [];$user_to_routes = [];

        if (is_array($api_instance->route_user_map) || is_object($api_instance->route_user_map)) {            
            foreach($api_instance->route_user_map as$route_user) {
                $user_id_arr[] =$route_user->api_user ?? null;
                $user_to_routes[$route_user->api_user][] = '/'.$api_instance->slug.($route_user->route ?? '');
            }
        }

        $relevant_users = APIUser::whereIn('id',$user_id_arr)->whereHas('environment', function($q){$q->where('domain', '=', app('request')->getHost());
        })->get();
        
        $users = [];
        foreach($relevant_users as$user) {
            $users[$user->app_name] = [
                'user' => $user, 
                'pass' => $user->app_secret,
                'ips' => $user->ips,
                'routes' => $user_to_routes[$user->id] ?? []
            ];
        }

        $userisok = false; $ipisok = false; $routeisok = false;

        $basic_auth_username =$request->getUser() ?? 'public';
        $basic_auth_password =$request->getPassword() ?? 'public';

        /* Validate Username & Password */
        $userisok = (isset($users[$basic_auth_username]) &&$users[$basic_auth_username]['user']->check_app_secret($basic_auth_password));
        
        /* Validate IP Address (if Required) */
        if ($userisok) {
            $clientIp =$request->ip();
            if (is_array($users[$basic_auth_username]['ips']) && count($users[$basic_auth_username]['ips']) > 0) {
                foreach($users[$basic_auth_username]['ips'] as$ip) {
                    if (str_starts_with($clientIp, $ip)) {$ipisok = true;
                        break;
                    }
                }
            } else {
                $ipisok = true;
            }
        }

        /* Validate Route */
        if ($ipisok && isset($users[$basic_auth_username]['routes'])) {
            $requestUri =$request->getRequestUri();
            foreach($users[$basic_auth_username]['routes'] as$path) {
                $pattern = '/^'.str_replace('/', '\/',$path).'/';
                if (preg_match($pattern, $requestUri) === 1) {$routeisok = true;
                    break;
                }
            }
        }

        if (!($userisok && $ipisok &&$routeisok)) {
            return response(json_encode(['error' => 'Unauthorized User']), 401)
                ->header('WWW-Authenticate', 'Basic realm="'.$api_instance->name.' API"')
                ->header('Content-type', 'application/json');
        }

        return true;
    }

    public function exec($slug, Request $request) {$exec_api = new ExecAPI();
        $api_instance = APIInstance::where('slug',$slug)->with('api')->whereHas('environment', function($q){$q->where('domain', '=', app('request')->getHost());
        })->first();

        if (is_null($api_instance)) {
            return response(json_encode(['error' => 'API Not Found']), 404)->header('Content-type', 'application/json');
        }

        $ret =$this->validate_user($api_instance,$request);
        if ($ret !== true) {
            return $ret;
        }

        set_error_handler(function ($severity,$message, $file,$line) {
            throw new ErrorException($message, $severity,$severity, $file,$line);
        });

        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);

        try {
            return $exec_api->eval_code($api_instance);
        } catch (Throwable $e) {$error_line = $e->getLine();$filePath = $e->getFile();$file_contents_abridged = [];

            if (file_exists($filePath)) {
                $file_contents = explode("\n", file_get_contents($filePath));
                for ($line_number = $error_line - 10; $line_number <= $error_line + 10; $line_number++) {
                    if (isset($file_contents[$line_number])) {$file_contents_abridged[] = str_pad($line_number + 1, strlen(strval(count($file_contents))), ' ', STR_PAD_LEFT) . ':  ' . $file_contents[$line_number];
                    }
                }
            }

            Log::error('API ERROR: "'.($api_instance->api->name ?? 'Unknown').'" on instance '.$api_instance->id."\n".
                'MESSAGE: '.$e->getMessage()."\n".
                'LINE: '.$error_line."\n".
                'URL: '.$request->fullUrl()."\n".
                'PARAMS: '.json_encode($request->all())."\n".
                'FILE: '.$filePath."\n".
                'CONTENTS: '."\n".implode("\n", $file_contents_abridged)."\n"
            );

            if ($api_instance->errors === 'all') {
                return response()->json([
                    'error' => [
                        'code' => 'error',
                        'message' => $e->getMessage(),
                        'line' => $error_line,
                        'file' => $filePath,
                        'file_contents' => $file_contents_abridged,
                    ]
                ], 500);
            }

            return response()->json([
                'error' => [
                    'code' => 'error',
                    'message' => $e->getMessage()
                ]
            ], 500);
        } finally {
            restore_error_handler();
        }
    }   
}