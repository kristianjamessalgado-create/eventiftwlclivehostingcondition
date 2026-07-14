<?php
session_start();

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../backend/lib/activity_logger.php';

function eventify_redirect_superadmin_unlock(string $type, string $message): void
{
    $openModal = trim((string)($_POST['open_modal'] ?? 'users'));
    $q = $type . '=' . urlencode($message) . '&open_modal=' . urlencode($openModal);
    header("Location: " . BASE_URL . "/backend/super_admin/dashboardsuperadmin.php?" . $q);
    exit();
}

// Only super admin can unlock accounts
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: " . BASE_URL . "/views/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    eventify_redirect_superadmin_unlock('error', 'Invalid request.');
}

// Direct unlock is intentionally disabled.
// Locked accounts must complete email OTP reactivation and forced password change.
$id = $_POST['id'] ?? '';
if (ctype_digit((string)$id)) {
    $actorId   = $_SESSION['user_id'] ?? null;
    $actorRole = $_SESSION['role'] ?? null;
    $details   = "Blocked direct unlock attempt for user ID {$id}; OTP reactivation required";
    log_activity($conn, $actorId, $actorRole, 'user_unlock_blocked', 'user', (int)$id, $details);
}
$conn->close();
eventify_redirect_superadmin_unlock('error', 'Direct unlock is disabled. Use Reactivate to send OTP, then the user must verify OTP and change password.');
