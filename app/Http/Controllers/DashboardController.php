<?php

namespace App\Http\Controllers;

use App\Models\Fail2banBan;
use App\Models\Fail2banIpStat;
use App\Services\Fail2ban\Fail2banSshClient;

class DashboardController extends Controller
{
    public function __construct(
        private Fail2banSshClient $ssh,
    ) {}

    public function index()
    {
        return view('dashboard', $this->buildDashboardData());
    }

    /**
     * Buduje całe dane dashboardu (SSH + SQL).
     */
    private function buildDashboardData(): array
    {
        // ============================================================
        // 1. INFORMACJE O SERWERZE MAIL (przez SSH)
        // ============================================================
        try {
            $osName = trim($this->ssh->run('grep PRETTY_NAME /etc/os-release | cut -d"=" -f2 | tr -d \'"\''));
            $hostname = trim($this->ssh->run('hostname'));
            $cpuModel = trim($this->ssh->run("grep 'model name' /proc/cpuinfo | head -1 | cut -d':' -f2 | xargs"));
            $cpuCores = (int) trim($this->ssh->run('nproc'));
        } catch (\Throwable $e) {
            $osName = 'Linux';
            $hostname = 'mail';
            $cpuModel = 'N/D';
            $cpuCores = 1;
        }

        // RAM
        $ramInfo = ['total' => 0, 'used' => 0, 'free' => 0];
        try {
            $freeRaw = $this->ssh->run('free -m');
            $freeLines = explode("\n", trim($freeRaw));
            if (count($freeLines) >= 2) {
                $cols = preg_split('/\s+/', $freeLines[1]);
                $ramInfo = [
                    'total' => (int) ($cols[1] ?? 0),
                    'used'  => (int) ($cols[2] ?? 0),
                    'free'  => (int) ($cols[3] ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            // zostają zera
        }

        // Dysk
        $diskInfo = ['total' => 0, 'used' => 0, 'free' => 0];
        try {
            $diskRaw = $this->ssh->run('df -BG / | tail -1 | awk \'{print $2,$3,$4}\'');
            $parts = preg_split('/\s+/', trim($diskRaw));
            if (count($parts) >= 3) {
                $diskInfo = [
                    'total' => (int) str_replace('G', '', $parts[0]),
                    'used'  => (int) str_replace('G', '', $parts[1]),
                    'free'  => (int) str_replace('G', '', $parts[2]),
                ];
            }
        } catch (\Throwable $e) {
            // zostają zera
        }

        // ============================================================
        // 2. SYSLOG (błędy systemowe)
        // ============================================================
        $syslogErrors = 'Brak zarejestrowanych błędów w syslog';
        try {
            $syslogRaw = $this->ssh->run("grep -iE 'error|failed|failure|crit|alert|emerg' /var/log/syslog | tail -n 50");
            $lines = array_filter(explode("\n", trim($syslogRaw)));
            if (!empty($lines)) {
                $syslogErrors = implode("\n", array_reverse(array_slice($lines, -40)));
            }
        } catch (\Throwable $e) {
            $syslogErrors = "Błąd SSH: " . $e->getMessage();
        }

        // ============================================================
        // 3. AMAVIS (spam)
        // ============================================================
        $amavisSpam = 'Brak wpisów SPAM w amavis.log';
        try {
            $amavisRaw = $this->ssh->run('tail -n 1000 /var/log/amavis/amavis.log');
            $lines = explode("\n", $amavisRaw);
            $parsedAmavis = [];

            foreach (array_reverse($lines) as $line) {
                if (stripos($line, 'spam') !== false && stripos($line, 'CLEAN') === false) {
                    preg_match('/\[(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\]/', $line, $ipMatch);
                    $ip = $ipMatch[1] ?? 'N/D';

                    preg_match_all('/<([^>]+)>/', $line, $emailMatches);
                    $sender = $emailMatches[1][0] ?? 'N/D';
                    $recipient = $emailMatches[1][1] ?? 'N/D';

                    $timestamp = trim(substr($line, 0, 19));
                    $entryKey = $timestamp . '-' . $ip . '-' . $sender;

                    if (!isset($parsedAmavis[$entryKey])) {
                        $parsedAmavis[$entryKey] = "Czas: {$timestamp} | IP: {$ip}\nOd: {$sender}\nDo: {$recipient}";
                    }
                }
            }

            if (!empty($parsedAmavis)) {
                $amavisSpam = implode("\n----------------------------------------\n", array_slice(array_values($parsedAmavis), 0, 15));
            }
        } catch (\Throwable $e) {
            $amavisSpam = "Błąd SSH: " . $e->getMessage();
        }

        // ============================================================
        // 4. FAIL2BAN (z bazy danych)
        // ============================================================
        $fail2banStats = [
            'active_bans'      => Fail2banIpStat::where('currently_banned', true)->count(),
            'total_ips'        => Fail2banIpStat::count(),
            'fortigate_banned' => Fail2banIpStat::where('fortigate_banned', true)->count(),
            'bans_24h'         => Fail2banBan::where('banned_at', '>=', now()->subDay())->count(),
        ];

        // Top 5 recydywistów — przekształcone na tablice z datą jako string
        $topRepeatOffenders = Fail2banIpStat::query()
            ->where('total_bans', '>=', 2)
            ->orderByDesc('total_bans')
            ->orderByDesc('last_banned_at')
            ->limit(5)
            ->get(['ip', 'total_bans', 'last_banned_at', 'currently_banned', 'jails', 'fortigate_banned'])
            ->map(fn($o) => [
                'ip'               => $o->ip,
                'total_bans'       => $o->total_bans,
                'last_banned_at'   => $o->last_banned_at?->format('Y-m-d H:i'),
                'currently_banned' => (bool) $o->currently_banned,
                'jails'            => $o->jails ?? [],
                'fortigate_banned' => (bool) $o->fortigate_banned,
            ])
            ->toArray();

        // Ostatnie 10 banów — przekształcone na tablice z datami jako string
        $recentBans = Fail2banBan::query()
            ->orderByDesc('banned_at')
            ->limit(10)
            ->get(['ip', 'jail', 'banned_at', 'unbanned_at'])
            ->map(fn($b) => [
                'ip'          => $b->ip,
                'jail'        => $b->jail,
                'banned_at'   => $b->banned_at?->format('Y-m-d H:i:s'),
                'unbanned_at' => $b->unbanned_at?->format('H:i:s'),
            ])
            ->toArray();

        // ============================================================
        // 5. ZWRÓCENIE DANYCH
        // ============================================================
        // Okno retencji — etykiety liczników per IP (top recydywistów liczy total_bans)
        $retentionDays = (int) config('fail2ban.retention_days');

        $machineInfo = [
            'hostname'  => $hostname,
            'os'        => $osName,
            'cpu_model' => $cpuModel,
            'cpu_cores' => $cpuCores,
            'ram'       => $ramInfo,
            'disk'      => $diskInfo,
        ];

        return compact(
            'machineInfo',
            'syslogErrors',
            'amavisSpam',
            'fail2banStats',
            'topRepeatOffenders',
            'recentBans',
            'retentionDays'
        );
    }
}