<?php

namespace App\Services\Fail2ban;

use RuntimeException;

class FortigateFeedService
{
    /**
     * Ścieżka do pliku feed.
     * public_path() wskazuje na /public — plik będzie dostępny
     * pod https://twoj-host/f2b-ips.txt
     */
    private string $path;

    public function __construct(?string $filename = null)
    {
        $this->path = public_path($filename ?: 'f2b-ips.txt');
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Zwraca listę IP z pliku (posortowaną numerycznie).
     *
     * @return string[]
     */
    public function all(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $content = file_get_contents($this->path);
        if ($content === false) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $ips = array_filter(array_map('trim', $lines), fn ($v) => $v !== '');
        $ips = array_values(array_unique($ips));

        $this->sortIps($ips);

        return $ips;
    }

    /**
     * Dodaje IP do pliku (jeśli jeszcze go nie ma). Plik zawsze posortowany.
     *
     * Zwraca liczbę adresów w pliku po operacji — wołający nie musi czytać
     * pliku ponownie, żeby ją poznać.
     */
    public function add(string $ip): int
    {
        $ip = trim($ip);
        if (! $this->isValidIp($ip)) {
            throw new RuntimeException("Nieprawidłowy adres IP: {$ip}");
        }

        return count($this->withLock(function (array $ips) use ($ip): array {
            if (in_array($ip, $ips, true)) {
                return [$ips, false]; // już istnieje — nic nie zmieniaj
            }

            $ips[] = $ip;
            $this->sortIps($ips);

            return [$ips, true];
        }));
    }

    /**
     * Usuwa IP z pliku. Plik zawsze posortowany.
     *
     * Zwraca liczbę adresów w pliku po operacji — wołający nie musi czytać
     * pliku ponownie, żeby ją poznać.
     */
    public function remove(string $ip): int
    {
        $ip = trim($ip);

        return count($this->withLock(function (array $ips) use ($ip): array {
            $before = count($ips);
            $ips = array_values(array_filter($ips, fn ($v) => $v !== $ip));

            if (count($ips) === $before) {
                return [$ips, false]; // nie było — nic nie zmieniaj
            }

            $this->sortIps($ips);

            return [$ips, true];
        }));
    }

    /**
     * Zastępuje całą zawartość pliku nową listą IP.
     *
     * Zwraca liczbę adresów faktycznie zapisanych (po odfiltrowaniu
     * niepoprawnych, usunięciu duplikatów i posortowaniu) — wołający zna
     * wynik zapisu bez ponownego odczytu pliku z dysku.
     *
     * @param  string[]  $ips
     */
    public function replaceAll(array $ips): int
    {
        $ips = $this->normalizeIps($ips);

        return count($this->withLock(fn (): array => [$ips, true]));
    }

    // ----------------------------------------------------------------

    /**
     * Wykonuje operację pod blokadą pliku, potem zapisuje posortowaną listę.
     *
     * Zwraca stan pliku po operacji (listę IP), więc wołający nie czyta pliku
     * ponownie — ani po to, by policzyć wpisy, ani by poznać zawartość.
     *
     * @param  callable  $callback  fn(array $ips): array{0: string[], 1: bool}
     * @return string[]
     */
    private function withLock(callable $callback): array
    {
        $dir = dirname($this->path);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Nie mogę utworzyć katalogu: {$dir}");
        }

        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new RuntimeException("Nie mogę otworzyć pliku: {$this->path}");
        }

        $newIps = [];

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException("Nie mogę zablokować pliku: {$this->path}");
            }

            // Wczytaj aktualną zawartość
            $content = stream_get_contents($handle) ?: '';
            $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
            $ips = array_values(array_unique(array_filter(
                array_map('trim', $lines),
                fn ($v) => $v !== ''
            )));

            // Wykonaj operację
            [$newIps, $changed] = $callback($ips);

            if ($changed) {
                // Zapisz od nowa (nadpisz)
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, implode("\n", $newIps) . (empty($newIps) ? '' : "\n"));
                fflush($handle);
            }

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $newIps;
    }

    /**
     * Sortuje listę IP numerycznie (IPv4 → po ip2long; IPv6 → po inet_pton).
     * Mieszane IPv4/IPv6 → IPv4 najpierw, potem IPv6.
     *
     * @param  string[]  $ips  (przez referencję)
     */
    private function sortIps(array &$ips): void
    {
        usort($ips, function (string $a, string $b): int {
            $aV4 = filter_var($a, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
            $bV4 = filter_var($b, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

            if ($aV4 !== $bV4) {
                return $aV4 ? -1 : 1;
            }

            if ($aV4) {
                return ip2long($a) <=> ip2long($b);
            }

            // IPv6 — porównanie binarne
            return strcmp(inet_pton($a) ?: '', inet_pton($b) ?: '');
        });
    }

    /**
     * Normalizuje listę IP: odfiltrowuje niepoprawne, usuwa duplikaty, sortuje.
     *
     * @param  string[]  $ips
     * @return string[]
     */
    private function normalizeIps(array $ips): array
    {
        $ips = array_filter(array_map('trim', $ips), fn ($v) => $this->isValidIp($v));
        $ips = array_values(array_unique($ips));
        $this->sortIps($ips);

        return $ips;
    }

    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}