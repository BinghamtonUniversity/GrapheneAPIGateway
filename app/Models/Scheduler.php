<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Cron\CronExpression;
use App\Models\ActivityLog;
use Throwable;

class Scheduler extends Model
{
  protected $table = 'scheduler';

  protected $fillable = ['cron','api_instance_id','route','name','args','enabled','verb'];
  protected $casts = ['args' => 'object','last_response'=>'object','enabled'=>'boolean'];
  protected $appends = ['next_runtimes'];

  public function api_instance() {
    return $this->belongsTo(APIInstance::class);
  }

  public function getNextRuntimesAttribute() {
    $cronString = $this->attributes['cron'] ?? null;
    if (!$cronString) {
      return [];
    }

    try {
      $cron = new CronExpression($cronString);
      return [
        $cron->getNextRunDate('now', 0)->format('Y-m-d H:i:s'),
        $cron->getNextRunDate('now', 1)->format('Y-m-d H:i:s'),
        $cron->getNextRunDate('now', 2)->format('Y-m-d H:i:s'),
        $cron->getNextRunDate('now', 3)->format('Y-m-d H:i:s'),
        $cron->getNextRunDate('now', 4)->format('Y-m-d H:i:s'),
      ];
    } catch (Throwable $e) {
      return [];
    }
  }

  protected static function booted()
  {
    static::saved(function($model){
      if (!app()->runningInConsole()) {
        if (isset($model->last_response)) {
            unset($model->last_response);
        }
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