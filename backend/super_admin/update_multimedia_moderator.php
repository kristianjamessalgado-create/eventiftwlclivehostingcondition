<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../backend/lib/activity_logger.php';
require_once __DIR__ . '/../lib/multimedia_moderator.php';

function eventify_redirect_superadmin_moderator(string $type, string $message): void
{
    $openModal = trim((string) ($_POST['open_modal'] ?? 'users'));
    header('Location: ' . BASE_URL . '/backend/super_admin/dashboardsuperadmin.php?' . $type . '=' . urlencode($message) . '&open_modal=' . urlencode($openModal));
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: ' . BASE_URL . '/views/login.php?error=Access denied');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    eventify_redirect_superadmin_moderator('error', 'Invalid request.');
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$setModerator = isset($_POST['is_moderator']) ? (int) $_POST['is_moderator'] : 0;
$setModerator = $setModerator === 1 ? 1 : 0;

if ($userId < 1) {
    eventify_redirect_superadmin_moderator('error', 'Invalid user.');
}

if (!eventify_users_ensure_multimedia_moderator_column($conn)) {
    eventify_redirect_superadmin_moderator('error', 'Could not update database. Run migrations/multimedia_moderator.sql.');
}

$stmt = $conn->prepare("SELECT role, name FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    eventify_redirect_superadmin_moderator('error', 'User not found.');
}

if (($user['role'] ?? '') !== 'multimedia') {
    eventify_redirect_superadmin_moderator('error', 'Only multimedia accounts can be photo moderators.');
}

$upd = $conn->prepare('UPDATE users SET is_multimedia_moderator = ? WHERE id = ?');
if (!$upd) {
    eventify_redirect_superadmin_moderator('error', 'Failed to update moderator status.');
}
$upd->bind_param('ii', $setModerator, $userId);
$ok = $upd->execute();
$upd->close();

if (!$ok) {
    eventify_redirect_superadmin_moderator('error', 'Failed to update moderator status.');
}

$actorId = (int) ($_SESSION['user_id'] ?? 0);
$actorRole = $_SESSION['role'] ?? null;
$who = trim((string) ($user['name'] ?? 'User'));
$details = $setModerator
    ? "Set {$who} (ID {$userId}) as multimedia photo moderator"
    : "Removed multimedia photo moderator from {$who} (ID {$userId})";
log_activity($conn, $actorId, $actorRole, 'multimedia_moderator_updated', 'user', $userId, $details);
$conn->close();

$msg = $setModerator
    ? 'Multimedia photo moderator enabled for ' . $who . '.'
    : 'Multimedia photo moderator removed from ' . $who . '.';
eventify_redirect_superadmin_moderator('success', $msg);
