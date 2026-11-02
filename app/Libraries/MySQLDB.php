<?php

namespace App\Libraries;

use Exception;

class MySQLDB {

    private static $connections = [];
    private static $databases = [];

    public static function config_database($db_name, $db_config) {
        self::$databases[$db_name] = $db_config;
    }

    public static function connect($db_name) {
        if (!isset(self::$connections[$db_name])) {
            if (!isset(self::$databases[$db_name])) {
                throw new Exception("Database configuration for '{$db_name}' not found.");
            }
            $config = self::$databases[$db_name];
            $conn = new \mysqli(
                $config->server ?? '', 
                $config->user ?? '', 
                $config->pass ?? '', 
                $config->name ?? '',
                $config->port ?? 3306
            );

            if ($conn->connect_error) {
                throw new Exception("MySQL Connection Error: " . $conn->connect_error);
            }
            self::$connections[$db_name] = $conn;
        }
        return self::$connections[$db_name];
    }

    private static function get_active_connection() {
        if (empty(self::$connections)) {
            $keys = array_keys(self::$databases);
            if (!empty($keys)) {
                return self::connect($keys[0]);
            }
            throw new Exception("No active MySQL connection available.");
        }
        return end(self::$connections);
    }

    private static function build_types(&$params) {
        $types = '';
        if (is_array($params)) {
            foreach($params as $param) {        
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
            }
        }
        return $types;
    }

    private static function bind_params_ref($stmt, $params) {
        $types = self::build_types($params);
        $bind_args = [$types];
        foreach ($params as $key => $value) {
            $bind_args[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_args);
    }

    public static function query($query, $params = []) 
    {
        $mysqli = self::get_active_connection();
        $results = [];

        if ($stmt = $mysqli->prepare($query)) {
            if (!empty($params)) {
                self::bind_params_ref($stmt, $params);
            }
            $stmt->execute(); 
            $meta = $stmt->result_metadata();
            if ($meta) {
                $fields = [];
                $row = [];
                while ($field = $meta->fetch_field()) { 
                    $var = $field->name; 
                    $row[$var] = null; 
                    $fields[] = &$row[$var]; 
                }
                call_user_func_array([$stmt, 'bind_result'], $fields);
                while ($stmt->fetch()) { 
                    $newfields = [];
                    foreach($row as $key => $value) {
                        $newfields[$key] = $value;
                    }
                    $results[] = $newfields; 
                }
            }
            $stmt->close();
        }  

        if ($mysqli->error) {
            throw new Exception($mysqli->error);
        }
        return $results;
    }

    public static function update($query, $params = []) 
    {
        $mysqli = self::get_active_connection();
        if ($stmt = $mysqli->prepare($query)) {
            if (!empty($params)) {
                self::bind_params_ref($stmt, $params);
            }
            $stmt->execute(); 
            $stmt->close();
        }  
        if ($mysqli->error) {
            throw new Exception($mysqli->error);
        }
    }

    public static function insert($query, $params = []) 
    {
        $mysqli = self::get_active_connection();
        $insert_id = null;
        if ($stmt = $mysqli->prepare($query)) {
            if (!empty($params)) {
                self::bind_params_ref($stmt, $params);
            }
            $stmt->execute(); 
            $insert_id = $stmt->insert_id;
            $stmt->close();
        }
        if ($mysqli->error) {
            throw new Exception($mysqli->error);
        }
        return $insert_id;
    }
}