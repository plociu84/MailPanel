<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fail2banBan extends Model
{
    protected $table = 'fail2ban_bans';

    protected $fillable = [
        'ip',
        'jail',
        'banned_at',
        'unbanned_at',
        'ban_duration',
        'unban_reason',
    ];

    protected $casts = [
        'banned_at'    => 'datetime',
        'unbanned_at'  => 'datetime',
        'ban_duration' => 'integer',
    ];

    public function scopeOpen($query)
    {
        return $query->whereNull('unbanned_at');
    }

    public function scopeForJail($query, string $jail)
    {
        return $query->where('jail', $jail);
    }

    public function scopeForIp($query, string $ip)
    {
        return $query->where('ip', $ip);
    }
}