<?php
/**
 * Server-side proxy for OpenStreetMap Nominatim (search + reverse).
 * Browsers cannot send a proper User-Agent; Nominatim requires one.
 * Organizers, admins, and super admins may call this endpoint (venue picker).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

$geocodeRole = (string) ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || !in_array($geocodeRole, ['organizer', 'admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$action = $_GET['action'] ?? 'search';
$ua = 'SchoolEventsEventify/1.0 (EVENTIFY campus events; contact: support@eventifywlc.com)';

// Simple throttle (per session) — Nominatim usage policy
$now = microtime(true);
$_SESSION['geocode_last'] = $_SESSION['geocode_last'] ?? 0;
if ($now - (float) $_SESSION['geocode_last'] < 1.05) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Please wait a second, then search again.']);
    exit;
}
$_SESSION['geocode_last'] = $now;

/**
 * @return array{body:?string,http:int,error:string}
 */
function eventify_http_get(string $url, string $userAgent): array
{
    $headers = [
        'User-Agent: ' . $userAgent,
        'Accept: application/json',
        'Accept-Language: en',
    ];

    if (function_exists('curl_init')) {
        foreach ([true, false] as $verifyPeer) {
            $ch = curl_init($url);
            if ($ch === false) {
                break;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_SSL_VERIFYPEER => $verifyPeer,
                CURLOPT_SSL_VERIFYHOST => $verifyPeer ? 2 : 0,
            ]);
            $body = curl_exec($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = (string) curl_error($ch);
            curl_close($ch);

            if ($body !== false && $body !== '') {
                return ['body' => $body, 'http' => $http, 'error' => ''];
            }

            // Retry without peer verify only when CA/cert bundle is broken (common on local XAMPP).
            $certBroken = stripos($err, 'certificate') !== false
                || stripos($err, 'cacert') !== false
                || stripos($err, 'SSL') !== false;
            if ($verifyPeer && $certBroken) {
                continue;
            }
            return ['body' => null, 'http' => $http, 'error' => $err !== '' ? $err : 'Empty response from geocoder'];
        }
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header'  => implode("\r\n", $headers) . "\r\n",
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $http = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $hline) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $hline, $m)) {
                $http = (int) $m[1];
                break;
            }
        }
    }
    if ($body === false || $body === '') {
        return ['body' => null, 'http' => $http, 'error' => 'Could not reach geocoding service'];
    }
    return ['body' => $body, 'http' => $http, 'error' => ''];
}

if ($action === 'search') {
    $q = trim((string) ($_GET['q'] ?? ''));
    if (strlen($q) < 2 || strlen($q) > 200) {
        echo json_encode(['ok' => true, 'results' => []]);
        exit;
    }

    // Prefer Philippines results for campus venues (WLC, Ormoc, etc.).
    $url = 'https://nominatim.openstreetmap.org/search?format=json&addressdetails=0&limit=8'
        . '&countrycodes=ph'
        . '&q=' . rawurlencode($q);

    $res = eventify_http_get($url, $ua);
    if ($res['body'] === null) {
        http_response_code(502);
        echo json_encode([
            'ok' => false,
            'error' => 'Map search is temporarily unavailable. Tap the map or use GPS instead.',
            'detail' => $res['error'],
        ]);
        exit;
    }

    if ($res['http'] === 429) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Too many searches. Wait a moment and try again.']);
        exit;
    }

    if ($res['http'] >= 400) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Map search could not complete. Tap the map or use GPS instead.']);
        exit;
    }

    $data = json_decode($res['body'], true);
    if (!is_array($data)) {
        echo json_encode(['ok' => true, 'results' => []]);
        exit;
    }

    $out = [];
    foreach ($data as $row) {
        if (!is_array($row) || !isset($row['lat'], $row['lon'])) {
            continue;
        }
        $out[] = [
            'lat'   => (float) $row['lat'],
            'lon'   => (float) $row['lon'],
            'label' => (string) ($row['display_name'] ?? ''),
        ];
    }

    // If PH filter returned nothing, retry worldwide once (still useful for odd spellings).
    if ($out === [] && stripos($q, 'http') === false) {
        $urlWorld = 'https://nominatim.openstreetmap.org/search?format=json&limit=6&q=' . rawurlencode($q);
        usleep(1100000); // honor 1 req/sec
        $_SESSION['geocode_last'] = microtime(true);
        $res2 = eventify_http_get($urlWorld, $ua);
        if ($res2['body'] !== null && $res2['http'] < 400) {
            $data2 = json_decode($res2['body'], true);
            if (is_array($data2)) {
                foreach ($data2 as $row) {
                    if (!is_array($row) || !isset($row['lat'], $row['lon'])) {
                        continue;
                    }
                    $out[] = [
                        'lat'   => (float) $row['lat'],
                        'lon'   => (float) $row['lon'],
                        'label' => (string) ($row['display_name'] ?? ''),
                    ];
                }
            }
        }
    }

    echo json_encode(['ok' => true, 'results' => $out]);
    exit;
}

if ($action === 'reverse') {
    $lat = $_GET['lat'] ?? '';
    $lon = $_GET['lon'] ?? '';
    if (!is_numeric($lat) || !is_numeric($lon)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid coordinates']);
        exit;
    }
    $la = (float) $lat;
    $lo = (float) $lon;
    if ($la < -90 || $la > 90 || $lo < -180 || $lo > 180) {
        echo json_encode(['ok' => false, 'error' => 'Invalid coordinates']);
        exit;
    }
    $url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' . rawurlencode((string) $la)
        . '&lon=' . rawurlencode((string) $lo);
    $res = eventify_http_get($url, $ua);
    if ($res['body'] === null) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Could not look up that map point.']);
        exit;
    }
    $row = json_decode($res['body'], true);
    if (!is_array($row) || !isset($row['display_name'])) {
        echo json_encode(['ok' => true, 'label' => '']);
        exit;
    }
    echo json_encode(['ok' => true, 'label' => (string) $row['display_name']]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Invalid action']);
