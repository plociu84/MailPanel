<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Fail2ban\Fail2banStatsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Fail2banPrune extends Command
{
    /**
     * Najkrótsza dopuszczalna retencja (zabezpieczenie przed literówką w --days).
     */
    private const MIN_RETENTION_DAYS = 7;

    /**
     * Górna granica liczby porcji w jednym przebiegu (bezpiecznik pętli usuwania).
     */
    private const MAX_CHUNKS = 1000;

    /**
     * Awaryjna retencja, gdy config `fail2ban.retention_days` jest niedostępny.
     */
    private const DEFAULT_RETENTION_DAYS = 30;

    protected $signature = 'fail2ban:prune
        {--days= : Retencja w dniach — domyślnie z configu: fail2ban.retention_days}
        {--chunk=5000 : Ile wierszy usuwać w jednej porcji}
        {--dry-run : Pokaż raport, nic nie usuwaj}
        {--keep-stats : Nie przeliczaj agregatów i nie zeruj sierot (zrobi to najbliższy tick)}
        {--force : Pozwól na retencję krótszą niż 7 dni}';

    protected $description = 'Usuwa zamknięte bany starsze niż N dni i zeruje osierocone agregaty IP';

    public function handle(Fail2banStatsService $stats): int
    {
        $days = (int) ($this->option('days') ?? config('fail2ban.retention_days', self::DEFAULT_RETENTION_DAYS));
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        if ($days < self::MIN_RETENTION_DAYS && ! $this->option('force')) {
            $this->error(sprintf(
                'Retencja %d dni jest krótsza niż minimum %d. Użyj --force, jeśli to zamierzone.',
                $days,
                self::MIN_RETENTION_DAYS
            ));

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $cutoffBinding = $cutoff->toDateTimeString();

        $candidates = DB::table('fail2ban_bans')
            ->whereNotNull('unbanned_at')
            ->where('unbanned_at', '<', $cutoff)
            ->selectRaw('COUNT(*) AS total, MIN(unbanned_at) AS oldest, MAX(unbanned_at) AS newest')
            ->first();

        $this->info(sprintf('Retencja: %d dni — cutoff %s', $days, $cutoffBinding));
        $this->table(['Metryka', 'Wartość'], [
            ['Wierszy w fail2ban_bans', (int) DB::table('fail2ban_bans')->count()],
            ['Kandydatów do usunięcia', (int) ($candidates->total ?? 0)],
            ['Najstarszy zamykany ban', (string) ($candidates->oldest ?? '—')],
            ['Najnowszy zamykany ban', (string) ($candidates->newest ?? '—')],
        ]);

        if ($dryRun) {
            $this->warn('--dry-run: nic nie usunięto.');

            return self::SUCCESS;
        }

        $startedAt = microtime(true);
        $deleted = 0;
        $chunks = 0;

        do {
            $affected = DB::delete(
                'DELETE FROM fail2ban_bans
                 WHERE id IN (
                     SELECT id FROM fail2ban_bans
                     WHERE unbanned_at IS NOT NULL AND unbanned_at < ?
                     LIMIT ?
                 )',
                [$cutoffBinding, $chunk]
            );

            $deleted += $affected;
            $chunks++;
        } while ($affected >= $chunk && $chunks < self::MAX_CHUNKS);

        $zeroedStats = 0;

        if ($this->option('keep-stats')) {
            $this->warn('--keep-stats: agregaty w fail2ban_ip_stats pozostawiono bez zmian.');
        } else {
            $stats->refreshIpStats();
            $zeroedStats = $this->zeroOrphanStats();
        }

        $elapsed = round(microtime(true) - $startedAt, 2);

        $this->table(['Metryka', 'Wartość'], [
            ['Usunięte bany', $deleted],
            ['Porcje', $chunks],
            ['Wyzerowane agregaty sierot', $zeroedStats],
            ['Wierszy w fail2ban_bans', (int) DB::table('fail2ban_bans')->count()],
            ['Czas (s)', $elapsed],
        ]);

        if ($deleted > 0 || $zeroedStats > 0) {
            Log::info('fail2ban:prune', [
                'days' => $days,
                'cutoff' => $cutoffBinding,
                'deleted' => $deleted,
                'chunks' => $chunks,
                'zeroed_ip_stats' => $zeroedStats,
                'elapsed_s' => $elapsed,
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * Czyści agregaty IP, które nie mają już żadnego bana (duchy w Historia/Recydywiści).
     *
     * Wiersze fail2ban_ip_stats zostają — trzymają `notes` operatora oraz flagę
     * `fortigate_banned`, z której FortigateFeedService buduje public/f2b-ips.txt.
     */
    private function zeroOrphanStats(): int
    {
        return DB::update(
            "UPDATE fail2ban_ip_stats
             SET total_bans = 0,
                 total_unique_jails = 0,
                 currently_banned = false,
                 first_banned_at = NULL,
                 last_banned_at = NULL,
                 jails = '[]',
                 updated_at = ?
             WHERE NOT EXISTS (
                 SELECT 1 FROM fail2ban_bans b WHERE b.ip = fail2ban_ip_stats.ip
             )",
            [now()->toDateTimeString()]
        );
    }
}
