<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Fail2ban\Fail2banCollectorService;
use App\Services\Fail2ban\Fail2banStatsService;
use App\Services\Fail2ban\Fail2banSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Fail2banTick extends Command
{
    protected $signature = 'fail2ban:tick';
    protected $description = 'Sekwencyjnie uruchamia collect + sync (dla crona co minutę)';

    public function handle(
        Fail2banCollectorService $collector,
        Fail2banSyncService $sync,
        Fail2banStatsService $stats,
    ): int {
        $start = microtime(true);

        $collectStats = null;
        $syncStats = null;
        $errors = [];

        // Krok 1: collect (bez refreshIpStats — zrobimy raz na końcu)
        try {
            $collectStats = $collector->collect(refreshStats: false);
        } catch (\Throwable $e) {
            $errors[] = 'collect: ' . $e->getMessage();
            Log::error('fail2ban:tick — collect failed', ['exception' => $e]);
        }

        // Krok 2: sync (bez refreshIpStats)
        try {
            $syncStats = $sync->sync(refreshStats: false);
        } catch (\Throwable $e) {
            $errors[] = 'sync: ' . $e->getMessage();
            Log::error('fail2ban:tick — sync failed', ['exception' => $e]);
        }

        // Krok 3: JEDNO refreshIpStats na końcu
        try {
            $stats->refreshIpStats();
        } catch (\Throwable $e) {
            $errors[] = 'stats: ' . $e->getMessage();
            Log::error('fail2ban:tick — refreshIpStats failed', ['exception' => $e]);
        }

        $elapsed = round(microtime(true) - $start, 2);

        $hasActivity = ($collectStats && ($collectStats['new_bans'] > 0 || $collectStats['closed_bans'] > 0))
                    || ($syncStats && ($syncStats['newly_banned'] > 0 || $syncStats['marked_unbanned'] > 0 || ($syncStats['resurrected'] ?? 0) > 0))
                    || !empty($errors);

        if ($hasActivity) {
            Log::info('fail2ban:tick', [
                'elapsed' => $elapsed,
                'collect' => $collectStats,
                'sync'    => $syncStats,
                'errors'  => $errors,
            ]);
        }

        $this->line(sprintf(
            '[%s] tick %.2fs | collect: +%d banów, -%d | sync: +%d, -%d, wskrzeszone: %d | sierot: %d%s',
            now()->format('Y-m-d H:i:s'),
            $elapsed,
            $collectStats['new_bans'] ?? 0,
            $collectStats['closed_bans'] ?? 0,
            $syncStats['newly_banned'] ?? 0,
            $syncStats['marked_unbanned'] ?? 0,
            $syncStats['resurrected'] ?? 0,
            $collectStats['skipped_orphans'] ?? 0,
            $errors ? ' | BŁĘDY: ' . implode('; ', $errors) : ''
        ));

        return empty($errors) ? self::SUCCESS : self::FAILURE;
    }
}