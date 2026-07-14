<?php
require_once __DIR__ . '/../../config/session.php';

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/admin_pending_event_edit.php';

function eventify_redirect_edit_pending(string $type, string $message): void
{
    $returnPanel = trim((string) ($_POST['return_panel'] ?? 'pending'));
    $openModal = trim((string) ($_POST['open_modal'] ?? 'pending'));
    $param = $type === 'success' ? 'success' : 'error';
    $q = $param . '=' . urlencode($message);
    if ($returnPanel !== '') {
        $q .= '&panel=' . urlencode($returnPanel);
    } elseif ($openModal !== '') {
        $q .= '&open_modal=' . urlencode($openModal);
    }
    header('Location: ' . BASE_URL . '/backend/admin/dashboard.php?' . $q);
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    eventify_redirect_edit_pending('error', 'Invalid or expired session. Refresh and try again.');
}

$eventId = (int) ($_POST['event_id'] ?? 0);
$actorId = (int) $_SESSION['user_id'];
$actorRole = (string) $_SESSION['role'];

$fields = [
    'title' => $_POST['title'] ?? '',
    'description' => $_POST['description'] ?? '',
    'date' => $_POST['date'] ?? '',
    'location' => $_POST['location'] ?? '',
    'department' => $_POST['department'] ?? 'ALL',
];

$result = eventify_admin_edit_pending_event($conn, $eventId, $fields, $actorId, $actorRole);
$conn->close();

if (empty($result['ok'])) {
    eventify_redirect_edit_pending('error', (string) ($result['error'] ?? 'Update failed.'));
}

if (!empty($result['unchanged'])) {
    eventify_redirect_edit_pending('success', 'No changes were made.');
}

$title = (string) ($result['event_title'] ?? 'Event');
eventify_redirect_edit_pending('success', 'Updated pending event "' . $title . '".');
