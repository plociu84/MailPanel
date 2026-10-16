<?php

namespace Tests\Unit;

use App\Models\Fail2banBan;
use App\Models\Fail2banIpStat;
use App\Services\Fail2ban\Fail2banStatsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * refreshIpStats() to pojedyncze `INSERT ... ON CONFLICT` po całej tabeli
 * fail2ban_bans — jego kontraktem jest kształt SQL, a nie wartość zwracana
 * do PHP. Testy pilnują więc, żeby refaktor nie zmienił po cichu agregatów,
 * listy kolumn ani mapowania ON CONFLICT. Baza nie jest potrzebna: `DB`
 * jest zamockowane, więc żadne połączenie nie jest otwierane.
 *
 * Gałąź z logowaniem wolnego przebiegu (> 1 s) nie ma testu — wymagałaby
 * realnego usypiania testu na ponad sekundę.
 */
class Fail2banStatsServiceTest extends TestCase
{
    /**
     * Zapytanie, które serwis ma wykonać (porównanie po normalizacji białych znaków).
     */
    private const EXPECTED_SQL = <<<'SQL'
        INSERT INTO fail2ban_ip_stats
            (ip, total_bans, total_unique_jails, first_banned_at, last_banned_at, last_unbanned_at, currently_banned, jails, created_at, updated_at)
        SELECT
            b.ip,
            COUNT(*) AS total_bans,
            COUNT(DISTINCT b.jail) AS total_unique_jails,
            MIN(b.banned_at) AS first_banned_at,
            MAX(b.banned_at) AS last_banned_at,
            MAX(b.unbanned_at) AS last_unbanned_at,
            BOOL_OR(b.unbanned_at IS NULL) AS currently_banned,
            to_jsonb(array_agg(DISTINCT b.jail)) AS jails,
            NOW(),
            NOW()
        FROM fail2ban_bans b
        GROUP BY b.ip
        ON CONFLICT (ip) DO UPDATE SET
            total_bans = EXCLUDED.total_bans,
            total_unique_jails = EXCLUDED.total_unique_jails,
            first_banned_at = EXCLUDED.first_banned_at,
            last_banned_at = EXCLUDED.last_banned_at,
            last_unbanned_at = EXCLUDED.last_unbanned_at,
            currently_banned = EXCLUDED.currently_banned,
            jails = EXCLUDED.jails,
            updated_at = NOW()
        SQL;

    private ?string $executedSql = null;

    public function test_refresh_executes_single_upsert_statement(): void
    {
        $sql = $this->sqlExecutedByRefresh();

        $this->assertSame(
            $this->normalize(self::EXPECTED_SQL),
            $this->normalize($sql)
        );
    }

    public function test_columns_used_by_sql_exist_in_models(): void
    {
        $sql = $this->sqlExecutedByRefresh();

        // Kolumny zapisywane do fail2ban_ip_stats muszą istnieć w modelu…
        preg_match('/INSERT INTO fail2ban_ip_stats\s*\(([^)]+)\)/i', $sql, $matches);
        $this->assertNotEmpty($matches, 'Nie udało się odczytać listy kolumn z zapytania.');

        $written = array_map('trim', explode(',', $matches[1]));
        $this->assertSame([], array_diff($written, $this->knownColumnsOf(new Fail2banIpStat)), 'SQL zapisuje kolumny nieznane modelowi Fail2banIpStat.');

        // …a kolumny źródłowe (alias b) w modelu Fail2banBan.
        preg_match_all('/\bb\.([a-z_]+)/i', $sql, $matches);
        $referenced = array_unique($matches[1]);
        $this->assertSame([], array_diff($referenced, $this->knownColumnsOf(new Fail2banBan)), 'SQL czyta kolumny nieznane modelowi Fail2banBan.');
    }

    public function test_fast_refresh_does_not_log_timing(): void
    {
        Log::spy();
        $this->sqlExecutedByRefresh();

        Log::shouldNotHaveReceived('info');
    }

    /**
     * Nazwy kolumn znane modelowi (fillable + klucz główny + znaczniki czasu).
     *
     * @return array<int, string>
     */
    private function knownColumnsOf(Fail2banBan|Fail2banIpStat $model): array
    {
        return array_merge($model->getFillable(), ['id', 'created_at', 'updated_at']);
    }

    /**
     * Wykonuje refreshIpStats() z zamockowanym `DB` i zwraca treść zapytania.
     */
    private function sqlExecutedByRefresh(): string
    {
        $this->executedSql = '';

        DB::shouldReceive('statement')
            ->once()
            ->with(Mockery::on(function (string $sql): bool {
                $this->executedSql = $sql;

                return true;
            }))
            ->andReturn(true);

        (new Fail2banStatsService)->refreshIpStats();

        $this->assertNotSame('', $this->executedSql, 'Serwis nie wykonał żadnego zapytania.');

        return $this->executedSql;
    }

    private function normalize(string $sql): string
    {
        return trim(preg_replace('/\s+/', ' ', $sql));
    }
}
