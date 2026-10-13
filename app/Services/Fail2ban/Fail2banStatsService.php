<?php

declare(strict_types=1);

namespace App\Services\Fail2ban;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Fail2banStatsService
{
    /**
     * Przelicza agregaty per IP (tabela fail2ban_ip_stats).
     *
     * Wykonuje jeden INSERT ... ON CONFLICT (ip) DO UPDATE, który:
     *  - dla nowych IP wstawia wiersz,
     *  - dla istniejących aktualizuje agregaty.
     *
     * UWAGA: ta operacja skanuje CAŁĄ tabelę fail2ban_bans.
     * Przy 100k+ wierszy może być wolna — dlatego wołamy ją raz na tick.
     */
    public function refreshIpStats(): void
    {
        $start = microtime(true);

        $sql = <<<SQL
        INSERT INTO fail2ban_ip_stats
            (ip, total_bans, total_unique_jails, first_banned_at, last_banned_at, last_unbanned_at, currently_banned, jails, created_at, updated_at)
        SELECT
            b.ip,
            COUNT(*) AS total_bans,
            COUNT(DISTINCT b.jail) AS total_unique_jails,
            MIN(b.banned_at) AS first_banned_at,
            MAX(b.banned_at) AS last_banned_at,
            MAX(b.unbanned_at) AS last_unbanned_at,
            BOOL_OR(b.unbanned_at IS NULL) AS currently_banned,
            to_jsonb(array_agg(DISTINCT b.jail)) AS jails,
            NOW(),
            NOW()
        FROM fail2ban_bans b
        GROUP BY b.ip
        ON CONFLICT (ip) DO UPDATE SET
            total_bans = EXCLUDED.total_bans,
            total_unique_jails = EXCLUDED.total_unique_jails,
            first_banned_at = EXCLUDED.first_banned_at,
            last_banned_at = EXCLUDED.last_banned_at,
            last_unbanned_at = EXCLUDED.last_unbanned_at,
            currently_banned = EXCLUDED.currently_banned,
            jails = EXCLUDED.jails,
            updated_at = NOW()
        SQL;

        DB::statement($sql);

        $elapsed = round(microtime(true) - $start, 2);

        if ($elapsed > 1.0) {
            Log::info('fail2ban:refreshIpStats', ['elapsed' => $elapsed]);
        }
    }
}