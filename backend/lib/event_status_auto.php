<?php

require_once __DIR__ . '/event_calendar.php';
require_once __DIR__ . '/notification_retention.php';
require_once __DIR__ . '/event_reminders.php';

function eventify_run_dashboard_maintenance(mysqli $conn): void
{
    eventify_auto_complete_past_events($conn);
    eventify_purge_old_notifications($conn);
    eventify_maybe_run_event_reminders($conn);
}

/**
 * Whether an organizer may set a closed/completed event back to active (e.g. ended by mistake).
 */
function eventify_event_can_organizer_reopen(array $event, ?DateTimeInterface $now = null): bool
{
    $status = strtolower(trim((string) ($event['status'] ?? '')));
    if (!in_array($status, ['closed', 'completed'], true)) {
        return false;
    }

    $tz = eventify_calendar_app_timezone();
    $nowDt = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);

    $end = eventify_event_effective_end_datetime($event);
    if ($end instanceof DateTimeInterface) {
        return $end->setTimezone($tz) >= $nowDt;
    }

    $start = substr(trim((string) ($event['date'] ?? '')), 0, 10);
    if ($start === '') {
        return false;
    }

    return $start >= $nowDt->format('Y-m-d');
}

/**
 * Status used when an event is finished (auto or organizer "mark as ended").
 * Matches ENUM if `completed` exists, otherwise `closed`.
 */
function eventify_events_completed_or_closed_target(mysqli $conn): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = 'closed';
    try {
        $col = $conn->query("SHOW COLUMNS FROM events LIKE 'status'");
        if ($col && ($row = $col->fetch_assoc())) {
            $type = strtolower((string) ($row['Type'] ?? ''));
            if (strpos($type, "'completed'") !== false) {
                $cached = 'completed';
            }
        }
    } catch (Throwable $e) {
        // keep default
    }
    return $cached;
}

function eventify_auto_complete_past_events(mysqli $conn): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    try {
        $targetStatus = eventify_events_completed_or_closed_target($conn);
        $res = $conn->query("SELECT * FROM events WHERE status = 'active' ORDER BY id ASC LIMIT 300");
        if (!$res) {
            return;
        }
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        if ($rows === []) {
            return;
        }
        eventify_events_attach_schedule_dates($conn, $rows);
        $stmt = $conn->prepare("UPDATE events SET status = ? WHERE id = ? AND status = 'active'");
        if (!$stmt) {
            return;
        }
        foreach ($rows as $row) {
            if (eventify_event_is_upcoming($row)) {
                continue;
            }
            $eid = (int) ($row['id'] ?? 0);
            if ($eid < 1) {
                continue;
            }
            $stmt->bind_param('si', $targetStatus, $eid);
            $stmt->execute();
        }
        $stmt->close();
    } catch (Throwable $e) {
        // Keep dashboard available even if auto-complete fails.
    }
}
