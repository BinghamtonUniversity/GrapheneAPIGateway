<?php

namespace App\Libraries;

use App\Models\API;
use App\Models\APIInstance;
use App\Models\APIVersion;
use App\Models\APIUser;
use App\Models\Resource;
use App\Libraries\Router;
use App\Libraries\MySQLDB;
use App\Libraries\OracleDB;
use App\Libraries\ValidateUser;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PDO;

class ExecAPI {

    private function build_routes($api_instance, $api_version) {
        if (!is_iterable($api_version->routes ?? null)) {
            return;
        }

        foreach($api_version->routes as $route_index => $route) {
            $extra = [];
            $verb = isset($route->verb) ? strtoupper($route->verb) : 'ALL';
            
            if (isset($route->required) && is_iterable($route->required)) {
                foreach($route->required as $required_param) {
                    if (isset($required_param->name)) {
                        $extra['required'][] = $required_param->name;
                    }
                }
            }
            Router::add_route(
                '/'.$api_instance->slug.($route->path ?? ''), 
                $api_instance->api->name, 
                $route->function_name, 
                $extra,
                $verb
            );
        }
    } 

    private function build_resources($api_instance) {
        $resources_arr = [];
        $resources_name_map = [];
        if (is_iterable($api_instance->resources)) {
            foreach($api_instance->resources as $resource_map) {
                if (isset($resource_map->resource)) {
                    $resources_arr[] = $resource_map->resource;
                    $resources_name_map[$resource_map->resource] = $resource_map->name ?? '';
                }
            }
        }

        $resources = Resource::whereIn('id', $resources_arr)->get();
        foreach($resources as $resource) {
            $alias = $resources_name_map[$resource->id] ?? null;
            if (!$alias) continue;

            if ($resource->resource_type == 'mysql') {
                $resource_config = $resource->config_with_secrets; 
                if (is_object($resource_config)) {
                    $resource_config->name = $resource->name;
                }
                MySQLDB::config_database($alias, $resource->config_with_secrets);
                config(['app.resources.'.$alias => $resource_config]);
                config(['database.connections.'.$alias => [
                    'driver'    => 'mysql',
                    'port'      => $resource->config->port ?? 3306,
                    'host'      => $resource->config->server ?? '',
                    'database'  => $resource->config->name ?? '',
                    'username'  => $resource->config->user ?? '',
                    'password'  => $resource->config_with_secrets->pass ?? '',
                ]]);
            } else if ($resource->resource_type == 'oracle') {
                $resource_config = $resource->config_with_secrets; 
                if (is_object($resource_config)) {
                    $resource_config->name = $resource->name;
                }
                OracleDB::config_database($alias, $resource->config_with_secrets);
                config(['app.resources.'.$alias => $resource_config]);
                config(['database.connections.'.$alias => [
                    'driver'        => 'oracle',
                    'tns'           => $resource->config->tns ?? '',
                    'port'          => $resource->config->port ?? 1521,
                    'username'      => $resource->config->user ?? '',
                    'password'      => $resource->config_with_secrets->pass ?? '',
                    'charset'       => $resource->config->charset ?? 'AL32UTF8',
                    'host'          => $resource->config->server ?? '',
                    'database'      => $resource->config->name ?? '',
                ]]);
            } else if ($resource->resource_type == 'sqlsrv') {
                $resource_config = $resource->config_with_secrets; 
                if (is_object($resource_config)) {
                    $resource_config->name = $resource->name;
                }
                config(['app.resources.'.$alias => $resource_config]);
                config(['database.connections.'.$alias => [
                    'driver'        => 'sqlsrv',
                    'host'          => $resource->config->server ?? '',
                    'database'      => $resource->config->name ?? '',
                    'username'      => $resource->config->user ?? '',
                    'password'      => $resource->config_with_secrets->pass ?? '',
                    'port'          => $resource->config->port ?? 1433,
                    'options'       => [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT],
                    'trust_server_certificate' => true
                ]]);
            } else if ($resource->resource_type == 'secret' || $resource->resource_type == 'value') {
                config(['app.resources.'.$alias => $resource->config_with_secrets->value ?? null]);
            }
        }
    }

    private function build_options($api_instance) {
        if (isset($api_instance->options) && (is_array($api_instance->options) || is_object($api_instance->options))) {
            config(['app.options' => $api_instance->options]);
        } else {
            config(['app.options' => []]);
        }
    }

    private function build_file($filename, $api_instance, $api_version) {
        $file_content = '';
        if (is_null($filename)) {
            $file_content = '<?php'."\n".
                'use \App\Libraries\MySQLDB;'."\n".
                'use \App\Libraries\OracleDB;'."\n".
                'use Illuminate\Support\Facades\DB;'."\n".
                'use Illuminate\Support\Arr;'."\n".
                'use Illuminate\Support\Facades\Mail;'."\n".
                'use \Carbon\Carbon;'."\n\n".
                'class '.($api_instance->api->name ?? 'GeneratedAPI').' {'."\n";

            if (is_iterable($api_version->functions ?? null)) {
                foreach($api_version->functions as $function) {
                    $code = implode("\n    ", explode("\n", $function->content ?? ''));
                    if (($function->name ?? '') === 'Constructor') {
                        $file_content .= 'function __construct($args=null,$resources=null,$options=null) {'."\n".
                            '    if(is_null($args)){$args=config("app.args");}if(is_null($resources)){$resources=config("app.resources");}if(is_null($options)){$options=config("app.options");}'."\n".
                            '    '.$code."\n".
                            '}'."\n\n";
                    } else {
                        $file_content .= 'public function '.($function->name ?? 'func').'($args=null,$resources=null,$options=null) {'."\n".
                            '    if(is_null($args)){$args=config("app.args");}if(is_null($resources)){$resources=config("app.resources");}if(is_null($options)){$options=config("app.options");}'."\n".
                            '    '.$code."\n".
                            '}'."\n\n";
                    }
                }
            }
            $file_content .= "}";
        } else {
            if (is_iterable($api_version->files ?? null)) {
                foreach($api_version->files as $code_file) {
                    if (($code_file->name ?? '') === $filename) {
                        $use_directive = 
                            'use \App\Libraries\MySQLDB;'."\n".
                            'use \App\Libraries\OracleDB;'."\n".
                            'use Illuminate\Support\Facades\DB;'."\n".
                            'use Illuminate\Support\Arr;'."\n".
                            'use Illuminate\Support\Facades\Mail;'."\n".
                            'use \Carbon\Carbon;'."\n";

                        $found = 0;
                        $file_content = preg_replace(
                            '/(namespace .*;)/',
                            "$1\n".$use_directive,
                            $code_file->content ?? '',
                            1,
                            $found
                        );

                        if ($found === 0) {
                            $file_content = "<?php\n".$use_directive."?>\n".($code_file->content ?? '');
                        }                    
                        break;
                    }
                }
            }
        }
        return $file_content;
    }

    private function build_module_cache($code_path, $api_instance) {
        $api_version_id = $api_instance->find_version_id();
        $api_version_metadata = APIVersion::select('id','updated_at')->where('id',$api_version_id)->first();

        if (file_exists($code_path.DIRECTORY_SEPARATOR.'api_version.json') && $api_version_metadata) {
            $api_version = json_decode(file_get_contents($code_path.DIRECTORY_SEPARATOR.'api_version.json'));
            if ($api_version && $api_version_id == $api_version_metadata->id && ($api_version->updated_at ?? null) == $api_version_metadata->updated_at?->toJSON()) {
                return $api_version;
            }
        }

        if (!file_exists($code_path)) { 
            mkdir($code_path, 0777, true); 
        }
        $api_version = APIVersion::where('id',$api_version_id)->first();
        
        $main_class = $this->build_file(null, $api_instance, $api_version);
        file_put_contents($code_path.DIRECTORY_SEPARATOR.'Module.php', $main_class);
        
        if (is_iterable($api_version->files ?? null)) {
            foreach($api_version->files as $code_file) {
                if (isset($code_file->name)) {
                    $file_code = $this->build_file($code_file->name, $api_instance, $api_version);
                    file_put_contents($code_path.DIRECTORY_SEPARATOR.$code_file->name, $file_code);
                }
            }
        }

        $api_version->files = collect($api_version->files ?? [])->map(function($item) {
            return isset($item->name) ? (object)['name' => $item->name] : (object)[];
        });

        file_put_contents($code_path.DIRECTORY_SEPARATOR.'api_version.json',
            json_encode(Arr::except($api_version->toArray(), ['functions']))
        );  
        return $api_version;
    }

    public function eval_code($api_instance) {
        $code_path = app()->basePath().DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'modules_cache'.DIRECTORY_SEPARATOR.$api_instance->id;
        $api_version = $this->build_module_cache($code_path, $api_instance);

        $this->build_routes($api_instance, $api_version);
        $this->build_resources($api_instance);
        $this->build_options($api_instance);

        include_once($code_path.DIRECTORY_SEPARATOR.'Module.php');
        if (is_iterable($api_version->files ?? null)) {
            foreach($api_version->files as $code_file) {
                if (isset($code_file->name)) {
                    include_once($code_path.DIRECTORY_SEPARATOR.$code_file->name);
                }
            }
        }

        DB::connection()->disconnect(); 

        $ret = Router::handle_route();
        if (!is_bool($ret) && !is_null($ret)) {
            return $ret;
        }
    }
}