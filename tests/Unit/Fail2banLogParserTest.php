<?php

namespace Tests\Unit;

use App\Services\Fail2ban\Fail2banLogParser;
use Carbon\Carbon;
use RuntimeException;
use Tests\TestCase;

/**
 * Parser logu fail2ban pracuje na surowym `tail -n` z serwera pocztowego.
 * Musi wyłowić dokładnie linie akcji ban/unban i zignorować resztę logu
 * (wyszukiwania, restory, komentarze), bo od `line_number` zależy import
 * przyrostowy — zła numeracja linii przesunęłaby offset.
 *
 * Wzorzec formatu (własny log fail2bana, bez hosta z sysloga):
 *   <data>,<mikrosekundy> fail2ban.actions [<pid>]: <poziom>  [<jail>] Ban <ip>
 */
class Fail2banLogParserTest extends TestCase
{
    private const BAN_LINE = '2026-10-15 08:00:01,123456 fail2ban.actions [2511]: NOTICE  [sshd] Ban 203.0.113.7';

    private const UNBAN_LINE = '2026-10-15 08:05:02,654321 fail2ban.actions [2511]: NOTICE  [postfix-sasl] Unban 203.0.113.7';

    private Fail2banLogParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new Fail2banLogParser;
    }

    public function test_parses_ban_line(): void
    {
        $this->assertSame(
            [
                'occurred_at' => '2026-10-15 08:00:01',
                'jail' => 'sshd',
                'action' => 'Ban',
                'ip' => '203.0.113.7',
            ],
            $this->parser->parseLine(self::BAN_LINE)
        );
    }

    public function test_parses_unban_line(): void
    {
        $this->assertSame(
            [
                'occurred_at' => '2026-10-15 08:05:02',
                'jail' => 'postfix-sasl',
                'action' => 'Unban',
                'ip' => '203.0.113.7',
            ],
            $this->parser->parseLine(self::UNBAN_LINE)
        );
    }

    public function test_parses_ipv6_address_and_jail_with_dots(): void
    {
        $line = '2026-10-15 09:11:12,000001 fail2ban.actions [2511]: NOTICE  [recidive] Ban 2001:db8:85a3::8a2e:370:7334';
        $event = $this->parser->parseLine($line);

        $this->assertSame('2001:db8:85a3::8a2e:370:7334', $event['ip']);
        $this->assertSame('recidive', $event['jail']);
    }

    public function test_parses_line_with_padding_inside_log_prefix(): void
    {
        // fail2ban wyrównuje nazwę loggera i pid spacjami — parser musi
        // tolerować dowolne odstępy (`\s+`), inaczej po aktualizacji
        // fail2bana import przestałby widzieć bany.
        $line = '2026-10-15 08:00:01,123456 fail2ban.actions       [2511]: NOTICE [sshd] Ban 203.0.113.7';

        $this->assertSame('203.0.113.7', $this->parser->parseLine($line)['ip']);
    }

    public function test_ignores_trailing_text_after_ip(): void
    {
        $this->assertSame('203.0.113.7', $this->parser->parseLine(self::BAN_LINE.' (1 failures)')['ip']);
    }

    public function test_rejects_lines_that_are_not_ban_actions(): void
    {
        $lines = [
            'wyszukiwanie z jaila filter' => '2026-10-15 08:00:01,123456 fail2ban.filter [2511]: INFO [sshd] Found 203.0.113.7',
            'restore banów przy starcie' => '2026-10-15 08:00:01,123456 fail2ban.actions [2511]: NOTICE  [sshd] Restore Ban 203.0.113.7',
            'akcja bez adresu IP' => '2026-10-15 08:00:01,123456 fail2ban.actions [2511]: NOTICE  [sshd] Ban',
            'akcja małymi literami' => '2026-10-15 08:00:01,123456 fail2ban.actions [2511]: NOTICE  [sshd] ban 203.0.113.7',
            'brak mikrosekund' => '2026-10-15 08:00:01 fail2ban.actions [2511]: NOTICE  [sshd] Ban 203.0.113.7',
            'wariant journald' => 'Oct 15 08:00:01 fail2ban[2511]: NOTICE  [sshd] Ban 203.0.113.7',
            'wariant syslog z hostem' => '2026-10-15 08:00:01,123456 mail fail2ban.actions [2511]: NOTICE  [sshd] Ban 203.0.113.7',
            'linia z wcięciem' => ' '.self::BAN_LINE,
            'pusta linia' => '',
            'komentarz/smieci' => '-- Mark --',
        ];

        foreach ($lines as $description => $line) {
            $this->assertNull($this->parser->parseLine($line), "Linia '{$description}' nie powinna pasować do wzorca.");
        }
    }

    public function test_parse_lines_numbers_all_lines_not_only_matches(): void
    {
        // Numeracja liczona po WSZYSTKICH liniach pliku — offset importu
        // przyrostowego musi wskazywać realną pozycję w logu, nie w wynikach.
        $lines = [
            '# nagłówek od operatora',
            self::BAN_LINE,
            '',
            '-- Mark --',
            self::UNBAN_LINE,
        ];

        $events = iterator_to_array($this->parser->parseLines($lines));

        $this->assertSame([2, 5], array_column($events, 'line_number'));
        $this->assertSame(['Ban', 'Unban'], array_column($events, 'action'));
    }

    public function test_parse_lines_handles_crlf_endings(): void
    {
        $lines = [self::BAN_LINE."\r\n", self::UNBAN_LINE."\r\n"];

        $events = iterator_to_array($this->parser->parseLines($lines));

        $this->assertSame(['203.0.113.7', '203.0.113.7'], array_column($events, 'ip'));
        $this->assertSame([1, 2], array_column($events, 'line_number'));
    }

    public function test_parse_lines_returns_generator_and_accepts_empty_input(): void
    {
        $this->assertInstanceOf(\Generator::class, $this->parser->parseLines([]));
        $this->assertSame([], iterator_to_array($this->parser->parseLines([])));
        $this->assertSame([], iterator_to_array($this->parser->parseLines($this->parser->splitLines(''))));
    }

    public function test_split_lines_keeps_explode_semantics(): void
    {
        $this->assertSame(['a', 'b'], $this->parser->splitLines("a\nb"));

        // Pusty element po ostatnim newline jest pomijany przez parseLines(),
        // więc nie przesuwa numeracji zdarzeń.
        $this->assertSame(['a', ''], $this->parser->splitLines("a\n"));
        $this->assertSame([], iterator_to_array($this->parser->parseLines($this->parser->splitLines("a\n"))));
    }

    public function test_parse_file_yields_matches_with_raw_line(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'fail2ban-log-');

        try {
            file_put_contents($file, "# nagłówek\r\n".self::BAN_LINE."\r\n-- Mark --\r\n".self::UNBAN_LINE."\r\n");

            $events = iterator_to_array($this->parser->parseFile($file));

            $this->assertCount(2, $events);
            $this->assertSame(['occurred_at', 'jail', 'action', 'ip', 'raw'], array_keys($events[0]));
            $this->assertSame(self::BAN_LINE, $events[0]['raw']);
            $this->assertSame(self::UNBAN_LINE, $events[1]['raw']);
        } finally {
            @unlink($file);
        }
    }

    public function test_parse_file_throws_for_unreadable_path(): void
    {
        $path = sys_get_temp_dir().'/nie-ma-takiego-katalogu/fail2ban.log';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Plik nie jest czytelny: {$path}");

        iterator_to_array($this->parser->parseFile($path));
    }

    public function test_to_carbon_uses_application_timezone_and_round_trips_the_timestamp(): void
    {
        config(['app.timezone' => 'Europe/Warsaw']);

        $event = $this->parser->parseLine(self::BAN_LINE);
        $occurredAt = $this->parser->toCarbon($event['occurred_at']);

        $this->assertInstanceOf(Carbon::class, $occurredAt);
        $this->assertSame('Europe/Warsaw', $occurredAt->timezoneName);
        $this->assertSame($event['occurred_at'], $occurredAt->format('Y-m-d H:i:s'));
    }
}
