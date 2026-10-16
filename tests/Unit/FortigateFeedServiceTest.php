<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Fail2ban\FortigateFeedService;
use Tests\TestCase;

/**
 * Kontrakt metod zapisujących feed: po zapisie zwracają liczbę adresów
 * umieszczonych w pliku (stan pliku po operacji), żeby wołający (panel)
 * nie musiał czytać pliku drugi raz tylko po to, by ją poznać.
 *
 * Plik testowy leży w storage/framework/testing — test nie dotyka
 * produkcyjnego public/f2b-ips.txt.
 */
class FortigateFeedServiceTest extends TestCase
{
    private const FILENAME = '../storage/framework/testing/f2b-ips-service-test.txt';

    private FortigateFeedService $feed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->feed = new FortigateFeedService(self::FILENAME);
    }

    protected function tearDown(): void
    {
        if (is_file($this->feed->path())) {
            unlink($this->feed->path());
        }

        parent::tearDown();
    }

    public function test_replace_all_returns_count_of_written_ips(): void
    {
        $written = $this->feed->replaceAll([
            '198.51.100.9',
            ' brak-ip ',
            '198.51.100.7',
            '198.51.100.7',
            '2001:db8::1',
        ]);

        $this->assertSame(3, $written);
        $this->assertSame(['198.51.100.7', '198.51.100.9', '2001:db8::1'], $this->feed->all());
    }

    public function test_replace_all_with_empty_list_reports_zero_and_empties_the_file(): void
    {
        $this->assertSame(1, $this->feed->replaceAll(['198.51.100.7']));

        $this->assertSame(0, $this->feed->replaceAll([]));
        $this->assertSame([], $this->feed->all());
    }

    public function test_add_and_remove_report_feed_size_after_the_operation(): void
    {
        $this->assertSame(1, $this->feed->add('198.51.100.7'));
        $this->assertSame(2, $this->feed->add('198.51.100.9'));
        $this->assertSame(['198.51.100.7', '198.51.100.9'], $this->feed->all());

        // IP już jest w pliku: zapis pomijany, ale zwrotka nadal opisuje stan pliku.
        $this->assertSame(2, $this->feed->add('198.51.100.7'));

        $this->assertSame(1, $this->feed->remove('198.51.100.9'));
        // IP nieobecny w pliku: plik bez zmian, licznik bez zmian.
        $this->assertSame(1, $this->feed->remove('198.51.100.9'));
        $this->assertSame(0, $this->feed->remove('198.51.100.7'));

        $this->assertSame([], $this->feed->all());
    }
}
