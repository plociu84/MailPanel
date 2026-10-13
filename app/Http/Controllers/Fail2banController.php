<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Fail2banNoteRequest;
use App\Http\Requests\Fail2banUnbanRequest;
use App\Models\Fail2banBan;
use App\Models\Fail2banIpStat;
use App\Models\Fail2banJail;
use App\Services\Fail2ban\Fail2banCollectorService;
use App\Services\Fail2ban\Fail2banSshClient;
use App\Services\Fail2ban\Fail2banStatsService;
use App\Services\Fail2ban\Fail2banSyncService;
use App\Services\Fail2ban\FortigateFeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Fail2banController extends Controller
{
    public function __construct(
        private Fail2banSshClient $ssh,
    ) {}

    public function index(Request $request, FortigateFeedService $feed)
    {
        $selectedJail = $request->get('jail', 'all');
        $statusFilter = $request->get('status', 'active');
        $dateFilter   = $request->get('date', 'all');
        $minBans      = (int) $request->get('min_bans', 0);
        $search       = trim((string) $request->get('q', ''));
        $listBans     = (int) $request->get('list_bans', 0); // 0 = nie pokazuj listy

        // Aktywna zakładka — walidacja whitelistą
        $activeTab = $request->get('tab', 'active');
        if (! in_array($activeTab, ['active', 'history', 'repeat', 'logs', 'list'], true)) {
            $activeTab = 'active';
        }

        $jails = Fail2banJail::orderBy('name')->get();

        $stats = [
            'currently_banned' => Fail2banIpStat::where('currently_banned', true)->count(),
            'total_ips'        => Fail2banIpStat::count(),
            'bans_last_24h'    => Fail2banBan::where('banned_at', '>=', now()->subDay())->count(),
            'bans_last_7d'     => Fail2banBan::where('banned_at', '>=', now()->subWeek())->count(),
            'fortigate_banned' => Fail2banIpStat::where('fortigate_banned', true)->count(),
            'fortigate_feed_count' => count($feed->all()),
        ];

        // Parametry zachowywane w linkach paginacji (wspólne filtry)
        $keepParams = $request->only(['jail', 'date', 'min_bans', 'q']);

        // 1. Aktywne bany
        $activeBansQuery = Fail2banIpStat::query()->where('currently_banned', true);

        if ($selectedJail !== 'all') {
            $activeBansQuery->whereRaw("jails @> ?::jsonb", [json_encode([$selectedJail])]);
        }

        if ($minBans > 0) {
            $activeBansQuery->where('total_bans', '>=', $minBans);
        }

        if ($search !== '') {
            $activeBansQuery->where(function ($q) use ($search) {
                $q->where('ip', 'ILIKE', "%{$search}%")
                  ->orWhereRaw("jails::text ILIKE ?", ["%{$search}%"]);
            });
        }

        $activeBans = $activeBansQuery
            ->orderByDesc('last_banned_at')
            ->paginate(50, ['*'], 'active_page')
            ->appends($keepParams)
            ->appends(['tab' => 'active']);

        // 2. Historia banów
        $historyQuery = Fail2banIpStat::query()
            ->where('currently_banned', false)
            ->where('total_bans', '>', 0);

        if ($selectedJail !== 'all') {
            $historyQuery->whereRaw("jails @> ?::jsonb", [json_encode([$selectedJail])]);
        }

        if ($minBans > 0) {
            $historyQuery->where('total_bans', '>=', $minBans);
        }

        if ($dateFilter === '24h') {
            $historyQuery->where('last_banned_at', '>=', now()->subDay());
        } elseif ($dateFilter === '7d') {
            $historyQuery->where('last_banned_at', '>=', now()->subWeek());
        } elseif ($dateFilter === '30d') {
            $historyQuery->where('last_banned_at', '>=', now()->subDays(30));
        }

        if ($search !== '') {
            $historyQuery->where(function ($q) use ($search) {
                $q->where('ip', 'ILIKE', "%{$search}%")
                  ->orWhereRaw("jails::text ILIKE ?", ["%{$search}%"]);
            });
        }

        $historyIps = $historyQuery
            ->orderByDesc('last_banned_at')
            ->paginate(50, ['*'], 'history_page')
            ->appends($keepParams)
            ->appends(['tab' => 'history']);

        // 3. Recydywiści (min. 2 bany)
        $repeatOffendersQuery = Fail2banIpStat::query()->where('total_bans', '>=', 2);

        if ($selectedJail !== 'all') {
            $repeatOffendersQuery->whereRaw("jails @> ?::jsonb", [json_encode([$selectedJail])]);
        }

        if ($search !== '') {
            $repeatOffendersQuery->where(function ($q) use ($search) {
                $q->where('ip', 'ILIKE', "%{$search}%")
                  ->orWhereRaw("jails::text ILIKE ?", ["%{$search}%"]);
            });
        }

        $repeatOffenders = $repeatOffendersQuery
            ->orderByDesc('total_bans')
            ->orderByDesc('last_banned_at')
            ->paginate(50, ['*'], 'repeat_page')
            ->appends($keepParams)
            ->appends(['tab' => 'repeat']);

        // 4. Ostatnie zdarzenia z logów
        $recentEvents = Fail2banBan::query()
            ->orderByDesc('banned_at')
            ->limit(100)
            ->get(['ip', 'jail', 'banned_at', 'unbanned_at', 'ban_duration', 'unban_reason']);

        // 5. Lista IP recydywistów (do kopiowania) — min. X banów
        $ipList = collect();
        if ($listBans > 0) {
            $ipQuery = Fail2banIpStat::query()
                ->where('total_bans', '>=', $listBans);

            if ($selectedJail !== 'all') {
                $ipQuery->whereRaw("jails @> ?::jsonb", [json_encode([$selectedJail])]);
            }

            if ($search !== '') {
                $ipQuery->where('ip', 'ILIKE', "%{$search}%");
            }

            $ipList = $ipQuery
                ->orderByDesc('total_bans')
                ->orderByRaw('ip::inet ASC')
                ->pluck('ip');
        }

        // 6. Opcjonalny eksport .txt (lista z bazy)
        if ($request->boolean('download') && $ipList->isNotEmpty()) {
            $filename = 'fail2ban-ips-min-' . $listBans . '-bans-' . now()->format('Ymd-His') . '.txt';

            return response()->streamDownload(function () use ($ipList) {
                echo $ipList->implode("\n");
            }, $filename, ['Content-Type' => 'text/plain']);
        }

        // 7. Opcjonalny podgląd feedu FortiGate (pobranie aktualnego pliku)
        if ($request->boolean('download_feed')) {
            $feedIps = $feed->all();
            $filename = 'fortigate-f2b-feed-' . now()->format('Ymd-His') . '.txt';

            return response()->streamDownload(function () use ($feedIps) {
                echo implode("\n", $feedIps);
            }, $filename, ['Content-Type' => 'text/plain']);
        }

        // 8. Informacja o liczbie wpisów w feedzie FortiGate
        $fortigateFeedCount = count($feed->all());

        return view('fail2ban.index', compact(
            'jails', 'selectedJail', 'statusFilter', 'dateFilter',
            'minBans', 'search', 'stats', 'activeBans',
            'historyIps', 'repeatOffenders', 'recentEvents',
            'activeTab', 'listBans', 'ipList', 'fortigateFeedCount'
        ));
    }

    public function unban(Fail2banUnbanRequest $request)
    {
        $ip   = $request->input('ip');
        $jail = $request->input('jail');

        try {
            $this->ssh->run(sprintf(
                'fail2ban-client set %s unbanip %s',
                escapeshellarg($jail),
                escapeshellarg($ip)
            ));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Błąd SSH podczas odblokowywania: {$e->getMessage()}");
        }

        $now = now();

        DB::table('fail2ban_bans')
            ->where('ip', $ip)
            ->where('jail', $jail)
            ->whereNull('unbanned_at')
            ->update([
                'unbanned_at'  => $now,
                'ban_duration' => DB::raw("EXTRACT(EPOCH FROM (TIMESTAMP '{$now->toDateTimeString()}' - banned_at))::int"),
                'unban_reason' => 'manual',
                'updated_at'   => $now,
            ]);

        $stillBanned = Fail2banBan::where('ip', $ip)->whereNull('unbanned_at')->exists();

        DB::table('fail2ban_ip_stats')
            ->where('ip', $ip)
            ->update([
                'currently_banned' => $stillBanned,
                'last_unbanned_at' => $now,
                'updated_at'       => $now,
            ]);

        return redirect()->back()->with('success', "Adres IP {$ip} został odblokowany w jailu {$jail}.");
    }

    public function showIp(string $ip)
    {
        $stat = Fail2banIpStat::where('ip', $ip)->firstOrFail();

        $bans = Fail2banBan::where('ip', $ip)
            ->orderByDesc('banned_at')
            ->get(['id', 'jail', 'banned_at', 'unbanned_at', 'ban_duration', 'unban_reason']);

        return response()->json([
            'ip'                  => $stat->ip,
            'notes'               => $stat->notes,
            'total_bans'          => $stat->total_bans,
            'jails'               => $stat->jails,
            'fortigate_banned'    => $stat->fortigate_banned,
            'fortigate_banned_at' => $stat->fortigate_banned_at?->format('Y-m-d H:i:s'),
            'bans'                => $bans->map(fn ($b) => [
                'jail'         => $b->jail,
                'banned_at'    => $b->banned_at->format('Y-m-d H:i:s'),
                'unbanned_at'  => $b->unbanned_at?->format('Y-m-d H:i:s'),
                'ban_duration' => $b->ban_duration,
                'unban_reason' => $b->unban_reason,
            ]),
        ]);
    }

    public function saveNote(Fail2banNoteRequest $request, string $ip)
    {
        $stat = Fail2banIpStat::where('ip', $ip)->firstOrFail();
        $stat->notes = $request->input('notes');
        $stat->save();

        return response()->json(['success' => true, 'notes' => $stat->notes]);
    }

    /**
     * Przełącza flagę FortiGate dla IP i synchronizuje plik feedu.
     */
    public function toggleFortigate(string $ip, FortigateFeedService $feed)
    {
        $stat = Fail2banIpStat::where('ip', $ip)->firstOrFail();

        try {
            if ($stat->fortigate_banned) {
                $feed->remove($ip);
                $stat->fortigate_banned    = false;
                $stat->fortigate_banned_at = null;
                $action = 'removed';
            } else {
                $feed->add($ip);
                $stat->fortigate_banned    = true;
                $stat->fortigate_banned_at = now();
                $action = 'added';
            }
            $stat->save();
        } catch (\Throwable $e) {
            Log::error('Fortigate feed update failed', [
                'ip'    => $ip,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Błąd aktualizacji feedu: {$e->getMessage()}",
            ], 500);
        }

        return response()->json([
            'success'             => true,
            'action'              => $action,
            'fortigate_banned'    => $stat->fortigate_banned,
            'fortigate_banned_at' => $stat->fortigate_banned_at?->format('Y-m-d H:i:s'),
            'feed_count'          => count($feed->all()),
        ]);
    }

    /**
     * Jednorazowa synchronizacja feedu z bazy (IP z fortigate_banned = true).
     */
    public function syncFortigateFeed(FortigateFeedService $feed)
    {
        try {
            $ips = Fail2banIpStat::where('fortigate_banned', true)
                ->pluck('ip')
                ->all();

            $feed->replaceAll($ips);

            return response()->json([
                'success' => true,
                'count'   => count($feed->all()),
                'message' => 'Zsynchronizowano feed FortiGate (' . count($ips) . ' IP).',
            ]);
        } catch (\Throwable $e) {
            Log::error('Fortigate feed sync failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => "Błąd synchronizacji: {$e->getMessage()}",
            ], 500);
        }
    }

    /**
     * Podgląd pliku feedu (JSON — do panelu).
     */
    public function previewFortigateFeed(FortigateFeedService $feed)
    {
        $ips = $feed->all();

        return response()->json([
            'success' => true,
            'count'   => count($ips),
            'ips'     => $ips,
            'path'    => $feed->path(),
        ]);
    }

    /**
     * Odświeżenie — collect + sync + jedno refreshIpStats.
     *
     * ZMIANA (Etap 2B.3): collect i sync wołane z refreshStats: false,
     * a refreshIpStats wykonujemy raz na końcu — zamiast dwa razy
     * (raz w collect, raz w sync).
     */
    public function refresh(Request $request, Fail2banStatsService $stats)
    {
        try {
            app(Fail2banCollectorService::class)->collect(refreshStats: false);
            app(Fail2banSyncService::class)->sync(refreshStats: false);
            $stats->refreshIpStats();

            Cache::put('fail2ban_last_synced', now()->format('H:i:s'), 3600);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Zsynchronizowano.',
                ]);
            }

            return redirect()->back()->with('success', 'Zsynchronizowano Fail2ban.');
        } catch (\Throwable $e) {
            Log::error('Fail2ban refresh failed', ['exception' => $e]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Błąd odświeżania: {$e->getMessage()}",
                ], 500);
            }

            return redirect()->back()->with('error', "Błąd odświeżania: {$e->getMessage()}");
        }
    }

    public function status(Request $request)
    {
        return response()->json([
            'syncing'     => false,
            'last_synced' => Cache::get('fail2ban_last_synced', '-'),
        ]);
    }
}