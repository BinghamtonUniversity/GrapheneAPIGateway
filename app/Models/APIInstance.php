<?php

namespace App\Models;

use App\Models\APIVersion;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class APIInstance extends Model
{
  protected $table = 'api_instances';
  protected $fillable = ['name','slug','public','api_version_id','environment_id','api_id','route_user_map','resources','options','errors'];
  protected $casts = ['route_user_map' => 'object', 'resources' => 'object', 'options' => 'array', 'public'=>'boolean'];

  public function api() {
    return $this->belongsTo(API::class);
  }

  public function api_version() {
    return $this->belongsTo(APIVersion::class);
  }

  public function environment() {
    return $this->belongsTo(Environment::class);
  }

  public function find_version_id() {
    if (is_null($this->api_version_id)) {
        $api_version = APIVersion::select('id')
            ->where('api_id', $this->api_id)
            ->orderby('created_at', 'desc')->first();
        return $api_version?->id;
    } else if ($this->api_version_id == 0) {
        $api_version = APIVersion::select('id')
            ->where('api_id', $this->api_id)
            ->where('stable', '=', 1)
            ->orderby('created_at', 'desc')->first();
        return $api_version?->id;
    } else {
        return $this->api_version_id;
    }
  }

  public function find_version() {
      $latest_version_id = $this->find_version_id();
      if (!$latest_version_id) {
          return null;
      }
      return APIVersion::where('id', '=', $latest_version_id)->orderBy('created_at', 'desc')->first();
  }

  protected static function booted()
  {
    static::saved(function($model){
      if (!app()->runningInConsole()) {
        $orig = $model->getOriginal();
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