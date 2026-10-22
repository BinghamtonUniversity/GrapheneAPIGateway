<?php

namespace App\Libraries;

class Router {
    private static $routes = [];

    public static function get_pwd() {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $full_path_without_args = explode('?', $uri)[0];
        $PWD = explode('/', $full_path_without_args);
        unset($PWD[0]);
        $PWD = array_values($PWD);
        foreach ($PWD as $key => $piece) {
            $PWD[$key] = urldecode($piece);
        }
        return $PWD;
    }

    private static function fetch_args($path, $help_params) {
        $PWD = self::get_pwd();
        $args = array_slice($PWD, count(explode('/', $path)) - 1);
        if (count($args) > 0 && $args[count($args) - 1] === '') {
            unset($args[count($args) - 1]);
        }
        $path = $args;

        $required = (isset($help_params['required']) && is_array($help_params['required']))
            ? $help_params['required']
            : [];

        $missing_args = [];
        $args = [];
        $body_params = [];
        
        $input = file_get_contents("php://input");
        if (!empty($input)) {
            parse_str($input, $body_params);
        }

        foreach ($required as $arg_index => $arg_name) {
            if (!(isset($path[$arg_index]) || isset($_GET[$arg_name]) || isset($body_params[$arg_name]))) {
                $missing_args[] = $arg_name;
            } else if (isset($path[$arg_index])) {
                $args[$arg_name] = $path[$arg_index];
            }
        }
        $args = array_merge($body_params, $_GET, $args);

        if (count($missing_args) > 0) {
            return response(json_encode(['error' => 'You must provide: ' . implode(',', $missing_args)]), 400)
                ->header('Content-Type', 'application/json');
        }

        return $args;
    }

    public static function add_route($path, $class, $function, $argsinfo, $verb) {
        $normalizedVerb = strtoupper($verb);
        self::$routes[$path][$normalizedVerb] = [
            'basepath' => $path, 
            'class' => $class, 
            'function' => $function, 
            'helppath' => '', 
            'argsinfo' => $argsinfo
        ];
    }

    public static function handle_route() {
        $PWD = self::get_pwd();
        $thisRoute = '/' . implode('/', $PWD);
        $wrong_verb = false;
        $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        foreach (self::$routes as $path => $path_info) {
            $path_regex = '/^' . str_replace('/', '\/', $path) . '(\/|\z)/';
            if (preg_match($path_regex, $thisRoute) === 1) {
                foreach ($path_info as $verb => $path_info_info) {
                    $routeVerb = strtoupper($verb);
                    if ($requestMethod === $routeVerb || $routeVerb === 'ALL') {
                        $args = self::fetch_args($path, $path_info_info['argsinfo']);
                        if (!is_array($args)) { 
                            return $args;
                        }

                        config(['app.args' => $args]);
                        $routeHandlerFunction = $path_info_info['function'];
                        $className = $path_info_info['class'];

                        if (!class_exists($className)) {
                            return response(json_encode(['error' => "API Class '{$className}' not found"]), 500)
                                ->header('Content-Type', 'application/json');
                        }

                        ob_start();
                        $routeHandlerClass = new $className();
                        
                        if (!method_exists($routeHandlerClass, $routeHandlerFunction)) {
                            ob_end_clean();
                            return response(json_encode(['error' => "API Method '{$routeHandlerFunction}' not found"]), 500)
                                ->header('Content-Type', 'application/json');
                        }

                        $return = $routeHandlerClass->$routeHandlerFunction();
                        $output = ob_get_clean();

                        if (is_null($return) && strlen($output)) {
                            return response($output, 200)->header('Content-Type', 'text/plain');
                        } else if (!is_null($return)) {
                            return $return;
                        }
                        return true;
                    } else {
                        $wrong_verb = true;
                    }
                }
            }
        }

        if ($wrong_verb) {
            return response(json_encode(['error' => $requestMethod . ' Method not allowed']), 405)
                ->header('Content-Type', 'application/json');
        }

        return response(json_encode(['error' => 'API call does not exist']), 404)
            ->header('Content-Type', 'application/json');
    }
}