<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Jenssegers\Agent\Agent;

class LoginActivities extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = ['browser', 'platform'];

    public static function add()
    {
        $model = new static;
        $model->user_id = Auth::user()->id ?? 0;
        $model->ip = request()->ip();
        $model->location = getLocation()->name;
        $model->agent = request()->userAgent();
        $model->save();

        return $model;
    }

    public function getBrowserAttribute(): string
    {
        return self::getAgent('browser');
    }

    private function getAgent($show)
    {
        $agent = new Agent;
        $agent->setUserAgent($this->agent);

        return $agent->$show();
    }

    public function getPlatformAttribute(): string
    {
        return self::getAgent('platform');
    }
}
