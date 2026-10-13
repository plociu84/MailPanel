<?php

namespace App\Services\Fail2ban;

use Carbon\Carbon;

class Fail2banLogParser
{
    private const PATTERN = '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}),\d+\s+fail2ban\.actions\s+\[\d+\]:\s+\w+\s+\[([^\]]+)\]\s+(Ban|Unban)\s+(\S+)/';

    public function parseLine(string $line): ?array
    {
        if (!preg_match(self::PATTERN, $line, $m)) {
            return null;
        }

        return [
            'occurred_at' => $m[1],
            'jail'        => $m[2],
            'action'      => $m[3],
            'ip'          => $m[4],
        ];
    }

    public function parseLines(iterable $lines): \Generator
    {
        $lineNumber = 0;
        foreach ($lines as $line) {
            $lineNumber++;
            $line = rtrim($line, "\r\n");

            $parsed = $this->parseLine($line);
            if ($parsed !== null) {
                $parsed['line_number'] = $lineNumber;
                yield $parsed;
            }
        }
    }

    public function parseFile(string $filePath): \Generator
    {
        if (!is_readable($filePath)) {
            throw new \RuntimeException("Plik nie jest czytelny: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Nie można otworzyć pliku: {$filePath}");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $parsed = $this->parseLine(rtrim($line, "\r\n"));
                if ($parsed !== null) {
                    $parsed['raw'] = rtrim($line, "\r\n");
                    yield $parsed;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    public function toCarbon(string $timestamp): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, config('app.timezone'));
    }

    public function splitLines(string $content): array
    {
        return explode("\n", $content);
    }
}