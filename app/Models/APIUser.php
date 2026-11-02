<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class APIUser extends Model
{
  protected $fillable = ['app_name','app_secret','ips','environment_id'];
  protected $casts = ['ips' => 'array'];
  protected $table = 'api_users';
  protected $hidden = ['encrypted_app_secret'];

  public function setAppSecretAttribute($secret) {
    if ($secret !== '*****') {
      $this->attributes['app_secret'] = Hash::make($secret);
      $this->attributes['encrypted_app_secret'] = Crypt::encrypt($secret);
    }
  }

  public function environment() {
    return $this->belongsTo(Environment::class);
  }

  public function getAppSecretAttribute($secret) {
    return '*****';
  }

  public function getDecryptedAppSecretAttribute($secret) {
    $encrypted = $this->attributes['encrypted_app_secret'] ?? null;
    if (!$encrypted) {
      return base64_encode('');
    }

    try {
        return base64_encode(Crypt::decrypt($encrypted));
    } catch (Throwable $e) {
        return base64_encode("ERROR");
    }
  }

  public function check_app_secret($secret) {
    $hashedSecret = $this->attributes['app_secret'] ?? '';
    return Hash::check($secret, $hashedSecret);
  }

  protected static function booted()
  {
    static::saved(function($model){
      if (!app()->runningInConsole()) {
        $orig = $model->getOriginal();
        if (isset($orig['app_secret']) && $orig['app_secret'] !== '') {
          $orig['app_secret'] = '*****';
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