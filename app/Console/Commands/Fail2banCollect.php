<?php

namespace App\Console\Commands;

use App\Services\Fail2ban\Fail2banCollectorService;
use Illuminate\Console\Command;

class Fail2banCollect extends Command
{
    protected $signature = 'fail2ban:collect {--full : Pełny reimport od początku logu}';
    protected $description = 'Pobiera nowe zdarzenia z logu fail2ban i zapisuje do bazy';

    public function handle(Fail2banCollectorService $collector): int
    {
        $full = (bool) $this->option('full');

        $this->info($full ? 'Pełny reimport...' : 'Kolektor: pobieranie nowych zdarzeń...');

        $start = microtime(true);

        try {
            $stats = $collector->collect($full);
        } catch (\Throwable $e) {
            $this->error('Błąd: ' . $e->getMessage());
            return self::FAILURE;
        }

        $elapsed = round(microtime(true) - $start, 2);

        $this->newLine();
        $this->info("Czas: {$elapsed}s");
        $this->table(
            ['Metryka', 'Wartość'],
            [
                ['Pobrano bajtów', number_format($stats['fetched_bytes'], 0, ',', ' ')],
                ['Przetworzonych zdarzeń', $stats['processed_events']],
                ['Nowych banów', $stats['new_bans']],
                ['Zamkniętych banów', $stats['closed_bans']],
                ['Sierot (Unban bez Ban)', $stats['skipped_orphans']],
            ]
        );

        return self::SUCCESS;
    }
}