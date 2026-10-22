<?php

namespace App\Libraries;

use Exception;

class OracleDB {

  public static $objConnect = null;
  private static $databases = [];

  private static function find_params_in_query($query, $params) {
    $query_params = [];
    if (is_array($params)) {
        foreach($params as $param_name => $param_value) {
            if (stristr($query, ':'.$param_name)) {
                $query_params[$param_name] = $param_value;
            }
        }
    }
    return $query_params;
  }

  public static function config_database($db_name, $db_config) {
      self::$databases[$db_name] = $db_config;
  }

  public static function connect($db_name)
  {
      if (!isset(self::$databases[$db_name])) {
          throw new Exception("Oracle database configuration for '{$db_name}' not found.");
      }
      $config = self::$databases[$db_name];
      self::$objConnect = @\oci_connect(
          $config->user ?? '',
          $config->pass ?? '',
          $config->tns ?? '',
          'AL32UTF8'
      );

      if (!self::$objConnect) {
          $e = \oci_error();
          throw new Exception("Oracle Connection Error: " . ($e['message'] ?? 'Unknown Error'));
      }
  }

  public static function select($query, $params = [])
  {   
    if (!self::$objConnect) {
        $keys = array_keys(self::$databases);
        if (!empty($keys)) {
            self::connect($keys[0]);
        } else {
            throw new Exception("No active Oracle database connection.");
        }
    }

    $stid = \oci_parse(self::$objConnect, $query);
    $params = self::find_params_in_query($query, $params);
    if (!$stid) {
      $e = \oci_error(self::$objConnect);
      throw new Exception($e['message'] ?? 'Failed to parse Oracle query');
    }

    foreach($params as $param_name => $param_value) {
      \oci_bind_by_name($stid, ':'.$param_name, $params[$param_name]);
    }

    $r = \oci_execute($stid);
    if (!$r) {
      $e = \oci_error($stid);
      throw new Exception($e['message'] ?? 'Failed to execute Oracle query');
    }

    $row_num = 0;
    $results = [];
    while ($row = \oci_fetch_array($stid, OCI_ASSOC+OCI_RETURN_NULLS+OCI_RETURN_LOBS)) {
      foreach ($row as $column_name => $value) {
        $results[$row_num][$column_name] = $value;
      }
      $row_num++;
    }
    \oci_free_statement($stid);
    return $results;
  }

  public static function insert($query, &$params = [], $index_name = null)
  {
    if (!self::$objConnect) {
        $keys = array_keys(self::$databases);
        if (!empty($keys)) {
            self::connect($keys[0]);
        } else {
            throw new Exception("No active Oracle database connection.");
        }
    }

    $params = self::find_params_in_query($query, $params);
    $rowid = null;

    if (!is_null($index_name)) {
      $query .= " RETURNING ".$index_name." INTO :rid";
    }

    $stid = \oci_parse(self::$objConnect, $query);
    foreach($params as $param_name => $param_value) {
      \oci_bind_by_name($stid, ':'.$param_name, $params[$param_name]);
    }
    if (!is_null($index_name)) {
      \oci_bind_by_name($stid, ":rid", $rowid, -1, OCI_B_INT);
    }

    $objExecute = \oci_execute($stid, OCI_DEFAULT);
    if ($objExecute) {
      \oci_commit(self::$objConnect);
    } else {
      \oci_rollback(self::$objConnect);
      $e = \oci_error($stid);
      throw new Exception($e['message'] ?? 'Oracle Execution Failed');
    }

    return !is_null($index_name) ? $rowid : true;
  }

  public static function delete($query, $params = []) { return self::insert($query, $params); }
  public static function update($query, $params = []) { return self::insert($query, $params); }

  public static function close()
  {
    if (self::$objConnect) {
        \oci_close(self::$objConnect);
        self::$objConnect = null;
    }
  }
}