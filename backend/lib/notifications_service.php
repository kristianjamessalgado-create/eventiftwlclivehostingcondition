<?php

require_once __DIR__ . '/web_push.php';

/**
 * Avoid duplicate reminder rows when cron and dashboard maintenance both run.
 */
function eventify_user_notification_already_exists(
    mysqli $conn,
    int $userId,
    string $type,
    string $message,
    ?int $eventId = null
): bool {
    if ($userId < 1 || !in_array($type, ['event_reminder', 'activity_reminder'], true)) {
        return false;
    }

    try {
        $eventIdVal = ($eventId !== null && $eventId > 0) ? $eventId : null;
        $stmt = $conn->prepare(
            'SELECT 1 FROM notifications WHERE user_id = ? AND type = ? AND message = ? AND (event_id <=> ?) LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('issi', $userId, $type, $message, $eventIdVal);
        $stmt->execute();
        $exists = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $exists;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Insert a single in-app notification row.
 */
function eventify_insert_user_notification(
    mysqli $conn,
    int $userId,
    string $type,
    string $title,
    string $message,
    ?int $eventId = null
): bool {
    if ($userId < 1 || trim($type) === '' || trim($title) === '') {
        return false;
    }

    if (eventify_user_notification_already_exists($conn, $userId, $type, $message, $eventId)) {
        return true;
    }

    try {
        if ($eventId !== null && $eventId > 0) {
            $stmt = $conn->prepare(
                'INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('isssi', $userId, $type, $title, $message, $eventId);
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)'
            );
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('isss', $userId, $type, $title, $message);
        }
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            eventify_push_notify_user($conn, $userId, $type, $title, $message, $eventId);
        }
        return (bool) $ok;
    } catch (Throwable $e) {
        return false;
    }
}
