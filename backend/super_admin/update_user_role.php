<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../backend/lib/activity_logger.php';
require_once __DIR__ . '/../lib/multimedia_moderator.php';

function eventify_redirect_superadmin_role(string $type, string $message): void
{
    $openModal = trim((string)($_POST['open_modal'] ?? 'users'));
    $q = $type . '=' . urlencode($message) . '&open_modal=' . urlencode($openModal);
    header("Location: " . BASE_URL . "/backend/super_admin/dashboardsuperadmin.php?" . $q);
    exit();
}

// Only super admin can change roles
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: " . BASE_URL . "/views/login.php?error=Access denied");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    eventify_redirect_superadmin_role('error', 'Invalid request method.');
}

if (!csrf_validate()) {
    eventify_redirect_superadmin_role('error', 'Invalid request. Please try again.');
}

$userId   = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$newRole  = $_POST['new_role'] ?? '';

$allowedRoles = ['super_admin', 'admin', 'organizer', 'multimedia', 'student'];

if ($userId <= 0 || !in_array($newRole, $allowedRoles, true)) {
    eventify_redirect_superadmin_role('error', 'Invalid role change request.');
}

// Prevent changing own role to avoid locking yourself out accidentally
if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
    eventify_redirect_superadmin_role('error', 'You cannot change your own role.');
}

// Fetch current role
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($currentRole);
$stmt->fetch();
$stmt->close();

if (!$currentRole) {
    $conn->close();
    eventify_redirect_superadmin_role('error', 'User not found.');
}

if ($currentRole === $newRole) {
    $conn->close();
    eventify_redirect_superadmin_role('error', "Role is already set to {$newRole}.");
}

$stmtUpdate = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
if (!$stmtUpdate) {
    $conn->close();
    eventify_redirect_superadmin_role('error', 'Failed to prepare role update.');
}

$stmtUpdate->bind_param("si", $newRole, $userId);
if ($stmtUpdate->execute()) {
    $stmtUpdate->close();

    if ($newRole !== 'multimedia') {
        eventify_users_ensure_multimedia_moderator_column($conn);
        $clr = $conn->prepare('UPDATE users SET is_multimedia_moderator = 0 WHERE id = ?');
        if ($clr) {
            $clr->bind_param('i', $userId);
            $clr->execute();
            $clr->close();
        }
    }

    // Log activity
    $actorId   = $_SESSION['user_id'] ?? null;
    $actorRole = $_SESSION['role'] ?? null;
    $details   = "Changed user ID {$userId} role from {$currentRole} to {$newRole}";
    log_activity($conn, $actorId, $actorRole, 'user_role_changed', 'user', $userId, $details);

    $conn->close();
    eventify_redirect_superadmin_role('success', 'User role updated.');
}

$stmtUpdate->close();
$conn->close();

eventify_redirect_superadmin_role('error', 'Failed to update user role.');

