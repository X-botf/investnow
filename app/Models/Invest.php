<?php

namespace App\Models;

use App\Enums\InvestStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = ['created_time', 'is_cancel'];

    protected $casts = [
        'status' => InvestStatus::class,
    ];

    public function schema()
    {
        return $this->hasOne(Schema::class, 'id', 'schema_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }

    public function getCreatedAtAttribute($value)
    {
        return date('M d, Y H:i', strtotime($value));
    }

    public function getNextProfitTimeAttribute($value)
    {
        return date('M d, Y H:i', strtotime($value));
    }

    public function getCreatedTimeAttribute(): string
    {
        return Carbon::parse($this->attributes['created_at'])->format('M d Y h:i');
    }

    public function getIsCancelAttribute(): string
    {

        if ($this->schema->schema_cancel) {
            $expiryTime = Carbon::parse($this->created_at)->addMinute($this->schema->expiry_minute)->format('M d Y h:i');
            $now = Carbon::now()->format('M d Y h:i');
            if ($expiryTime >= $now) {
                return true;
            }

            return false;
        }

        return false;
    }

    public function scopeSearch($query, $search)
    {
        if ($search != null) {
            return $query->where(function ($query) use ($search) {
                $query->orWhere('user_id', 'LIKE', '%'.$search.'%')
                    ->orWhere('schema_id', 'LIKE', '%'.$search.'%')
                    ->orWhere('transaction_id', 'LIKE', '%'.$search.'%')
                    ->orWhere('invest_amount', 'LIKE', '%'.$search.'%')
                    ->orWhere('interest_type', 'LIKE', '%'.$search.'%')
                    ->orWhere('return_type', 'LIKE', '%'.$search.'%')
                    ->orWhere('wallet', 'LIKE', '%'.$search.'%')
                    ->orWhere('status', 'LIKE', '%'.$search.'%')
                    ->orWhereHas('schema', function ($query) use ($search) {
                        $query->where('name', 'LIKE', '%'.$search.'%');
                    })
                    ->orWhereHas('user', function ($query) use ($search) {
                        $query->where('username', 'LIKE', '%'.$search.'%');
                    });
            });
        }

        return $query;
    }
}
