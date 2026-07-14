<?php
require_once __DIR__ . '/env.php';
eventify_load_env_file();

if (!defined('EVENTIFY_APP_TIMEZONE')) {
    define('EVENTIFY_APP_TIMEZONE', eventify_env('EVENTIFY_APP_TIMEZONE', 'Asia/Manila') ?? 'Asia/Manila');
}
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set(EVENTIFY_APP_TIMEZONE);
}

$host = eventify_env('DB_HOST', 'localhost') ?? 'localhost';
$user = eventify_env('DB_USER', 'root') ?? 'root';
$pass = eventify_env('DB_PASS', '') ?? '';
$db   = eventify_env('DB_NAME', 'school_events_db') ?? 'school_events_db';

$dbLocalPath = __DIR__ . '/db.local.php';
if (is_readable($dbLocalPath)) {
    require $dbLocalPath;
}

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Keep SQL NOW() aligned with EVENTIFY_APP_TIMEZONE (e.g. auto-close after end time).
try {
    $tz = defined('EVENTIFY_APP_TIMEZONE') ? (string) EVENTIFY_APP_TIMEZONE : 'Asia/Manila';
    $dtz = new DateTimeZone($tz);
    $nowLocal = new DateTimeImmutable('now', $dtz);
    $offset = $nowLocal->format('P');
    if ($offset !== false && $offset !== '') {
        $conn->query("SET time_zone = '" . $conn->real_escape_string($offset) . "'");
    }
} catch (Throwable $e) {
    // Non-fatal — PHP-side maintenance still runs.
}
