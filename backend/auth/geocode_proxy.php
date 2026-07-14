<?php
/**
 * Server-side proxy for OpenStreetMap Nominatim (search + reverse).
 * Browsers cannot send a proper User-Agent; Nominatim requires one.
 * Only organizers may call this endpoint.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'organizer') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$action = $_GET['action'] ?? 'search';
$ua = 'SchoolEventsEventify/1.0 (educational project)';

// Simple throttle (per session)
$now = microtime(true);
$_SESSION['geocode_last'] = $_SESSION['geocode_last'] ?? 0;
if ($now - (float) $_SESSION['geocode_last'] < 1.0) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many requests. Wait a moment.']);
    exit;
}
$_SESSION['geocode_last'] = $now;

function eventify_http_get(string $url, string $userAgent): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_HTTPHEADER     => ['User-Agent: ' . $userAgent, 'Accept-Language: en'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return $body === false ? null : $body;
    }
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 12,
            'header'  => "User-Agent: {$userAgent}\r\nAccept-Language: en\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}

if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2 || strlen($q) > 200) {
        echo json_encode(['ok' => true, 'results' => []]);
        exit;
    }
    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=6&q=' . rawurlencode($q);
    $raw = eventify_http_get($url, $ua);
    if ($raw === null) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Geocoding service unavailable']);
        exit;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        echo json_encode(['ok' => true, 'results' => []]);
        exit;
    }
    $out = [];
    foreach ($data as $row) {
        if (!isset($row['lat'], $row['lon'])) {
            continue;
        }
        $out[] = [
            'lat'   => (float) $row['lat'],
            'lon'   => (float) $row['lon'],
            'label' => (string) ($row['display_name'] ?? ''),
        ];
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
    $url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' . rawurlencode((string) $la) . '&lon=' . rawurlencode((string) $lo);
    $raw = eventify_http_get($url, $ua);
    if ($raw === null) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Reverse geocoding unavailable']);
        exit;
    }
    $row = json_decode($raw, true);
    if (!is_array($row) || !isset($row['display_name'])) {
        echo json_encode(['ok' => true, 'label' => '']);
        exit;
    }
    echo json_encode(['ok' => true, 'label' => (string) $row['display_name']]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Invalid action']);
