<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function getCreatedAtAttribute(): string
    {
        return Carbon::parse($this->attributes['created_at'])->format('M d Y h:i');
    }

    public function scopeSearch($query, $search)
    {
        if ($search != null) {
            return $query->where(function ($query) use ($search) {
                $query->orWhere('email', 'LIKE', '%'.$search.'%');
            });
        }

        return $query;
    }
}
