<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Crypt;

class Resource extends Model
{
  protected $fillable = ['name','type','config','resource_type'];
  protected $secret_fields = ['password','pass','secret','key'];

  private function getConfigHelper($config_string, $return_secrets = false)
  {
    if (!is_null($config_string) && $config_string !== '') {
      $resource_config = is_array($config_string) ? $config_string : json_decode($config_string, true);
      if (is_array($resource_config)) {
        foreach($resource_config as $index => $config_attr) {
          foreach($this->secret_fields as $secret_field) {
            if (($index === $secret_field || $this->resource_type === $secret_field) && $resource_config[$index] !== '') {
              if ($return_secrets === true) {
                try {
                  $resource_config[$index] = Crypt::decrypt($resource_config[$index]);
                } catch (\Throwable $e) {
                  /* Do Nothing -- Don't Change Value */
                }
              } else {
                $resource_config[$index] = '*****';
              }
            }
          }
        }
        return (object)$resource_config;
      }
    }
    return (object)[];
  }

  public function getConfigAttribute($config_string)
  {
    return $this->getConfigHelper($config_string, false);
  }

  public function getConfigWithSecretsAttribute($config_string)
  {
    $rawConfig = $this->attributes['config'] ?? null;
    return $this->getConfigHelper($rawConfig, true);
  }

  public function setConfigAttribute($new_config)
  {
    $orig_config = [];
    if (isset($this->attributes['config'])) {
      $orig_config = json_decode($this->attributes['config'], true) ?? [];
    }

    if (is_string($new_config)) {
      $new_config = json_decode($new_config, true) ?? [];
    }

    if (is_array($new_config)) {
      foreach($new_config as $index => $new_config_attr) {
        foreach($this->secret_fields as $secret_field) {
          if (($index === $secret_field || $this->resource_type === $secret_field) && $new_config_attr !== '*****' && $new_config_attr !== '') {
            $new_config[$index] = Crypt::encrypt($new_config_attr);
          } else if (($index === $secret_field || $this->resource_type === $secret_field) && $new_config_attr === '*****' && isset($orig_config[$index])) {
            $new_config[$index] = $orig_config[$index];
          }
        }
      }
      $this->attributes['config'] = json_encode($new_config);
    } else {
      $this->attributes['config'] = json_encode([]);
    }
  }

  protected static function booted()
  {
    static::saved(function($model){
      if (!app()->runningInConsole()) {
        $orig = $model->getOriginal();
        if (isset($orig['config']) && is_string($orig['config'])) {
          $configData = json_decode($orig['config'], true);
          if (is_array($configData)) {
            foreach($configData as $index => $config_attr) {
              foreach($model->secret_fields as $secret_field) {
                if ($index === $secret_field || ($orig['resource_type'] ?? null) === $secret_field) {
                  $configData[$index] = '*****';
                }
              }
            }
            $orig['config'] = $configData;
          }
        }
        $activity_log = new ActivityLog([
          'event' => class_basename($model),
          'new' => $model,
          'old' => $orig,
        ]);
        $activity_log->save();
      }
    });
  }
}