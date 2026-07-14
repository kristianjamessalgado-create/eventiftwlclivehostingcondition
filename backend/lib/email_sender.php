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

function eventify_send_email_via_mail(string $to, string $subject, string $body): array
{
    $fromName = defined('EVENTIFY_SMTP_FROM_NAME') && EVENTIFY_SMTP_FROM_NAME !== ''
        ? (string) EVENTIFY_SMTP_FROM_NAME
        : 'EVENTIFY';
    $fromEmail = defined('EVENTIFY_SMTP_FROM_EMAIL') && EVENTIFY_SMTP_FROM_EMAIL !== ''
        ? (string) EVENTIFY_SMTP_FROM_EMAIL
        : 'noreply@localhost';
    $from = $fromName . ' <' . $fromEmail . '>';
    $headers = 'From: ' . $from . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';

    if (@mail($to, $subject, $body, $headers)) {
        return ['ok' => true, 'via' => 'mail'];
    }

    return ['ok' => false, 'error' => 'PHP mail() failed'];
}

function eventify_send_email(string $to, string $subject, string $body): array
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient email'];
    }

    $forceMail = defined('EVENTIFY_SMTP_FORCE_MAIL') && EVENTIFY_SMTP_FORCE_MAIL;
    if ($forceMail || !eventify_email_enabled()) {
        $result = eventify_send_email_via_mail($to, $subject, $body);
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

    $result = eventify_send_email_smtp($to, $subject, $body);
    if (!empty($result['ok'])) {
        return $result;
    }

    $fallbackMail = !defined('EVENTIFY_SMTP_FALLBACK_MAIL')
        || EVENTIFY_SMTP_FALLBACK_MAIL;
    $connectFailed = isset($result['error'])
        && stripos((string) $result['error'], 'SMTP connect failed') !== false;

    if ($fallbackMail && $connectFailed) {
        $mailResult = eventify_send_email_via_mail($to, $subject, $body);
        if (!empty($mailResult['ok'])) {
            return $mailResult;
        }
    }

    return $result;
}

function eventify_send_email_smtp(string $to, string $subject, string $body): array
{
    $host = (string) EVENTIFY_SMTP_HOST;
    $port = (int) EVENTIFY_SMTP_PORT;
    $user = defined('EVENTIFY_SMTP_USERNAME') ? (string) EVENTIFY_SMTP_USERNAME : '';
    $pass = defined('EVENTIFY_SMTP_PASSWORD') ? (string) EVENTIFY_SMTP_PASSWORD : '';
    $from = defined('EVENTIFY_SMTP_FROM_EMAIL') && EVENTIFY_SMTP_FROM_EMAIL !== ''
        ? (string) EVENTIFY_SMTP_FROM_EMAIL
        : ($user !== '' ? $user : 'noreply@localhost');
    $fromName = defined('EVENTIFY_SMTP_FROM_NAME') && EVENTIFY_SMTP_FROM_NAME !== ''
        ? (string) EVENTIFY_SMTP_FROM_NAME
        : 'EVENTIFY';
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
    $fp = @stream_socket_client($transport . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
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
        $write($fp, 'AUTH LOGIN');
        $resp = $read($fp);
        if (!$expect($resp, [334])) {
            fclose($fp);
            return ['ok' => false, 'error' => 'AUTH LOGIN failed: ' . trim($resp)];
        }
        $write($fp, base64_encode($user));
        $resp = $read($fp);
        if (!$expect($resp, [334])) {
            fclose($fp);
            return ['ok' => false, 'error' => 'SMTP username rejected: ' . trim($resp)];
        }
        $write($fp, base64_encode($pass));
        $resp = $read($fp);
        if (!$expect($resp, [235])) {
            fclose($fp);
            return ['ok' => false, 'error' => 'SMTP password rejected: ' . trim($resp)];
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

    $headers = [];
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'From: ' . $fromName . ' <' . $from . '>';
    $headers[] = 'To: <' . $to . '>';
    $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';

    $message = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", $body) . "\r\n.";
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
