<?php

namespace App\Services\Fail2ban;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class Fail2banSshClient
{
    private string $host;
    private string $sshKey;
    private string $sshUser;

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
        $this->host    = config('services.ssh.host');
        $this->sshKey  = config('services.ssh.key');
        $this->sshUser = config('services.ssh.user');
    }

    public function fileSize(string $remotePath): int
    {
        $out = $this->run('wc -c < ' . escapeshellarg($remotePath));
        return (int) trim($out);
    }

    public function fileInode(string $remotePath): string
    {
        $out = $this->run('stat -c %i ' . escapeshellarg($remotePath));
        return trim($out);
    }

    public function readFrom(string $remotePath, int $offsetBytes = 0): string
    {
        if ($offsetBytes <= 0) {
            $cmd = 'cat ' . escapeshellarg($remotePath);
        } else {
            $cmd = 'tail -c +' . ($offsetBytes + 1) . ' ' . escapeshellarg($remotePath);
        }

        return $this->run($cmd);
    }

    public function run(string $remoteCommand): string
    {
        $sshCommand = [
            'ssh',
            '-i', $this->sshKey,
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=' . $this->connectTimeout,
            '-o', 'ControlMaster=auto',
            '-o', 'ControlPath=/tmp/ssh-cm-%r@%h:%p',
            '-o', 'ControlPersist=60s',
            '-o', 'LogLevel=ERROR',
            $this->sshUser . '@' . $this->host,
            $remoteCommand,
        ];

        $process = new Process($sshCommand);
        $process->setTimeout($this->commandTimeout);
        $process->run();

        $stdout   = $process->getOutput();
        $stderr   = trim($process->getErrorOutput());
        $exitCode = $process->getExitCode();

        $this->logStderr($remoteCommand, $stderr, $exitCode, $process->isSuccessful());

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'SSH command failed (exit %d): %s',
                $exitCode,
                empty($stderr) ? trim($stdout) : $stderr
            ));
        }

        return $stdout;
    }

    private function logStderr(string $command, string $stderr, ?int $exitCode, bool $success): void
    {
        if (empty($stderr)) {
            return;
        }

        if (!$success || $exitCode !== 0) {
            Log::warning('SSH command failed', [
                'host'      => $this->host,
                'command'   => $command,
                'stderr'    => $stderr,
                'exit_code' => $exitCode,
            ]);
            return;
        }

        if ($this->isSuspiciousStderr($stderr)) {
            Log::warning('SSH command produced suspicious stderr', [
                'host'      => $this->host,
                'command'   => $command,
                'stderr'    => $stderr,
                'exit_code' => $exitCode,
            ]);
            return;
        }

        Log::debug('SSH command produced stderr (non-fatal)', [
            'host'    => $this->host,
            'command' => $command,
            'stderr'  => $stderr,
        ]);
    }

    private function isSuspiciousStderr(string $stderr): bool
    {
        $lower = strtolower($stderr);

        foreach (self::BENIGN_STDERR_PATTERNS as $pattern) {
            if (preg_match('/' . $pattern . '/i', $stderr)) {
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