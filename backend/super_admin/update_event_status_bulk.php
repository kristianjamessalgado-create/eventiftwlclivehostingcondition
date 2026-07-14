<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../backend/lib/activity_logger.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true)) {
    header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Access denied"));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    header("Location: " . BASE_URL . "/backend/admin/dashboard.php?success=" . urlencode("Invalid request."));
    exit();
}

$action = $_POST['action'] ?? '';
$ids = $_POST['event_ids'] ?? [];
$reason = trim($_POST['reject_reason'] ?? '');

if (!in_array($action, ['approve', 'reject'], true) || !is_array($ids) || empty($ids)) {
    $to = ($_SESSION['role'] ?? '') === 'super_admin' ? '/backend/super_admin/dashboardsuperadmin.php' : '/backend/admin/dashboard.php';
    header("Location: " . BASE_URL . $to . "?success=" . urlencode("No valid events selected."));
    exit();
}

if ($action === 'approve') {
    $to = ($_SESSION['role'] ?? '') === 'super_admin' ? '/backend/super_admin/dashboardsuperadmin.php' : '/backend/admin/dashboard.php';
    header("Location: " . BASE_URL . $to . "?success=" . urlencode("Bulk approve is disabled. Use OTP per event for approval."));
    exit();
}

$cleanIds = [];
foreach ($ids as $id) {
    $v = (int) $id;
    if ($v > 0) {
        $cleanIds[$v] = $v;
    }
}
$cleanIds = array_values($cleanIds);
if (empty($cleanIds)) {
    $to = ($_SESSION['role'] ?? '') === 'super_admin' ? '/backend/super_admin/dashboardsuperadmin.php' : '/backend/admin/dashboard.php';
    header("Location: " . BASE_URL . $to . "?success=" . urlencode("No valid events selected."));
    exit();
}

$newStatus = $action === 'approve' ? 'active' : 'rejected';
$updatedCount = 0;
$actorId = (int) ($_SESSION['user_id'] ?? 0);
$actorRole = $_SESSION['role'] ?? '';

foreach ($cleanIds as $eventId) {
    if ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE events SET status = ?, reject_reason = ? WHERE id = ? AND status = 'pending'");
        if (!$stmt) continue;
        $stmt->bind_param("ssi", $newStatus, $reason, $eventId);
    } else {
        $stmt = $conn->prepare("UPDATE events SET status = ?, reject_reason = NULL WHERE id = ? AND status = 'pending'");
        if (!$stmt) continue;
        $stmt->bind_param("si", $newStatus, $eventId);
    }
    $stmt->execute();
    $changed = $stmt->affected_rows > 0;
    $stmt->close();
    if (!$changed) {
        continue;
    }
    $updatedCount++;

    // Activity log
    $actionKey = $action === 'approve' ? 'event_approved_bulk' : 'event_rejected_bulk';
    log_activity($conn, $actorId, $actorRole, $actionKey, 'event', $eventId, ucfirst($action) . 'd in bulk');

    // Organizer notification
    $evStmt = $conn->prepare("SELECT title, organizer_id, reject_reason FROM events WHERE id = ?");
    if ($evStmt) {
        $evStmt->bind_param("i", $eventId);
        $evStmt->execute();
        $ev = $evStmt->get_result()->fetch_assoc();
        $evStmt->close();
        if ($ev) {
            $organizerId = (int) ($ev['organizer_id'] ?? 0);
            if ($organizerId > 0) {
                $title = (string) ($ev['title'] ?? 'Event');
                $notifType = $action === 'approve' ? 'event_approved' : 'event_rejected';
                $notifTitle = $action === 'approve' ? 'Event approved' : 'Event rejected';
                $notifMsg = $action === 'approve'
                    ? ('Your event "' . $title . '" has been approved and is now visible to students.')
                    : ('Your event "' . $title . '" was rejected.' . (!empty($ev['reject_reason']) ? ' Reason: ' . $ev['reject_reason'] : ''));
                $ins = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, ?, ?, ?, ?)");
                if ($ins) {
                    $ins->bind_param("isssi", $organizerId, $notifType, $notifTitle, $notifMsg, $eventId);
                    $ins->execute();
                    $ins->close();
                }
            }
        }
    }
}

$conn->close();
$msg = $updatedCount . " event(s) " . ($action === 'approve' ? 'approved' : 'rejected') . ".";
$to = ($_SESSION['role'] ?? '') === 'super_admin' ? '/backend/super_admin/dashboardsuperadmin.php' : '/backend/admin/dashboard.php';
$returnPanel = trim((string) ($_POST['return_panel'] ?? 'pending'));
$q = 'success=' . urlencode($msg);
if ($returnPanel !== '' && $to === '/backend/admin/dashboard.php') {
    $q .= '&panel=' . urlencode($returnPanel);
}
header('Location: ' . BASE_URL . $to . '?' . $q);
exit();
