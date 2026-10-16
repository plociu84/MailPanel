<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Fail2banIpStat;
use App\Services\Fail2ban\Fail2banStatsService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Fail2banPruneCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test odpala migrate:fresh — wolno go uruchomić wyłącznie na bazie in-memory.
     */
    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.connections.'.config('database.default').'.database') !== ':memory:') {
            $this->fail('fail2ban:prune czyści bazę (migrate:fresh) — test wymaga połączenia :memory:.');
        }
    }

    public function test_deletes_only_closed_bans_older_than_retention(): void
    {
        $this->mock(Fail2banStatsService::class)->shouldReceive('refreshIpStats')->once();

        $expired = [
            $this->ban('10.0.0.1', now()->subDays(45), now()->subDays(40)),
            $this->ban('10.0.0.1', now()->subDays(44), now()->subDays(39), 'nginx-http-auth'),
            $this->ban('10.0.0.2', now()->subDays(31), now()->subDays(31)),
        ];

        $kept = [
            $this->ban('10.0.0.3', now()->subDays(10), now()->subDays(5)),
            $this->ban('10.0.0.4', now()->subHours(2), null),
            $this->ban('10.0.0.5', now()->subDays(60), null),
        ];

        $this->artisan('fail2ban:prune', ['--days' => 30, '--chunk' => 2])
            ->assertExitCode(Command::SUCCESS);

        $remaining = DB::table('fail2ban_bans')->orderBy('id')->pluck('id')->all();

        $this->assertSame($kept, $remaining);
        $this->assertSame([], array_intersect($expired, $remaining));
        $this->assertSame(2, DB::table('fail2ban_bans')->whereNull('unbanned_at')->count());
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $this->mock(Fail2banStatsService::class)->shouldNotReceive('refreshIpStats');

        $id = $this->ban('10.1.0.1', now()->subDays(45), now()->subDays(40));

        $this->artisan('fail2ban:prune', ['--days' => 30, '--dry-run' => true])
            ->expectsOutputToContain('--dry-run')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('fail2ban_bans', ['id' => $id]);
    }

    public function test_refuses_retention_shorter_than_minimum_without_force(): void
    {
        $this->mock(Fail2banStatsService::class)->shouldNotReceive('refreshIpStats');

        $this->ban('10.2.0.1', now()->subDays(10), now()->subDays(8));

        $this->artisan('fail2ban:prune', ['--days' => 3])
            ->assertExitCode(Command::FAILURE);

        $this->assertSame(1, DB::table('fail2ban_bans')->count());
    }

    public function test_force_allows_short_retention(): void
    {
        $this->mock(Fail2banStatsService::class)->shouldReceive('refreshIpStats')->once();

        $id = $this->ban('10.2.1.1', now()->subDays(10), now()->subDays(8));

        $this->artisan('fail2ban:prune', ['--days' => 3, '--force' => true])
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseMissing('fail2ban_bans', ['id' => $id]);
    }

    public function test_zeroes_orphan_stats_but_preserves_notes_and_fortigate_flag(): void
    {
        $this->mock(Fail2banStatsService::class)->shouldReceive('refreshIpStats')->once();

        $orphanWithNotes = $this->stat('10.3.0.1', 5, currentlyBanned: true, notes: 'blokada na stałe', fortigate: true);
        $prunedOrphan = $this->stat('10.3.0.2', 3, currentlyBanned: true);
        $keptStat = $this->stat('10.3.0.3', 1);

        $this->ban('10.3.0.2', now()->subDays(45), now()->subDays(40));
        $this->ban('10.3.0.3', now()->subDays(10), now()->subDays(5));

        $this->artisan('fail2ban:prune', ['--days' => 30])->assertExitCode(Command::SUCCESS);

        $orphanWithNotes->refresh();
        $this->assertSame(0, $orphanWithNotes->total_bans);
        $this->assertSame(0, $orphanWithNotes->total_unique_jails);
        $this->assertFalse($orphanWithNotes->currently_banned);
        $this->assertNull($orphanWithNotes->first_banned_at);
        $this->assertNull($orphanWithNotes->last_banned_at);
        $this->assertSame([], $orphanWithNotes->jails);
        $this->assertSame('blokada na stałe', $orphanWithNotes->notes);
        $this->assertTrue($orphanWithNotes->fortigate_banned);

        $prunedOrphan->refresh();
        $this->assertSame(0, $prunedOrphan->total_bans);

        $keptStat->refresh();
        $this->assertSame(1, $keptStat->total_bans);
        $this->assertNotNull($keptStat->last_banned_at);
    }

    public function test_keep_stats_skips_aggregates_and_orphan_cleanup(): void
    {
        $this->mock(Fail2banStatsService::class)->shouldNotReceive('refreshIpStats');

        $stat = $this->stat('10.4.0.1', 4, currentlyBanned: true);
        $id = $this->ban('10.4.0.1', now()->subDays(45), now()->subDays(40));

        $this->artisan('fail2ban:prune', ['--days' => 30, '--keep-stats' => true])
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseMissing('fail2ban_bans', ['id' => $id]);

        $stat->refresh();
        $this->assertSame(4, $stat->total_bans);
    }

    public function test_default_retention_comes_from_config(): void
    {
        config(['fail2ban.retention_days' => 10]);

        $this->mock(Fail2banStatsService::class)->shouldReceive('refreshIpStats')->once();

        $expired = $this->ban('10.5.0.1', now()->subDays(20), now()->subDays(15));
        $kept = $this->ban('10.5.0.2', now()->subDays(8), now()->subDays(5));

        $this->artisan('fail2ban:prune')
            ->expectsOutputToContain('Retencja: 10 dni')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseMissing('fail2ban_bans', ['id' => $expired]);
        $this->assertDatabaseHas('fail2ban_bans', ['id' => $kept]);
    }

    private function ban(string $ip, Carbon $bannedAt, ?Carbon $unbannedAt, string $jail = 'sshd'): int
    {
        return (int) DB::table('fail2ban_bans')->insertGetId([
            'ip' => $ip,
            'jail' => $jail,
            'banned_at' => $bannedAt->toDateTimeString(),
            'unbanned_at' => $unbannedAt?->toDateTimeString(),
            'created_at' => $bannedAt->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    private function stat(
        string $ip,
        int $totalBans,
        bool $currentlyBanned = false,
        ?string $notes = null,
        bool $fortigate = false,
    ): Fail2banIpStat {
        return Fail2banIpStat::create([
            'ip' => $ip,
            'total_bans' => $totalBans,
            'total_unique_jails' => $totalBans > 0 ? 1 : 0,
            'first_banned_at' => now()->subDays(45),
            'last_banned_at' => now()->subDays(40),
            'last_unbanned_at' => now()->subDays(40),
            'currently_banned' => $currentlyBanned,
            'jails' => ['sshd'],
            'notes' => $notes,
            'fortigate_banned' => $fortigate,
        ]);
    }
}
