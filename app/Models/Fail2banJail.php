<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fail2banJail extends Model
{
    protected $table = 'fail2ban_jails';

    protected $fillable = [
        'name',
        'is_active',
        'currently_banned',
        'bantime',
        'findtime',
        'maxretry',
        'last_sync_at',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'currently_banned' => 'integer',
        'bantime'          => 'integer',
        'findtime'         => 'integer',
        'maxretry'         => 'integer',
        'last_sync_at'     => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}