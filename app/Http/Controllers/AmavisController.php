<?php

namespace App\Http\Controllers;

use App\Services\Fail2ban\Fail2banSshClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AmavisController extends Controller
{
    private string $quarantineDir = '/var/lib/amavis/virusmails';

    public function __construct(
        private Fail2banSshClient $sshClient
    ) {}

    public function index()
    {
        // Pobieranie logów do wyświetlenia (ostatnie 200 linii)
        try {
            $logsRaw = $this->sshClient->run('tail -n 200 /var/log/amavis/amavis.log');
            $logLines = array_filter(explode("\n", trim($logsRaw)));
            $logs = implode("\n", array_reverse($logLines));
        } catch (\Exception $e) {
            $logs = 'Brak logów lub błąd połączenia.';
        }
        
        // Pobieranie statystyk z całego dnia (od północy)
        $stats = $this->getDailyStats();
        
        // Pobierz wszystko w jednym połączeniu SSH
        $quarantined = $this->getQuarantinedEmailsOptimized(30);
        
        // Pobieranie wiadomości SPAMMY z całego dnia
        $spammyMessages = $this->getSpammyMessagesFromToday();
        
        return view('amavis.index', compact('logs', 'quarantined', 'stats', 'spammyMessages'));
    }
    
    private function getSpammyMessagesFromToday()
    {
        $spammy = [];
        
        try {
            // Pobierz więcej linii (1000), aby na pewno objąć cały dzień
            $output = $this->sshClient->run('tail -n 1000 /var/log/amavis/amavis.log');
        } catch (\Exception $e) {
            return $spammy;
        }
        
        $lines = explode("\n", trim($output));
        $todayMonth = date('M'); // np. "Sep"
        $todayDay = intval(date('j')); // np. 16
        
        foreach ($lines as $line) {
            // Szukaj tylko linii z "Passed SPAMMY"
            if (stripos($line, 'Passed SPAMMY') === false) {
                continue;
            }
            
            // Sprawdź datę w linii
            if (preg_match('/^([A-Za-z]{3})\s+(\d{1,2})\s+\d{2}:\d{2}:\d{2}/', $line, $dateMatches)) {
                $month = $dateMatches[1];
                $day = intval($dateMatches[2]);
                
                if ($month !== $todayMonth || $day !== $todayDay) {
                    continue; // nie dzisiejsza wiadomość
                }
            } else {
                continue; // brak daty – pomijamy
            }
            
            $message = [];
            
            if (preg_match('/^(\w+\s+\d+\s+\d+:\d+:\d+)/', $line, $matches)) {
                $message['date'] = $this->sanitizeUtf8($matches[1]);
            }
            
            if (preg_match('/<([^>]+)>/', $line, $matches)) {
                $message['from'] = $this->sanitizeUtf8($matches[1]);
            }
            
            if (preg_match('/->\s*<([^>]+)>/', $line, $matches)) {
                $message['to'] = $this->sanitizeUtf8($matches[1]);
            }
            
            if (preg_match('/Hits:\s*([^,]+)/', $line, $matches)) {
                $message['spam_score'] = $this->sanitizeUtf8(trim($matches[1]));
            }
            
            if (preg_match('/Queue-ID:\s*(\S+)/', $line, $matches)) {
                $message['queue_id'] = $this->sanitizeUtf8($matches[1]);
            }
            
            $spammy[] = $message;
        }
        
        return $spammy;
    }
    
    private function getDailyStats()
    {
        $stats = [
            'total' => 0,
            'clean' => 0,
            'spammy' => 0,
            'spam' => 0,
            'blocked' => 0,
            'virus' => 0
        ];
        
        // Miesiąc (np. Sep) oraz dzień miesiąca bez wiodącego zera (np. 16 lub 9)
        $month = date('M');
        $day = date('j');
        
        // Dopasowuje elastyczną liczbę spacji między miesiącem a dniem (np. "Sep  9" lub "Sep 16")
        $datePattern = sprintf('%s\s+%d\b', $month, $day);
        
        $script = <<<BASH
LOG="/var/log/amavis/amavis.log"
if [ ! -f "\$LOG" ]; then
    echo "TOTAL: 0"
    echo "CLEAN: 0"
    echo "SPAMMY: 0"
    echo "SPAM: 0"
    echo "VIRUS: 0"
    exit 0
fi

echo "TOTAL: $(grep -E "$datePattern" "\$LOG" | wc -l)"
echo "CLEAN: $(grep -E "$datePattern" "\$LOG" | grep -c "Passed CLEAN")"
echo "SPAMMY: $(grep -E "$datePattern" "\$LOG" | grep -c "Passed SPAMMY")"
echo "SPAM: $(grep -E "$datePattern" "\$LOG" | grep -c "Blocked SPAM")"
echo "VIRUS: $(grep -E "$datePattern" "\$LOG" | grep -cE "Blocked (INFECTED|VIRUS)")"
BASH;
        
        try {
            $output = $this->sshClient->run($script);
            
            if ($output) {
                if (preg_match('/TOTAL:\s*(\d+)/', $output, $m)) $stats['total'] = intval($m[1]);
                if (preg_match('/CLEAN:\s*(\d+)/', $output, $m)) $stats['clean'] = intval($m[1]);
                if (preg_match('/SPAMMY:\s*(\d+)/', $output, $m)) $stats['spammy'] = intval($m[1]);
                if (preg_match('/SPAM:\s*(\d+)/', $output, $m)) $stats['spam'] = intval($m[1]);
                if (preg_match('/VIRUS:\s*(\d+)/', $output, $m)) $stats['virus'] = intval($m[1]);
                
                $stats['blocked'] = $stats['spam'] + $stats['virus'];
            }
        } catch (\Exception $e) {
            Log::error('Amavis stats error: ' . $e->getMessage());
        }
        
        return $stats;
    }
    
    private function getQuarantinedEmailsOptimized($days = 30)
    {
        $quarantined = [];
        
        $script = <<<'BASH'
find /var/lib/amavis/virusmails -type f \( -name "spam-*" -o -name "virus-*" -o -name "banned-*" \) -mtime -30 | head -100 | while read file; do
    echo "===FILE_START==="
    echo "FILENAME: $(basename "$file")"
    echo "FULL_PATH: $file"
    echo "FILE_DATE: $(stat -c %y "$file")"
    echo "FILE_SIZE: $(stat -c %s "$file")"
    echo "===HEADERS_START==="
    if [[ "$file" == *.gz ]]; then
        zcat "$file" | grep -E "^(From|To|Subject|Date|X-Spam-Score|X-Spam-Status|Message-ID):" | head -15
    else
        grep -E "^(From|To|Subject|Date|X-Spam-Score|X-Spam-Status|Message-ID):" "$file" | head -15
    fi
    echo "===FILE_END==="
done
BASH;
        
        try {
            $output = $this->sshClient->run($script);
            
            if ($output) {
                $blocks = explode("===FILE_START===", $output);
                
                foreach ($blocks as $block) {
                    if (empty(trim($block))) continue;
                    
                    $email = [
                        'id' => '',
                        'full_path' => '',
                        'file_date' => '',
                        'file_size' => '',
                        'type' => 'spam',
                        'info' => [
                            'from' => 'Nieznany',
                            'to' => 'Nieznany',
                            'subject' => 'Brak tematu',
                            'date' => 'Nieznana data',
                            'spam_score' => 'N/A',
                            'spam_status' => 'N/A',
                            'message_id' => 'N/A'
                        ]
                    ];
                    
                    if (preg_match('/FILENAME:\s*(.+)/', $block, $matches)) {
                        $email['id'] = trim($matches[1]);
                        if (strpos($email['id'], 'virus-') === 0) $email['type'] = 'virus';
                        elseif (strpos($email['id'], 'banned-') === 0) $email['type'] = 'banned';
                    }
                    
                    if (preg_match('/FULL_PATH:\s*(.+)/', $block, $matches)) {
                        $email['full_path'] = trim($matches[1]);
                    }
                    
                    if (preg_match('/FILE_DATE:\s*(.+)/', $block, $matches)) {
                        $email['file_date'] = trim($matches[1]);
                    }
                    
                    if (preg_match('/FILE_SIZE:\s*(\d+)/', $block, $matches)) {
                        $email['file_size'] = $this->formatSize($matches[1]);
                    }
                    
                    if (preg_match('/===HEADERS_START===\s*(.*?)===FILE_END===/s', $block, $matches)) {
                        $headers = $matches[1];
                        
                        if (preg_match('/^From:\s*(.+)$/mi', $headers, $m)) {
                            $email['info']['from'] = $this->sanitizeUtf8($this->decodeMimeHeader(trim($m[1])));
                        }
                        
                        if (preg_match('/^To:\s*(.+)$/mi', $headers, $m)) {
                            $email['info']['to'] = $this->sanitizeUtf8($this->decodeMimeHeader(trim($m[1])));
                        }
                        
                        if (preg_match('/^Subject:\s*(.+)$/mi', $headers, $m)) {
                            $email['info']['subject'] = $this->sanitizeUtf8($this->decodeMimeHeader(trim($m[1])));
                        }
                        
                        if (preg_match('/^Date:\s*(.+)$/mi', $headers, $m)) {
                            $email['info']['date'] = $this->sanitizeUtf8(trim($m[1]));
                        }
                        
                        if (preg_match('/^X-Spam-Score:\s*(.+)$/mi', $headers, $m)) {
                            $email['info']['spam_score'] = $this->sanitizeUtf8(trim($m[1]));
                        }
                        
                        if (preg_match('/^X-Spam-Status:\s*(.+)$/mi', $headers, $m)) {
                            $email['info']['spam_status'] = $this->sanitizeUtf8(trim($m[1]));
                        }
                        
                        if (preg_match('/^Message-ID:\s*(.+)$/mi', $headers, $m)) {
                            $email['info']['message_id'] = $this->sanitizeUtf8(trim($m[1]));
                        }
                    }
                    
                    if (!empty($email['id'])) {
                        $quarantined[] = $email;
                    }
                }
            }
        } catch (\Exception $e) {
            // Log lub cicha obsługa
        }
        
        usort($quarantined, function($a, $b) {
            return strcmp($b['file_date'], $a['file_date']);
        });
        
        return $quarantined;
    }
    
    public function preview($id)
    {
        Log::info('PREVIEW START', ['id_raw' => $id]);
        
        try {
            $id = basename($id);
            
            $findCmd = "find {$this->quarantineDir} -name " . escapeshellarg($id) . " -type f";
            Log::info('PREVIEW FIND', ['cmd' => $findCmd]);
            
            $fullPath = trim($this->sshClient->run($findCmd));
            Log::info('PREVIEW PATH', ['path' => $fullPath]);
            
            if (empty($fullPath)) {
                return response()->json(['error' => 'Wiadomość nie została znaleziona.'], 404);
            }
            
            if (preg_match('/\.gz$/', $fullPath)) {
                $content = $this->sshClient->run('zcat ' . escapeshellarg($fullPath) . ' 2>/dev/null | head -200');
            } else {
                $content = $this->sshClient->run('cat ' . escapeshellarg($fullPath) . ' 2>/dev/null | head -200');
            }
            
            Log::info('PREVIEW CONTENT LEN', ['len' => strlen($content)]);
            
            if (empty($content)) {
                return response()->json(['error' => 'Nie udało się pobrać treści wiadomości.'], 500);
            }
            
            $parsed = $this->parseEmailContent($content);
            
            Log::info('PREVIEW OK');
            
            return response()->json($parsed);
            
        } catch (\Exception $e) {
            Log::error('PREVIEW EXCEPTION', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Wystąpił błąd podczas przetwarzania wiadomości: ' . $e->getMessage()], 500);
        }
    }
    
    private function parseEmailContent($content)
    {
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        
        $result = [
            'headers' => [],
            'body' => '',
            'html_body' => '',
            'raw' => ''
        ];
        
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);
        
        $parts = preg_split('/\n\n/', $content, 2);
        
        if (count($parts) > 0) {
            $headerLines = explode("\n", $parts[0]);
            $currentHeader = '';
            
            foreach ($headerLines as $line) {
                if (preg_match('/^([A-Za-z-]+):\s*(.*)$/', $line, $matches)) {
                    $currentHeader = $matches[1];
                    $result['headers'][$currentHeader] = trim($matches[2]);
                } elseif (!empty($currentHeader) && preg_match('/^\s+(.+)$/', $line, $matches)) {
                    if (isset($result['headers'][$currentHeader])) {
                        $result['headers'][$currentHeader] .= ' ' . trim($matches[1]);
                    }
                }
            }
            
            $headersToDecode = ['Subject', 'From', 'To', 'Cc'];
            foreach ($headersToDecode as $header) {
                if (isset($result['headers'][$header])) {
                    $result['headers'][$header] = $this->sanitizeUtf8($this->decodeMimeHeader($result['headers'][$header]));
                }
            }
            
            foreach ($result['headers'] as $key => $value) {
                $result['headers'][$key] = $this->sanitizeUtf8($value);
            }
            
            if (count($parts) > 1) {
                $body = $parts[1];
                
                $contentType = $result['headers']['Content-Type'] ?? '';
                
                if (stripos($contentType, 'multipart') !== false) {
                    if (preg_match('/boundary="?([^";\s]+)"?/', $contentType, $matches)) {
                        $boundary = $matches[1];
                        
                        $mimeParts = explode('--' . $boundary, $body);
                        
                        foreach ($mimeParts as $part) {
                            $partEncoding = '';
                            if (preg_match('/Content-Transfer-Encoding:\s*([^\s;]+)/i', $part, $encMatches)) {
                                $partEncoding = strtolower(trim($encMatches[1]));
                            }
                            
                            if (preg_match('/Content-Type:\s*text\/plain/i', $part)) {
                                $partParts = preg_split('/\n\n/', $part, 2);
                                if (count($partParts) > 1) {
                                    $result['body'] = $this->sanitizeUtf8($this->decodeContentTransfer(trim($partParts[1]), $partEncoding));
                                    break;
                                }
                            } elseif (preg_match('/Content-Type:\s*text\/html/i', $part)) {
                                $partParts = preg_split('/\n\n/', $part, 2);
                                if (count($partParts) > 1) {
                                    $result['html_body'] = $this->sanitizeUtf8($this->decodeContentTransfer(trim($partParts[1]), $partEncoding));
                                }
                            }
                        }
                    }
                } else {
                    $transferEncoding = $result['headers']['Content-Transfer-Encoding'] ?? '';
                    $result['body'] = $this->sanitizeUtf8($this->decodeContentTransfer(trim($body), $transferEncoding));
                }
            }
        }
        
        if (empty($result['body']) && empty($result['html_body'])) {
            $result['body'] = $this->sanitizeUtf8(trim($content));
        }
        
        $result['raw'] = $this->sanitizeUtf8(substr($content, 0, 50000));
        
        // Wymuś stringi zamiast null — żeby JS nie wywalał się na .replace()
        $result['body']      = (string) ($result['body'] ?? '');
        $result['html_body'] = (string) ($result['html_body'] ?? '');
        $result['raw']       = (string) ($result['raw'] ?? '');
        
        return $result;
    }
    
    private function decodeContentTransfer($content, $encoding = '')
    {
        $encoding = strtolower(trim($encoding));
        
        if ($encoding === 'base64') {
            $decoded = base64_decode(preg_replace('/\s+/', '', $content));
            if ($decoded !== false) {
                return $decoded;
            }
        } elseif ($encoding === 'quoted-printable') {
            return quoted_printable_decode($content);
        } else {
            if (preg_match('/=\d{2}|=\w{2}/', $content) && preg_match('/=$/', $content)) {
                return quoted_printable_decode($content);
            }
        }
        
        return $content;
    }
    
    private function sanitizeUtf8($text)
    {
        if (empty($text)) {
            return '';
        }
        
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        
        if (mb_strlen($text) > 100000) {
            $text = mb_substr($text, 0, 100000) . '... [treść skrócona]';
        }
        
        return $text;
    }
    
    public function release(Request $request, $id)
    {
        try {
            $id = basename($id);
            
            $fullPath = trim($this->sshClient->run("find {$this->quarantineDir} -name " . escapeshellarg($id) . " -type f"));
            
            if (empty($fullPath)) {
                return redirect()->back()->with('error', "Wiadomość o ID {$id} nie została znaleziona w kwarantannie.");
            }
            
            $relativePath = ltrim(str_replace($this->quarantineDir, '', $fullPath), '/');
            $output = $this->sshClient->run('amavisd-release ' . escapeshellarg($relativePath));
            
            $success =
                stripos($output, 'released') !== false ||
                stripos($output, 'delivered') !== false ||
                stripos($output, 'Ok: queued') !== false ||
                stripos($output, '250 2.0.0') !== false ||
                empty($output);
            
            if ($success) {
                $this->sshClient->run('rm -f ' . escapeshellarg($fullPath));
                return redirect()->back()->with('success', "Wiadomość została uwolniona z kwarantanny i dostarczona do adresata.");
            }
            
            return redirect()->back()->with('error', "Błąd podczas uwalniania wiadomości: {$output}");
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Błąd SSH: " . $e->getMessage());
        }
    }
    
    public function releaseAll()
    {
        try {
            $script = 'cd ' . escapeshellarg($this->quarantineDir) . ' && find . -type f -name "spam-*" -printf "%P\\n" | while read f; do amavisd-release "$f" && rm -f "$f"; done';
            $output = $this->sshClient->run($script);
            
            if (
                stripos($output, 'released') !== false ||
                stripos($output, 'delivered') !== false ||
                stripos($output, 'Ok: queued') !== false ||
                stripos($output, '250 2.0.0') !== false ||
                empty($output)
            ) {
                return redirect()->back()->with('success', 'Wszystkie wiadomości zostały uwolnione z kwarantanny i dostarczone do adresatów.');
            }
            
            return redirect()->back()->with('error', "Błąd podczas uwalniania wiadomości: {$output}");
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Błąd SSH: " . $e->getMessage());
        }
    }
    
    public function deleteQuarantine($id)
    {
        try {
            $id = basename($id);
            
            $fullPath = trim($this->sshClient->run("find {$this->quarantineDir} -name " . escapeshellarg($id) . " -type f"));
            
            if (empty($fullPath)) {
                return redirect()->back()->with('error', "Wiadomość o ID {$id} nie została znaleziona w kwarantannie.");
            }
            
            $output = $this->sshClient->run('rm -f ' . escapeshellarg($fullPath));
            
            if (trim($output) === '') {
                return redirect()->back()->with('success', "Wiadomość została usunięta z kwarantanny.");
            }
            
            return redirect()->back()->with('error', "Błąd podczas usuwania wiadomości: {$output}");
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Błąd SSH: " . $e->getMessage());
        }
    }
    
    private function decodeMimeHeader($header)
    {
        if (empty($header)) {
            return $header;
        }
        
        $header = preg_replace_callback(
            '/=\?([^?]+)\?([QB])\?([^?]+)\?=/i',
            function($matches) {
                $charset = $matches[1];
                $encoding = strtoupper($matches[2]);
                $encoded_text = $matches[3];
                
                if ($encoding === 'Q') {
                    return quoted_printable_decode(str_replace('_', ' ', $encoded_text));
                } else {
                    return base64_decode($encoded_text);
                }
            },
            $header
        );
        
        $header = str_replace('_', ' ', $header);
        
        return $header;
    }
    
    private function formatSize($bytes)
    {
        $bytes = intval($bytes);
        
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
}