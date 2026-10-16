<?php

namespace App\Services;

use App\Services\Fail2ban\Fail2banSshClient;
use Exception;

class MailServerService
{
    /**
     * Minimalna długość sekretu maskowanego w logach. Krótszych wartości nie
     * maskujemy, aby nie zniszczyć czytelności logów (nie trafiają do komend).
     */
    private const MIN_REDACT_LENGTH = 4;

    public function __construct(
        private Fail2banSshClient $sshClient
    ) {}

    /**
     * Wykonuje polecenie na zdalnym hoście.
     *
     * @param  array<int, string>  $redact  Sekrety, które mają zostać zamaskowane w logach SSH
     */
    public function execute(string $command, array $redact = []): string
    {
        try {
            return trim($this->sshClient->run($command, $redact));
        } catch (Exception $e) {
            throw new Exception('SSH Command failed: '.$e->getMessage());
        }
    }

    /**
     * Loginy są wstawiane do skryptu powłoki wykonywanego na serwerze poczty,
     * dlatego dopuszczamy wyłącznie znaki bezpieczne dla shella oraz dla formatu
     * "login:hasło", który czyta chpasswd.
     *
     * @throws Exception
     */
    private function assertSafeLogin(string $login): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]*$/', $login) !== 1) {
            throw new Exception("Nieprawidłowy login użytkownika poczty: '{$login}'");
        }
    }

    /**
     * Weryfikuje klucz wpisu mapy Postfixa (lewa kolumna pliku).
     *
     * Klucz nie może zawierać białych ani sterujących znaków — plik mapy jest
     * zapisywany w całości, więc nowa linia w kluczu dopisałaby dodatkowy wpis
     * (albo zepsuła tabelę przed `postmap`).
     */
    private function assertSafeMapKey(string $key, string $label): void
    {
        if ($key === '' || preg_match('/[\x00-\x1F\x7F\s]/', $key) === 1) {
            throw new Exception("Nieprawidłowa wartość pola {$label}: '{$key}' — dozwolone są tylko znaki bez białych znaków i znaków sterujących.");
        }
    }

    /**
     * Weryfikuje wartość wpisu mapy Postfixa (prawa kolumna pliku).
     *
     * Spacje są dozwolone (np. `rafalbielawski, kadrytelmor`), ale znaki
     * sterujące już nie — rozbiłyby wpis na dodatkowe linie pliku.
     */
    private function assertSafeMapValue(string $value, string $label): void
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new Exception("Nieprawidłowa wartość pola {$label} — znaki sterujące są niedozwolone.");
        }
    }

    /**
     * Maskuje sekrety w tekście przeznaczonym do logu.
     *
     * @param  array<int, string>  $secrets
     */
    private function redactSecrets(string $text, array $secrets): string
    {
        foreach ($secrets as $secret) {
            if (strlen($secret) >= self::MIN_REDACT_LENGTH) {
                $text = str_replace($secret, '[REDACTED]', $text);
            }
        }

        return $text;
    }

    /**
     * Wybiera z podanych wartości te, które należy maskować w logach.
     *
     * @param  array<int, string|null>  $values
     * @return array<int, string>
     */
    private function secretsForLog(array $values): array
    {
        return array_values(array_filter(
            $values,
            static fn (?string $value): bool => is_string($value) && strlen($value) >= self::MIN_REDACT_LENGTH
        ));
    }

    /**
     * Escape'uje wartość tak, aby w wyrażeniu regularnym sed (BRE) była ciągiem literalnym.
     */
    private function escapeSedPattern(string $value): string
    {
        return str_replace(
            ['\\', '/', '.', '*', '[', ']', '^', '$'],
            ['\\\\', '\\/', '\\.', '\\*', '\\[', '\\]', '\\^', '\\$'],
            $value
        );
    }

    /**
     * Buduje literał SQL dla wywołania `mysql -e`.
     *
     * Powłokę zabezpiecza escapeshellarg() w miejscu wywołania (całe zapytanie),
     * więc tutaj podwajamy wyłącznie znaki istotne dla parsera SQL: apostrof
     * oraz backslash (MySQL domyślnie traktuje backslash jako znak ucieczki).
     */
    private function sqlLiteral(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "''"], $value)."'";
    }

    /**
     * Buduje polecenie dopisujące wpis do /var/log/konta.log na zdalnym hoście.
     *
     * Komunikaty zawierają wartości podane w panelu (alias, nadawca, odbiorca),
     * dlatego cała treść jest cytowana przez escapeshellarg() — wcześniej surowa
     * interpolacja w `echo '...'` pozwalała na wstrzyknięcie poleceń powłoki.
     * Znaki sterujące są zamieniane na spacje, aby nie dało się sfałszować wpisu
     * w logu (wielolinijkowy komunikat rozjechałby też pojedynczą linię skryptu).
     */
    private function logLineCommand(string $logMessage): string
    {
        $normalized = (string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $logMessage);
        $logLine = '=== '.$normalized.' ('.date('Y-m-d H:i:s').') ===';

        return sprintf("echo %s >> /var/log/konta.log;\n", escapeshellarg($logLine));
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

        $command = sprintf('mysql -N -e %s', escapeshellarg($query));
        $output = $this->execute($command);

        $users = [];
        foreach (explode("\n", trim($output)) as $line) {
            if (empty($line)) {
                continue;
            }
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
        $imieNazwisko = trim($imie.' '.$nazwisko);
        $emailPl = $emailLocalPart.'@telmor.pl';
        $emailComPl = $emailLocalPart.'@telmor.com.pl';
        $cyrusUser = (string) config('services.cyrus.admin_user', 'cyrus');
        $cyrusPassword = (string) config('services.cyrus.admin_password');

        $this->assertSafeLogin($login);

        $script = "echo '=== START TWORZENIA KONTA: ".$login.' ('.date('Y-m-d H:i:s').") ===' >> /var/log/konta.log;\n";

        // 1. Tworzenie usera systemowego
        $script .= sprintf("echo '--> Tworzenie użytkownika systemowego %1\$s' 2>&1;\n", $login);
        $script .= sprintf("useradd -d /home/telmor/%1\$s -g telmor -M -s /bin/false %1\$s 2>&1;\n", $login);

        $passwordPayload = '';

        if ($password !== '') {
            $passwordPayload = base64_encode($login.':'.$password."\n");
            $script .= "echo '--> Ustawianie hasła systemowego' 2>&1;\n";
            $script .= "printf '%s' ".escapeshellarg($passwordPayload)." | base64 -d | chpasswd 2>&1;\n";
        }

        // 2. Wpisy do Postfix vusertable
        $script .= "echo '--> Dodawanie wpisów do Postfix vusertable' 2>&1;\n";
        $script .= sprintf("printf '%%s\\t%%s\\n' %s %s >> /etc/postfix/vusertable;\n", escapeshellarg($emailPl), escapeshellarg($login));
        $script .= sprintf("printf '%%s\\t%%s\\n' %s %s >> /etc/postfix/vusertable;\n", escapeshellarg($emailComPl), escapeshellarg($login));
        $script .= "postmap /etc/postfix/vusertable 2>&1;\n";
        $script .= "postfix reload 2>&1;\n";

        // 3. Cyrus IMAP
        $script .= "echo '--> Tworzenie skrzynek w Cyrus IMAP' 2>&1;\n";
        $script .= "cat << 'EOF_CYRADM' > /tmp/cyradm_commands.txt\n";
        $script .= 'cm user.'.$login."\n";
        $script .= 'cm user.'.$login.".Trash\n";
        $script .= 'cm user.'.$login.".Sent\n";
        $script .= 'cm user.'.$login.".Drafts\n";
        $script .= 'cm user.'.$login.".antyspam\n";
        $script .= 'sam user.'.$login.".antyspam anyone p\n";
        $script .= 'mboxcfg user.'.$login.".antyspam expire 14\n";
        $script .= 'mboxcfg user.'.$login.".Trash expire 7\n";
        $script .= 'sq user.'.$login." STORAGE 2097152\n";
        $script .= "quit\n";
        $script .= "EOF_CYRADM\n";

        $script .= sprintf("export TERM=dumb; cyradm --user %s --password %s localhost < /tmp/cyradm_commands.txt 2>&1 | sed 's/localhost> //g';\n", escapeshellarg($cyrusUser), escapeshellarg($cyrusPassword));
        $script .= "rm -f /tmp/cyradm_commands.txt;\n";

        // 4. Baza Roundcube
        $sqlRoundcube = sprintf(
            "INSERT INTO roundcubemail.users (username, mail_host, created, language, preferences) VALUES (%1\$s, %2\$s, NOW(), 'pl_PL', 'a:0:{}'); ".
            'SET @rc_id = LAST_INSERT_ID(); '.
            'INSERT INTO roundcubemail.identities (user_id, changed, del, standard, name, email) VALUES (@rc_id, NOW(), 0, 1, %3$s, %4$s); '.
            'INSERT INTO roundcubemail.identities (user_id, changed, del, standard, name, email) VALUES (@rc_id, NOW(), 0, 0, %3$s, %5$s);',
            $this->sqlLiteral($login),
            $this->sqlLiteral($mailHost),
            $this->sqlLiteral($imieNazwisko),
            $this->sqlLiteral($emailPl),
            $this->sqlLiteral($emailComPl)
        );

        $script .= "echo '--> Zapisywanie w bazie Roundcube' 2>&1;\n";
        $script .= sprintf("mysql -e %s 2>&1;\n", escapeshellarg($sqlRoundcube));
        $script .= "echo '=== KONIEC TWORZENIA KONTA ===' >> /var/log/konta.log;\n";

        $secrets = $this->secretsForLog([$password, $passwordPayload]);

        try {
            $output = $this->execute($script, $secrets);
            $output = $this->redactSecrets($output, $secrets);
            $this->execute('echo '.escapeshellarg($output).' >> /var/log/konta.log', $secrets);

            return $output;
        } catch (Exception $e) {
            throw new Exception('Błąd podczas pełnego tworzenia konta: '.$e->getMessage());
        }
    }

    public function updateRoundcubeUser(int $userId, string $login, string $imie, string $nazwisko, string $emailPl, string $emailComPl, string $mailHost, ?string $password = null): string
    {
        $imieNazwisko = trim($imie.' '.$nazwisko);

        $this->assertSafeLogin($login);

        $script = "echo '=== START AKTUALIZACJI KONTA: ".$login.' ('.date('Y-m-d H:i:s').") ===' >> /var/log/konta.log;\n";

        $passwordPayload = '';

        if ($password !== null && $password !== '') {
            $passwordPayload = base64_encode($login.':'.$password."\n");
            $script .= sprintf("echo '--> Zmiana hasła systemowego dla ' %s;\n", escapeshellarg($login));
            $script .= "printf '%s' ".escapeshellarg($passwordPayload)." | base64 -d | chpasswd 2>&1;\n";
        }

        $sql = sprintf(
            'UPDATE roundcubemail.users SET username = %1$s, mail_host = %2$s WHERE user_id = %3$d; '.
            'UPDATE roundcubemail.identities SET name = %4$s, email = %5$s WHERE user_id = %3$d AND standard = 1; '.
            'UPDATE roundcubemail.identities SET name = %4$s, email = %6$s WHERE user_id = %3$d AND standard = 0;',
            $this->sqlLiteral($login),
            $this->sqlLiteral($mailHost),
            $userId,
            $this->sqlLiteral($imieNazwisko),
            $this->sqlLiteral($emailPl),
            $this->sqlLiteral($emailComPl)
        );

        $script .= sprintf("echo '--> Aktualizacja wpisów w bazie Roundcube'; mysql -e %s 2>&1;\n", escapeshellarg($sql));
        $script .= "echo '=== KONIEC AKTUALIZACJI KONTA ===' >> /var/log/konta.log;\n";

        $secrets = $this->secretsForLog([$password, $passwordPayload]);

        try {
            $output = $this->execute($script, $secrets);

            return $this->redactSecrets($output, $secrets);
        } catch (Exception $e) {
            throw new Exception('Błąd podczas aktualizacji użytkownika: '.$e->getMessage());
        }
    }

    public function deleteRoundcubeUser(int $userId): string
    {
        $queryLogin = sprintf('SELECT username FROM roundcubemail.users WHERE user_id = %d;', $userId);
        $commandLogin = sprintf('mysql -N -e %s', escapeshellarg($queryLogin));
        $login = trim($this->execute($commandLogin));

        if (empty($login)) {
            throw new Exception("Nie znaleziono użytkownika o ID: $userId");
        }

        $script = "echo '=== START USUWANIA KONTA: ' ".escapeshellarg($login)." ' (".date('Y-m-d H:i:s').") ===' >> /var/log/konta.log;\n";

        $queryEmails = sprintf('SELECT email FROM roundcubemail.identities WHERE user_id = %d;', $userId);
        $commandEmails = sprintf('mysql -N -e %s', escapeshellarg($queryEmails));
        $emailsOutput = $this->execute($commandEmails);
        $emails = array_filter(explode("\n", $emailsOutput));

        $script .= "echo '--> Usuwanie wpisów z Postfix vusertable';";
        foreach ($emails as $email) {
            $email = trim($email);
            if (! empty($email)) {
                $sedPattern = '/^'.$this->escapeSedPattern($email).'\s/d';
                $script .= sprintf('sed -i %s /etc/postfix/vusertable; ', escapeshellarg($sedPattern));
            }
        }
        $script .= "postmap /etc/postfix/vusertable; postfix reload 2>&1;\n";

        $script .= sprintf(
            "echo '--> Usuwanie użytkownika systemowego Linux ' %s; userdel %s 2>&1;\n",
            escapeshellarg($login),
            escapeshellarg($login)
        );

        $sqlRoundcube = sprintf(
            'DELETE FROM roundcubemail.identities WHERE user_id = %d; '.
            'DELETE FROM roundcubemail.users WHERE user_id = %d;',
            $userId,
            $userId
        );
        $script .= sprintf("echo '--> Usuwanie rekordów z bazy Roundcube'; mysql -e %s 2>&1;\n", escapeshellarg($sqlRoundcube));
        $script .= "echo '=== KONIEC USUWANIA KONTA ===' >> /var/log/konta.log;\n";

        try {
            return $this->execute($script);
        } catch (Exception $e) {
            throw new Exception('Błąd podczas usuwania użytkownika: '.$e->getMessage());
        }
    }

    // ============================================================
    // MODUŁ ALIASÓW POCZTOWYCH (/etc/postfix/valiastable)
    // ============================================================

    public function getAliases(): array
    {
        $raw = $this->execute('cat /etc/postfix/valiastable');
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

        $this->assertSafeMapKey($alias, 'alias');
        $this->assertSafeMapValue($destination, 'destination');

        $raw = $this->execute('cat /etc/postfix/valiastable');
        $lines = explode("\n", $raw);

        $newLine = sprintf('%-35s %s', $alias, $destination);
        $lines[] = $newLine;

        $updatedContent = implode("\n", $lines);

        return $this->saveValiastableContent($updatedContent, "DODAWANIE ALIASU: {$alias} -> {$destination}");
    }

    public function updateAlias(string $oldAlias, string $newAlias, string $destination): string
    {
        $oldAlias = trim($oldAlias);
        $newAlias = trim($newAlias);
        $destination = trim($destination);

        $this->assertSafeMapKey($oldAlias, 'old_alias');
        $this->assertSafeMapKey($newAlias, 'alias');
        $this->assertSafeMapValue($destination, 'destination');

        $raw = $this->execute('cat /etc/postfix/valiastable');
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
                $updatedLines[] = sprintf('%-35s %s', $newAlias, $destination);
                $replaced = true;
            } else {
                $updatedLines[] = $line;
            }
        }

        if (! $replaced) {
            $updatedLines[] = sprintf('%-35s %s', $newAlias, $destination);
        }

        $updatedContent = implode("\n", $updatedLines);

        return $this->saveValiastableContent($updatedContent, "AKTUALIZACJA ALIASU: {$oldAlias} -> {$newAlias}");
    }

    public function deleteAlias(string $alias): string
    {
        $alias = trim($alias);

        $raw = $this->execute('cat /etc/postfix/valiastable');
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

        $script = $this->logLineCommand($logMessage);
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
        $raw = $this->execute('cat /etc/postfix/vsendertable');
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

        $this->assertSafeMapKey($sender, 'sender');
        $this->assertSafeMapValue($allowedLogins, 'allowed_logins');

        $raw = $this->execute('cat /etc/postfix/vsendertable');
        $lines = explode("\n", $raw);

        $newLine = sprintf('%-35s %s', $sender, $allowedLogins);
        $lines[] = $newLine;

        $updatedContent = implode("\n", $lines);

        return $this->saveVsendertableContent($updatedContent, "DODAWANIE UPRAWNIENIA NADAWCY: {$sender} -> {$allowedLogins}");
    }

    public function updateSenderRule(string $oldSender, string $newSender, string $allowedLogins): string
    {
        $oldSender = trim($oldSender);
        $newSender = trim($newSender);
        $allowedLogins = trim($allowedLogins);

        $this->assertSafeMapKey($oldSender, 'old_sender');
        $this->assertSafeMapKey($newSender, 'sender');
        $this->assertSafeMapValue($allowedLogins, 'allowed_logins');

        $raw = $this->execute('cat /etc/postfix/vsendertable');
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
                $updatedLines[] = sprintf('%-35s %s', $newSender, $allowedLogins);
                $replaced = true;
            } else {
                $updatedLines[] = $line;
            }
        }

        if (! $replaced) {
            $updatedLines[] = sprintf('%-35s %s', $newSender, $allowedLogins);
        }

        $updatedContent = implode("\n", $updatedLines);

        return $this->saveVsendertableContent($updatedContent, "AKTUALIZACJA UPRAWNIENIA NADAWCY: {$oldSender} -> {$newSender}");
    }

    public function deleteSenderRule(string $sender): string
    {
        $sender = trim($sender);

        $raw = $this->execute('cat /etc/postfix/vsendertable');
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

        $script = $this->logLineCommand($logMessage);
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
        $raw = $this->execute('cat /etc/postfix/internal_rcpts_only');
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

        $this->assertSafeMapKey($recipient, 'recipient');
        $this->assertSafeMapValue($action, 'action');

        $raw = $this->execute('cat /etc/postfix/internal_rcpts_only');
        $lines = explode("\n", $raw);

        $newLine = sprintf('%-35s %s', $recipient, $action);
        $lines[] = $newLine;

        $updatedContent = implode("\n", $lines);

        return $this->saveInternalRcptsContent($updatedContent, "DODAWANIE BLOKADY Z zEWNĄTRZ: {$recipient} -> {$action}");
    }

    public function updateInternalRcpt(string $oldRecipient, string $newRecipient, string $action = 'banned_from_world'): string
    {
        $oldRecipient = trim($oldRecipient);
        $newRecipient = trim($newRecipient);
        $action = trim($action);

        $this->assertSafeMapKey($oldRecipient, 'old_recipient');
        $this->assertSafeMapKey($newRecipient, 'recipient');
        $this->assertSafeMapValue($action, 'action');

        $raw = $this->execute('cat /etc/postfix/internal_rcpts_only');
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
                $updatedLines[] = sprintf('%-35s %s', $newRecipient, $action);
                $replaced = true;
            } else {
                $updatedLines[] = $line;
            }
        }

        if (! $replaced) {
            $updatedLines[] = sprintf('%-35s %s', $newRecipient, $action);
        }

        $updatedContent = implode("\n", $updatedLines);

        return $this->saveInternalRcptsContent($updatedContent, "AKTUALIZACJA BLOKADY Z ZEWNĄTRZ: {$oldRecipient} -> {$newRecipient}");
    }

    public function deleteInternalRcpt(string $recipient): string
    {
        $recipient = trim($recipient);

        $raw = $this->execute('cat /etc/postfix/internal_rcpts_only');
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

        $script = $this->logLineCommand($logMessage);
        $script .= "cp /etc/postfix/internal_rcpts_only /etc/postfix/internal_rcpts_only.bak;\n";
        $script .= sprintf("echo %s | base64 -d > /etc/postfix/internal_rcpts_only;\n", escapeshellarg($base64Content));
        $script .= "postmap /etc/postfix/internal_rcpts_only 2>&1;\n";
        $script .= "postfix reload 2>&1;\n";

        return $this->execute($script);
    }
}
