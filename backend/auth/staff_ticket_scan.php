<?php
/**
 * Staff ticket scanner API (admin, super admin, or the event's assigned organizer).
 *
 * POST action=lookup : check a scanned ticket QR / typed ticket code
 * POST action=admit  : mark a valid ticket as used (entry granted)
 *
 * Returns JSON: { ok, state, message, ticket? }
 * state: valid | used | cancelled | wrong_event | no_access | not_found | error
 */
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/event_ticketing.php';
require_once __DIR__ . '/../lib/activity_logger.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function eventify_staff_scan_reply(array $data, int $http = 200): void
{
    http_response_code($http);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

$role = (string) ($_SESSION['role'] ?? '');
$staffId = (int) ($_SESSION['user_id'] ?? 0);
if ($staffId < 1 || !in_array($role, ['organizer', 'admin', 'super_admin'], true)) {
    eventify_staff_scan_reply(['ok' => false, 'state' => 'error', 'message' => 'Please sign in with an organizer or admin account.'], 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_validate()) {
    eventify_staff_scan_reply(['ok' => false, 'state' => 'error', 'message' => 'Session expired. Reload the scanner page and try again.'], 400);
}
if (!eventify_ticketing_ensure_schema($conn)) {
    eventify_staff_scan_reply(['ok' => false, 'state' => 'error', 'message' => 'Ticketing is not set up on this server.'], 500);
}

$action = ($_POST['action'] ?? '') === 'admit' ? 'admit' : 'lookup';
$raw = trim((string) ($_POST['code'] ?? ''));
$scopeEventId = (int) ($_POST['event_id'] ?? 0);

// Accept the ticket QR link (…/ticket_checkin.php?tk=TOKEN), a bare token, or a ticket code (TKT-XXXX).
$token = '';
$ticketCode = '';
if ($raw !== '') {
    $query = (string) (parse_url($raw, PHP_URL_QUERY) ?? '');
    if ($query !== '') {
        parse_str($query, $qs);
        $token = trim((string) ($qs['tk'] ?? ''));
        $ticketCode = trim((string) ($qs['code'] ?? '')); // ticket_pass.php?code=TKT-…
    }
    if ($token === '' && $ticketCode === '') {
        if (preg_match('/^TKT-[A-Z0-9]+$/i', $raw)) {
            $ticketCode = strtoupper($raw);
        } elseif (preg_match('/^[A-Za-z0-9]{16,128}$/', $raw)) {
            $token = $raw;
        }
    }
}
if ($token === '' && $ticketCode === '') {
    eventify_staff_scan_reply([
        'ok' => false,
        'state' => 'not_found',
        'message' => 'This is not an EVENTIFY ticket QR. Ask the student to open their ticket pass (My tickets) and scan that QR.',
    ]);
}

$sql = 'SELECT t.id, t.user_id, t.event_id, t.ticket_code, t.status, t.used_at,
               tt.name AS type_name,
               e.title AS event_title, e.status AS event_status, e.date AS event_date, e.organizer_id,
               u.name AS holder_name, u.user_id AS holder_student_id
        FROM event_tickets t
        JOIN events e ON e.id = t.event_id
        JOIN event_ticket_types tt ON tt.id = t.ticket_type_id
        JOIN users u ON u.id = t.user_id
        WHERE ' . ($token !== '' ? 't.checkin_token = ?' : 't.ticket_code = ?') . ' LIMIT 1';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    eventify_staff_scan_reply(['ok' => false, 'state' => 'error', 'message' => 'Could not check the ticket. Try again.'], 500);
}
$lookupValue = $token !== '' ? $token : $ticketCode;
$stmt->bind_param('s', $lookupValue);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    eventify_staff_scan_reply([
        'ok' => false,
        'state' => 'not_found',
        'message' => 'FAKE OR UNKNOWN TICKET — this code does not match any ticket in EVENTIFY. Do not admit.',
    ]);
}

$isAdmin = in_array($role, ['admin', 'super_admin'], true);
if (!$isAdmin && (int) $ticket['organizer_id'] !== $staffId) {
    eventify_staff_scan_reply([
        'ok' => false,
        'state' => 'no_access',
        'message' => 'This ticket is for "' . $ticket['event_title'] . '", which is not assigned to you. Only its assigned organizer or an admin can verify it.',
    ]);
}

$info = [
    'code' => (string) $ticket['ticket_code'],
    'type' => (string) $ticket['type_name'],
    'holder' => (string) $ticket['holder_name'],
    'student_id' => (string) ($ticket['holder_student_id'] ?? ''),
    'event' => (string) $ticket['event_title'],
    'event_id' => (int) $ticket['event_id'],
    'event_date' => (string) ($ticket['event_date'] ?? ''),
    'used_at' => $ticket['used_at'] ? date('M j, Y g:i A', strtotime((string) $ticket['used_at'])) : null,
];

if ($scopeEventId > 0 && (int) $ticket['event_id'] !== $scopeEventId) {
    eventify_staff_scan_reply([
        'ok' => false,
        'state' => 'wrong_event',
        'message' => 'Real ticket, but for a DIFFERENT event: "' . $ticket['event_title'] . '". Do not admit to this event.',
        'ticket' => $info,
    ]);
}

$status = (string) $ticket['status'];
if ($status === 'cancelled') {
    eventify_staff_scan_reply([
        'ok' => false,
        'state' => 'cancelled',
        'message' => 'This ticket was CANCELLED. Do not admit.',
        'ticket' => $info,
    ]);
}
if ($status === 'used') {
    eventify_staff_scan_reply([
        'ok' => false,
        'state' => 'used',
        'message' => 'ALREADY USED' . ($info['used_at'] ? ' on ' . $info['used_at'] : '') . '. This ticket was scanned before — do not admit again.',
        'ticket' => $info,
    ]);
}

if ($action === 'lookup') {
    eventify_staff_scan_reply([
        'ok' => true,
        'state' => 'valid',
        'message' => 'VALID TICKET — check the student\'s ID matches the holder below, then tap Admit.',
        'ticket' => $info,
    ]);
}

// action === 'admit'
if (strtolower((string) $ticket['event_status']) !== 'active') {
    eventify_staff_scan_reply([
        'ok' => false,
        'state' => 'error',
        'message' => 'This event is not open for entry (status: ' . $ticket['event_status'] . ').',
        'ticket' => $info,
    ]);
}
$mark = eventify_mark_ticket_used($conn, (int) $ticket['id'], (int) $ticket['user_id']);
if (empty($mark['ok'])) {
    eventify_staff_scan_reply([
        'ok' => false,
        'state' => 'used',
        'message' => 'This ticket was just used by another scan. Do not admit again.',
        'ticket' => $info,
    ]);
}

log_activity(
    $conn,
    $staffId,
    $role,
    'ticket_admitted',
    'event_ticket',
    (int) $ticket['id'],
    'Admitted ' . $info['holder'] . ' with ticket ' . $info['code'] . ' to "' . $info['event'] . '"'
);

$info['used_at'] = date('M j, Y g:i A');
eventify_staff_scan_reply([
    'ok' => true,
    'state' => 'admitted',
    'message' => 'ADMITTED — ' . $info['holder'] . ' is checked in.',
    'ticket' => $info,
]);
