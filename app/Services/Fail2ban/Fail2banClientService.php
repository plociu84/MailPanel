<?php

namespace App\Services\Fail2ban;

class Fail2banClientService
{
    public function __construct(
        private Fail2banSshClient $ssh,
    ) {}

    /**
     * Pobiera listę jaili z fail2ban-client status.
     *
     * @return string[]
     */
    public function getJailList(): array
    {
        $output = $this->ssh->run('fail2ban-client status');

        foreach (explode("\n", $output) as $line) {
            if (preg_match('/Jail list:\s*(.+)$/', $line, $m)) {
                $jails = array_map('trim', explode(',', $m[1]));
                return array_values(array_filter($jails));
            }
        }

        return [];
    }

    /**
     * Pobiera status pojedynczego jaila.
     *
     * @return array{
     *     currently_failed: int,
     *     total_failed: int,
     *     currently_banned: int,
     *     total_banned: int,
     *     banned_ips: string[]
     * }
     */
    public function getJailStatus(string $jail): array
    {
        $output = $this->ssh->run('fail2ban-client status ' . escapeshellarg($jail));

        $result = [
            'currently_failed' => 0,
            'total_failed'     => 0,
            'currently_banned' => 0,
            'total_banned'     => 0,
            'banned_ips'       => [],
        ];

        foreach (explode("\n", $output) as $line) {
            if (preg_match('/Currently failed:\s*(\d+)/', $line, $m)) {
                $result['currently_failed'] = (int) $m[1];
            } elseif (preg_match('/Total failed:\s*(\d+)/', $line, $m)) {
                $result['total_failed'] = (int) $m[1];
            } elseif (preg_match('/Currently banned:\s*(\d+)/', $line, $m)) {
                $result['currently_banned'] = (int) $m[1];
            } elseif (preg_match('/Total banned:\s*(\d+)/', $line, $m)) {
                $result['total_banned'] = (int) $m[1];
            } elseif (preg_match('/Banned IP list:\s*(.*)$/', $line, $m)) {
                $ips = preg_split('/\s+/', trim($m[1]));
                $result['banned_ips'] = array_values(array_filter($ips));
            }
        }

        return $result;
    }

    /**
     * Pobiera pełne metadane jaila (bantime, findtime, maxretry) w JEDNYM połączeniu SSH.
     *
     * @return array{bantime: ?int, findtime: ?int, maxretry: ?int}
     */
    public function getJailConfig(string $jail): array
    {
        $jailEsc = escapeshellarg($jail);
        $cmd = sprintf(
            'fail2ban-client get %1$s bantime && fail2ban-client get %1$s findtime && fail2ban-client get %1$s maxretry',
            $jailEsc
        );

        try {
            $output = $this->ssh->run($cmd);
        } catch (\Throwable $e) {
            return ['bantime' => null, 'findtime' => null, 'maxretry' => null];
        }

        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $output)),
            fn ($l) => $l !== ''
        ));

        return [
            'bantime'  => isset($lines[0]) && is_numeric($lines[0]) ? (int) $lines[0] : null,
            'findtime' => isset($lines[1]) && is_numeric($lines[1]) ? (int) $lines[1] : null,
            'maxretry' => isset($lines[2]) && is_numeric($lines[2]) ? (int) $lines[2] : null,
        ];
    }
}