<?php
/**
 * Send RSVP event-start reminders (push + in-app).
 *
 * GoDaddy cron (every 5 minutes):
 *   curl -s "https://eventifywlc.com/backend/cron/send_event_reminders.php?key=YOUR_EVENTIFY_CRON_KEY"
 *
 * CLI: php backend/cron/send_event_reminders.php
 */

declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../lib/event_reminders.php';

$cronKey = defined('EVENTIFY_CRON_KEY') ? trim((string) EVENTIFY_CRON_KEY) : '';
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if ($cronKey === '') {
        http_response_code(503);
        echo json_encode([
            'ok' => false,
            'error' => 'EVENTIFY_CRON_KEY is not set in .env',
        ]);
        exit;
    }
    $provided = trim((string) ($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? ''));
    if ($provided === '' || !hash_equals($cronKey, $provided)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        exit;
    }
}

$result = eventify_run_event_reminders($conn);

$stampFile = __DIR__ . '/.reminder_last_run';
@file_put_contents($stampFile, (string) time());

if ($isCli) {
    echo 'Event reminders — checked: ' . $result['checked']
        . ', sent: ' . $result['sent']
        . ', skipped: ' . $result['skipped'] . PHP_EOL;
    if ($result['errors'] !== []) {
        echo 'Errors: ' . implode('; ', $result['errors']) . PHP_EOL;
    }
    exit($result['ok'] ? 0 : 1);
}

echo json_encode($result);
