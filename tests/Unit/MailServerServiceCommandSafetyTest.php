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

        $service->createRoundcubeUser("jan'; id; echo '", 'jan', 'kowalski', 'jan', 'localhost', 'haslo123');
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
}
