<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schema extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = [
        'total_revenue',
    ];

    public function schedule()
    {
        return $this->hasOne(Schedule::class, 'id', 'return_period');
    }

    public function getTotalRevenueAttribute()
    {
        $currencySymbol = setting('currency_symbol', 'global');
        if ($this->type == 'fixed' && $this->return_type == 'period') {
            if ($this->interest_type == 'percentage') {
                return $currencySymbol.calPercentage($this->fixed_amount, $this->return_interest) * $this->number_of_period;
            }

            return $currencySymbol.$this->return_interest * $this->number_of_period;

        }

        return __('Infinity');
    }

    public function holiday()
    {
        return $this->belongsToMany(Holiday::class, 'schema_holiday', 'schema_id', 'holiday_id');
    }
}
