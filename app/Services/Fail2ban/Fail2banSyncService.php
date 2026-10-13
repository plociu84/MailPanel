<?php

namespace App\Services\Fail2ban;

use App\Models\Fail2banBan;
use App\Models\Fail2banJail;
use Illuminate\Support\Facades\DB;

class Fail2banSyncService
{
    /**
     * Próg czasowy dla „wskrzeszania" banów.
     * Jeśli ban został zamknięty w ciągu ostatnich X minut, a fail2ban
     * go dalej trzyma — to znaczy, że to był fałszywy Unban (np. restart fail2bana).
     */
    private const RESURRECT_WINDOW_MINUTES = 10;

    public function __construct(
        private Fail2banClientService $client,
    ) {}

    /**
     * Synchronizuje jaile i aktywne bany z fail2ban-client do bazy.
     *
     * @return array{new_jails: int, active_jails: int, newly_banned: int, marked_unbanned: int, resurrected: int}
     */
    public function sync(): array
    {
        $stats = [
            'new_jails'        => 0,
            'active_jails'     => 0,
            'newly_banned'     => 0,
            'marked_unbanned' => 0,
            'resurrected'      => 0,
        ];

        // 1. Pobierz listę jaili
        $jailNames = $this->client->getJailList();
        if (empty($jailNames)) {
            return $stats;
        }

        $stats['active_jails'] = count($jailNames);

        // 2. Zaktualizuj tabelę fail2ban_jails
        $activeNames = [];
        foreach ($jailNames as $name) {
            $activeNames[] = $name;

            $jail = Fail2banJail::firstOrNew(['name' => $name]);
            $isNew = !$jail->exists;

            $config = $this->client->getJailConfig($name);
            $jail->bantime  = $config['bantime'];
            $jail->findtime = $config['findtime'];
            $jail->maxretry = $config['maxretry'];

            $jail->is_active    = true;
            $jail->last_sync_at = now();
            $jail->save();

            if ($isNew) {
                $stats['new_jails']++;
            }
        }

        // 3. Oznacz jaile, które zniknęły, jako nieaktywne
        Fail2banJail::whereNotIn('name', $activeNames)
            ->where('is_active', true)
            ->update(['is_active' => false, 'currently_banned' => 0]);

        // 4. Dla każdego jaila: pobierz aktywnych i zaktualizuj bazę
        DB::transaction(function () use ($jailNames, &$stats) {
            foreach ($jailNames as $name) {
                $status = $this->client->getJailStatus($name);

                // Aktualizuj licznik w jailu
                Fail2banJail::where('name', $name)->update([
                    'currently_banned' => $status['currently_banned'],
                ]);

                // Uzgadnij bany: insert / wskrześ / nic
                if (!empty($status['banned_ips'])) {
                    $result = $this->reconcileBans($name, $status['banned_ips']);
                    $stats['newly_banned'] += $result['created'];
                    $stats['resurrected']  += $result['resurrected'];
                }

                // Zamknij bany, których nie ma w fail2banie
                $stats['marked_unbanned'] += $this->markUnbanned($name, $status['banned_ips']);
            }

            // Odśwież agregaty per IP
            $this->refreshIpStats();
        });

        return $stats;
    }

    /**
     * Uzgadnia stan banów dla danego jaila z listą aktywnych IP z fail2ban-client.
     *
     * Dla każdego IP z listy:
     *  - ma JAKIKOLWIEK otwarty ban w bazie → nic nie rób
     *  - nie ma żadnego bana → utwórz nowy
     *  - ma tylko zamknięte:
     *      - najnowszy zamknięty < RESURRECT_WINDOW_MINUTES temu → wskrześ
     *      - najnowszy zamknięty ≥ RESURRECT_WINDOW_MINUTES temu → utwórz nowy
     *
     * @return array{created: int, resurrected: int}
     */
    private function reconcileBans(string $jail, array $activeIps): array
    {
        $result = ['created' => 0, 'resurrected' => 0];

        // Pobierz WSZYSTKIE bany dla tego jaila dla tych IP (otwarte i zamknięte)
        $bans = Fail2banBan::where('jail', $jail)
            ->whereIn('ip', $activeIps)
            ->get();

        $now = now();
        $resurrectThreshold = $now->copy()->subMinutes(self::RESURRECT_WINDOW_MINUTES);

        // Grupujemy bany według IP
        $groupedBans = $bans->groupBy('ip');

        $toInsert = [];
        $toResurrect = [];

        foreach ($activeIps as $ip) {
            $ipBans = $groupedBans->get($ip, collect());

            // Priorytet: jeśli jest jakikolwiek otwarty ban — nic nie rób
            if ($ipBans->contains('unbanned_at', null)) {
                continue;
            }

            // Znajdź najnowszy zamknięty ban
            $latestClosed = $ipBans->whereNotNull('unbanned_at')
                ->sortByDesc('unbanned_at')
                ->first();

            if ($latestClosed === null) {
                // Brak jakiegokolwiek bana → nowy
                $toInsert[] = $ip;
            } elseif ($latestClosed->unbanned_at >= $resurrectThreshold) {
                // Zamknięty niedawno, a fail2ban go trzyma → fałszywy Unban, wskrześ
                $toResurrect[] = $latestClosed->id;
            } else {
                // Zamknięty dawno → to naprawdę nowy ban
                $toInsert[] = $ip;
            }
        }

        // Wskrześ (batch UPDATE)
        if (!empty($toResurrect)) {
            $result['resurrected'] = DB::table('fail2ban_bans')
                ->whereIn('id', $toResurrect)
                ->update([
                    'unbanned_at'  => null,
                    'unban_reason' => null,
                    'ban_duration' => null,
                    'updated_at'   => $now,
                ]);
        }

        // Insert nowych (batch, z pominięciem konfliktów)
        if (!empty($toInsert)) {
            $inserts = [];
            foreach ($toInsert as $ip) {
                $inserts[] = [
                    'ip'          => $ip,
                    'jail'        => $jail,
                    'banned_at'   => $now,
                    'unbanned_at' => null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }

            $insertedCount = 0;
            foreach (array_chunk($inserts, 500) as $chunk) {
                $insertedCount += DB::table('fail2ban_bans')->insertOrIgnore($chunk);
            }
            $result['created'] = $insertedCount;
        }

        return $result;
    }

    /**
     * Oznacza jako odbanowane te wpisy, które są otwarte w bazie,
     * ale nie ma ich już w fail2ban-client status dla tego jaila.
     * Zwraca liczbę zmienionych wpisów.
     */
    private function markUnbanned(string $jail, array $activeIps): int
    {
        $now = now();

        $query = DB::table('fail2ban_bans')
            ->where('jail', $jail)
            ->whereNull('unbanned_at');

        if (!empty($activeIps)) {
            $query->whereNotIn('ip', $activeIps);
        }

        $count = $query->update([
            'unbanned_at'  => $now,
            'ban_duration' => DB::raw("EXTRACT(EPOCH FROM (TIMESTAMP '{$now->toDateTimeString()}' - banned_at))::int"),
            'unban_reason' => 'expired',
            'updated_at'   => $now,
        ]);

        return $count;
    }

    /**
     * Przelicza agregaty per IP (zoptymalizowane pod kątem wydajności).
     */
    private function refreshIpStats(): void
    {
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
    }
}