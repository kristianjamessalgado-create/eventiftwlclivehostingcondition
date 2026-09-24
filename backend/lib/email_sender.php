<?php

function eventify_smtp_relay_mode(): bool
{
    if (defined('EVENTIFY_SMTP_NO_AUTH') && EVENTIFY_SMTP_NO_AUTH) {
        return true;
    }
    $user = defined('EVENTIFY_SMTP_USERNAME') ? (string) EVENTIFY_SMTP_USERNAME : '';
    $pass = defined('EVENTIFY_SMTP_PASSWORD') ? (string) EVENTIFY_SMTP_PASSWORD : '';
    $port = defined('EVENTIFY_SMTP_PORT') ? (int) EVENTIFY_SMTP_PORT : 0;

    return $user === '' && $pass === '' && $port === 25;
}

function eventify_email_enabled(): bool
{
    $hasHost = defined('EVENTIFY_SMTP_HOST')
        && EVENTIFY_SMTP_HOST !== '';
    $hasPort = defined('EVENTIFY_SMTP_PORT')
        && (int) EVENTIFY_SMTP_PORT > 0;

    if (!$hasHost || !$hasPort) {
        return false;
    }

    if (eventify_smtp_relay_mode()) {
        return true;
    }

    return defined('EVENTIFY_SMTP_USERNAME')
        && EVENTIFY_SMTP_USERNAME !== ''
        && defined('EVENTIFY_SMTP_PASSWORD')
        && EVENTIFY_SMTP_PASSWORD !== '';
}

function eventify_smtp_ehlo_host(): string
{
    if (defined('EVENTIFY_SMTP_EHLO_HOST') && EVENTIFY_SMTP_EHLO_HOST !== '') {
        return (string) EVENTIFY_SMTP_EHLO_HOST;
    }

    $from = defined('EVENTIFY_SMTP_FROM_EMAIL') ? (string) EVENTIFY_SMTP_FROM_EMAIL : '';
    if ($from !== '' && strpos($from, '@') !== false) {
        return substr($from, strrpos($from, '@') + 1);
    }

    return 'localhost';
}

function eventify_smtp_disable_tls(int $port): bool
{
    if (defined('EVENTIFY_SMTP_DISABLE_TLS') && EVENTIFY_SMTP_DISABLE_TLS) {
        return true;
    }

    return $port === 25;
}

function eventify_email_header_safe(string $value): string
{
    return str_replace(["\r", "\n", "\0"], '', $value);
}

function eventify_email_from_identity(): array
{
    $fromName = defined('EVENTIFY_SMTP_FROM_NAME') && EVENTIFY_SMTP_FROM_NAME !== ''
        ? eventify_email_header_safe((string) EVENTIFY_SMTP_FROM_NAME)
        : 'EVENTIFY';
    $fromEmail = defined('EVENTIFY_SMTP_FROM_EMAIL') && EVENTIFY_SMTP_FROM_EMAIL !== ''
        ? eventify_email_header_safe((string) EVENTIFY_SMTP_FROM_EMAIL)
        : '';
    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $user = defined('EVENTIFY_SMTP_USERNAME') ? (string) EVENTIFY_SMTP_USERNAME : '';
        $fromEmail = filter_var($user, FILTER_VALIDATE_EMAIL) ? $user : 'noreply@localhost';
    }

    return [$fromName, $fromEmail];
}

function eventify_email_domain(string $fromEmail): string
{
    if (strpos($fromEmail, '@') !== false) {
        $domain = strtolower(substr($fromEmail, strrpos($fromEmail, '@') + 1));
        if ($domain !== '' && $domain !== 'localhost') {
            return $domain;
        }
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    if ($host !== '' && $host !== 'localhost' && $host !== '127.0.0.1') {
        return $host;
    }

    return 'eventifywlc.com';
}

function eventify_email_simple_html(string $plain): string
{
    $escaped = nl2br(htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '</head><body style="margin:0;padding:24px;background:#f4f6f8;color:#111;'
        . 'font-family:Arial,Helvetica,sans-serif;line-height:1.5;">'
        . '<div style="max-width:520px;margin:0 auto;background:#fff;padding:24px;'
        . 'border:1px solid #e5e7eb;border-radius:8px;">'
        . $escaped
        . '</div></body></html>';
}

/**
 * @return array{from_name:string,from_email:string,subject_encoded:string,header_lines:array<int,string>,mime_body:string}
 */
function eventify_compose_email(string $to, string $subject, string $plain, string $html = ''): array
{
    [$fromName, $fromEmail] = eventify_email_from_identity();
    $to = eventify_email_header_safe($to);
    $subject = eventify_email_header_safe($subject);
    $domain = eventify_email_domain($fromEmail);
    $boundary = 'ev_' . bin2hex(random_bytes(12));
    $messageId = '<' . bin2hex(random_bytes(8)) . '.' . time() . '@' . $domain . '>';
    $fromHeader = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>';
    $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    if (trim($html) === '') {
        $html = eventify_email_simple_html($plain);
    }

    $normalize = static function (string $text): string {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return str_replace("\n", "\r\n", $text);
    };

    $headerLines = [
        'Date: ' . date('r'),
        'From: ' . $fromHeader,
        'Reply-To: ' . $fromEmail,
        'Message-ID: ' . $messageId,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'Auto-Submitted: auto-generated',
        'X-Auto-Response-Suppress: All',
    ];

    $mimeBody = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $normalize($plain) . "\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $normalize($html) . "\r\n"
        . '--' . $boundary . "--\r\n";

    return [
        'from_name' => $fromName,
        'from_email' => $fromEmail,
        'subject_encoded' => $subjectEncoded,
        'header_lines' => $headerLines,
        'mime_body' => $mimeBody,
    ];
}

function eventify_smtp_dot_stuff(string $data): string
{
    $data = str_replace(["\r\n.", "\n."], ["\r\n..", "\n.."], $data);
    if ($data !== '' && $data[0] === '.') {
        $data = '.' . $data;
    }
    return $data;
}

function eventify_smtp_connect_timeout(): int
{
    $host = strtolower(defined('EVENTIFY_SMTP_HOST') ? (string) EVENTIFY_SMTP_HOST : '');
    if (strpos($host, 'gmail.com') !== false || strpos($host, 'googlemail.com') !== false) {
        $httpHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $httpHost = preg_replace('/:\d+$/', '', $httpHost) ?? $httpHost;
        if ($httpHost !== '' && strpos($httpHost, 'localhost') === false && strpos($httpHost, '127.0.0.1') === false) {
            // Gmail SMTP is blocked on GoDaddy shared hosting; fail faster than the old 20s hang.
            return 8;
        }
    }

    return 20;
}

function eventify_send_email_via_mail(string $to, string $subject, string $body, string $htmlBody = ''): array
{
    $composed = eventify_compose_email($to, $subject, $body, $htmlBody);
    $headers = implode("\r\n", $composed['header_lines']);
    $params = '';
    if (stripos(PHP_OS, 'WIN') === false && function_exists('escapeshellarg')) {
        $params = '-f' . $composed['from_email'];
    }

    $ok = $params !== ''
        ? @mail($to, $composed['subject_encoded'], $composed['mime_body'], $headers, $params)
        : @mail($to, $composed['subject_encoded'], $composed['mime_body'], $headers);

    if ($ok) {
        return ['ok' => true, 'via' => 'mail'];
    }

    return ['ok' => false, 'error' => 'PHP mail() failed'];
}

function eventify_send_email(string $to, string $subject, string $body, string $htmlBody = ''): array
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient email'];
    }

    $forceMail = defined('EVENTIFY_SMTP_FORCE_MAIL') && EVENTIFY_SMTP_FORCE_MAIL;
    if ($forceMail || !eventify_email_enabled()) {
        $result = eventify_send_email_via_mail($to, $subject, $body, $htmlBody);
        if ($result['ok']) {
            return $result;
        }
        if (!eventify_email_enabled()) {
            return [
                'ok' => false,
                'error' => 'SMTP not configured and PHP mail() failed. Copy config/smtp.local.php.example to config/smtp.local.php',
            ];
        }
    }

    $result = eventify_send_email_smtp($to, $subject, $body, $htmlBody);
    if (!empty($result['ok'])) {
        return $result;
    }

    $fallbackMail = defined('EVENTIFY_SMTP_FALLBACK_MAIL') && EVENTIFY_SMTP_FALLBACK_MAIL;
    $connectFailed = isset($result['error'])
        && stripos((string) $result['error'], 'SMTP connect failed') !== false;

    if ($fallbackMail && $connectFailed) {
        $mailResult = eventify_send_email_via_mail($to, $subject, $body, $htmlBody);
        if (!empty($mailResult['ok'])) {
            return $mailResult;
        }
    }

    return $result;
}

function eventify_send_email_smtp(string $to, string $subject, string $body, string $htmlBody = ''): array
{
    $host = (string) EVENTIFY_SMTP_HOST;
    $port = (int) EVENTIFY_SMTP_PORT;
    $user = defined('EVENTIFY_SMTP_USERNAME') ? (string) EVENTIFY_SMTP_USERNAME : '';
    $pass = defined('EVENTIFY_SMTP_PASSWORD') ? (string) EVENTIFY_SMTP_PASSWORD : '';
    $composed = eventify_compose_email($to, $subject, $body, $htmlBody);
    $from = $composed['from_email'];
    $relayMode = eventify_smtp_relay_mode();
    $ehloHost = eventify_smtp_ehlo_host();

    $allowInsecure = defined('EVENTIFY_SMTP_ALLOW_INSECURE_TLS') && EVENTIFY_SMTP_ALLOW_INSECURE_TLS;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => !$allowInsecure,
            'verify_peer_name' => !$allowInsecure,
            'allow_self_signed' => $allowInsecure,
        ],
    ]);

    $useImplicitTls = $port === 465;
    $transport = $useImplicitTls ? 'ssl://' . $host : $host;
    $timeout = eventify_smtp_connect_timeout();
    $fp = @stream_socket_client($transport . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        return ['ok' => false, 'error' => 'SMTP connect failed: ' . $errstr];
    }
    stream_set_timeout($fp, 15);

    $read = static function ($socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (preg_match('/^\d{3}\s/', $line)) {
                break;
            }
        }
        return $data;
    };
    $write = static function ($socket, string $cmd): void {
        fwrite($socket, $cmd . "\r\n");
    };
    $expect = static function (string $resp, array $okCodes): bool {
        foreach ($okCodes as $code) {
            if (strpos($resp, (string) $code) === 0) {
                return true;
            }
        }
        return false;
    };

    $resp = $read($fp);
    if (!$expect($resp, [220])) {
        fclose($fp);
        return ['ok' => false, 'error' => 'SMTP greeting failed: ' . trim($resp)];
    }

    $write($fp, 'EHLO ' . $ehloHost);
    $resp = $read($fp);
    if (!$expect($resp, [250])) {
        fclose($fp);
        return ['ok' => false, 'error' => 'EHLO failed: ' . trim($resp)];
    }

    if (!$useImplicitTls && !eventify_smtp_disable_tls($port)) {
        $write($fp, 'STARTTLS');
        $resp = $read($fp);
        if (!$expect($resp, [220])) {
            fclose($fp);
            return ['ok' => false, 'error' => 'STARTTLS failed: ' . trim($resp)];
        }
        $cryptoMethods = 0;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $cryptoMethods |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $cryptoMethods |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) {
            $cryptoMethods |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT')) {
            $cryptoMethods |= STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT;
        }
        if ($cryptoMethods === 0 && defined('STREAM_CRYPTO_METHOD_TLS_CLIENT')) {
            $cryptoMethods = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        }

        if (!@stream_socket_enable_crypto($fp, true, $cryptoMethods)) {
            fclose($fp);
            return ['ok' => false, 'error' => 'TLS handshake failed (try SMTP port 465 or disable TLS for relay port 25)'];
        }
        $write($fp, 'EHLO ' . $ehloHost);
        $resp = $read($fp);
        if (!$expect($resp, [250])) {
            fclose($fp);
            return ['ok' => false, 'error' => 'EHLO after TLS failed: ' . trim($resp)];
        }
    }

    if (!$relayMode) {
        $authOk = false;
        $lastAuthError = '';

        $write($fp, 'AUTH LOGIN');
        $resp = $read($fp);
        if ($expect($resp, [334])) {
            $write($fp, base64_encode($user));
            $resp = $read($fp);
            if ($expect($resp, [334])) {
                $write($fp, base64_encode($pass));
                $resp = $read($fp);
                if ($expect($resp, [235])) {
                    $authOk = true;
                } else {
                    $lastAuthError = trim($resp);
                }
            } else {
                $lastAuthError = trim($resp);
            }
        } else {
            $lastAuthError = trim($resp);
        }

        if (!$authOk) {
            $write($fp, 'AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass));
            $resp = $read($fp);
            if ($expect($resp, [235])) {
                $authOk = true;
            } else {
                $lastAuthError = trim($resp);
            }
        }

        if (!$authOk) {
            fclose($fp);
            return ['ok' => false, 'error' => 'SMTP password rejected: ' . $lastAuthError];
        }
    }

    $write($fp, 'MAIL FROM:<' . $from . '>');
    $resp = $read($fp);
    if (!$expect($resp, [250])) {
        fclose($fp);
        return ['ok' => false, 'error' => 'MAIL FROM failed: ' . trim($resp)];
    }
    $write($fp, 'RCPT TO:<' . $to . '>');
    $resp = $read($fp);
    if (!$expect($resp, [250, 251])) {
        fclose($fp);
        return ['ok' => false, 'error' => 'RCPT TO failed: ' . trim($resp)];
    }
    $write($fp, 'DATA');
    $resp = $read($fp);
    if (!$expect($resp, [354])) {
        fclose($fp);
        return ['ok' => false, 'error' => 'DATA command failed: ' . trim($resp)];
    }

    $smtpHeaders = $composed['header_lines'];
    array_splice($smtpHeaders, 3, 0, [
        'To: <' . eventify_email_header_safe($to) . '>',
        'Subject: ' . $composed['subject_encoded'],
    ]);

    $message = implode("\r\n", $smtpHeaders) . "\r\n\r\n"
        . eventify_smtp_dot_stuff($composed['mime_body'])
        . "\r\n.";
    fwrite($fp, $message . "\r\n");
    $resp = $read($fp);
    if (!$expect($resp, [250])) {
        fclose($fp);
        return ['ok' => false, 'error' => 'Message send failed: ' . trim($resp)];
    }

    $write($fp, 'QUIT');
    fclose($fp);
    return ['ok' => true, 'via' => 'smtp'];
}
