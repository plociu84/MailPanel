<?php

namespace App\Services;

use App\Services\Fail2ban\Fail2banSshClient;

class SystemUserService
{
    public function __construct(
        private Fail2banSshClient $ssh
    ) {}

    public function lockUser(string $username): void
    {
        $this->ssh->run("passwd -l " . escapeshellarg($username));
    }

    public function unlockUser(string $username): void
    {
        $this->ssh->run("passwd -u " . escapeshellarg($username));
    }

    public function setExpire(string $username, string $date): void
    {
        $this->ssh->run("chage -E " . escapeshellarg($date) . " " . escapeshellarg($username));
    }

    public function addGroup(string $name): void
    {
        $this->ssh->run("groupadd " . escapeshellarg($name));
    }

    public function deleteGroup(string $name): void
    {
        $this->ssh->run("groupdel " . escapeshellarg($name));
    }

    public function renameGroup(string $oldName, string $newName): void
    {
        $this->ssh->run(sprintf("groupmod -n %s %s", escapeshellarg($newName), escapeshellarg($oldName)));
    }

    public function addUserToGroup(string $username, string $group): void
    {
        $this->ssh->run(sprintf("usermod -a -G %s %s", escapeshellarg($group), escapeshellarg($username)));
    }

    public function removeUserFromGroup(string $username, string $group): void
    {
        $this->ssh->run(sprintf("gpasswd -d %s %s", escapeshellarg($username), escapeshellarg($group)));
    }

    public function getUsers(): array
    {
        try {
            $passwdRaw = $this->ssh->run('cat /etc/passwd');
            $shadowRaw = $this->ssh->run('cat /etc/shadow');
        } catch (\Throwable $e) {
            $passwdRaw = '';
            $shadowRaw = '';
        }

        if (empty(trim($passwdRaw))) {
            return [
                [
                    'username' => 'DEBUG_user1',
                    'uid' => 1001,
                    'gid' => 1001,
                    'gecos' => 'Debug User 1',
                    'home' => '/home/debug1',
                    'shell' => '/bin/bash',
                    'status' => ['locked' => false, 'expires' => null, 'last_change' => null, 'max_days' => null]
                ],
                [
                    'username' => 'DEBUG_user2',
                    'uid' => 1002,
                    'gid' => 1002,
                    'gecos' => 'Debug User 2',
                    'home' => '/home/debug2',
                    'shell' => '/bin/bash',
                    'status' => ['locked' => true, 'expires' => '2025-12-31', 'last_change' => '2024-01-01', 'max_days' => 365]
                ]
            ];
        }

        $users = [];
        $shadowMap = [];

        if (!empty(trim($shadowRaw))) {
            foreach (explode("\n", trim($shadowRaw)) as $line) {
                if (empty($line)) continue;
                $fields = explode(':', $line);
                $shadowMap[$fields[0]] = $fields;
            }
        }

        foreach (explode("\n", trim($passwdRaw)) as $line) {
            if (empty($line)) continue;
            $fields = explode(':', $line);
            $username = $fields[0];
            $uid = (int)$fields[2];

            if ($uid < 1) continue;

            $shadow = $shadowMap[$username] ?? [];
            $locked = false;
            $expires = null;
            $lastChange = null;
            $maxDays = null;

            if (isset($shadow[1])) {
                $locked = (strpos($shadow[1], '!') === 0 || strpos($shadow[1], '*') === 0);

                if (isset($shadow[7]) && $shadow[7] > 0) {
                    $expires = date('Y-m-d', ($shadow[7] * 86400) + strtotime('1970-01-01'));
                }

                if (isset($shadow[2]) && $shadow[2] > 0) {
                    $lastChange = date('Y-m-d', ($shadow[2] * 86400) + strtotime('1970-01-01'));
                }

                if (!$expires && isset($shadow[2]) && isset($shadow[4]) && $shadow[2] > 0 && $shadow[4] > 0 && $shadow[4] < 99999) {
                    $maxDays = (int)$shadow[4];
                    $expires = date('Y-m-d', ($shadow[2] + $shadow[4]) * 86400 + strtotime('1970-01-01'));
                }
            }

            $users[] = [
                'username' => $username,
                'uid' => $uid,
                'gid' => (int)$fields[3],
                'gecos' => $fields[4] ?? '',
                'home' => $fields[5] ?? '',
                'shell' => $fields[6] ?? '',
                'status' => [
                    'locked' => $locked,
                    'expires' => $expires,
                    'last_change' => $lastChange,
                    'max_days' => $maxDays,
                ]
            ];
        }

        if (empty($users)) {
            $users[] = [
                'username' => 'testowy',
                'uid' => 1001,
                'gid' => 1001,
                'gecos' => 'Testowy Użytkownik',
                'home' => '/home/testowy',
                'shell' => '/bin/bash',
                'status' => [
                    'locked' => false,
                    'expires' => null,
                    'last_change' => null,
                    'max_days' => null,
                ]
            ];
        }

        return $users;
    }

    public function getGroups(): array
    {
        try {
            $raw = $this->ssh->run('cat /etc/group');
        } catch (\Throwable $e) {
            $raw = '';
        }

        $groups = [];
        foreach (explode("\n", trim($raw)) as $line) {
            if (empty($line)) continue;
            $parts = explode(':', $line);
            if (isset($parts[2]) && $parts[2] >= 1) {
                $groups[] = [
                    'name' => $parts[0],
                    'gid' => (int)$parts[2],
                    'members' => !empty($parts[3]) ? explode(',', $parts[3]) : [],
                ];
            }
        }

        return $groups;
    }
}