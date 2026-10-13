<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fail2banSyncState extends Model
{
    protected $table = 'fail2ban_sync_state';

    public $timestamps = false;

    protected $fillable = [
        'key',
        'value',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public static function get(string $key, $default = null)
    {
        $row = static::where('key', $key)->first();
        return $row ? $row->value : $default;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_scalar($value) ? (string) $value : json_encode($value), 'updated_at' => now()]
        );
    }
}