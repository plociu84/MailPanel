<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Fail2ban\Fail2banSshClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MailServerInfoService
{
    private const CACHE_KEY = 'mail_server_info';
    private const CACHE_TTL = 300; // 5 minut

    public function __construct(
        private Fail2banSshClient $ssh,
    ) {}

    /**
     * @return array{host: string, os: string, uptime: string, postfix: string, cyrus: string, amavis: string, spamassassin: string, status: string}
     */
    public function get(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->fetchFromServer();
        });
    }

    public function refresh(): array
    {
        Cache::forget(self::CACHE_KEY);
        return $this->get();
    }

    private function fetchFromServer(): array
    {
        try {
            $output = $this->ssh->run(
                'echo "===OS===" && (grep \'^PRETTY_NAME=\' /etc/os-release 2>/dev/null | cut -d\'"\' -f2 || echo "") && ' .
                'echo "===UPTIME===" && (uptime -p 2>/dev/null || uptime) && ' .
                'echo "===POSTFIX===" && (postconf -h mail_version 2>/dev/null || echo "") && ' .
                'echo "===CYRUS===" && (dpkg-query -W -f=\'${Version}\' cyrus-imapd 2>/dev/null || echo "") && ' .
                'echo "===AMAVIS===" && (amavisd-new -V 2>&1 | head -1 || echo "") && ' .
                'echo "===SPAMASSASSIN===" && (spamassassin --version 2>&1 | head -1 || echo "")'
            );

            $os           = $this->parseSection($output, 'OS');
            $uptime       = $this->parseSection($output, 'UPTIME');
            $postfix      = $this->parseSection($output, 'POSTFIX');
            $cyrus        = $this->parseSection($output, 'CYRUS');
            $amavis       = $this->parseSection($output, 'AMAVIS');
            $spamassassin = $this->parseSection($output, 'SPAMASSASSIN');

            return [
                'host'         => (string) config('services.ssh.host', 'Mail'),
                'os'           => $this->formatOs($os),
                'uptime'       => $this->formatUptime($uptime),
                'postfix'      => $this->extractVersion($postfix),
                'cyrus'        => $this->extractVersion($cyrus),
                'amavis'       => $this->extractAmavisVersion($amavis),
                'spamassassin' => $this->extractSpamassassinVersion($spamassassin),
                'status'       => 'online',
            ];
        } catch (\Throwable $e) {
            Log::warning('MailServerInfo: nie udało się pobrać informacji', [
                'error' => $e->getMessage(),
            ]);

            return [
                'host'         => (string) config('services.ssh.host', 'Mail'),
                'os'           => 'niedostępne',
                'uptime'       => 'niedostępne',
                'postfix'      => 'niedostępne',
                'cyrus'        => 'niedostępne',
                'amavis'       => 'niedostępne',
                'spamassassin' => 'niedostępne',
                'status'       => 'offline',
            ];
        }
    }

    private function parseSection(string $output, string $section): string
    {
        if (preg_match('/===' . preg_quote($section, '/') . '===\s*(.+?)(?====|\z)/s', $output, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private function formatOs(string $raw): string
    {
        $raw = trim($raw);
        return $raw === '' ? 'niedostępne' : $raw;
    }

    private function formatUptime(string $raw): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return 'niedostępne';
        }

        $replacements = [
            'up '         => '',
            ' years'      => ' lat',
            ' year'       => ' rok',
            ' months'     => ' mies.',
            ' month'      => ' mies.',
            ' weeks'      => ' tyg.',
            ' week'       => ' tyg.',
            ' days'       => ' dni',
            ' day'        => ' dzień',
            ' hours'      => ' godz.',
            ' hour'       => ' godz.',
            ' minutes'    => ' min.',
            ' minute'     => ' min.',
            ' seconds'    => ' sek.',
            ' second'     => ' sek.',
        ];

        return trim(str_replace(array_keys($replacements), array_values($replacements), $raw));
    }

    private function extractVersion(string $raw): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return 'niedostępne';
        }

        if (preg_match('/([0-9]+\.[0-9]+\.[0-9]+)/', $raw, $m)) {
            return $m[1];
        }

        return $raw;
    }

    private function extractAmavisVersion(string $raw): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return 'niedostępne';
        }

        if (preg_match('/amavisd-new-([0-9]+\.[0-9]+\.[0-9]+)/i', $raw, $m)) {
            return $m[1];
        }

        if (preg_match('/([0-9]+\.[0-9]+\.[0-9]+)/', $raw, $m)) {
            return $m[1];
        }

        return $raw;
    }

    /**
     * Wyciąga wersję SpamAssassina.
     * Format: "SpamAssassin version 3.4.6"
     */
    private function extractSpamassassinVersion(string $raw): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return 'niedostępne';
        }

        if (preg_match('/SpamAssassin version\s+([0-9]+\.[0-9]+\.[0-9]+)/i', $raw, $m)) {
            return $m[1];
        }

        if (preg_match('/([0-9]+\.[0-9]+\.[0-9]+)/', $raw, $m)) {
            return $m[1];
        }

        return $raw;
    }
}