<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\Fail2ban\Fail2banSshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Panel „Logi z tworzenia konta” na dashboardzie czyta `/var/log/konta.log`
 * z serwera poczty. W tym samym pliku leżą wpisy o aliasach i uprawnieniach
 * nadawców — panel ma pokazywać wyłącznie operacje na kontach, z najnowszymi
 * na górze, i nie wywracać się, gdy pliku nie ma albo SSH zawiedzie.
 */
class DashboardMailAccountLogTest extends TestCase
{
    use RefreshDatabase;

    /** Jedyny dopuszczalny sposób odczytu logu kont (brak pliku = pusty wynik, nie błąd SSH). */
    private const READ_COMMAND = 'tail -n 400 /var/log/konta.log 2>/dev/null || true';

    /** Fragment prawdziwego /var/log/konta.log — kolejność i znaczniki jak w pliku. */
    private const MAIL_LOG = <<<'LOG'
=== START TWORZENIA KONTA: patrycjachmielewska (2026-09-14 11:01:19) ===
=== KONIEC TWORZENIA KONTA ===
--> Tworzenie użytkownika systemowego patrycjachmielewska
--> Zapisywanie w bazie Roundcube
=== START USUWANIA KONTA: jakubbarabas (2026-09-14 11:23:45) ===
=== KONIEC USUWANIA KONTA ===
=== USUWANIE UPRAWNIENIA NADAWCY: export@telmor.pl (2026-09-14 13:39:13) ===
=== DODAWANIE ALIASU: olaf.skrzypinski@telmor.pl -> tomasz.swiatek@telmor.pl (2026-09-30 15:32:18) ===
--> krok, który nie należy do żadnego wpisu konta
=== START AKTUALIZACJI KONTA: testpanel (2026-10-15 16:10:39) ===
=== KONIEC AKTUALIZACJI KONTA ===
LOG;

    /** @var list<string> */
    private array $sshCommands = [];

    public function test_panel_lists_account_operations_newest_first_and_skips_other_entries(): void
    {
        // Log kont zwracamy tylko dla odczytu `/var/log/konta.log`; pozostałe sekcje
        // dashboardu (syslog, amavis) dostają pusty wynik.
        $this->fakeSsh(fn (string $command): string => $command === self::READ_COMMAND ? self::MAIL_LOG : '');

        $response = $this->actingAs($this->admin(), 'admin')->get('/');

        $response->assertOk()
            ->assertSee('Logi z tworzenia konta')
            ->assertSee('Użytkownicy poczty');

        // Operacje na kontach wraz z krokami — output jest w pliku po linii `KONIEC`,
        // ale i tak należy do swojego wpisu.
        $response->assertSee('Utworzenie')
            ->assertSee('patrycjachmielewska')
            ->assertSee('Tworzenie użytkownika systemowego patrycjachmielewska')
            ->assertSee('Zapisywanie w bazie Roundcube')
            ->assertSee('Usunięcie')
            ->assertSee('jakubbarabas')
            ->assertSee('Aktualizacja')
            ->assertSee('testpanel');

        // Aliasy i uprawnienia nadawców oraz surowe znaczniki logu nie trafiają do panelu.
        $response->assertDontSee('DODAWANIE ALIASU')
            ->assertDontSee('UPRAWNIENIA NADAWCY')
            ->assertDontSee('START TWORZENIA KONTA')
            ->assertDontSee('KONIEC AKTUALIZACJI KONTA')
            ->assertDontSee('krok, który nie należy do żadnego wpisu konta');

        $html = (string) $response->getContent();
        $newest = strpos($html, 'testpanel');
        $oldest = strpos($html, 'patrycjachmielewska');

        $this->assertIsInt($newest);
        $this->assertIsInt($oldest);
        $this->assertLessThan($oldest, $newest, 'Najnowsza operacja ma stać wyżej niż starsza.');

        $this->assertContains(self::READ_COMMAND, $this->sshCommands);
    }

    public function test_panel_shows_empty_state_when_log_has_no_account_entries(): void
    {
        $this->fakeSsh('');

        $this->actingAs($this->admin(), 'admin')->get('/')
            ->assertOk()
            ->assertSee('Brak wpisów o kontach w /var/log/konta.log');
    }

    public function test_panel_reports_ssh_failure_instead_of_breaking_the_page(): void
    {
        $this->fakeSsh(function (string $command): string {
            if ($command === self::READ_COMMAND) {
                throw new RuntimeException('SSH command failed (exit 1)');
            }

            return '';
        });

        $this->actingAs($this->admin(), 'admin')->get('/')
            ->assertOk()
            ->assertSee('Błąd SSH: SSH command failed (exit 1)');
    }

    /**
     * Podstawia atrapę klienta SSH. Dashboard wykonuje kilka odczytów zdalnych
     * (syslog, amavis), dlatego domyślnie zwracamy pusty wynik — te testy pilnują
     * wyłącznie odczytu logu kont.
     *
     * @param  string|callable(string): string  $output
     */
    private function fakeSsh(string|callable $output): void
    {
        $this->sshCommands = [];

        $ssh = Mockery::mock(Fail2banSshClient::class);
        $ssh->shouldReceive('run')->andReturnUsing(function (string $command) use ($output): string {
            $this->sshCommands[] = $command;

            return is_string($output) ? $output : $output($command);
        });

        $this->app->instance(Fail2banSshClient::class, $ssh);
    }

    private function admin(): Admin
    {
        return Admin::create([
            'username' => 'tester',
            'password' => bcrypt('secret'),
        ]);
    }
}
