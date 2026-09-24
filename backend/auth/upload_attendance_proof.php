<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/attendance_proof.php';

$kind = strtolower(trim((string) ($_POST['kind'] ?? '')));
$recordId = (int) ($_POST['record_id'] ?? 0);
$returnTo = strtolower(trim((string) ($_POST['return'] ?? 'dashboard')));
$filter = strtolower(trim((string) ($_POST['filter'] ?? 'all')));
if (!in_array($filter, ['all', 'events', 'activities'], true)) {
    $filter = 'all';
}

$redirect = static function (string $type, string $message) use ($returnTo, $filter): void {
    $safe = rawurlencode($message);
    if ($returnTo === 'history') {
        $qs = 'filter=' . rawurlencode($filter) . '&' . ($type === 'error' ? 'error=' : 'proof=ok&msg=') . $safe;
        header('Location: ' . BASE_URL . '/attendance_history.php?' . $qs);
        exit();
    }
    $qs = 'panel=attendance&' . ($type === 'error' ? 'error=' : 'msg=') . $safe;
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?' . $qs);
    exit();
};

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . rawurlencode('Please sign in to add a photo.'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    $redirect('error', 'Invalid request. Please try again.');
}

if (!in_array($kind, ['event', 'activity'], true) || $recordId < 1) {
    $redirect('error', 'That check-in record was not found.');
}

$userId = (int) $_SESSION['user_id'];
eventify_attendance_proof_ensure_columns($conn);

$table = $kind === 'activity' ? 'event_day_session_attendance' : 'registrations';
if (!eventify_attendance_proof_column_exists($conn, $table)) {
    $redirect('error', 'Photo storage is not ready yet. Please try again in a moment.');
}

if ($kind === 'event') {
    $st = $conn->prepare(
        "SELECT id, proof_photo FROM registrations
         WHERE id = ? AND user_id = ? AND status = 'present' AND time_in IS NOT NULL
         LIMIT 1"
    );
} else {
    $st = $conn->prepare(
        'SELECT id, proof_photo FROM event_day_session_attendance
         WHERE id = ? AND user_id = ?
         LIMIT 1'
    );
}

if (!$st) {
    $redirect('error', 'Could not save the photo. Please try again.');
}
$st->bind_param('ii', $recordId, $userId);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) {
    $redirect('error', 'That check-in record was not found.');
}

$stored = eventify_attendance_proof_store_upload($_FILES['photo'] ?? [], $userId, $kind, $recordId);
if (!$stored['ok']) {
    $redirect('error', $stored['error']);
}

$newPath = $stored['path'];
$up = $kind === 'activity'
    ? $conn->prepare('UPDATE event_day_session_attendance SET proof_photo = ? WHERE id = ? AND user_id = ? LIMIT 1')
    : $conn->prepare('UPDATE registrations SET proof_photo = ? WHERE id = ? AND user_id = ? LIMIT 1');
if (!$up) {
    eventify_attendance_proof_delete_file($newPath);
    $redirect('error', 'Could not save the photo. Please try again.');
}
$up->bind_param('sii', $newPath, $recordId, $userId);
$ok = $up->execute();
$up->close();

if (!$ok) {
    eventify_attendance_proof_delete_file($newPath);
    $redirect('error', 'Could not save the photo. Please try again.');
}

$oldPath = (string) ($row['proof_photo'] ?? '');
if ($oldPath !== '' && $oldPath !== $newPath) {
    eventify_attendance_proof_delete_file($oldPath);
}

$redirect('ok', empty($oldPath) ? 'Photo added to this check-in.' : 'Photo updated for this check-in.');
