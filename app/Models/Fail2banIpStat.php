<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fail2banIpStat extends Model
{
    protected $table = 'fail2ban_ip_stats';

    protected $fillable = [
        'ip',
        'total_bans',
        'total_unique_jails',
        'first_banned_at',
        'last_banned_at',
        'last_unbanned_at',
        'currently_banned',
        'jails',
        'notes',
        'fortigate_banned',
        'fortigate_banned_at',
    ];

    protected $casts = [
        'total_bans'          => 'integer',
        'total_unique_jails'  => 'integer',
        'first_banned_at'     => 'datetime',
        'last_banned_at'      => 'datetime',
        'last_unbanned_at'    => 'datetime',
        'currently_banned'    => 'boolean',
        'jails'               => 'array',
        'fortigate_banned'    => 'boolean',
        'fortigate_banned_at' => 'datetime',
    ];

    public function scopeCurrentlyBanned($query)
    {
        return $query->where('currently_banned', true);
    }

    public function scopeRepeatOffenders($query, int $minBans = 2)
    {
        return $query->where('total_bans', '>=', $minBans)
                     ->orderByDesc('total_bans');
    }

    public function scopeFortigateBanned($query)
    {
        return $query->where('fortigate_banned', true);
    }

    public function bans()
    {
        return $this->hasMany(Fail2banBan::class, 'ip', 'ip');
    }
}