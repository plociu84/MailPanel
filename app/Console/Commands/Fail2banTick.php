<?php

namespace App\Console\Commands;

use App\Services\Fail2ban\Fail2banCollectorService;
use App\Services\Fail2ban\Fail2banSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Fail2banTick extends Command
{
    protected $signature = 'fail2ban:tick';
    protected $description = 'Sekwencyjnie uruchamia collect + sync (dla crona co minutę)';

    public function handle(
        Fail2banCollectorService $collector,
        Fail2banSyncService $sync
    ): int {
        $start = microtime(true);

        $collectStats = null;
        $syncStats = null;
        $errors = [];

        // Krok 1: collect (jeśli padnie — logujemy, ale próbujemy sync)
        try {
            $collectStats = $collector->collect();
        } catch (\Throwable $e) {
            $errors[] = 'collect: ' . $e->getMessage();
            Log::error('fail2ban:tick — collect failed', ['error' => $e->getMessage()]);
        }

        // Krok 2: sync
        try {
            $syncStats = $sync->sync();
        } catch (\Throwable $e) {
            $errors[] = 'sync: ' . $e->getMessage();
            Log::error('fail2ban:tick — sync failed', ['error' => $e->getMessage()]);
        }

        $elapsed = round(microtime(true) - $start, 2);

        // Log do Laravela tylko jeśli coś się działo (żeby nie spamować pustymi wpisami)
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

        // Wyjście CLI (dla /var/log/fail2ban-panel.log)
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