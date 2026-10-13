<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Fail2ban\Fail2banLogParser;
use App\Services\Fail2ban\Fail2banSshClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Fail2banTestParser extends Command
{
    protected $signature = 'fail2ban:test-parser {--limit=20 : Ile pierwszych zdarzeń pokazać} {--tail=1000 : Ile ostatnich linii logu pobrać}';

    protected $description = 'Test parsera logu fail2ban przez SSH (bez zapisu do bazy)';

    public function handle(Fail2banLogParser $parser, Fail2banSshClient $ssh): int
    {
        $host = (string) config('services.ssh.host');
        $remotePath = (string) config('fail2ban.log_path', '/var/log/fail2ban.log');

        $this->info("Łączenie z {$host}...");
        $this->info("Plik: {$remotePath}");

        // 1. Metadane pliku
        try {
            $size  = $ssh->fileSize($remotePath);
            $inode = $ssh->fileInode($remotePath);
        } catch (\Throwable $e) {
            $this->error('Błąd SSH: ' . $e->getMessage());
            Log::error('fail2ban:test-parser — SSH failed', ['exception' => $e]);
            return self::FAILURE;
        }

        $this->info('Rozmiar: ' . number_format($size, 0, ',', ' ') . ' B');
        $this->info("Inode: {$inode}");

        // 2. Pobierz ostatnie N linii (zamiast całego pliku)
        $tailLines = max(1, (int) $this->option('tail'));
        $this->info("Pobieranie ostatnich {$tailLines} linii przez SSH...");

        $start = microtime(true);

        try {
            $content = $ssh->run('tail -n ' . $tailLines . ' ' . escapeshellarg($remotePath));
        } catch (\Throwable $e) {
            $this->error('Błąd pobierania: ' . $e->getMessage());
            Log::error('fail2ban:test-parser — fetch failed', ['exception' => $e]);
            return self::FAILURE;
        }

        $fetchTime = round(microtime(true) - $start, 2);
        $this->info('Pobrano ' . number_format(strlen($content), 0, ',', ' ') . " B w {$fetchTime}s");

        // 3. Parsowanie
        $this->info('Parsowanie...');
        $start = microtime(true);

        $limit = max(1, (int) $this->option('limit'));
        $counts = [];
        $jails = [];
        $first = [];
        $last = null;
        $total = 0;

        $lines = $parser->splitLines($content);
        foreach ($parser->parseLines($lines) as $event) {
            $total++;
            $counts[$event['action']] = ($counts[$event['action']] ?? 0) + 1;
            $jails[$event['jail']] = ($jails[$event['jail']] ?? 0) + 1;

            if ($total <= $limit) {
                $first[] = $event;
            }
            $last = $event;
        }

        $parseTime = round(microtime(true) - $start, 2);

        // 4. Jeśli nic nie znaleziono — to jest błąd (parser nie działa)
        if ($total === 0) {
            $this->newLine();
            $this->warn('Nie znaleziono żadnych zdarzeń.');
            $this->warn('Sprawdź:');
            $this->line('  - czy plik ' . $remotePath . ' istnieje i ma treść,');
            $this->line('  - czy pattern w Fail2banLogParser pasuje do formatu logu,');
            $this->line('  - czy pobrano wystarczająco dużo linii (--tail).');
            return self::FAILURE;
        }

        // 5. Podsumowanie
        $this->newLine();
        $this->info("Czas parsowania: {$parseTime}s");
        $this->info("Łącznie zdarzeń: {$total}");

        $this->table(
            ['Akcja', 'Liczba'],
            collect($counts)
                ->sortDesc()
                ->map(fn ($v, $k) => [$k, $v])
                ->values()
                ->all()
        );

        $this->newLine();
        $this->info('Jaile:');
        $this->table(
            ['Jail', 'Liczba zdarzeń'],
            collect($jails)
                ->sortDesc()
                ->map(fn ($v, $k) => [$k, $v])
                ->values()
                ->all()
        );

        $this->newLine();
        $this->info("Pierwsze {$limit} zdarzeń:");
        foreach ($first as $e) {
            $this->line(sprintf(
                '  [%s] %s %s %s',
                $e['occurred_at'],
                $e['action'],
                $e['ip'],
                $e['jail']
            ));
        }

        if ($last !== null) {
            $this->newLine();
            $this->info("Ostatnie: [{$last['occurred_at']}] {$last['action']} {$last['ip']} ({$last['jail']})");
        }

        return self::SUCCESS;
    }
}