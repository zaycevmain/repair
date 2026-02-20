<?php
namespace Repair;

class Mailer {
    /** Кодирование заголовка в UTF-8 по RFC 2047 (чтобы тема и имя отправителя отображались в почтовых клиентах). */
    private static function encodeHeader(string $text): string {
        if (preg_match('/[^\x20-\x7E]/', $text)) {
            return '=?UTF-8?B?' . base64_encode($text) . '?=';
        }
        return $text;
    }

    public static function send(string $to, string $subject, string $bodyHtml): bool {
        $from = trim(setting('mail', 'from_email') ?? '');
        $fromName = trim(setting('mail', 'from_name') ?? '') ?: 'Реестр поломок';
        if (!$from) {
            return false;
        }
        $host = setting('mail', 'smtp_host');
        $fromHeader = $fromName ? (self::encodeHeader($fromName) . " <$from>") : $from;
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . $fromHeader,
        ];
        if ($host) {
            return self::sendSmtp($to, $subject, $bodyHtml, $from, $fromName);
        }
        return @mail($to, self::encodeHeader($subject), $bodyHtml, implode("\r\n", $headers));
    }

    private static function sendSmtp(string $to, string $subject, string $bodyHtml, string $from, string $fromName): bool {
        $host = trim(setting('mail', 'smtp_host') ?? '');
        if (strtolower($host) === 'smtp.google.com') {
            $host = 'smtp.gmail.com';
        }
        $port = (int) (setting('mail', 'smtp_port') ?: 587);
        $user = setting('mail', 'smtp_user');
        $pass = setting('mail', 'smtp_pass');
        $secure = setting('mail', 'smtp_secure') === 'ssl' ? 'ssl' : 'tls';
        $addr = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $errno = $errstr = null;
        $sock = @stream_socket_client($addr, $errno, $errstr, 15);
        if (!$sock) {
            return false;
        }
        stream_set_timeout($sock, 10);
        $read = function () use ($sock) {
            $s = '';
            while ($line = fgets($sock)) {
                $s .= $line;
                if (strlen($line) < 4 || substr($line, 3, 1) === ' ') break;
            }
            return $s;
        };
        $write = function ($msg) use ($sock) {
            fwrite($sock, $msg . "\r\n");
        };
        $read();
        $write("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $read();
        if ($secure === 'tls') {
            $write('STARTTLS');
            $read();
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $write("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $read();
        }
        if ($user && $pass) {
            $write('AUTH LOGIN');
            $read();
            $write(base64_encode($user));
            $read();
            $write(base64_encode($pass));
            $read();
        }
        $write("MAIL FROM:<$from>");
        $read();
        $write("RCPT TO:<$to>");
        $read();
        $write('DATA');
        $read();
        $fromEnc = $fromName ? (self::encodeHeader($fromName) . " <$from>") : $from;
        $subjectEnc = self::encodeHeader($subject);
        $headers = "From: $fromEnc\r\nContent-Type: text/html; charset=utf-8\r\nSubject: $subjectEnc\r\n";
        $write($headers . "\r\n" . $bodyHtml . "\r\n.");
        $read();
        $write('QUIT');
        fclose($sock);
        return true;
    }

    public static function sendToList(string $emailsComma, string $subject, string $bodyHtml): void {
        $list = array_filter(array_map('trim', explode(',', $emailsComma)));
        foreach ($list as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                self::send($email, $subject, $bodyHtml);
            }
        }
    }

    /**
     * Проверка настроек: заполненность и при SMTP — подключение к серверу.
     * Возвращает ['ok' => true, 'message' => '...'] или ['ok' => false, 'error' => '...'].
     */
    public static function testConnection(): array {
        $from = setting('mail', 'from_email');
        if (!$from || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Не указан или неверный email «От кого».'];
        }
        $host = trim(setting('mail', 'smtp_host') ?? '');
        if (!$host) {
            return ['ok' => true, 'message' => 'Используется встроенная функция mail(). Указан отправитель: ' . $from . '.'];
        }
        if (strtolower($host) === 'smtp.google.com') {
            $host = 'smtp.gmail.com';
        }
        $port = (int) (setting('mail', 'smtp_port') ?: 587);
        $secure = setting('mail', 'smtp_secure') === 'ssl' ? 'ssl' : 'tls';
        $addr = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $errno = $errstr = null;
        $sock = @stream_socket_client($addr, $errno, $errstr, 15);
        if (!$sock) {
            $msg = 'Не удалось подключиться к ' . $host . ':' . $port . '. ' . ($errstr ?: 'Код: ' . $errno);
            if (stripos($host, 'gmail') !== false || stripos($errstr ?? '', 'timed out') !== false) {
                $msg .= ' Для Gmail: хост smtp.gmail.com, порт 587 (TLS) или 465 (SSL). Используйте пароль приложения (Google → Аккаунт → Безопасность → Пароли приложений), не обычный пароль.';
            }
            return ['ok' => false, 'error' => $msg];
        }
        stream_set_timeout($sock, 10);
        $read = function () use ($sock) {
            $s = '';
            while ($line = @fgets($sock)) {
                $s .= $line;
                if (strlen($line) < 4 || substr($line, 3, 1) === ' ') break;
            }
            return $s;
        };
        $write = function ($msg) use ($sock) {
            @fwrite($sock, $msg . "\r\n");
        };
        $read();
        $write("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $r = $read();
        if ($secure === 'tls') {
            $write('STARTTLS');
            $r = $read();
            if (strpos($r, '220') === false && strpos($r, 'Ready') === false) {
                fclose($sock);
                return ['ok' => false, 'error' => 'Сервер не поддержал STARTTLS. Ответ: ' . trim($r)];
            }
            if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($sock);
                return ['ok' => false, 'error' => 'Ошибка включения TLS.'];
            }
            $write("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $read();
        }
        $user = setting('mail', 'smtp_user');
        $pass = setting('mail', 'smtp_pass');
        if ($user !== '' || $pass !== '') {
            $write('AUTH LOGIN');
            $read();
            $write(base64_encode($user));
            $r = $read();
            $write(base64_encode($pass));
            $r = $read();
            if (strpos($r, '235') === false && stripos($r, 'success') === false) {
                fclose($sock);
                return ['ok' => false, 'error' => 'Ошибка авторизации SMTP. Проверьте логин и пароль. Ответ: ' . trim(substr(preg_replace('/\s+/', ' ', $r), 0, 120))];
            }
        }
        $write('QUIT');
        fclose($sock);
        return ['ok' => true, 'message' => 'Подключение к SMTP успешно. Хост: ' . $host . ':' . $port . ', отправитель: ' . $from . '.'];
    }
}
