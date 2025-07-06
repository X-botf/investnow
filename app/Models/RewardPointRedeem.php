<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewardPointRedeem extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function ranking()
    {
        return $this->hasOne(Ranking::class, 'id', 'ranking_id');
    }
}
