<?php
require_once __DIR__ . '/../../config/session.php';

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/activity_logger.php';
require_once __DIR__ . '/../lib/event_approval_otp.php';

function eventify_organizer_otp_redirect(string $message, bool $isSuccess = false): void
{
    $param = $isSuccess ? 'msg' : 'error';
    $q = $param . '=' . urlencode($message);
    // Reopen whichever modal the organizer submitted from, so a wrong OTP
    // doesn't dump them back to the calendar to hunt for the event again.
    $returnModal = trim((string) ($_POST['return_modal'] ?? ''));
    $eventId = (int) ($_POST['event_id'] ?? 0);
    if (in_array($returnModal, ['events', 'eventDetails'], true)) {
        if ($returnModal === 'events') {
            $q .= '&panel=events';
        } else {
            $q .= '&open_modal=' . urlencode($returnModal);
        }
        if ($eventId > 0) {
            $q .= '&event_id=' . $eventId;
        }
    }
    header('Location: ' . BASE_URL . '/backend/auth/dashboardorganizer.php?' . $q);
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'organizer') {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    eventify_organizer_otp_redirect('Invalid or expired session. Refresh the organizer dashboard and try again.');
}

$organizerId = (int) ($_SESSION['user_id'] ?? 0);
$eventId = (int) ($_POST['event_id'] ?? 0);
$otpCode = preg_replace('/\D+/', '', trim((string) ($_POST['otp_code'] ?? '')));

if ($eventId <= 0 || !preg_match('/^\d{6}$/', $otpCode)) {
    eventify_organizer_otp_redirect('Enter the 6-digit OTP from your latest email or notification bell.');
}

if (!eventify_event_otp_table_ready($conn)) {
    eventify_organizer_otp_redirect('OTP table missing. Ask admin to run school_events_event_approval_otp.sql.');
}

$evStmt = $conn->prepare('SELECT id, title, status FROM events WHERE id = ? AND organizer_id = ?');
if (!$evStmt) {
    eventify_organizer_otp_redirect('Failed to validate event.');
}
$evStmt->bind_param('ii', $eventId, $organizerId);
$evStmt->execute();
$eventRow = $evStmt->get_result()->fetch_assoc();
$evStmt->close();

if (!$eventRow) {
    eventify_organizer_otp_redirect('Event not found.');
}
if (($eventRow['status'] ?? '') !== 'pending') {
    eventify_organizer_otp_redirect('This event is no longer pending.');
}

$otpStmt = $conn->prepare(
    'SELECT id, otp_hash, expires_at FROM event_approval_otps
     WHERE event_id = ? AND organizer_id = ? AND used_at IS NULL
     ORDER BY id DESC LIMIT 1'
);
if (!$otpStmt) {
    eventify_organizer_otp_redirect('No OTP request found.');
}
$otpStmt->bind_param('ii', $eventId, $organizerId);
$otpStmt->execute();
$otpRow = $otpStmt->get_result()->fetch_assoc();
$otpStmt->close();

if (!$otpRow) {
    eventify_organizer_otp_redirect(
        'No active OTP for this event yet. Ask an admin to click Request OTP after reviewing your event, then use the newest code from email or the bell icon.'
    );
}
if (strtotime((string) $otpRow['expires_at']) < time()) {
    eventify_organizer_otp_redirect(
        'OTP expired (valid 10 minutes). Ask admin to send a new OTP, then enter the latest code only.'
    );
}
if (!password_verify($otpCode, (string) $otpRow['otp_hash'])) {
    eventify_organizer_otp_redirect(
        'Incorrect OTP. Open the bell icon and use the newest OTP for this event, or ask admin to resend.'
    );
}

$conn->begin_transaction();
try {
    $markOtp = $conn->prepare('UPDATE event_approval_otps SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
    if (!$markOtp) {
        throw new Exception('Unable to update OTP');
    }
    $otpId = (int) $otpRow['id'];
    $markOtp->bind_param('i', $otpId);
    $markOtp->execute();
    $markOtp->close();

    $approve = $conn->prepare(
        "UPDATE events SET status = 'active', reject_reason = NULL WHERE id = ? AND organizer_id = ? AND status = 'pending'"
    );
    if (!$approve) {
        throw new Exception('Unable to approve event');
    }
    $approve->bind_param('ii', $eventId, $organizerId);
    $approve->execute();
    $changed = $approve->affected_rows > 0;
    $approve->close();
    if (!$changed) {
        throw new Exception('Event was not updated');
    }

    $eventTitle = (string) ($eventRow['title'] ?? 'Event');
    $notifToOrganizer = $conn->prepare(
        "INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_approved', 'Event approved', ?, ?)"
    );
    if ($notifToOrganizer) {
        $msgOrg = 'Your event "' . $eventTitle . '" is now approved and visible to students.';
        $notifToOrganizer->bind_param('isi', $organizerId, $msgOrg, $eventId);
        $notifToOrganizer->execute();
        $notifToOrganizer->close();
    }

    $admins = $conn->query("SELECT id FROM users WHERE role IN ('admin','super_admin') AND status = 'active'");
    if ($admins) {
        $insAdminNotif = $conn->prepare(
            "INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_auto_approved', ?, ?, ?)"
        );
        if ($insAdminNotif) {
            $adminTitle = 'Event approved via organizer OTP';
            $adminMsg = 'Organizer verified OTP. Event "' . $eventTitle . '" is now active.';
            while ($adm = $admins->fetch_assoc()) {
                $adminId = (int) ($adm['id'] ?? 0);
                if ($adminId > 0) {
                    $insAdminNotif->bind_param('issi', $adminId, $adminTitle, $adminMsg, $eventId);
                    $insAdminNotif->execute();
                }
            }
            $insAdminNotif->close();
        }
    }

    log_activity($conn, $organizerId, 'organizer', 'event_approved_via_otp', 'event', $eventId, 'Organizer verified OTP and event became active');
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    eventify_organizer_otp_redirect('Failed to verify OTP. Please try again.');
}

try {
    require_once __DIR__ . '/../lib/notifications_service.php';
    eventify_notify_students_event_published($conn, $eventId, 'otp');
} catch (Throwable $e) {
    // ignore notify failures
}

eventify_organizer_otp_redirect('OTP verified successfully. Your event is now approved and visible on the calendar.', true);
