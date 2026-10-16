<?php

declare(strict_types=1);

namespace App\Services\Fail2ban;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class Fail2banSshClient
{
    private string $host;

    private string $sshKey;

    private string $sshUser;

    /** Minimalna długość sekretu maskowanego w logach. */
    private const MIN_REDACT_LENGTH = 4;

    private const BENIGN_STDERR_PATTERNS = [
        'Permanently added',
        'Warning: Identity file',
        'Pseudo-terminal will not be allocated',
        'Connection to .* closed',
    ];

    private const SUSPICIOUS_STDERR_PATTERNS = [
        'error',
        'failed',
        'failure',
        'denied',
        'refused',
        'timeout',
        'not found',
        'no such file',
        'permission',
        'unreachable',
        'cannot',
        'fatal',
    ];

    public function __construct(
        private int $connectTimeout = 10,
        private int $commandTimeout = 60,
    ) {
        $this->host = (string) config('services.ssh.host');
        $this->sshKey = (string) config('services.ssh.key');
        $this->sshUser = (string) config('services.ssh.user');

        // Katalog na gniazda ControlMaster i known_hosts
        $this->ensureSshRuntimeDir();
    }

    public function fileSize(string $remotePath): int
    {
        $out = $this->run('wc -c < '.escapeshellarg($remotePath));

        return (int) trim($out);
    }

    public function fileInode(string $remotePath): string
    {
        $out = $this->run('stat -c %i '.escapeshellarg($remotePath));

        return trim($out);
    }

    public function readFrom(string $remotePath, int $offsetBytes = 0): string
    {
        if ($offsetBytes <= 0) {
            $cmd = 'cat '.escapeshellarg($remotePath);
        } else {
            $cmd = 'tail -c +'.($offsetBytes + 1).' '.escapeshellarg($remotePath);
        }

        return $this->run($cmd);
    }

    /**
     * Wykonuje polecenie na zdalnym hoście.
     *
     * @param  array<int, string>  $redact  Sekrety, które nigdy nie mogą trafić do logów
     *
     * @throws \RuntimeException
     */
    public function run(string $remoteCommand, array $redact = []): string
    {
        $controlPath = $this->sshRuntimeDir().'/cm-%C';
        $knownHosts = $this->sshRuntimeDir().'/known_hosts';

        $sshCommand = [
            'ssh',
            '-i', $this->sshKey,
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'UserKnownHostsFile='.$knownHosts,
            '-o', 'IdentitiesOnly=yes',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout='.$this->connectTimeout,
            '-o', 'ControlMaster=auto',
            '-o', 'ControlPath='.$controlPath,
            '-o', 'ControlPersist=60s',
            '-o', 'LogLevel=ERROR',
            $this->sshUser.'@'.$this->host,
            $remoteCommand,
        ];

        $process = new Process($sshCommand);
        $process->setTimeout($this->commandTimeout);
        $process->run();

        $stdout = $process->getOutput();
        $stderr = trim($process->getErrorOutput());
        $exitCode = $process->getExitCode();

        $this->logStderr($remoteCommand, $stderr, $exitCode, $process->isSuccessful(), $redact);

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'SSH command failed (exit %d): %s',
                $exitCode,
                empty($stderr) ? trim($stdout) : $stderr
            ));
        }

        return $stdout;
    }

    /**
     * Katalog na gniazda ControlMaster i known_hosts.
     * W storage/app/ssh — poza /tmp, który może być czyszczony.
     */
    private function sshRuntimeDir(): string
    {
        return storage_path('app/ssh');
    }

    /**
     * Tworzy katalog na runtime SSH (jeśli nie istnieje).
     * Uprawnienia 0700 — tylko właściciel (www-data) ma dostęp.
     */
    private function ensureSshRuntimeDir(): void
    {
        $dir = $this->sshRuntimeDir();

        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
                throw new \RuntimeException("Nie mogę utworzyć katalogu: {$dir}");
            }
        }

        // Upewnij się, że uprawnienia są 0700 (nawet jeśli katalog istniał)
        @chmod($dir, 0700);
    }

    /**
     * @param  array<int, string>  $redact
     */
    private function logStderr(string $command, string $stderr, ?int $exitCode, bool $success, array $redact = []): void
    {
        if (empty($stderr)) {
            return;
        }

        $command = $this->redactSecrets($command, $redact);
        $stderr = $this->redactSecrets($stderr, $redact);

        if (! $success || $exitCode !== 0) {
            Log::warning('SSH command failed', [
                'host' => $this->host,
                'command' => $command,
                'stderr' => $stderr,
                'exit_code' => $exitCode,
            ]);

            return;
        }

        if ($this->isSuspiciousStderr($stderr)) {
            Log::warning('SSH command produced suspicious stderr', [
                'host' => $this->host,
                'command' => $command,
                'stderr' => $stderr,
                'exit_code' => $exitCode,
            ]);

            return;
        }

        Log::debug('SSH command produced stderr (non-fatal)', [
            'host' => $this->host,
            'command' => $command,
            'stderr' => $stderr,
        ]);
    }

    /**
     * Maskuje sekrety przed zapisem do logu. Wartości krótsze niż
     * MIN_REDACT_LENGTH pomijamy, aby nie zamaskować fragmentów logu.
     *
     * @param  array<int, string>  $secrets
     */
    protected function redactSecrets(string $text, array $secrets): string
    {
        foreach ($secrets as $secret) {
            if (strlen($secret) >= self::MIN_REDACT_LENGTH) {
                $text = str_replace($secret, '[REDACTED]', $text);
            }
        }

        return $text;
    }

    private function isSuspiciousStderr(string $stderr): bool
    {
        $lower = strtolower($stderr);

        foreach (self::BENIGN_STDERR_PATTERNS as $pattern) {
            if (preg_match('/'.$pattern.'/i', $stderr)) {
                return false;
            }
        }

        foreach (self::SUSPICIOUS_STDERR_PATTERNS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
