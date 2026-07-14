<?php
require_once __DIR__ . '/../../config/session.php';

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/event_organizer_assign.php';

function eventify_redirect_assign_organizer(string $type, string $message): void
{
    $returnTo = trim((string) ($_POST['return_to'] ?? 'dashboard'));
    $returnPanel = trim((string) ($_POST['return_panel'] ?? ''));
    $openModal = trim((string) ($_POST['open_modal'] ?? 'pending'));
    $role = (string) ($_SESSION['role'] ?? '');
    $param = $type === 'success' ? 'success' : 'error';
    $q = $param . '=' . urlencode($message);
    if ($returnPanel !== '') {
        $q .= '&panel=' . urlencode($returnPanel);
    } elseif ($openModal !== '') {
        $q .= '&open_modal=' . urlencode($openModal);
    }

    if ($returnTo === 'manage_events') {
        header('Location: ' . BASE_URL . '/backend/super_admin/manage_events.php?' . $q);
        exit();
    }
    if ($role === 'super_admin') {
        header('Location: ' . BASE_URL . '/backend/super_admin/dashboardsuperadmin.php?' . $q);
        exit();
    }
    header('Location: ' . BASE_URL . '/backend/admin/dashboard.php?' . $q);
    exit();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true)) {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    eventify_redirect_assign_organizer('error', 'Invalid or expired session. Refresh and try again.');
}

$eventId = (int) ($_POST['event_id'] ?? 0);
$newOrganizerId = (int) ($_POST['organizer_id'] ?? 0);
$actorId = (int) $_SESSION['user_id'];
$actorRole = (string) $_SESSION['role'];

$result = eventify_assign_event_organizer($conn, $eventId, $newOrganizerId, $actorId, $actorRole);
$conn->close();

if (empty($result['ok'])) {
    eventify_redirect_assign_organizer('error', (string) ($result['error'] ?? 'Assignment failed.'));
}

if (!empty($result['noop'])) {
    eventify_redirect_assign_organizer('success', 'That organizer is already assigned to this event.');
}

$title = (string) ($result['event_title'] ?? 'Event');
$newName = (string) ($result['new_organizer_name'] ?? 'organizer');
eventify_redirect_assign_organizer(
    'success',
    'Assigned "' . $title . '" to ' . $newName . '. Send a new OTP to the assigned organizer.'
);
