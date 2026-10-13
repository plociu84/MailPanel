<?php

declare(strict_types=1);

namespace App\Services\Fail2ban;

use App\Models\Fail2banBan;
use App\Models\Fail2banJail;
use Illuminate\Support\Facades\DB;

class Fail2banSyncService
{
    private const RESURRECT_WINDOW_MINUTES = 10;

    public function __construct(
        private Fail2banClientService $client,
        private Fail2banStatsService $stats,
    ) {}

    /**
     * Synchronizuje jaile i aktywne bany z fail2ban-client do bazy.
     *
     * @param  bool  $refreshStats  Czy przeliczyć agregaty po synchronizacji
     */
    public function sync(bool $refreshStats = true): array
    {
        $stats = [
            'new_jails'        => 0,
            'active_jails'     => 0,
            'newly_banned'     => 0,
            'marked_unbanned'  => 0,
            'resurrected'      => 0,
        ];

        // 1. Pobierz listę jaili
        $jailNames = $this->client->getJailList();
        if (empty($jailNames)) {
            if ($refreshStats) {
                $this->stats->refreshIpStats();
            }
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

                Fail2banJail::where('name', $name)->update([
                    'currently_banned' => $status['currently_banned'],
                ]);

                if (!empty($status['banned_ips'])) {
                    $result = $this->reconcileBans($name, $status['banned_ips']);
                    $stats['newly_banned'] += $result['created'];
                    $stats['resurrected']  += $result['resurrected'];
                }

                $stats['marked_unbanned'] += $this->markUnbanned($name, $status['banned_ips']);
            }
        });

        // 5. Odśwież agregaty — tylko jeśli poproszono
        if ($refreshStats) {
            $this->stats->refreshIpStats();
        }

        return $stats;
    }

    private function reconcileBans(string $jail, array $activeIps): array
    {
        $result = ['created' => 0, 'resurrected' => 0];

        $bans = Fail2banBan::where('jail', $jail)
            ->whereIn('ip', $activeIps)
            ->get();

        $now = now();
        $resurrectThreshold = $now->copy()->subMinutes(self::RESURRECT_WINDOW_MINUTES);

        $groupedBans = $bans->groupBy('ip');

        $toInsert = [];
        $toResurrect = [];

        foreach ($activeIps as $ip) {
            $ipBans = $groupedBans->get($ip, collect());

            if ($ipBans->contains('unbanned_at', null)) {
                continue;
            }

            $latestClosed = $ipBans->whereNotNull('unbanned_at')
                ->sortByDesc('unbanned_at')
                ->first();

            if ($latestClosed === null) {
                $toInsert[] = $ip;
            } elseif ($latestClosed->unbanned_at >= $resurrectThreshold) {
                $toResurrect[] = $latestClosed->id;
            } else {
                $toInsert[] = $ip;
            }
        }

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
}