<?php

namespace App\Services;

use App\Services\Fail2ban\Fail2banSshClient;
use Exception;

class MailServerService
{
    public function __construct(
        private Fail2banSshClient $sshClient
    ) {}

    public function execute(string $command): string
    {
        try {
            return trim($this->sshClient->run($command));
        } catch (\Exception $e) {
            throw new Exception("SSH Command failed: " . $e->getMessage());
        }
    }

    public function getRoundcubeUsers(): array
    {
        $query = "SELECT u.user_id, u.username, COALESCE(i.name, '') as full_name, COALESCE(i.email, '') as primary_email, u.mail_host, u.created, u.last_login, u.preferences 
                    FROM roundcubemail.users u 
                    LEFT JOIN (
                        SELECT user_id, name, email 
                        FROM roundcubemail.identities 
                        WHERE standard = 1 
                        GROUP BY user_id
                    ) i ON u.user_id = i.user_id;";
        
        $command = sprintf("mysql -N -e %s", escapeshellarg($query));
        $output = $this->execute($command);
        
        $users = [];
        foreach (explode("\n", trim($output)) as $line) {
            if (empty($line)) continue;
            $users[] = explode("\t", $line);
        }

        return $users;
    }

    public function getRoundcubeUsersColumns(): array
    {
        return ['user_id', 'username', 'Imię i Nazwisko', 'E-mail główny', 'mail_host', 'created', 'last_login', 'preferences'];
    }

    public function createRoundcubeUser(string $login, string $imie, string $nazwisko, string $emailLocalPart, string $mailHost = 'localhost', string $password = ''): string
    {
        $imieNazwisko = trim($imie . ' ' . $nazwisko);
        $emailPl = $emailLocalPart . '@telmor.pl';
        $emailComPl = $emailLocalPart . '@telmor.com.pl';
        $cyrusUser = (string) config('services.cyrus.admin_user', 'cyrus');
        $cyrusPassword = (string) config('services.cyrus.admin_password');

        $script = "set -x;\n";
        $script .= "echo '=== START TWORZENIA KONTA: " . $login . " (" . date('Y-m-d H:i:s') . ") ===' >> /var/log/konta.log;\n";

        // 1. Tworzenie usera systemowego
        $script .= sprintf("echo '--> Tworzenie użytkownika systemowego %1\$s' 2>&1;\n", $login);
        $script .= sprintf("useradd -d /home/telmor/%1\$s -g telmor -M -s /bin/false %1\$s 2>&1;\n", $login);
        
        if (!empty($password)) {
            $script .= "echo '--> Ustawianie hasła systemowego' 2>&1;\n";
            $script .= sprintf("echo '%1\$s:%2\$s' | chpasswd 2>&1;\n", $login, $password);
        }

        // 2. Wpisy do Postfix vusertable
        $script .= "echo '--> Dodawanie wpisów do Postfix vusertable' 2>&1;\n";
        $script .= sprintf("echo -e \"%s\t%s\" >> /etc/postfix/vusertable;\n", $emailPl, $login);
        $script .= sprintf("echo -e \"%s\t%s\" >> /etc/postfix/vusertable;\n", $emailComPl, $login);
        $script .= "postmap /etc/postfix/vusertable 2>&1;\n";
        $script .= "postfix reload 2>&1;\n";

        // 3. Cyrus IMAP
        $script .= "echo '--> Tworzenie skrzynek w Cyrus IMAP' 2>&1;\n";
        $script .= "cat << 'EOF_CYRADM' > /tmp/cyradm_commands.txt\n";
        $script .= "cm user." . $login . "\n";
        $script .= "cm user." . $login . ".Trash\n";
        $script .= "cm user." . $login . ".Sent\n";
        $script .= "cm user." . $login . ".Drafts\n";
        $script .= "cm user." . $login . ".antyspam\n";
        $script .= "sam user." . $login . ".antyspam anyone p\n";
        $script .= "mboxcfg user." . $login . ".antyspam expire 14\n";
        $script .= "mboxcfg user." . $login . ".Trash expire 7\n";
        $script .= "sq user." . $login . " STORAGE 2097152\n";
        $script .= "quit\n";
        $script .= "EOF_CYRADM\n";
        
        $script .= sprintf("export TERM=dumb; cyradm --user %s --password %s localhost < /tmp/cyradm_commands.txt 2>&1 | sed 's/localhost> //g';\n", escapeshellarg($cyrusUser), escapeshellarg($cyrusPassword));
        $script .= "rm -f /tmp/cyradm_commands.txt;\n";

        // 4. Baza Roundcube
        $sqlRoundcube = sprintf(
            "INSERT INTO roundcubemail.users (username, mail_host, created, language, preferences) VALUES (%1\$s, %2\$s, NOW(), 'pl_PL', 'a:0:{}'); " .
            "SET @rc_id = LAST_INSERT_ID(); " .
            "INSERT INTO roundcubemail.identities (user_id, changed, del, standard, name, email) VALUES (@rc_id, NOW(), 0, 1, %3\$s, %4\$s); " .
            "INSERT INTO roundcubemail.identities (user_id, changed, del, standard, name, email) VALUES (@rc_id, NOW(), 0, 0, %3\$s, %5\$s);",
            escapeshellarg($login),
            escapeshellarg($mailHost),
            escapeshellarg($imieNazwisko),
            escapeshellarg($emailPl),
            escapeshellarg($emailComPl)
        );

        $script .= "echo '--> Zapisywanie w bazie Roundcube' 2>&1;\n";
        $script .= sprintf("mysql -e %s 2>&1;\n", escapeshellarg($sqlRoundcube));
        $script .= "echo '=== KONIEC TWORZENIA KONTA ===' >> /var/log/konta.log;\n";

        try {
            $output = $this->execute($script);
            $this->execute("echo " . escapeshellarg($output) . " >> /var/log/konta.log");
            return $output;
        } catch (Exception $e) {
            throw new Exception("Błąd podczas pełnego tworzenia konta: " . $e->getMessage());
        }
    }

    public function updateRoundcubeUser(int $userId, string $login, string $imie, string $nazwisko, string $emailPl, string $emailComPl, string $mailHost, ?string $password = null): string
    {
        $imieNazwisko = trim($imie . ' ' . $nazwisko);

        $script = "echo '=== START AKTUALIZACJI KONTA: " . $login . " (" . date('Y-m-d H:i:s') . ") ===' >> /var/log/konta.log;\n";

        if (!empty($password)) {
            $script .= sprintf("echo '--> Zmiana hasła systemowego dla %1\$s'; echo '%1\$s:%2\$s' | chpasswd 2>&1;\n", $login, $password);
        }

        $sql = sprintf(
            "UPDATE roundcubemail.users SET username = %1\$s, mail_host = %2\$s WHERE user_id = %3\$d; " .
            "UPDATE roundcubemail.identities SET name = %4\$s, email = %5\$s WHERE user_id = %3\$d AND standard = 1; " .
            "UPDATE roundcubemail.identities SET name = %4\$s, email = %6\$s WHERE user_id = %3\$d AND standard = 0;",
            escapeshellarg($login),
            escapeshellarg($mailHost),
            $userId,
            escapeshellarg($imieNazwisko),
            escapeshellarg($emailPl),
            escapeshellarg($emailComPl)
        );

        $script .= sprintf("echo '--> Aktualizacja wpisów w bazie Roundcube'; mysql -e %s 2>&1;\n", escapeshellarg($sql));
        $script .= "echo '=== KONIEC AKTUALIZACJI KONTA ===' >> /var/log/konta.log;\n";

        try {
            return $this->execute($script);
        } catch (Exception $e) {
            throw new Exception("Błąd podczas aktualizacji użytkownika: " . $e->getMessage());
        }
    }

    public function deleteRoundcubeUser(int $userId): string
    {
        $queryLogin = sprintf("SELECT username FROM roundcubemail.users WHERE user_id = %d;", $userId);
        $commandLogin = sprintf("mysql -N -e %s", escapeshellarg($queryLogin));
        $login = trim($this->execute($commandLogin));

        if (empty($login)) {
            throw new Exception("Nie znaleziono użytkownika o ID: $userId");
        }

        $script = "echo '=== START USUWANIA KONTA: " . $login . " (" . date('Y-m-d H:i:s') . ") ===' >> /var/log/konta.log;\n";

        $queryEmails = sprintf("SELECT email FROM roundcubemail.identities WHERE user_id = %d;", $userId);
        $commandEmails = sprintf("mysql -N -e %s", escapeshellarg($queryEmails));
        $emailsOutput = $this->execute($commandEmails);
        $emails = array_filter(explode("\n", $emailsOutput));

        $script .= "echo '--> Usuwanie wpisów z Postfix vusertable';";
        foreach ($emails as $email) {
            $email = trim($email);
            if (!empty($email)) {
                $script .= sprintf("sed -i '/^%s\\s/d' /etc/postfix/vusertable; ", $email);
            }
        }
        $script .= "postmap /etc/postfix/vusertable; postfix reload 2>&1;\n";

        $script .= sprintf("echo '--> Usuwanie użytkownika systemowego Linux %s'; userdel %s 2>&1;\n", $login, $login);

        $sqlRoundcube = sprintf(
            "DELETE FROM roundcubemail.identities WHERE user_id = %d; " .
            "DELETE FROM roundcubemail.users WHERE user_id = %d;",
            $userId,
            $userId
        );
        $script .= sprintf("echo '--> Usuwanie rekordów z bazy Roundcube'; mysql -e %s 2>&1;\n", escapeshellarg($sqlRoundcube));
        $script .= "echo '=== KONIEC USUWANIA KONTA ===' >> /var/log/konta.log;\n";

        try {
            return $this->execute($script);
        } catch (Exception $e) {
            throw new Exception("Błąd podczas usuwania użytkownika: " . $e->getMessage());
        }
    }

    // ============================================================
    // MODUŁ ALIASÓW POCZTOWYCH (/etc/postfix/valiastable)
    // ============================================================

    public function getAliases(): array
    {
        $raw = $this->execute("cat /etc/postfix/valiastable");
        $aliases = [];

        foreach (explode("\n", $raw) as $line) {
            $lineTrim = trim($line);
            
            if (empty($lineTrim) || strpos($lineTrim, '#') === 0) {
                continue;
            }

            $parts = preg_split('/\s+/', $lineTrim, 2);
            if (count($parts) === 2) {
                $aliases[] = [
                    'alias' => $parts[0],
                    'destination' => $parts[1],
                ];
            }
        }

        return $aliases;
    }

    public function addAlias(string $alias, string $destination): string
    {
        $alias = trim($alias);
        $destination = trim($destination);

        $raw = $this->execute("cat /etc/postfix/valiastable");
        $lines = explode("\n", $raw);
        
        $newLine = sprintf("%-35s %s", $alias, $destination);
        $lines[] = $newLine;

        $updatedContent = implode("\n", $lines);
        return $this->saveValiastableContent($updatedContent, "DODAWANIE ALIASU: {$alias} -> {$destination}");
    }

    public function updateAlias(string $oldAlias, string $newAlias, string $destination): string
    {
        $oldAlias = trim($oldAlias);
        $newAlias = trim($newAlias);
        $destination = trim($destination);

        $raw = $this->execute("cat /etc/postfix/valiastable");
        $lines = explode("\n", $raw);
        $updatedLines = [];
        $replaced = false;

        foreach ($lines as $line) {
            $lineTrim = trim($line);

            if (empty($lineTrim) || strpos($lineTrim, '#') === 0) {
                $updatedLines[] = $line;
                continue;
            }

            $parts = preg_split('/\s+/', $lineTrim, 2);

            if (count($parts) === 2 && $parts[0] === $oldAlias) {
                $updatedLines[] = sprintf("%-35s %s", $newAlias, $destination);
                $replaced = true;
            } else {
                $updatedLines[] = $line;
            }
        }

        if (!$replaced) {
            $updatedLines[] = sprintf("%-35s %s", $newAlias, $destination);
        }

        $updatedContent = implode("\n", $updatedLines);
        return $this->saveValiastableContent($updatedContent, "AKTUALIZACJA ALIASU: {$oldAlias} -> {$newAlias}");
    }

    public function deleteAlias(string $alias): string
    {
        $alias = trim($alias);

        $raw = $this->execute("cat /etc/postfix/valiastable");
        $lines = explode("\n", $raw);
        $updatedLines = [];

        foreach ($lines as $line) {
            $lineTrim = trim($line);

            if (empty($lineTrim) || strpos($lineTrim, '#') === 0) {
                $updatedLines[] = $line;
                continue;
            }

            $parts = preg_split('/\s+/', $lineTrim, 2);

            if (count($parts) === 2 && $parts[0] === $alias) {
                continue;
            }

            $updatedLines[] = $line;
        }

        $updatedContent = implode("\n", $updatedLines);
        return $this->saveValiastableContent($updatedContent, "USUWANIE ALIASU: {$alias}");
    }

    private function saveValiastableContent(string $content, string $logMessage): string
    {
        $base64Content = base64_encode($content);

        $script = sprintf("echo '=== %s (%s) ===' >> /var/log/konta.log;\n", $logMessage, date('Y-m-d H:i:s'));
        $script .= "cp /etc/postfix/valiastable /etc/postfix/valiastable.bak;\n";
        $script .= sprintf("echo %s | base64 -d > /etc/postfix/valiastable;\n", escapeshellarg($base64Content));
        $script .= "postmap /etc/postfix/valiastable 2>&1;\n";
        $script .= "postfix reload 2>&1;\n";

        return $this->execute($script);
    }

    // ============================================================
    // MODUŁ UPRAWNIEŃ NADAWCÓW (/etc/postfix/vsendertable)
    // ============================================================

    public function getSenderRules(): array
    {
        $raw = $this->execute("cat /etc/postfix/vsendertable");
        $rules = [];

        foreach (explode("\n", $raw) as $line) {
            $lineTrim = trim($line);
            
            if (empty($lineTrim) || strpos($lineTrim, '#') === 0) {
                continue;
            }

            $parts = preg_split('/\s+/', $lineTrim, 2);
            if (count($parts) === 2) {
                $rules[] = [
                    'sender' => $parts[0],
                    'allowed_logins' => $parts[1],
                ];
            }
        }

        return $rules;
    }

    public function addSenderRule(string $sender, string $allowedLogins): string
    {
        $sender = trim($sender);
        $allowedLogins = trim($allowedLogins);

        $raw = $this->execute("cat /etc/postfix/vsendertable");
        $lines = explode("\n", $raw);
        
        $newLine = sprintf("%-35s %s", $sender, $allowedLogins);
        $lines[] = $newLine;

        $updatedContent = implode("\n", $lines);
        return $this->saveVsendertableContent($updatedContent, "DODAWANIE UPRAWNIENIA NADAWCY: {$sender} -> {$allowedLogins}");
    }

    public function updateSenderRule(string $oldSender, string $newSender, string $allowedLogins): string
    {
        $oldSender = trim($oldSender);
        $newSender = trim($newSender);
        $allowedLogins = trim($allowedLogins);

        $raw = $this->execute("cat /etc/postfix/vsendertable");
        $lines = explode("\n", $raw);
        $updatedLines = [];
        $replaced = false;

        foreach ($lines as $line) {
            $lineTrim = trim($line);

            if (empty($lineTrim) || strpos($lineTrim, '#') === 0) {
                $updatedLines[] = $line;
                continue;
            }

            $parts = preg_split('/\s+/', $lineTrim, 2);

            if (count($parts) === 2 && $parts[0] === $oldSender) {
                $updatedLines[] = sprintf("%-35s %s", $newSender, $allowedLogins);
                $replaced = true;
            } else {
                $updatedLines[] = $line;
            }
        }

        if (!$replaced) {
            $updatedLines[] = sprintf("%-35s %s", $newSender, $allowedLogins);
        }

        $updatedContent = implode("\n", $updatedLines);
        return $this->saveVsendertableContent($updatedContent, "AKTUALIZACJA UPRAWNIENIA NADAWCY: {$oldSender} -> {$newSender}");
    }

    public function deleteSenderRule(string $sender): string
    {
        $sender = trim($sender);

        $raw = $this->execute("cat /etc/postfix/vsendertable");
        $lines = explode("\n", $raw);
        $updatedLines = [];

        foreach ($lines as $line) {
            $lineTrim = trim($line);

            if (empty($lineTrim) || strpos($lineTrim, '#') === 0) {
                $updatedLines[] = $line;
                continue;
            }

            $parts = preg_split('/\s+/', $lineTrim, 2);

            if (count($parts) === 2 && $parts[0] === $sender) {
                continue;
            }

            $updatedLines[] = $line;
        }

        $updatedContent = implode("\n", $updatedLines);
        return $this->saveVsendertableContent($updatedContent, "USUWANIE UPRAWNIENIA NADAWCY: {$sender}");
    }

    private function saveVsendertableContent(string $content, string $logMessage): string
    {
        $base64Content = base64_encode($content);

        $script = sprintf("echo '=== %s (%s) ===' >> /var/log/konta.log;\n", $logMessage, date('Y-m-d H:i:s'));
        $script .= "cp /etc/postfix/vsendertable /etc/postfix/vsendertable.bak;\n";
        $script .= sprintf("echo %s | base64 -d > /etc/postfix/vsendertable;\n", escapeshellarg($base64Content));
        $script .= "postmap /etc/postfix/vsendertable 2>&1;\n";
        $script .= "postfix reload 2>&1;\n";

        return $this->execute($script);
    }

    // ============================================================
    // MODUŁ ODBIORCÓW WEWNĘTRZNYCH (/etc/postfix/internal_rcpts_only)
    // ============================================================

    public function getInternalRcpts(): array
    {
        $raw = $this->execute("cat /etc/postfix/internal_rcpts_only");
        $items = [];

        foreach (explode("\n", $raw) as $line) {
            $lineTrim = trim($line);
            
            if (empty($lineTrim) || strpos($lineTrim, '#') === 0) {
                continue;
            }

            $parts = preg_split('/\s+/', $lineTrim, 2);
            if (count($parts) === 2) {
                $items[] = [
                    'recipient' => $parts[0],
                    'action' => $parts[1],
                ];
            }
        }

        return $items;
    }

    public function addInternalRcpt(string $recipient, string $action = 'banned_from_world'): string
    {
        $recipient = trim($recipient);
        $action = trim($action);

        $raw = $this->execute("cat /etc/postfix/internal_rcpts_only");
        $lines = explode("\n", $raw);
        
        $newLine = sprintf("%-35s %s", $recipient, $action);
        $lines[] = $newLine;

        $updatedContent = implode("\n", $lines);
        return $this->saveInternalRcptsContent($updatedContent, "DODAWANIE BLOKADY Z zEWNĄTRZ: {$recipient} -> {$action}");
    }

    public function updateInternalRcpt(string $oldRecipient, string $newRecipient, string $action = 'banned_from_world'): string
    {
        $oldRecipient = trim($oldRecipient);
        $newRecipient = trim($newRecipient);
        $action = trim($action);

        $raw = $this->execute("cat /etc/postfix/internal_rcpts_only");
        $lines = explode("\n", $raw);
        $updatedLines = [];
        $replaced = false;

        foreach ($lines as $line) {
            $lineTrim = trim($line);

            if (empty($lineTrim) || strpos($lineTrim, '#') === 0) {
                $updatedLines[] = $line;
                continue;
            }

            $parts = preg_split('/\s+/', $lineTrim, 2);

            if (count($parts) === 2 && $parts[0] === $oldRecipient) {
                $updatedLines[] = sprintf("%-35s %s", $newRecipient, $action);
                $replaced = true;
            } else {
                $updatedLines[] = $line;
            }
        }

        if (!$replaced) {
            $updatedLines[] = sprintf("%-35s %s", $newRecipient, $action);
        }

        $updatedContent = implode("\n", $updatedLines);
        return $this->saveInternalRcptsContent($updatedContent, "AKTUALIZACJA BLOKADY Z ZEWNĄTRZ: {$oldRecipient} -> {$newRecipient}");
    }

    public function deleteInternalRcpt(string $recipient): string
    {
        $recipient = trim($recipient);

        $raw = $this->execute("cat /etc/postfix/internal_rcpts_only");
        $lines = explode("\n", $raw);
        $updatedLines = [];

        foreach ($lines as $line) {
            $lineTrim = trim($line);

            if (empty($lineTrim) || strpos($lineTrim, '#') === 0) {
                $updatedLines[] = $line;
                continue;
            }

            $parts = preg_split('/\s+/', $lineTrim, 2);

            if (count($parts) === 2 && $parts[0] === $recipient) {
                continue;
            }

            $updatedLines[] = $line;
        }

        $updatedContent = implode("\n", $updatedLines);
        return $this->saveInternalRcptsContent($updatedContent, "USUWANIE BLOKADY Z ZEWNĄTRZ: {$recipient}");
    }

    private function saveInternalRcptsContent(string $content, string $logMessage): string
    {
        $base64Content = base64_encode($content);

        $script = sprintf("echo '=== %s (%s) ===' >> /var/log/konta.log;\n", $logMessage, date('Y-m-d H:i:s'));
        $script .= "cp /etc/postfix/internal_rcpts_only /etc/postfix/internal_rcpts_only.bak;\n";
        $script .= sprintf("echo %s | base64 -d > /etc/postfix/internal_rcpts_only;\n", escapeshellarg($base64Content));
        $script .= "postmap /etc/postfix/internal_rcpts_only 2>&1;\n";
        $script .= "postfix reload 2>&1;\n";

        return $this->execute($script);
    }
}