<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string      $key
 * @property string|null $value
 * @property \Carbon\Carbon|null $updated_at
 */
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

    /**
     * Pobiera wartość stanu po kluczu.
     */
    public static function get(string $key, $default = null): mixed
    {
        $row = static::where('key', $key)->first();
        return $row ? $row->value : $default;
    }

    /**
     * Zapisuje wartość stanu (atomowo).
     *
     * Używa upsert zamiast updateOrCreate — eliminuje wyścig przy równoległych set().
     * Wymaga unikalnego indeksu na kolumnie `key` (jest w migracji).
     */
    public static function set(string $key, $value): void
    {
        $encoded = match (true) {
            $value === null   => null,
            is_scalar($value) => (string) $value,
            default           => json_encode($value),
        };

        static::upsert(
            [[
                'key'        => $key,
                'value'      => $encoded,
                'updated_at' => now(),
            ]],
            ['key'],
            ['value', 'updated_at']
        );
    }
}