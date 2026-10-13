<?php

namespace App\Console\Commands;

use App\Services\Fail2ban\Fail2banSyncService;
use Illuminate\Console\Command;

class Fail2banSync extends Command
{
    protected $signature = 'fail2ban:sync';
    protected $description = 'Synchronizuje jaile i aktywne bany z fail2ban-client do bazy';

    public function handle(Fail2banSyncService $sync): int
    {
        $this->info('Synchronizacja z fail2ban-client...');

        $start = microtime(true);

        try {
            $stats = $sync->sync();
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
                ['Aktywnych jaili', $stats['active_jails']],
                ['Nowych jaili', $stats['new_jails']],
                ['Nowo wykrytych banów', $stats['newly_banned']],
                ['Wskrzeszonych banów', $stats['resurrected']],
                ['Oznaczonych jako odbanowane', $stats['marked_unbanned']],
            ]
        );

        return self::SUCCESS;
    }
}