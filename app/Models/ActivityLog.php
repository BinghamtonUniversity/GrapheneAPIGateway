<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Environment;

class ActivityLog extends Model
{
  protected $table = 'activity_log';

  protected $fillable = ['action','event','type','user_id','comment','new','old','event_id'];
  protected $casts = ['new' => 'object','old' => 'object'];

  protected static function booted()
  {
    static::saving(function($model){
        $request = request();
        $model->user_id = '';
        $model->type = '';
        $model->comment = '';
        $model->action = $request->method();

        if ($request->has('id')) {
            $model->event_id = $request->input('id');
        }
        if ($request->has('user_id')) {
            $model->user_id = $request->input('user_id');
        }
        if ($request->headers->has('X-Unique-Id')) {
            $model->user_id = $request->header('X-Unique-Id');
        }
        if ($request->has('type')) {
            $model->type = $request->input('type');
        } else if ($request->has('environment_id')) {
            $environment = Environment::where('id', $request->input('environment_id'))->first();
            if (!is_null($environment)) {
                $model->type = $environment->type;
            }
        } else if (is_object($model->old) && isset($model->old->type)) {
            $model->type = $model->old->type;
        } else if (is_array($model->old) && isset($model->old['type'])) {
            $model->type = $model->old['type'];
        }

        if ($request->has('comment')) {
            $model->comment = $request->input('comment');
        } else if ($request->has('summary')) {
            $model->comment = $request->input('summary');
        }
    });
  }
}