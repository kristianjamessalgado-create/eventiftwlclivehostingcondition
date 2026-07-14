<?php

/**
 * In-app notification presentation helpers.
 */

/** @return array{icon: string, accent: string, label: string} */
function eventify_notification_visual(string $type): array
{
    $type = strtolower(trim($type));
    $map = [
        'event_approved' => ['icon' => 'fa-circle-check', 'accent' => 'success', 'label' => 'Approved'],
        'event_auto_approved' => ['icon' => 'fa-circle-check', 'accent' => 'success', 'label' => 'Approved'],
        'event_rejected' => ['icon' => 'fa-circle-xmark', 'accent' => 'danger', 'label' => 'Rejected'],
        'event_pending_review' => ['icon' => 'fa-hourglass-half', 'accent' => 'warning', 'label' => 'Pending'],
        'event_organizer_reassigned' => ['icon' => 'fa-user-pen', 'accent' => 'secondary', 'label' => 'Reassigned'],
        'event_admin_corrected' => ['icon' => 'fa-pen-to-square', 'accent' => 'info', 'label' => 'Corrected'],
        'event_update_pending_review' => ['icon' => 'fa-pen-to-square', 'accent' => 'warning', 'label' => 'Review'],
        'event_updated_pending' => ['icon' => 'fa-clock', 'accent' => 'info', 'label' => 'Updated'],
        'event_update' => ['icon' => 'fa-pen', 'accent' => 'info', 'label' => 'Update'],
        'event_approval_otp' => ['icon' => 'fa-key', 'accent' => 'primary', 'label' => 'OTP'],
        'rsvp_confirmed' => ['icon' => 'fa-user-check', 'accent' => 'success', 'label' => 'RSVP'],
        'rsvp_cancelled' => ['icon' => 'fa-user-minus', 'accent' => 'warning', 'label' => 'RSVP'],
        'event_rsvp_new' => ['icon' => 'fa-user-plus', 'accent' => 'info', 'label' => 'RSVP'],
        'event_rsvp_cancelled' => ['icon' => 'fa-user-minus', 'accent' => 'warning', 'label' => 'RSVP'],
        'event_attendance_confirmed' => ['icon' => 'fa-qrcode', 'accent' => 'success', 'label' => 'Check-in'],
        'activity_attendance' => ['icon' => 'fa-person-running', 'accent' => 'success', 'label' => 'Activity'],
        'activity_update' => ['icon' => 'fa-bolt', 'accent' => 'info', 'label' => 'Activity'],
        'staff_message' => ['icon' => 'fa-envelope', 'accent' => 'primary', 'label' => 'Message'],
        'ticket_payment_pending' => ['icon' => 'fa-ticket-alt', 'accent' => 'warning', 'label' => 'Tickets'],
        'ticket_paid' => ['icon' => 'fa-ticket-alt', 'accent' => 'success', 'label' => 'Tickets'],
        'account_pending_approval' => ['icon' => 'fa-user-clock', 'accent' => 'warning', 'label' => 'Account'],
        'photo_approved' => ['icon' => 'fa-camera', 'accent' => 'success', 'label' => 'Photo'],
        'photo_rejected' => ['icon' => 'fa-camera', 'accent' => 'danger', 'label' => 'Photo'],
        'photo_pending_approval' => ['icon' => 'fa-hourglass-half', 'accent' => 'warning', 'label' => 'Review'],
        'photo_deleted' => ['icon' => 'fa-trash-alt', 'accent' => 'danger', 'label' => 'Photo'],
        'event_photos_available' => ['icon' => 'fa-images', 'accent' => 'success', 'label' => 'Photos'],
        'event_photos_updated' => ['icon' => 'fa-images', 'accent' => 'info', 'label' => 'New photos'],
    ];
    if (isset($map[$type])) {
        return $map[$type];
    }
    if (strpos($type, 'reject') !== false) {
        return ['icon' => 'fa-circle-xmark', 'accent' => 'danger', 'label' => 'Alert'];
    }
    if (strpos($type, 'approv') !== false) {
        return ['icon' => 'fa-circle-check', 'accent' => 'success', 'label' => 'Approved'];
    }
    return ['icon' => 'fa-bell', 'accent' => 'neutral', 'label' => 'Notice'];
}

function eventify_format_notification_time(?string $createdAt): string
{
    $createdAt = trim((string) $createdAt);
    if ($createdAt === '') {
        return '';
    }
    $tz = function_exists('eventify_app_timezone') ? eventify_app_timezone() : new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');
    try {
        $dt = new DateTimeImmutable($createdAt, $tz);
    } catch (Exception $e) {
        return $createdAt;
    }
    $now = new DateTimeImmutable('now', $tz);
    $diff = $now->getTimestamp() - $dt->getTimestamp();
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ' min ago';
    }
    if ($dt->format('Y-m-d') === $now->format('Y-m-d')) {
        return 'Today · ' . $dt->format('g:i A');
    }
    $yesterday = $now->modify('-1 day')->format('Y-m-d');
    if ($dt->format('Y-m-d') === $yesterday) {
        return 'Yesterday · ' . $dt->format('g:i A');
    }
    if ($dt->format('Y') === $now->format('Y')) {
        return $dt->format('M j · g:i A');
    }
    return $dt->format('M j, Y · g:i A');
}

function eventify_notification_mark_url(int $id): string
{
    $base = defined('BASE_URL') ? BASE_URL : '';
    return $base . '/backend/auth/mark_notification_read.php?id=' . $id;
}
