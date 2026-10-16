<?php

namespace Tests\Unit;

use App\Services\Fail2ban\Fail2banSshClient;
use App\Services\MailServerService;
use Exception;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class MailServerServiceCommandSafetyTest extends TestCase
{
    private const LOGIN = 'jan.kowalski';

    private const HOSTILE_PASSWORD = "a'; touch /tmp/pwned; echo '";

    private const HOSTILE_LOGIN = "jan'; id; echo '";

    /**
     * Klucz wpisu mapy: bez białych znaków (inaczej walidacja usługi go odrzuca),
     * ale z metaznakami powłoki, żeby sprawdzić cytowanie komunikatu logu.
     */
    private const HOSTILE_MAP_KEY = "x';touch\${IFS}/tmp/pwned;echo'";

    private const HOSTILE_MAP_VALUE = 'kontakt@telmor.pl; touch /tmp/pwned';

    /**
     * @var list<array{command: string, redact: array<int, string>}>
     */
    private array $captured = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Nie używamy sekretów produkcyjnych w testach (trafiają do asercji/dumpów).
        config(['services.cyrus.admin_password' => 'test-cyrus-secret']);
    }

    public function test_create_user_keeps_password_out_of_remote_command(): void
    {
        $service = $this->serviceCapturingCommands();

        $service->createRoundcubeUser(self::LOGIN, 'jan', 'kowalski', 'jan.kowalski', 'localhost', self::HOSTILE_PASSWORD);

        $command = $this->chpasswdCommand();

        $this->assertStringNotContainsString(self::HOSTILE_PASSWORD, $command);
        $this->assertStringNotContainsString('set -x', $command);
        $this->assertStringContainsString('base64 -d | chpasswd', $command);
        $this->assertSame(
            [self::LOGIN.':'.self::HOSTILE_PASSWORD],
            $this->decodedPayloads($command)
        );
    }

    public function test_update_user_keeps_password_out_of_remote_command(): void
    {
        $service = $this->serviceCapturingCommands();

        $service->updateRoundcubeUser(1, self::LOGIN, 'jan', 'kowalski', 'jan@telmor.pl', 'jan@telmor.com.pl', 'localhost', self::HOSTILE_PASSWORD);

        $command = $this->chpasswdCommand();

        $this->assertStringNotContainsString(self::HOSTILE_PASSWORD, $command);
        $this->assertStringContainsString('base64 -d | chpasswd', $command);
        $this->assertSame(
            [self::LOGIN.':'.self::HOSTILE_PASSWORD],
            $this->decodedPayloads($command)
        );
    }

    public function test_update_without_password_does_not_touch_password(): void
    {
        $service = $this->serviceCapturingCommands();

        $service->updateRoundcubeUser(1, self::LOGIN, 'jan', 'kowalski', 'jan@telmor.pl', 'jan@telmor.com.pl', 'localhost', null);

        foreach ($this->captured as $call) {
            $this->assertStringNotContainsString('chpasswd', $call['command']);
        }
    }

    public function test_password_is_passed_to_ssh_client_for_redaction(): void
    {
        $service = $this->serviceCapturingCommands();

        $service->createRoundcubeUser(self::LOGIN, 'jan', 'kowalski', 'jan.kowalski', 'localhost', self::HOSTILE_PASSWORD);

        $this->assertNotEmpty($this->captured);

        foreach ($this->captured as $call) {
            $this->assertContains(self::HOSTILE_PASSWORD, $call['redact']);
        }
    }

    public function test_server_output_is_redacted_before_appending_to_remote_log(): void
    {
        $service = $this->serviceCapturingCommands(['chpasswd: blad dla '.self::HOSTILE_PASSWORD]);

        $service->createRoundcubeUser(self::LOGIN, 'jan', 'kowalski', 'jan.kowalski', 'localhost', self::HOSTILE_PASSWORD);

        $appendCommand = $this->captured[1]['command'];

        $this->assertStringNotContainsString(self::HOSTILE_PASSWORD, $appendCommand);
        $this->assertStringContainsString('[REDACTED]', $appendCommand);
    }

    public function test_create_user_rejects_login_with_shell_metacharacters(): void
    {
        $ssh = Mockery::mock(Fail2banSshClient::class);
        $ssh->shouldNotReceive('run');

        $service = new MailServerService($ssh);

        $this->expectException(Exception::class);

        $service->createRoundcubeUser(self::HOSTILE_LOGIN, 'jan', 'kowalski', 'jan', 'localhost', 'haslo123');
    }

    public function test_create_user_rejects_login_with_newline(): void
    {
        $ssh = Mockery::mock(Fail2banSshClient::class);
        $ssh->shouldNotReceive('run');

        $service = new MailServerService($ssh);

        $this->expectException(Exception::class);

        $service->createRoundcubeUser("jan\nroot", 'jan', 'kowalski', 'jan', 'localhost', 'haslo123');
    }

    public function test_ssh_client_redacts_secrets_before_logging(): void
    {
        $client = new class extends Fail2banSshClient
        {
            /**
             * @param  array<int, string>  $secrets
             */
            public function redact(string $text, array $secrets): string
            {
                return $this->redactSecrets($text, $secrets);
            }
        };

        $this->assertSame('login:[REDACTED]', $client->redact('login:TajneHaslo123', ['TajneHaslo123']));
        $this->assertSame('login:[REDACTED] i [REDACTED]', $client->redact('login:TajneHaslo123 i dGFqbmU=', ['TajneHaslo123', 'dGFqbmU=']));
        $this->assertSame('brak sekretow', $client->redact('brak sekretow', []));
        $this->assertSame('a:b', $client->redact('a:b', ['x']));
    }

    public function test_vusertable_entry_is_shell_quoted(): void
    {
        $service = $this->serviceCapturingCommands();

        $service->createRoundcubeUser(self::LOGIN, 'jan', 'kowalski', '$(id)', 'localhost', 'haslo123');

        $vusertableLine = $this->lineContaining('>> /etc/postfix/vusertable;');

        // Wartość jest argumentem powłoki w apostrofach, nie wnętrzem "..." (tam $(...) byłoby wykonane).
        $this->assertStringContainsString("printf '%s\\t%s\\n' '\$(id)@telmor.pl' '".self::LOGIN."'", $vusertableLine);
        $this->assertStringNotContainsString('echo -e "', $vusertableLine);
        $this->assertStringNotContainsString('"$(id)', $vusertableLine);
    }

    /**
     * Wartości z panelu (alias, nadawca, odbiorca) trafiają do komunikatu logu,
     * który jest fragmentem zdalnego skryptu — cała treść musi zostać jednym,
     * zacytowanym słowem shella, także gdy zawiera metaznaki powłoki.
     */
    public function test_map_log_lines_are_shell_quoted(): void
    {
        $calls = [
            'DODAWANIE ALIASU' => static fn (MailServerService $service) => $service->addAlias(self::HOSTILE_MAP_KEY, self::HOSTILE_MAP_VALUE),
            'DODAWANIE UPRAWNIENIA NADAWCY' => static fn (MailServerService $service) => $service->addSenderRule(self::HOSTILE_MAP_KEY, self::HOSTILE_MAP_VALUE),
            'DODAWANIE BLOKADY Z zEWNĄTRZ' => static fn (MailServerService $service) => $service->addInternalRcpt(self::HOSTILE_MAP_KEY, self::HOSTILE_MAP_VALUE),
        ];

        foreach ($calls as $marker => $call) {
            $service = $this->serviceCapturingCommands();

            $call($service);

            $words = $this->shellWords($this->lineContaining($marker));

            $this->assertCount(5, $words, "Log modułu {$marker}: komunikat rozjechał się na wiele słów shella.");
            $this->assertSame('echo', $words[0]);
            $this->assertStringStartsWith('=== '.$marker, $words[1]);
            $this->assertStringEndsWith(' ===', $words[1]);
            $this->assertStringContainsString(self::HOSTILE_MAP_KEY, $words[1]);
            $this->assertStringContainsString(self::HOSTILE_MAP_VALUE, $words[1]);
            $this->assertNotContains('touch', $words);
            $this->assertSame(['>>', '/var/log/konta.log', ';'], array_slice($words, -3));
        }
    }

    /**
     * Ścieżka usuwania nie buduje wpisu w pliku mapy, więc klucza nie odrzuca —
     * wpis w logu i tak musi pozostać jedną linią (bez sfałszowania wpisu).
     */
    public function test_map_log_line_cannot_be_forged_with_control_characters(): void
    {
        $service = $this->serviceCapturingCommands();

        $service->deleteAlias("x\n=== SFAŁSZOWANY WPIS ===");

        $words = $this->shellWords($this->lineContaining('USUWANIE ALIASU'));

        $this->assertStringNotContainsString("\n", $words[1]);
        $this->assertStringContainsString('=== SFAŁSZOWANY WPIS ===', $words[1]);
    }

    /**
     * Klucz wpisu (lewa kolumna pliku mapy) nie może zawierać białych ani
     * sterujących znaków — inaczej dopisałby dodatkowe pozycje do pliku.
     */
    public function test_map_entry_rejects_key_with_whitespace_or_control_characters(): void
    {
        $hostileKeys = [
            "x\n  attacker@example.com",
            "x\ttab",
            "x\x00null",
            'evil key',
        ];

        $calls = [
            'addAlias' => static fn (MailServerService $service, string $key) => $service->addAlias($key, 'kontakt@telmor.pl'),
            'addSenderRule' => static fn (MailServerService $service, string $key) => $service->addSenderRule($key, 'jan.kowalski'),
            'addInternalRcpt' => static fn (MailServerService $service, string $key) => $service->addInternalRcpt($key),
        ];

        foreach ($calls as $method => $call) {
            foreach ($hostileKeys as $key) {
                $service = $this->serviceCapturingCommands();

                try {
                    $call($service, $key);
                } catch (Exception $exception) {
                    $this->assertStringContainsString('Nieprawidłowa wartość pola', $exception->getMessage());
                    $this->assertMapFileWasNotWritten($method.'()');

                    continue;
                }

                $this->fail($method.'() przyjął klucz z niedozwolonymi znakami: '.(string) json_encode($key));
            }
        }
    }

    /**
     * Wartość wpisu (prawa kolumna) może zawierać spacje, ale nie znaki
     * sterujące — nowa linia rozbiłaby wpis na dodatkowe pozycje pliku.
     */
    public function test_map_entry_rejects_value_with_control_characters(): void
    {
        $hostileValues = [
            "kontakt@telmor.pl\n  attacker@example.com",
            "kontakt@telmor.pl\ttab",
            "kontakt@telmor.pl\x00null",
        ];

        $calls = [
            'addAlias' => static fn (MailServerService $service, string $value) => $service->addAlias('biuro', $value),
            'addSenderRule' => static fn (MailServerService $service, string $value) => $service->addSenderRule('biuro@telmor.pl', $value),
            'addInternalRcpt' => static fn (MailServerService $service, string $value) => $service->addInternalRcpt('pomoc@', $value),
        ];

        foreach ($calls as $method => $call) {
            foreach ($hostileValues as $value) {
                $service = $this->serviceCapturingCommands();

                try {
                    $call($service, $value);
                } catch (Exception $exception) {
                    $this->assertStringContainsString('Nieprawidłowa wartość pola', $exception->getMessage());
                    $this->assertMapFileWasNotWritten($method.'()');

                    continue;
                }

                $this->fail($method.'() przyjął wartość z niedozwolonymi znakami: '.(string) json_encode($value));
            }
        }
    }

    /**
     * Ścieżki aktualizacji również budują wpis w pliku mapy.
     */
    public function test_map_entry_update_rejects_injected_lines(): void
    {
        $calls = [
            'updateAlias' => static fn (MailServerService $service, string $key, string $value) => $service->updateAlias('biuro', $key, $value),
            'updateSenderRule' => static fn (MailServerService $service, string $key, string $value) => $service->updateSenderRule('biuro@telmor.pl', $key, $value),
            'updateInternalRcpt' => static fn (MailServerService $service, string $key, string $value) => $service->updateInternalRcpt('pomoc@', $key, $value),
        ];

        $hostilePairs = [
            ["x\nattacker@example.com", 'kontakt@telmor.pl'],
            ['biuro', "kontakt@telmor.pl\n  attacker@example.com"],
        ];

        foreach ($calls as $method => $call) {
            foreach ($hostilePairs as [$key, $value]) {
                $service = $this->serviceCapturingCommands();

                try {
                    $call($service, $key, $value);
                } catch (Exception $exception) {
                    $this->assertStringContainsString('Nieprawidłowa wartość pola', $exception->getMessage());
                    $this->assertMapFileWasNotWritten($method.'()');

                    continue;
                }

                $this->fail($method.'() przyjął wartość z nową linią: '.(string) json_encode([$key, $value]));
            }
        }
    }

    /**
     * Legalne wartości z panelu (spacja po przecinku) nadal przechodzą i trafiają
     * do pliku mapy jako dokładnie jedna linia na wpis.
     */
    public function test_map_entry_writes_single_line_for_legit_value_with_space(): void
    {
        $service = $this->serviceCapturingCommands();

        $service->addAlias('rekrutacja', 'rafalbielawski, kadrytelmor');

        $written = $this->writtenMapContent('valiastable');

        $this->assertCount(2, $written, 'Wpis rozjechał się na dodatkowe linie pliku mapy.');
        $this->assertSame(['rekrutacja', 'rafalbielawski, kadrytelmor'], preg_split('/\s+/', trim($written[1]), 2));
    }

    public function test_sql_literal_escapes_apostrophes_and_backslashes(): void
    {
        $this->assertSame("'jan.kowalski'", $this->invokeSqlLiteral('jan.kowalski'));
        $this->assertSame("''", $this->invokeSqlLiteral(''));
        $this->assertSame("'o''brien'", $this->invokeSqlLiteral("o'brien"));
        $this->assertSame("'a''); DROP TABLE x; --'", $this->invokeSqlLiteral("a'); DROP TABLE x; --"));
        $this->assertSame("'C:\\\\sciezka'", $this->invokeSqlLiteral('C:\\sciezka'));
    }

    public function test_create_user_escapes_name_and_email_in_sql(): void
    {
        $service = $this->serviceCapturingCommands();

        $service->createRoundcubeUser(self::LOGIN, "a'); DROP TABLE x; --", 'kowalski', "o'brien", 'localhost', 'haslo123');

        $sql = $this->shellDecode($this->sqlArgument());

        $this->assertStringContainsString("'a''); DROP TABLE x; -- kowalski'", $sql);
        $this->assertStringContainsString("'o''brien@telmor.pl'", $sql);
        $this->assertStringNotContainsString("VALUES (@rc_id, NOW(), 0, 1, 'a');", $sql);
    }

    public function test_delete_user_shell_quotes_login_read_from_database(): void
    {
        $service = $this->serviceCapturingCommands([self::HOSTILE_LOGIN, '', 'ok']);

        $service->deleteRoundcubeUser(7);

        $logLine = $this->lineContaining('START USUWANIA KONTA');
        $logWords = $this->shellWords($logLine);

        // Cała wstrzyknięta treść zostaje jednym słowem shella — nie powstaje z niej polecenie.
        $this->assertCount(7, $logWords);
        $this->assertSame('echo', $logWords[0]);
        $this->assertSame('=== START USUWANIA KONTA: ', $logWords[1]);
        $this->assertSame(self::HOSTILE_LOGIN, $logWords[2]);
        $this->assertSame(['>>', '/var/log/konta.log', ';'], array_slice($logWords, -3));
        $this->assertNotContains('id', $logWords);

        $userdelLine = $this->lineContaining('userdel');

        $this->assertStringContainsString('userdel '.escapeshellarg(self::HOSTILE_LOGIN), $userdelLine);
        $this->assertSame(
            ['echo', '--> Usuwanie użytkownika systemowego Linux ', self::HOSTILE_LOGIN, ';', 'userdel', self::HOSTILE_LOGIN, '2>&1', ';'],
            $this->shellWords($userdelLine)
        );
    }

    public function test_delete_user_escapes_regex_metacharacters_in_sed_pattern(): void
    {
        $emails = implode("\n", ['jan@telmor.pl', 'plan/.*[x]^$', 'a\\b@x.pl']);

        $service = $this->serviceCapturingCommands([self::LOGIN, $emails, 'ok']);

        $service->deleteRoundcubeUser(7);

        $patterns = $this->sedPatterns();

        $this->assertSame(
            ['/^jan@telmor\.pl\s/d', '/^plan\/\.\*\[x\]\^\$\s/d', '/^a\\\\b@x\.pl\s/d'],
            $patterns
        );

        $sedLine = $this->lineContaining('sed -i');

        foreach ($patterns as $pattern) {
            $this->assertStringContainsString('sed -i '.escapeshellarg($pattern).' /etc/postfix/vusertable;', $sedLine);
        }
    }

    public function test_delete_user_shell_quotes_hostile_email_in_sed_pattern(): void
    {
        $hostileEmail = "jan@x.pl'; userdel root; echo '";

        $service = $this->serviceCapturingCommands([self::LOGIN, $hostileEmail, 'ok']);

        $service->deleteRoundcubeUser(7);

        $expectedPattern = "/^jan@x\\.pl'; userdel root; echo '\\s/d";

        $this->assertSame([$expectedPattern], $this->sedPatterns());

        // Wstrzyknięty tekst jest wyłącznie wnętrzem wzorca — nie tworzy nowego polecenia.
        $this->assertSame(
            [
                'echo', '--> Usuwanie wpisów z Postfix vusertable', ';',
                'sed', '-i', $expectedPattern, '/etc/postfix/vusertable', ';',
                'postmap', '/etc/postfix/vusertable', ';',
                'postfix', 'reload', '2>&1', ';',
            ],
            $this->shellWords($this->lineContaining('sed -i'))
        );
    }

    public function test_delete_user_fails_when_database_has_no_login(): void
    {
        $service = $this->serviceCapturingCommands(['', '', 'ok']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Nie znaleziono użytkownika o ID: 7');

        $service->deleteRoundcubeUser(7);
    }

    /**
     * @param  list<string>  $outputs
     */
    private function serviceCapturingCommands(array $outputs = ['ok']): MailServerService
    {
        $this->captured = [];
        $index = 0;

        $ssh = Mockery::mock(Fail2banSshClient::class);
        $ssh->shouldReceive('run')
            ->andReturnUsing(function (string $command, array $redact = []) use (&$index, $outputs): string {
                $this->captured[] = ['command' => $command, 'redact' => $redact];

                $output = $outputs[min($index, count($outputs) - 1)];
                $index++;

                return $output;
            });

        return new MailServerService($ssh);
    }

    private function chpasswdCommand(): string
    {
        foreach ($this->captured as $call) {
            if (str_contains($call['command'], 'chpasswd')) {
                return $call['command'];
            }
        }

        $this->fail('Żadne wywołanie SSH nie zawierało komendy chpasswd.');
    }

    /**
     * @return list<string>
     */
    private function decodedPayloads(string $command): array
    {
        preg_match_all("/printf '%s' '([A-Za-z0-9+\/=]+)'/", $command, $matches);

        return array_map(
            static fn (string $payload): string => trim((string) base64_decode($payload, true)),
            $matches[1]
        );
    }

    private function invokeSqlLiteral(string $value): string
    {
        $method = new ReflectionMethod(MailServerService::class, 'sqlLiteral');
        $method->setAccessible(true);

        $service = new MailServerService(Mockery::mock(Fail2banSshClient::class));

        return (string) $method->invoke($service, $value);
    }

    private function lineContaining(string $needle): string
    {
        foreach ($this->captured as $call) {
            foreach (explode("\n", $call['command']) as $line) {
                if (str_contains($line, $needle)) {
                    return $line;
                }
            }
        }

        $this->fail('Żadna linia wygenerowanego skryptu nie zawiera: '.$needle);
    }

    private function sqlArgument(): string
    {
        $line = $this->lineContaining('mysql -e ');

        $start = strpos($line, 'mysql -e ') + strlen('mysql -e ');
        $end = strpos($line, ' 2>&1;', $start);

        return substr($line, $start, $end - $start);
    }

    /**
     * Rozwija argument powłoki zapisany przez escapeshellarg() do postaci,
     * jaką zobaczy zdalny program (odwrotność escapeshellarg dla jednego słowa).
     */
    private function shellDecode(string $escaped): string
    {
        $this->assertSame("'", $escaped[0]);
        $this->assertSame("'", substr($escaped, -1));

        $quoteEscape = "'".'\\'."''";

        return str_replace($quoteEscape, "'", substr($escaped, 1, -1));
    }

    /**
     * Wzorce `sed` wyciągnięte z wygenerowanego skryptu w postaci, jaką zobaczy
     * zdalny `sed` (po zdjęciu cytowania powłoki).
     *
     * @return list<string>
     */
    private function sedPatterns(): array
    {
        $patterns = [];
        $words = $this->shellWords($this->lineContaining('sed -i'));

        foreach ($words as $index => $word) {
            if ($word === '-i') {
                $patterns[] = $words[$index + 1];
            }
        }

        return $patterns;
    }

    /**
     * Sprawdza, że żadne wywołanie SSH nie nadpisało pliku mapy Postfixa.
     */
    private function assertMapFileWasNotWritten(string $context): void
    {
        foreach ($this->captured as $call) {
            $this->assertStringNotContainsString(
                'base64 -d > /etc/postfix/',
                $call['command'],
                $context.' nadpisało plik mapy na zdalnym hoście.'
            );
        }
    }

    /**
     * Treść, jaką skrypt zapisałby do pliku mapy na zdalnym hoście.
     *
     * @return list<string>
     */
    private function writtenMapContent(string $mapFile): array
    {
        $needle = 'base64 -d > /etc/postfix/'.$mapFile.';';

        foreach ($this->captured as $call) {
            if (! str_contains($call['command'], $needle)) {
                continue;
            }

            $words = $this->shellWords($this->lineContaining($needle));

            return explode("\n", (string) base64_decode($words[1], true));
        }

        $this->fail('Żaden wygenerowany skrypt nie zapisywał pliku mapy: '.$mapFile);
    }

    /**
     * Rozbija linię skryptu na słowa tak, jak zrobiłby to shell: cytowanie
     * apostrofami jest zdejmowane, a operatory `;` trafiają do wyniku jako
     * osobne tokeny, dzięki czemu widać, czy wstrzyknięta wartość nie stała się
     * osobnym poleceniem.
     *
     * @return list<string>
     */
    private function shellWords(string $line): array
    {
        $words = [];
        $current = '';
        $started = false;
        $inSingleQuote = false;
        $length = strlen($line);

        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];

            if ($inSingleQuote) {
                if ($char === "'") {
                    $inSingleQuote = false;
                } else {
                    $current .= $char;
                }

                continue;
            }

            if ($char === "'") {
                $inSingleQuote = true;
                $started = true;

                continue;
            }

            if ($char === '\\' && $i + 1 < $length) {
                $current .= $line[++$i];
                $started = true;

                continue;
            }

            if ($char === ';') {
                if ($started) {
                    $words[] = $current;
                    $current = '';
                    $started = false;
                }

                $words[] = ';';

                continue;
            }

            if ($char === ' ' || $char === "\t") {
                if ($started) {
                    $words[] = $current;
                    $current = '';
                    $started = false;
                }

                continue;
            }

            $current .= $char;
            $started = true;
        }

        if ($inSingleQuote) {
            $this->fail('Nie domknięte cytowanie w wygenerowanym skrypcie: '.$line);
        }

        if ($started) {
            $words[] = $current;
        }

        return $words;
    }
}
