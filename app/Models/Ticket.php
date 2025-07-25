<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends \Coderflex\LaravelTicket\Models\Ticket
{
    use HasFactory;

    public function getCreatedAtAttribute()
    {
        return Carbon::parse($this->attributes['created_at'])->format('M d Y h:i');
    }

    public function scopeUuid($query, $uuid)
    {
        return $query->where('uuid', $uuid)->first();
    }

    public function messages(): HasMany
    {
        $tableName = config('laravel_ticket.table_names.messages', 'messages');

        return $this->hasMany(
            Message::class,
            (string) $tableName['columns']['ticket_foreing_id'],
        );
    }

    public function scopeSearch($query, $search)
    {
        if ($search != null) {
            return $query->where(function ($query) use ($search) {
                $query->orWhere('uuid', 'LIKE', '%'.$search.'%')
                    ->orWhere('title', 'LIKE', '%'.$search.'%')
                    ->orWhere('message', 'LIKE', '%'.$search.'%')
                    ->orWhere('priority', 'LIKE', '%'.$search.'%')
                    ->orWhere('status', 'LIKE', '%'.$search.'%');
            });
        }

        return $query;
    }
}
