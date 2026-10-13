<?php

declare(strict_types=1);

namespace App\Services\Fail2ban;

use App\Models\Fail2banBan;
use App\Models\Fail2banSyncState;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Fail2banCollectorService
{
    private const STATE_OFFSET_KEY = 'log_offset';
    private const STATE_INODE_KEY  = 'log_inode';
    private const STATE_FILE_KEY   = 'log_file';

    public function __construct(
        private Fail2banSshClient $ssh,
        private Fail2banLogParser $parser,
        private Fail2banStatsService $stats,
        private string $remotePath = '/var/log/fail2ban.log',
    ) {}

    /**
     * Główna metoda — pobiera nowe linie i zapisuje zdarzenia do bazy.
     *
     * @param  bool  $fullReimport   Pełny reimport od początku logu
     * @param  bool  $refreshStats   Czy przeliczyć agregaty po imporcie (domyślnie tak)
     */
    public function collect(bool $fullReimport = false, bool $refreshStats = true): array
    {
        $stats = [
            'fetched_bytes'    => 0,
            'processed_events' => 0,
            'new_bans'         => 0,
            'closed_bans'      => 0,
            'skipped_orphans'  => 0,
        ];

        // 1. Sprawdź stan pliku zdalnego
        $size  = $this->ssh->fileSize($this->remotePath);
        $inode = $this->ssh->fileInode($this->remotePath);

        $lastInode  = Fail2banSyncState::get(self::STATE_INODE_KEY);
        $lastOffset = (int) Fail2banSyncState::get(self::STATE_OFFSET_KEY, 0);

        // 2. Wykryj rotację (inode się zmienił) albo force reimport
        if ($fullReimport || $lastInode === null || $lastInode !== $inode) {
            Log::info('Fail2ban collect: reset offsetu (rotacja lub pierwszy import)', [
                'old_inode' => $lastInode,
                'new_inode' => $inode,
                'size'      => $size,
            ]);
            $lastOffset = 0;
        }

        // 3. Jeśli plik się zmniejszył — reset
        if ($size < $lastOffset) {
            Log::warning('Fail2ban collect: plik mniejszy niż offset, reset do 0', [
                'size'   => $size,
                'offset' => $lastOffset,
            ]);
            $lastOffset = 0;
        }

        // 4. Nic nowego — kończymy
        if ($size === $lastOffset) {
            if ($refreshStats) {
                $this->stats->refreshIpStats();
            }
            return $stats;
        }

        // 5. Pobierz nowy fragment
        $content = $this->ssh->readFrom($this->remotePath, $lastOffset);
        $stats['fetched_bytes'] = strlen($content);

        // 6. Parsuj zdarzenia
        $lines  = $this->parser->splitLines($content);
        $events = iterator_to_array($this->parser->parseLines($lines));

        if (empty($events)) {
            $this->saveState($inode, $size);
            if ($refreshStats) {
                $this->stats->refreshIpStats();
            }
            return $stats;
        }

        // 7. Przetwarzaj w transakcji
        DB::transaction(function () use ($events, &$stats) {
            foreach ($events as $event) {
                $stats['processed_events']++;

                $occurredAt = $this->parser->toCarbon($event['occurred_at']);

                match ($event['action']) {
                    'Ban'   => $this->handleBan($event['ip'], $event['jail'], $occurredAt, $stats),
                    'Unban' => $this->handleUnban($event['ip'], $event['jail'], $occurredAt, $stats),
                    default => null,
                };
            }
        });

        // 8. Zapisz nowy offset dopiero PO udanej transakcji
        $this->saveState($inode, $size);

        // 9. Odśwież agregaty — tylko jeśli poproszono
        if ($refreshStats) {
            $this->stats->refreshIpStats();
        }

        return $stats;
    }

    private function handleBan(string $ip, string $jail, Carbon $bannedAt, array &$stats): void
    {
        $existingOpen = Fail2banBan::where('ip', $ip)
            ->where('jail', $jail)
            ->whereNull('unbanned_at')
            ->exists();

        if ($existingOpen) {
            return;
        }

        $exactExists = Fail2banBan::where('ip', $ip)
            ->where('jail', $jail)
            ->where('banned_at', $bannedAt)
            ->exists();

        if ($exactExists) {
            return;
        }

        try {
            Fail2banBan::create([
                'ip'        => $ip,
                'jail'      => $jail,
                'banned_at' => $bannedAt,
            ]);

            $stats['new_bans']++;
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Wyścig — partial unique index zadziałał
        }
    }

    private function handleUnban(string $ip, string $jail, Carbon $unbannedAt, array &$stats): void
    {
        $bans = Fail2banBan::where('ip', $ip)
            ->where('jail', $jail)
            ->whereNull('unbanned_at')
            ->get();

        if ($bans->isEmpty()) {
            $stats['skipped_orphans']++;
            return;
        }

        foreach ($bans as $ban) {
            $ban->unbanned_at  = $unbannedAt;
            $ban->ban_duration = (int) $unbannedAt->timestamp - (int) $ban->banned_at->timestamp;
            $ban->unban_reason = 'expired';
            $ban->save();

            $stats['closed_bans']++;
        }
    }

    private function saveState(string $inode, int $offset): void
    {
        Fail2banSyncState::set(self::STATE_FILE_KEY, $this->remotePath);
        Fail2banSyncState::set(self::STATE_INODE_KEY, $inode);
        Fail2banSyncState::set(self::STATE_OFFSET_KEY, $offset);
    }
}