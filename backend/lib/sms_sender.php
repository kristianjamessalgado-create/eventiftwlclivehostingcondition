<?php

function eventify_normalize_ph_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === null || $digits === '') {
        return '';
    }
    if (strpos($digits, '63') === 0 && strlen($digits) === 12) {
        return $digits;
    }
    if (strpos($digits, '0') === 0 && strlen($digits) === 11) {
        return '63' . substr($digits, 1);
    }
    if (strlen($digits) === 10 && strpos($digits, '9') === 0) {
        return '63' . $digits;
    }
    return $digits;
}

function eventify_send_sms_semaphore(string $to, string $message): array
{
    if (!defined('EVENTIFY_SMS_PROVIDER') || strtolower((string) EVENTIFY_SMS_PROVIDER) !== 'semaphore') {
        return ['ok' => false, 'error' => 'SMS provider not set to semaphore'];
    }
    if (!defined('SEMAPHORE_API_KEY') || trim((string) SEMAPHORE_API_KEY) === '' || strpos((string) SEMAPHORE_API_KEY, 'PASTE_YOUR_') !== false) {
        return ['ok' => false, 'error' => 'SEMAPHORE_API_KEY is missing'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL extension not available'];
    }

    $sender = defined('SEMAPHORE_SENDER_NAME') ? trim((string) SEMAPHORE_SENDER_NAME) : '';
    $endpoint = 'https://api.semaphore.co/api/v4/messages';
    $post = [
        'apikey' => (string) SEMAPHORE_API_KEY,
        'number' => $to,
        'message' => $message,
    ];
    if ($sender !== '') {
        $post['sendername'] = $sender;
    }

    $ch = curl_init($endpoint);
    if (!$ch) {
        return ['ok' => false, 'error' => 'Failed to initialize cURL'];
    }
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'error' => 'Semaphore request failed: ' . $err];
    }
    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'error' => 'Semaphore HTTP ' . $code . ': ' . $resp];
    }

    return ['ok' => true, 'response' => $resp];
}
