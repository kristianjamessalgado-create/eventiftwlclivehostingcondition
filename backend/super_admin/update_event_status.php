<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../backend/lib/activity_logger.php';
include __DIR__ . '/../../backend/lib/event_approval_otp.php';
include __DIR__ . '/../../backend/lib/sms_sender.php';
include __DIR__ . '/../../backend/lib/email_sender.php';

function eventify_redirect_event_status(string $type, string $message): void
{
    $baseUrl = BASE_URL;
    $role = $_SESSION['role'] ?? '';
    $returnTo = (string)($_POST['return_to'] ?? '');
    $returnPanel = trim((string)($_POST['return_panel'] ?? ''));
    $openModal = trim((string)($_POST['open_modal'] ?? ''));
    $q = $type . '=' . urlencode($message);
    if ($returnPanel !== '') {
        $q .= '&panel=' . urlencode($returnPanel);
    } elseif ($openModal !== '') {
        $q .= '&open_modal=' . urlencode($openModal);
    }

    if ($role === 'super_admin') {
        header("Location: {$baseUrl}/backend/super_admin/dashboardsuperadmin.php?{$q}");
        exit();
    }
    if ($role === 'admin' && $returnTo === 'dashboard') {
        header("Location: {$baseUrl}/backend/admin/dashboard.php?{$q}");
        exit();
    }
    header("Location: {$baseUrl}/backend/super_admin/manage_events.php?{$q}");
    exit();
}

// Only admin or super_admin can change event status
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    header("Location: " . BASE_URL . "/views/login.php?error=Access denied");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    eventify_redirect_event_status('error', 'Invalid request method.');
}

if (!csrf_validate()) {
    eventify_redirect_event_status('error', 'Invalid request. Please try again.');
}

$eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$action  = $_POST['action'] ?? '';

$validActions = ['approve', 'approve_with_otp', 'send_otp', 'reject', 'close'];
if ($eventId <= 0 || !in_array($action, $validActions, true)) {
    eventify_redirect_event_status('error', 'Invalid request.');
}

// Admin and Super Admin can close active events
if ($action === 'close' && !in_array((string) ($_SESSION['role'] ?? ''), ['admin', 'super_admin'], true)) {
    eventify_redirect_event_status('error', 'Only Admin or Super Admin can close events.');
}

// Read current event state for safer transitions and richer logs.
$metaStmt = $conn->prepare("SELECT status, reject_reason FROM events WHERE id = ? LIMIT 1");
if (!$metaStmt) {
    $conn->close();
    eventify_redirect_event_status('error', 'Failed to load event state.');
}
$metaStmt->bind_param("i", $eventId);
$metaStmt->execute();
$eventMeta = $metaStmt->get_result()->fetch_assoc();
$metaStmt->close();
if (!$eventMeta) {
    $conn->close();
    eventify_redirect_event_status('error', 'Event not found.');
}
$previousStatus = strtolower((string)($eventMeta['status'] ?? ''));

// Map action to new status
if ($action === 'approve' || $action === 'approve_with_otp') {
    $newStatus = 'active';
} elseif ($action === 'reject') {
    $newStatus = 'rejected';
} else {
    $newStatus = 'closed';
}

if ($action === 'send_otp') {
    $adminId = (int) ($_SESSION['user_id'] ?? 0);
    $otpResult = eventify_send_event_approval_otp($conn, $eventId, $adminId, 10);
    if (empty($otpResult['ok'])) {
        $conn->close();
        eventify_redirect_event_status('error', (string) ($otpResult['error'] ?? 'Failed to send OTP.'));
    }
    log_activity($conn, $adminId, $_SESSION['role'] ?? '', 'event_approval_otp_sent', 'event', $eventId, 'Sent OTP via ' . ($otpResult['delivery_method'] ?? 'unknown'));
    $masked = (string) ($otpResult['masked_target'] ?? 'organizer');
    $deliveredLabel = (string) ($otpResult['delivered_label'] ?? 'in-app notification');
    $deliveryNote = (string) ($otpResult['delivery_note'] ?? '');
    $conn->close();
    eventify_redirect_event_status('success', "OTP sent to {$masked} via {$deliveredLabel}.{$deliveryNote}");
}

if ($action === 'approve_with_otp') {
    if (!eventify_event_otp_table_ready($conn)) {
        $conn->close();
        eventify_redirect_event_status('error', 'OTP table missing. Run school_events_event_approval_otp.sql first.');
    }
    $otpCode = trim((string)($_POST['otp_code'] ?? ''));
    if (!preg_match('/^\d{6}$/', $otpCode)) {
        $conn->close();
        eventify_redirect_event_status('error', 'Invalid OTP format.');
    }

    $otpStmt = $conn->prepare("SELECT id, otp_hash, expires_at FROM event_approval_otps WHERE event_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1");
    $otpStmt->bind_param("i", $eventId);
    $otpStmt->execute();
    $otp = $otpStmt->get_result()->fetch_assoc();
    $otpStmt->close();
    if (!$otp) {
        $conn->close();
        eventify_redirect_event_status('error', 'No active OTP found. Send OTP first.');
    }
    if (strtotime((string)$otp['expires_at']) < time()) {
        $conn->close();
        eventify_redirect_event_status('error', 'OTP expired. Send a new OTP.');
    }
    if (!password_verify($otpCode, (string)$otp['otp_hash'])) {
        $conn->close();
        eventify_redirect_event_status('error', 'Incorrect OTP.');
    }
    $usedBy = (int) ($_SESSION['user_id'] ?? 0);
    $markUsed = $conn->prepare("UPDATE event_approval_otps SET used_at = NOW(), verified_by = ? WHERE id = ?");
    if ($markUsed) {
        $otpId = (int) ($otp['id'] ?? 0);
        $markUsed->bind_param("ii", $usedBy, $otpId);
        $markUsed->execute();
        $markUsed->close();
    }
}

if (in_array($action, ['approve', 'approve_with_otp'], true) && $previousStatus !== 'pending') {
    $conn->close();
    eventify_redirect_event_status('error', 'Only pending events can be approved.');
}
if ($action === 'reject' && !in_array($previousStatus, ['pending', 'active'], true)) {
    $conn->close();
    eventify_redirect_event_status('error', 'Only pending or active events can be rejected.');
}
if ($action === 'close' && $previousStatus !== 'active') {
    $conn->close();
    eventify_redirect_event_status('error', 'Only active events can be closed.');
}

if ($action === 'reject') {
    $reason = trim($_POST['reject_reason'] ?? '');
    $hasRejectReasonColumn = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM events LIKE 'reject_reason'");
        $hasRejectReasonColumn = (bool)($col && $col->num_rows > 0);
    } catch (Throwable $e) {
        $hasRejectReasonColumn = false;
    }
    if ($hasRejectReasonColumn) {
        $stmt = $conn->prepare("UPDATE events SET status = ?, reject_reason = ? WHERE id = ?");
        if (!$stmt) {
            $conn->close();
            eventify_redirect_event_status('error', 'Failed to prepare reject update.');
        }
        $stmt->bind_param("ssi", $newStatus, $reason, $eventId);
    } else {
        $stmt = $conn->prepare("UPDATE events SET status = ? WHERE id = ?");
        if (!$stmt) {
            $conn->close();
            eventify_redirect_event_status('error', 'Failed to prepare reject update.');
        }
        $stmt->bind_param("si", $newStatus, $eventId);
    }
} else {
    $hasRejectReasonColumn = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM events LIKE 'reject_reason'");
        $hasRejectReasonColumn = (bool)($col && $col->num_rows > 0);
    } catch (Throwable $e) {
        $hasRejectReasonColumn = false;
    }
    $stmt = $hasRejectReasonColumn
        ? $conn->prepare("UPDATE events SET status = ?, reject_reason = NULL WHERE id = ?")
        : $conn->prepare("UPDATE events SET status = ? WHERE id = ?");
    if (!$stmt) {
        $conn->close();
        eventify_redirect_event_status('error', 'Failed to prepare event update.');
    }
    $stmt->bind_param("si", $newStatus, $eventId);
}
$stmt->execute();
$changed = $stmt->affected_rows > 0;
if ($changed) {
    $stmt->close();

    // Log activity
    $actorId   = $_SESSION['user_id'] ?? null;
    $actorRole = $_SESSION['role'] ?? null;
    $actionKey = ($action === 'approve' || $action === 'approve_with_otp') ? 'event_approved' : ($action === 'reject' ? 'event_rejected' : 'event_closed');
    $details   = "Event {$eventId}: {$previousStatus} -> {$newStatus}";
    if ($action === 'reject') {
        $reasonForLog = trim((string)($_POST['reject_reason'] ?? ''));
        if ($reasonForLog !== '') {
            $details .= "; reason=" . mb_strimwidth($reasonForLog, 0, 140, '...');
        }
    }
    log_activity($conn, $actorId, $actorRole, $actionKey, 'event', $eventId, $details);

    // Notify organizer when event is approved or rejected
    if (in_array($action, ['approve', 'approve_with_otp', 'reject'], true)) {
        $hasRejectReasonColumn = false;
        try {
            $col = $conn->query("SHOW COLUMNS FROM events LIKE 'reject_reason'");
            $hasRejectReasonColumn = (bool)($col && $col->num_rows > 0);
        } catch (Throwable $e) {
            $hasRejectReasonColumn = false;
        }
        $evSql = $hasRejectReasonColumn
            ? "SELECT e.title, e.organizer_id, e.reject_reason, u.email FROM events e JOIN users u ON e.organizer_id = u.id WHERE e.id = ?"
            : "SELECT e.title, e.organizer_id, '' AS reject_reason, u.email FROM events e JOIN users u ON e.organizer_id = u.id WHERE e.id = ?";
        $evStmt = $conn->prepare($evSql);
        if ($evStmt) {
            $evStmt->bind_param("i", $eventId);
            $evStmt->execute();
            $ev = $evStmt->get_result();
            $evStmt->close();
        } else {
            $ev = null;
        }
        if ($ev && $row = $ev->fetch_assoc()) {
            $organizerId = (int)$row['organizer_id'];
            $eventTitle  = $row['title'] ?? 'Event';
            $organizerEmail = $row['email'] ?? '';
            $approvedAction = in_array($action, ['approve', 'approve_with_otp'], true);
            $notifType   = $approvedAction ? 'event_approved' : 'event_rejected';
            $notifTitle  = $approvedAction ? 'Event approved' : 'Event rejected';
            $notifMsg    = $approvedAction
                ? ('Your event "' . $eventTitle . '" has been approved and is now visible to students.')
                : ('Your event "' . $eventTitle . '" was rejected.' . ($row['reject_reason'] ? ' Reason: ' . $row['reject_reason'] : ''));
            $ins = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, ?, ?, ?, ?)");
            if ($ins) {
                $ins->bind_param("isssi", $organizerId, $notifType, $notifTitle, $notifMsg, $eventId);
                $ins->execute();
                $ins->close();
            }
            // Optional: send email to organizer via SMTP helper
            if ($organizerEmail) {
                $subject = '[EVENTIFY] ' . $notifTitle . ': ' . $eventTitle;
                $body    = $notifMsg . "\n\nLog in to your organizer dashboard to view details.";
                eventify_send_email($organizerEmail, $subject, $body);
            }
        }
    }

    if (in_array($action, ['approve', 'approve_with_otp'], true)) {
        try {
            require_once __DIR__ . '/../lib/notifications_service.php';
            eventify_notify_students_event_published($conn, $eventId, 'approved');
        } catch (Throwable $e) {
            // ignore
        }
    }

    $conn->close();
    $msg = ($action === 'approve' || $action === 'approve_with_otp') ? 'Event approved.' : ($action === 'reject' ? 'Event rejected.' : 'Event closed.');
    eventify_redirect_event_status('success', $msg);
}

$stmt->close();
$conn->close();

eventify_redirect_event_status('error', 'No changes were made. Event may already be in that state.');

