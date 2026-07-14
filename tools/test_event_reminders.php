<?php
/**
 * Dry-run event reminders (does not send). Usage:
 *   php tools/test_event_reminders.php
 *   php tools/test_event_reminders.php --send
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../backend/lib/event_reminders.php';

$send = in_array('--send', $argv ?? [], true);
$now = new DateTimeImmutable('now', eventify_calendar_app_timezone());

echo 'Now: ' . $now->format('Y-m-d H:i:s T') . PHP_EOL;

$res = $conn->query("
    SELECT r.user_id, r.event_id, e.title, e.status, e.date, e.start_time
    FROM registrations r
    INNER JOIN users u ON u.id = r.user_id AND u.role = 'student'
    INNER JOIN events e ON e.id = r.event_id AND e.status = 'active'
    LIMIT 20
");
if (!$res) {
    fwrite(STDERR, "Query failed\n");
    exit(1);
}

$rows = $res->fetch_all(MYSQLI_ASSOC);
if ($rows === []) {
    echo "No active RSVP registrations found.\n";
    exit(0);
}

$eventsById = [];
foreach ($rows as $row) {
    $eid = (int) ($row['event_id'] ?? 0);
    if ($eid > 0 && !isset($eventsById[$eid])) {
        $eventsById[$eid] = $row;
    }
}
$eventList = array_values($eventsById);
eventify_events_attach_schedule_dates($conn, $eventList);
$eventsById = [];
foreach ($eventList as $ev) {
    $eventsById[(int) ($ev['id'] ?? 0)] = $ev;
}

$userIds = array_column($rows, 'user_id');
$settingsMap = eventify_load_student_reminder_settings_map($conn, $userIds);
$offsets = eventify_reminder_timing_offsets();
$grace = EVENTIFY_REMINDER_GRACE_MINUTES * 60;

foreach ($rows as $row) {
    $userId = (int) ($row['user_id'] ?? 0);
    $eventId = (int) ($row['event_id'] ?? 0);
    $event = $eventsById[$eventId] ?? null;
    if (!$event) {
        continue;
    }
    $settings = $settingsMap[$userId] ?? ['event_reminders' => 1, 'reminder_timing' => '1_day'];
    echo PHP_EOL . "User {$userId} — {$row['title']} (pref: {$settings['reminder_timing']})" . PHP_EOL;

    foreach (eventify_event_upcoming_occurrence_starts($event, $now) as $occ) {
        $startsAt = $occ['starts_at'];
        echo '  Day ' . $occ['schedule_date'] . ' starts ' . $startsAt->format('Y-m-d H:i') . PHP_EOL;
        foreach ($offsets as $timing => $offset) {
            if (!eventify_student_wants_event_reminder($settings, $timing)) {
                continue;
            }
            $remindAt = $startsAt->getTimestamp() - $offset;
            $inWindow = $now->getTimestamp() >= $remindAt && $now->getTimestamp() < ($remindAt + $grace);
            $sent = eventify_reminder_was_sent($conn, $userId, $eventId, $occ['schedule_date'], $timing);
            echo '    ' . $timing . ': remind at ' . date('Y-m-d H:i', $remindAt)
                . ($inWindow ? ' [DUE NOW]' : '')
                . ($sent ? ' [already sent]' : '') . PHP_EOL;
        }
    }
}

if ($send) {
    echo PHP_EOL . 'Running send...' . PHP_EOL;
    $result = eventify_run_event_reminders($conn, $now);
    print_r($result);
}
