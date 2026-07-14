<?php

function eventify_event_otp_table_ready(mysqli $conn): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $q = $conn->query("SHOW TABLES LIKE 'event_approval_otps'");
        $ready = (bool) ($q && $q->num_rows > 0);
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

function eventify_generate_otp_code(int $digits = 6): string
{
    $max = (10 ** $digits) - 1;
    $min = 10 ** ($digits - 1);
    return (string) random_int($min, $max);
}

function eventify_mask_email(string $email): string
{
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return '***';
    }
    $name = $parts[0];
    $domain = $parts[1];
    if (strlen($name) <= 2) {
        $nameMasked = substr($name, 0, 1) . '*';
    } else {
        $nameMasked = substr($name, 0, 2) . str_repeat('*', max(1, strlen($name) - 2));
    }
    return $nameMasked . '@' . $domain;
}

function eventify_mask_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) < 4) {
        return '***';
    }
    return str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -4);
}

function eventify_event_has_active_approval_otp(mysqli $conn, int $eventId): bool
{
    if ($eventId <= 0 || !eventify_event_otp_table_ready($conn)) {
        return false;
    }
    $stmt = $conn->prepare(
        'SELECT expires_at FROM event_approval_otps
         WHERE event_id = ? AND used_at IS NULL
         ORDER BY id DESC LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || empty($row['expires_at'])) {
        return false;
    }
    return strtotime((string) $row['expires_at']) >= time();
}

/**
 * Send event approval OTP to the organizer (email/SMS + in-app notification).
 *
 * @return array{ok:bool,error?:string,delivery_method?:string,delivery_target?:string,delivered_label?:string,delivery_note?:string,masked_target?:string}
 */
function eventify_send_event_approval_otp(mysqli $conn, int $eventId, int $createdByUserId, int $expiryMinutes = 10): array
{
    if (!eventify_event_otp_table_ready($conn)) {
        return ['ok' => false, 'error' => 'OTP table missing. Run school_events_event_approval_otp.sql first.'];
    }
    if ($eventId <= 0) {
        return ['ok' => false, 'error' => 'Invalid event.'];
    }

    require_once __DIR__ . '/email_sender.php';
    require_once __DIR__ . '/sms_sender.php';

    $evStmt = $conn->prepare(
        "SELECT e.id, e.organizer_id, e.title, e.status, u.email, u.organizer_contact_email, u.organizer_phone, u.organizer_contact_method
         FROM events e
         JOIN users u ON e.organizer_id = u.id
         WHERE e.id = ?"
    );
    if (!$evStmt) {
        return ['ok' => false, 'error' => 'Failed to prepare OTP request.'];
    }
    $evStmt->bind_param('i', $eventId);
    $evStmt->execute();
    $ev = $evStmt->get_result()->fetch_assoc();
    $evStmt->close();

    if (!$ev || ($ev['status'] ?? '') !== 'pending') {
        return ['ok' => false, 'error' => 'OTP can only be sent for pending events.'];
    }

    $deliveryMethod = ($ev['organizer_contact_method'] ?? 'email') === 'phone' ? 'phone' : 'email';
    $fallbackEmail = trim((string) ($ev['email'] ?? ''));
    if ($fallbackEmail === '') {
        $fallbackEmail = trim((string) ($ev['organizer_contact_email'] ?? ''));
    }

    $deliveryTarget = '';
    if ($deliveryMethod === 'phone') {
        $deliveryTarget = trim((string) ($ev['organizer_phone'] ?? ''));
        if ($deliveryTarget === '') {
            $deliveryMethod = 'email';
        }
    }
    if ($deliveryMethod === 'email') {
        $deliveryTarget = trim((string) ($ev['email'] ?? ''));
        if ($deliveryTarget === '') {
            $deliveryTarget = trim((string) ($ev['organizer_contact_email'] ?? ''));
        }
    }
    if ($deliveryTarget === '') {
        return ['ok' => false, 'error' => 'Organizer has no OTP contact set in profile. Add your email under Profile → OTP Verification Method.'];
    }

    $expiryMinutes = max(3, min(30, $expiryMinutes));
    $otpCode = eventify_generate_otp_code(6);
    $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));
    $organizerId = (int) ($ev['organizer_id'] ?? 0);

    $invalidate = $conn->prepare('UPDATE event_approval_otps SET used_at = NOW() WHERE event_id = ? AND used_at IS NULL');
    if ($invalidate) {
        $invalidate->bind_param('i', $eventId);
        $invalidate->execute();
        $invalidate->close();
    }

    $otpIns = $conn->prepare(
        'INSERT INTO event_approval_otps (event_id, organizer_id, delivery_method, delivery_target, otp_hash, expires_at, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$otpIns) {
        return ['ok' => false, 'error' => 'Failed to save OTP.'];
    }
    $otpIns->bind_param('iissssi', $eventId, $organizerId, $deliveryMethod, $deliveryTarget, $otpHash, $expiresAt, $createdByUserId);
    if (!$otpIns->execute()) {
        $otpIns->close();
        return ['ok' => false, 'error' => 'Failed to save OTP.'];
    }
    $otpIns->close();

    $title = 'Event approval OTP';
    $msg = 'Your OTP for event "' . ($ev['title'] ?? 'Event') . '" is ' . $otpCode . '. It expires in ' . $expiryMinutes . ' minutes.';

    // Drop older bell notifications so organizers do not reuse a superseded code.
    $clearNotif = $conn->prepare(
        "DELETE FROM notifications WHERE user_id = ? AND event_id = ? AND type = 'event_approval_otp'"
    );
    if ($clearNotif) {
        $clearNotif->bind_param('ii', $organizerId, $eventId);
        $clearNotif->execute();
        $clearNotif->close();
    }

    $ins = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_approval_otp', ?, ?, ?)");
    if ($ins) {
        $ins->bind_param('issi', $organizerId, $title, $msg, $eventId);
        $ins->execute();
        $ins->close();
    }

    $deliveredLabel = 'in-app notification (bell icon)';
    $deliveryNote = '';
    if ($deliveryMethod === 'email') {
        $subject = '[EVENTIFY] Event approval OTP';
        $body = $msg . "\n\nEnter this code in My Events to verify and activate your event.";
        $emailResult = eventify_send_email($deliveryTarget, $subject, $body);
        if (!empty($emailResult['ok'])) {
            $deliveredLabel = 'email + in-app notification';
        } else {
            $deliveryNote = ' Email failed: ' . ($emailResult['error'] ?? 'unknown error') . ' Check the bell icon for your OTP.';
        }
    } elseif ($deliveryMethod === 'phone') {
        $normalizedPhone = eventify_normalize_ph_phone($deliveryTarget);
        if ($normalizedPhone !== '') {
            $smsResult = eventify_send_sms_semaphore($normalizedPhone, $msg);
            if (!empty($smsResult['ok'])) {
                $deliveredLabel = 'phone SMS + in-app notification';
            } else {
                $deliveryNote = ' SMS failed: ' . ($smsResult['error'] ?? 'unknown error');
                if ($fallbackEmail !== '') {
                    $subject = '[EVENTIFY] Event approval OTP';
                    $body = $msg . "\n\nSMS delivery failed, so this OTP was sent by email.";
                    $fallbackEmailResult = eventify_send_email($fallbackEmail, $subject, $body);
                    if (!empty($fallbackEmailResult['ok'])) {
                        $deliveredLabel = 'email fallback + in-app notification';
                        $deliveryNote = '';
                    } else {
                        $deliveryNote .= ' Email fallback failed: ' . ($fallbackEmailResult['error'] ?? 'unknown error') . ' Check the bell icon for your OTP.';
                    }
                } else {
                    $deliveryNote .= ' Check the bell icon for your OTP.';
                }
            }
        } else {
            $deliveryNote = ' SMS failed: invalid phone number.';
            if ($fallbackEmail !== '') {
                $subject = '[EVENTIFY] Event approval OTP';
                $body = $msg . "\n\nSMS delivery failed, so this OTP was sent by email.";
                $fallbackEmailResult = eventify_send_email($fallbackEmail, $subject, $body);
                if (!empty($fallbackEmailResult['ok'])) {
                    $deliveredLabel = 'email fallback + in-app notification';
                    $deliveryNote = '';
                } else {
                    $deliveryNote .= ' Email fallback failed: ' . ($fallbackEmailResult['error'] ?? 'unknown error') . ' Check the bell icon for your OTP.';
                }
            } else {
                $deliveryNote .= ' Check the bell icon for your OTP.';
            }
        }
    }

    $masked = $deliveryMethod === 'email' ? eventify_mask_email($deliveryTarget) : eventify_mask_phone($deliveryTarget);
    return [
        'ok' => true,
        'delivery_method' => $deliveryMethod,
        'delivery_target' => $deliveryTarget,
        'delivered_label' => $deliveredLabel,
        'delivery_note' => $deliveryNote,
        'masked_target' => $masked,
    ];
}
