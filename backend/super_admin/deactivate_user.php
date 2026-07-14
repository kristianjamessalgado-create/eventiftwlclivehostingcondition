<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../backend/lib/activity_logger.php';

function eventify_redirect_superadmin_user(string $type, string $message): void
{
    $openModal = trim((string)($_POST['open_modal'] ?? 'users'));
    $q = $type . '=' . urlencode($message) . '&open_modal=' . urlencode($openModal);
    header("Location: " . BASE_URL . "/backend/super_admin/dashboardsuperadmin.php?" . $q);
    exit();
}

// Only super admin can deactivate accounts
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: " . BASE_URL . "/views/login.php?error=Access denied");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    eventify_redirect_superadmin_user('error', 'Invalid request.');
}

if (!isset($_POST['id']) || !ctype_digit((string)$_POST['id'])) {
    eventify_redirect_superadmin_user('error', 'Invalid user ID');
}

$id = (int)$_POST['id'];

// Prevent deactivating own account
if ($id === (int)($_SESSION['user_id'] ?? 0)) {
    eventify_redirect_superadmin_user('error', 'You cannot deactivate your own account.');
}

$stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND status <> 'inactive'");
$stmt->bind_param("i", $id);
$stmt->execute();
$changed = $stmt->affected_rows > 0;
$stmt->close();
if (!$changed) {
    $conn->close();
    eventify_redirect_superadmin_user('error', 'No changes made. User may already be inactive or not found.');
}

// Log activity
$actorId   = $_SESSION['user_id'] ?? null;
$actorRole = $_SESSION['role'] ?? null;
$details   = "Deactivated user ID {$id}";
log_activity($conn, $actorId, $actorRole, 'user_deactivated', 'user', (int)$id, $details);

$conn->close();

eventify_redirect_superadmin_user('success', 'User deactivated.');

