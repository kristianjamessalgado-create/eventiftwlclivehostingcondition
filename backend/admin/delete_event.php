<?php
/**
 * Admin / Super Admin: permanently delete an event (with confirm + cleanup).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/admin_event_delete.php';

function eventify_redirect_delete_event(string $type, string $message): void
{
    $role = (string) ($_SESSION['role'] ?? '');
    $returnTo = (string) ($_POST['return_to'] ?? 'dashboard');
    $panel = trim((string) ($_POST['return_panel'] ?? 'events'));
    $q = $type . '=' . urlencode($message);

    if ($role === 'super_admin') {
        if ($returnTo === 'manage_events') {
            header('Location: ' . BASE_URL . '/backend/super_admin/manage_events.php?' . $q);
            exit;
        }
        header('Location: ' . BASE_URL . '/backend/super_admin/dashboardsuperadmin.php?' . $q . '&open_modal=events');
        exit;
    }

    header('Location: ' . BASE_URL . '/backend/admin/dashboard.php?' . $q . '&panel=' . urlencode($panel !== '' ? $panel : 'events'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    eventify_redirect_delete_event('error', 'Invalid request.');
}

if (!csrf_validate()) {
    eventify_redirect_delete_event('error', 'Invalid security token. Please try again.');
}

$role = (string) ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'super_admin'], true)) {
    eventify_redirect_delete_event('error', 'Access denied.');
}

$eventId = (int) ($_POST['event_id'] ?? 0);
$confirm = trim((string) ($_POST['confirm_delete'] ?? ''));
$forceRevenue = !empty($_POST['force_revenue_delete']);

if ($eventId < 1) {
    eventify_redirect_delete_event('error', 'Invalid event.');
}

// Require explicit confirm token from the modal.
if ($confirm !== 'DELETE') {
    eventify_redirect_delete_event('error', 'Delete cancelled. Type DELETE to confirm.');
}

$result = eventify_admin_delete_event(
    $conn,
    $eventId,
    (int) $_SESSION['user_id'],
    $role,
    $forceRevenue
);

if (empty($result['ok'])) {
    eventify_redirect_delete_event('error', (string) ($result['error'] ?? 'Could not delete event.'));
}

$title = (string) ($result['title'] ?? 'Event');
eventify_redirect_delete_event('success', 'Deleted "' . $title . '".');
