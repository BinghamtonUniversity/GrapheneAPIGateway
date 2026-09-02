<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Cron\CronExpression;
use App\Models\ActivityLog;

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
    try {
      $cron = CronExpression::factory($this->attributes['cron']);
      return [
        $cron->getNextRunDate(null,0)->format('Y-m-d H:i:s'),
        $cron->getNextRunDate(null,1)->format('Y-m-d H:i:s'),
        $cron->getNextRunDate(null,2)->format('Y-m-d H:i:s'),
        $cron->getNextRunDate(null,3)->format('Y-m-d H:i:s'),
        $cron->getNextRunDate(null,4)->format('Y-m-d H:i:s'),
      ];
    } catch (\Exception $e) {
      return [];
    }
  }

  public static function boot()
  {
    parent::boot();
    self::saved(function($model){
      if (!app()->runningInConsole()) {
        //09/02/2026, AKT - Removing the last_response to be recorded in the activity logs//
          if ($model->last_response) {
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