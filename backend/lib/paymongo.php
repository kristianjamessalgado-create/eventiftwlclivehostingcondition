<?php

/**
 * Minimal PayMongo helper for GCash payments via hosted Checkout Sessions.
 *
 * Flow (no webhook needed for the demo):
 *   1. eventify_paymongo_create_checkout_session() -> redirect the buyer to checkout_url
 *   2. PayMongo redirects back to success_url after the GCash authorization
 *   3. eventify_paymongo_retrieve_checkout_session() -> confirm it was actually paid
 *
 * Real keys live in config/paymongo.local.php (gitignored). Use sk_test_/pk_test_
 * for development; live keys require a verified merchant account + public HTTPS host.
 */

require_once __DIR__ . '/../../config/config.php';

const EVENTIFY_PAYMONGO_API_BASE = 'https://api.paymongo.com/v1';

function eventify_paymongo_secret_key(): string
{
    return defined('EVENTIFY_PAYMONGO_SECRET_KEY') ? trim((string) EVENTIFY_PAYMONGO_SECRET_KEY) : '';
}

function eventify_paymongo_public_key(): string
{
    return defined('EVENTIFY_PAYMONGO_PUBLIC_KEY') ? trim((string) EVENTIFY_PAYMONGO_PUBLIC_KEY) : '';
}

/** True only when a usable secret key is configured (placeholder values don't count). */
function eventify_paymongo_enabled(): bool
{
    $key = eventify_paymongo_secret_key();
    if ($key === '' || stripos($key, 'sk_') !== 0) {
        return false;
    }
    // Reject the shipped placeholder so the option stays hidden until real keys are set.
    if (stripos($key, 'xxxx') !== false) {
        return false;
    }
    return true;
}

function eventify_paymongo_is_test_key(): bool
{
    return stripos(eventify_paymongo_secret_key(), 'sk_test_') === 0;
}

/** Minimum peso amount enforced by EVENTIFY before starting GCash checkout (0 = disabled). */
function eventify_paymongo_gcash_min_peso(): float
{
    if (!defined('EVENTIFY_PAYMONGO_GCASH_MIN_PESO')) {
        return 0.0;
    }
    return max(0.0, (float) EVENTIFY_PAYMONGO_GCASH_MIN_PESO);
}

function eventify_paymongo_gcash_amount_allowed(float $amountPeso): bool
{
    if ($amountPeso < 1.0) {
        return false;
    }
    $min = eventify_paymongo_gcash_min_peso();
    return $min <= 0 || $amountPeso >= $min;
}

/**
 * Low-level authenticated request to the PayMongo REST API.
 *
 * @return array{ok: bool, status: int, body: array<string,mixed>, error?: string}
 */
function eventify_paymongo_request(string $method, string $path, ?array $payload = null): array
{
    $key = eventify_paymongo_secret_key();
    if ($key === '') {
        return ['ok' => false, 'status' => 0, 'body' => [], 'error' => 'PayMongo is not configured.'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'body' => [], 'error' => 'cURL is not available on this server.'];
    }

    $url = EVENTIFY_PAYMONGO_API_BASE . $path;
    $ch = curl_init($url);
    $headers = [
        'Authorization: Basic ' . base64_encode($key . ':'),
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'status' => 0, 'body' => [], 'error' => 'Network error contacting PayMongo: ' . $err];
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $body = json_decode((string) $raw, true);
    if (!is_array($body)) {
        $body = [];
    }
    $ok = $status >= 200 && $status < 300;
    $result = ['ok' => $ok, 'status' => $status, 'body' => $body];
    if (!$ok) {
        $result['error'] = eventify_paymongo_extract_error($body) ?: ('PayMongo error (HTTP ' . $status . ').');
    }
    return $result;
}

function eventify_paymongo_extract_error(array $body): string
{
    if (!empty($body['errors']) && is_array($body['errors'])) {
        $messages = [];
        foreach ($body['errors'] as $e) {
            $detail = trim((string) ($e['detail'] ?? ''));
            if ($detail !== '') {
                $messages[] = $detail;
            }
        }
        if ($messages !== []) {
            return implode(' ', $messages);
        }
    }
    return '';
}

/**
 * Create a GCash Checkout Session for an order total.
 *
 * @param array{
 *   amount: float,            // in pesos
 *   description: string,
 *   line_item_name: string,
 *   reference_number: string,
 *   success_url: string,
 *   cancel_url: string,
 *   buyer_name?: string,
 *   buyer_email?: string
 * } $params
 * @return array{ok: bool, id?: string, checkout_url?: string, error?: string}
 */
function eventify_paymongo_create_checkout_session(array $params): array
{
    $amountCentavos = (int) round(((float) ($params['amount'] ?? 0)) * 100);
    if ($amountCentavos < 100) {
        return ['ok' => false, 'error' => 'Amount must be at least ₱1.00.'];
    }
    $minPeso = eventify_paymongo_gcash_min_peso();
    if ($minPeso > 0 && $amountCentavos < (int) round($minPeso * 100)) {
        return [
            'ok' => false,
            'error' => 'The amount is below the ₱' . number_format($minPeso, 2) . ' online payment minimum.',
        ];
    }

    $attributes = [
        'send_email_receipt' => false,
        'show_description' => true,
        'show_line_items' => true,
        'description' => (string) ($params['description'] ?? 'EVENTIFY tickets'),
        'line_items' => [[
            'currency' => 'PHP',
            'amount' => $amountCentavos,
            'name' => (string) ($params['line_item_name'] ?? 'Event tickets'),
            'quantity' => 1,
        ]],
        'payment_method_types' => ['gcash'],
        'success_url' => (string) ($params['success_url'] ?? ''),
        'cancel_url' => (string) ($params['cancel_url'] ?? ''),
        'reference_number' => (string) ($params['reference_number'] ?? ''),
    ];

    $billing = [];
    if (!empty($params['buyer_name'])) {
        $billing['name'] = (string) $params['buyer_name'];
    }
    if (!empty($params['buyer_email'])) {
        $billing['email'] = (string) $params['buyer_email'];
    }
    if ($billing !== []) {
        $attributes['billing'] = $billing;
    }

    $res = eventify_paymongo_request('POST', '/checkout_sessions', ['data' => ['attributes' => $attributes]]);
    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error'] ?? 'Could not start the PayMongo checkout.'];
    }

    $data = $res['body']['data'] ?? [];
    $id = (string) ($data['id'] ?? '');
    $checkoutUrl = (string) ($data['attributes']['checkout_url'] ?? '');
    if ($id === '' || $checkoutUrl === '') {
        return ['ok' => false, 'error' => 'PayMongo did not return a checkout URL.'];
    }
    return ['ok' => true, 'id' => $id, 'checkout_url' => $checkoutUrl];
}

/**
 * Retrieve a Checkout Session and determine whether it has actually been paid.
 *
 * @return array{ok: bool, paid: bool, payment_id?: string, status?: string, error?: string}
 */
function eventify_paymongo_retrieve_checkout_session(string $sessionId): array
{
    $sessionId = trim($sessionId);
    if ($sessionId === '') {
        return ['ok' => false, 'paid' => false, 'error' => 'Missing checkout session id.'];
    }
    $res = eventify_paymongo_request('GET', '/checkout_sessions/' . rawurlencode($sessionId));
    if (!$res['ok']) {
        return ['ok' => false, 'paid' => false, 'error' => $res['error'] ?? 'Could not verify payment.'];
    }

    $attr = $res['body']['data']['attributes'] ?? [];
    $paid = false;
    $paymentId = '';
    $status = '';

    // Preferred signal: a settled payment attached to the session.
    $payments = $attr['payments'] ?? [];
    if (is_array($payments)) {
        foreach ($payments as $p) {
            $pStatus = (string) ($p['attributes']['status'] ?? '');
            if ($pStatus === 'paid') {
                $paid = true;
                $paymentId = (string) ($p['id'] ?? '');
                $status = $pStatus;
                break;
            }
            if ($status === '') {
                $status = $pStatus;
            }
        }
    }

    // Fallback: the payment intent reports success.
    if (!$paid) {
        $intentStatus = (string) ($attr['payment_intent']['attributes']['status'] ?? '');
        if (in_array($intentStatus, ['succeeded', 'paid'], true)) {
            $paid = true;
            $status = $intentStatus;
        } elseif ($status === '') {
            $status = $intentStatus;
        }
    }

    return ['ok' => true, 'paid' => $paid, 'payment_id' => $paymentId, 'status' => $status];
}

/** Build an absolute URL (scheme+host) for the given app-relative path. */
function eventify_paymongo_absolute_url(string $relativePath): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = strtolower(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]);
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = defined('BASE_URL') ? (string) BASE_URL : '';
    if ($relativePath !== '' && $relativePath[0] !== '/') {
        $relativePath = '/' . $relativePath;
    }
    return $scheme . '://' . $host . $base . $relativePath;
}
