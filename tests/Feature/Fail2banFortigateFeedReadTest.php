<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Fail2banIpStat;
use App\Services\Fail2ban\FortigateFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Licznik wpisów feedu FortiGate jest liczony z jednego odczytu pliku na żądanie.
 *
 * Strona /fail2ban pokazuje tę samą liczbę w karcie statystyk (badge „feed: N”)
 * oraz w sekcji feedu zakładki „Lista IP”. Oba miejsca muszą korzystać z jednego
 * wywołania FortigateFeedService::all() — inaczej każde wejście na panel czyta
 * public/f2b-ips.txt dwa razy.
 */
class Fail2banFortigateFeedReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_uses_one_feed_read_for_stats_badge_and_list_section(): void
    {
        $feed = Mockery::mock(FortigateFeedService::class);
        $feed->shouldReceive('all')
            ->once()
            ->andReturn(['198.51.100.7', '198.51.100.9', '203.0.113.4']);
        // Widok (list.blade.php) dokłada ścieżkę pliku — to nie jest odczyt zawartości.
        $feed->shouldReceive('path')->andReturn(public_path('f2b-ips.txt'));

        $this->app->instance(FortigateFeedService::class, $feed);

        $response = $this->actingAs($this->admin(), 'admin')->get('/fail2ban');

        $response->assertOk();
        $response->assertSee('feed: 3', false);
        $response->assertSee('<strong>3</strong> wpisów', false);
    }

    public function test_feed_download_reuses_the_same_single_read(): void
    {
        $feed = Mockery::mock(FortigateFeedService::class);
        $feed->shouldReceive('all')
            ->once()
            ->andReturn(['198.51.100.7', '203.0.113.4']);

        $this->app->instance(FortigateFeedService::class, $feed);

        $response = $this->actingAs($this->admin(), 'admin')
            ->get('/fail2ban?tab=list&download_feed=1');

        $response->assertOk();
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('content-type'));
        $this->assertSame("198.51.100.7\n203.0.113.4", $response->streamedContent());
    }

    public function test_feed_sync_writes_the_feed_without_reading_it_back(): void
    {
        $feed = Mockery::mock(FortigateFeedService::class);
        $feed->shouldReceive('replaceAll')
            ->once()
            ->with(Mockery::on(static function (array $ips): bool {
                sort($ips);

                return $ips === ['198.51.100.7', '203.0.113.4'];
            }))
            ->andReturn(2);
        // Sedno regresji: liczba wpisów pochodzi ze zwrotki replaceAll,
        // więc kontroler nie czyta pliku drugi raz po jego zapisaniu.
        $feed->shouldReceive('all')->never();

        $this->app->instance(FortigateFeedService::class, $feed);

        Fail2banIpStat::create(['ip' => '198.51.100.7', 'fortigate_banned' => true]);
        Fail2banIpStat::create(['ip' => '203.0.113.4', 'fortigate_banned' => true]);
        Fail2banIpStat::create(['ip' => '198.51.100.9', 'fortigate_banned' => false]);

        $response = $this->actingAs($this->admin(), 'admin')
            ->post('/fail2ban/fortigate-feed/sync');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('count', 2);
        $response->assertJsonPath('message', 'Zsynchronizowano feed FortiGate (2 IP).');
    }

    private function admin(): Admin
    {
        return Admin::create([
            'username' => 'tester',
            'password' => bcrypt('secret'),
        ]);
    }
}
