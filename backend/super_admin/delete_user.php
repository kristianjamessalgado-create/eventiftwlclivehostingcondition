<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../backend/lib/activity_logger.php';

function eventify_redirect_superadmin_delete_user(string $type, string $message): void
{
    $openModal = trim((string) ($_POST['open_modal'] ?? 'users'));
    $q = $type . '=' . urlencode($message) . '&open_modal=' . urlencode($openModal);
    header('Location: ' . BASE_URL . '/backend/super_admin/dashboardsuperadmin.php?' . $q);
    exit();
}

function eventify_sa_table_exists(mysqli $conn, string $table): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($table === '') {
        return false;
    }
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    return (bool) ($res && $res->num_rows > 0);
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    eventify_redirect_superadmin_delete_user('error', 'Invalid request.');
}

if (strtoupper(trim((string) ($_POST['confirm_delete'] ?? ''))) !== 'DELETE') {
    eventify_redirect_superadmin_delete_user('error', 'Type DELETE to confirm account removal.');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    eventify_redirect_superadmin_delete_user('error', 'Invalid user ID.');
}

$actorId = (int) ($_SESSION['user_id'] ?? 0);
if ($id === $actorId) {
    eventify_redirect_superadmin_delete_user('error', 'You cannot delete your own account.');
}

$stmt = $conn->prepare('SELECT id, name, email, role, profile_picture FROM users WHERE id = ? LIMIT 1');
if (!$stmt) {
    $conn->close();
    eventify_redirect_superadmin_delete_user('error', 'Database error.');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $conn->close();
    eventify_redirect_superadmin_delete_user('error', 'User not found.');
}

if (($user['role'] ?? '') === 'super_admin') {
    $conn->close();
    eventify_redirect_superadmin_delete_user('error', 'Super admin accounts cannot be deleted.');
}

$eventCount = 0;
$evStmt = $conn->prepare('SELECT COUNT(*) AS c FROM events WHERE organizer_id = ?');
if ($evStmt) {
    $evStmt->bind_param('i', $id);
    $evStmt->execute();
    $eventCount = (int) (($evStmt->get_result()->fetch_assoc()['c'] ?? 0));
    $evStmt->close();
}
if ($eventCount > 0) {
    $conn->close();
    eventify_redirect_superadmin_delete_user(
        'error',
        'This organizer still has ' . $eventCount . ' event(s). Close or delete those events first, or deactivate the account instead.'
    );
}

$label = trim((string) ($user['name'] ?? '')) . ' <' . trim((string) ($user['email'] ?? '')) . '>';
$role = (string) ($user['role'] ?? '');

$conn->begin_transaction();
try {
    if (eventify_sa_table_exists($conn, 'registrations')) {
        $q = $conn->prepare('DELETE FROM registrations WHERE user_id = ?');
        if ($q) {
            $q->bind_param('i', $id);
            $q->execute();
            $q->close();
        }
    }
    if (eventify_sa_table_exists($conn, 'attendance')) {
        $q = $conn->prepare('DELETE FROM attendance WHERE user_id = ?');
        if ($q) {
            $q->bind_param('i', $id);
            $q->execute();
            $q->close();
        }
    }
    if (eventify_sa_table_exists($conn, 'staff_messages')) {
        $q = $conn->prepare('DELETE FROM staff_messages WHERE sender_id = ? OR recipient_id = ?');
        if ($q) {
            $q->bind_param('ii', $id, $id);
            $q->execute();
            $q->close();
        }
    }
    if (eventify_sa_table_exists($conn, 'account_email_otps')) {
        $q = $conn->prepare('DELETE FROM account_email_otps WHERE user_id = ?');
        if ($q) {
            $q->bind_param('i', $id);
            $q->execute();
            $q->close();
        }
    }

    $del = $conn->prepare('DELETE FROM users WHERE id = ? AND role <> ? LIMIT 1');
    $blockedRole = 'super_admin';
    $del->bind_param('is', $id, $blockedRole);
    $del->execute();
    $removed = $del->affected_rows > 0;
    $del->close();
    if (!$removed) {
        throw new RuntimeException('Could not delete this account.');
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    eventify_redirect_superadmin_delete_user('error', $e->getMessage() ?: 'Could not delete this account.');
}

$pic = str_replace('\\', '/', (string) ($user['profile_picture'] ?? ''));
if ($pic !== '' && strpos($pic, 'uploads/') === 0 && strpos($pic, '..') === false) {
    $abs = dirname(__DIR__, 2) . '/' . $pic;
    if (is_file($abs)) {
        @unlink($abs);
    }
}

log_activity(
    $conn,
    $actorId,
    (string) ($_SESSION['role'] ?? ''),
    'user_deleted',
    'user',
    $id,
    'Deleted account ' . $label . ' (role ' . $role . ', ID ' . $id . ')'
);

$conn->close();
eventify_redirect_superadmin_delete_user('success', 'Account deleted: ' . trim((string) ($user['name'] ?? 'User')) . '.');
