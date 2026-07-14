<?php
/**
 * Download .ics calendar for activities hub (student personal schedule or single activity).
 */
session_start();

include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
require_once __DIR__ . '/backend/lib/event_day_sessions.php';
require_once __DIR__ . '/backend/lib/event_calendar.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Access denied');
}

$userId = (int) $_SESSION['user_id'];
$role = (string) ($_SESSION['role'] ?? '');
$eventId = (int) ($_GET['id'] ?? 0);
$activityId = (int) ($_GET['activity'] ?? 0);
$scope = trim((string) ($_GET['scope'] ?? ''));

if ($eventId < 1) {
    http_response_code(400);
    exit('Invalid event');
}

$studentDept = null;
if ($role === 'student') {
    $du = $conn->prepare('SELECT department FROM users WHERE id = ? LIMIT 1');
    if ($du) {
        $du->bind_param('i', $userId);
        $du->execute();
        $dr = $du->get_result()->fetch_assoc();
        $du->close();
        $studentDept = $dr['department'] ?? null;
    }
}

$event = eventify_load_event_for_activities_hub($conn, $eventId);
if (!$event) {
    http_response_code(404);
    exit('Event not found');
}

if (!eventify_user_can_view_event_activities($conn, $event, $userId, $role, $studentDept)) {
    http_response_code(403);
    exit('Access denied');
}

$viewerId = in_array($role, ['student', 'organizer'], true) ? $userId : null;
$allSessions = eventify_load_event_day_sessions($conn, $eventId, null, $viewerId);
$eventTitle = trim((string) ($event['title'] ?? 'Event'));

if ($activityId > 0) {
    $sessions = [];
    foreach ($allSessions as $s) {
        if ((int) ($s['id'] ?? 0) === $activityId) {
            $sessions = [$s];
            break;
        }
    }
    if ($sessions === []) {
        http_response_code(404);
        exit('Activity not found');
    }
    $filename = 'activity-' . $activityId . '.ics';
    $calName = ($sessions[0]['title'] ?? 'Activity') . ' — ' . $eventTitle;
} elseif ($scope === 'mine' && $role === 'student') {
    $sessions = array_values(array_filter($allSessions, static function ($s) {
        return !empty($s['user_rsvped']);
    }));
    if ($sessions === []) {
        http_response_code(404);
        exit('No RSVP\'d activities to export');
    }
    $filename = 'my-schedule-event-' . $eventId . '.ics';
    $calName = 'My schedule — ' . $eventTitle;
} else {
    http_response_code(400);
    exit('Invalid export scope');
}

$ics = eventify_build_ics_for_sessions($sessions, $calName, $eventTitle);
$conn->close();

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9._-]+/', '_', $filename) . '"');
header('Cache-Control: no-store');
echo $ics;
