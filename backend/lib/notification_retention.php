<?php

/**
 * Purge in-app notifications older than admin-configured retention (default 30 days).
 */

function eventify_notification_retention_days(mysqli $conn): int
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = 30;
    try {
        $res = $conn->query('SELECT notification_retention_days FROM admin_settings ORDER BY updated_at DESC LIMIT 1');
        if ($res && ($row = $res->fetch_assoc())) {
            $days = (int) ($row['notification_retention_days'] ?? 30);
            if ($days >= 1 && $days <= 365) {
                $cached = $days;
            }
        }
    } catch (Throwable $e) {
        // Keep default.
    }

    return $cached;
}

function eventify_purge_old_notifications(mysqli $conn): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $days = eventify_notification_retention_days($conn);
    if ($days < 1) {
        return;
    }

    try {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)");
        if ($stmt) {
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        // Keep dashboards available even if purge fails.
    }
}
