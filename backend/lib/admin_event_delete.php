<?php

/**
 * Admin / Super Admin event lifecycle helpers: impact summary + hard delete.
 */

require_once __DIR__ . '/activity_logger.php';

/**
 * @return array{
 *   rsvp_count:int,
 *   attendance_count:int,
 *   ticket_order_count:int,
 *   paid_order_count:int,
 *   photo_count:int,
 *   session_count:int,
 *   has_student_impact:bool,
 *   has_revenue_impact:bool
 * }
 */
function eventify_event_delete_impact(mysqli $conn, int $eventId): array
{
    $impact = [
        'rsvp_count' => 0,
        'attendance_count' => 0,
        'ticket_order_count' => 0,
        'paid_order_count' => 0,
        'photo_count' => 0,
        'session_count' => 0,
        'has_student_impact' => false,
        'has_revenue_impact' => false,
    ];
    if ($eventId < 1) {
        return $impact;
    }

    $queries = [
        'rsvp_count' => 'SELECT COUNT(*) AS c FROM registrations WHERE event_id = ?',
        'attendance_count' => 'SELECT COUNT(*) AS c FROM attendance WHERE event_id = ?',
        'ticket_order_count' => "SHOW TABLES LIKE 'ticket_orders'",
        'photo_count' => "SHOW TABLES LIKE 'event_photos'",
        'session_count' => "SHOW TABLES LIKE 'event_day_sessions'",
    ];

    foreach (['rsvp_count', 'attendance_count'] as $key) {
        $stmt = $conn->prepare($queries[$key]);
        if ($stmt) {
            $stmt->bind_param('i', $eventId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $impact[$key] = (int) ($row['c'] ?? 0);
        }
    }

    try {
        $t = $conn->query("SHOW TABLES LIKE 'ticket_orders'");
        if ($t && $t->num_rows > 0) {
            $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM ticket_orders WHERE event_id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $eventId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $impact['ticket_order_count'] = (int) ($row['c'] ?? 0);
            }
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM ticket_orders WHERE event_id = ? AND LOWER(COALESCE(status,'')) IN ('paid','completed','confirmed')");
            if ($stmt) {
                $stmt->bind_param('i', $eventId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $impact['paid_order_count'] = (int) ($row['c'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    try {
        $t = $conn->query("SHOW TABLES LIKE 'event_photos'");
        if ($t && $t->num_rows > 0) {
            $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM event_photos WHERE event_id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $eventId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $impact['photo_count'] = (int) ($row['c'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    try {
        $t = $conn->query("SHOW TABLES LIKE 'event_day_sessions'");
        if ($t && $t->num_rows > 0) {
            $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM event_day_sessions WHERE event_id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $eventId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $impact['session_count'] = (int) ($row['c'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    $impact['has_student_impact'] = ($impact['rsvp_count'] + $impact['attendance_count']) > 0;
    $impact['has_revenue_impact'] = $impact['paid_order_count'] > 0;

    return $impact;
}

/**
 * Permanently delete an event and non-cascading related rows.
 *
 * @return array{ok:bool,error?:string,title?:string,impact?:array}
 */
function eventify_admin_delete_event(
    mysqli $conn,
    int $eventId,
    int $actorId,
    string $actorRole,
    bool $forceRevenue = false
): array {
    if ($eventId < 1) {
        return ['ok' => false, 'error' => 'Invalid event.'];
    }
    if (!in_array($actorRole, ['admin', 'super_admin'], true)) {
        return ['ok' => false, 'error' => 'Only admins can delete events.'];
    }

    $stmt = $conn->prepare(
        'SELECT id, title, status, organizer_id FROM events WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Database error.'];
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$event) {
        return ['ok' => false, 'error' => 'Event not found.'];
    }

    $impact = eventify_event_delete_impact($conn, $eventId);
    if ($impact['has_revenue_impact'] && !$forceRevenue) {
        return [
            'ok' => false,
            'error' => 'This event has paid ticket orders. Closing is safer than deleting. To permanently delete, confirm the revenue wipe.',
            'impact' => $impact,
            'needs_force' => true,
            'title' => (string) ($event['title'] ?? 'Event'),
        ];
    }

    $title = (string) ($event['title'] ?? 'Event');
    $organizerId = (int) ($event['organizer_id'] ?? 0);

    $conn->begin_transaction();
    try {
        // Tables that may lack ON DELETE CASCADE.
        foreach (['registrations', 'attendance'] as $table) {
            $del = $conn->prepare("DELETE FROM {$table} WHERE event_id = ?");
            if ($del) {
                $del->bind_param('i', $eventId);
                $del->execute();
                $del->close();
            }
        }

        // Optional legacy tables without cascade.
        foreach (['event_rsvps', 'event_attendees', 'checkins'] as $table) {
            try {
                $exists = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
                if ($exists && $exists->num_rows > 0) {
                    $del = $conn->prepare("DELETE FROM {$table} WHERE event_id = ?");
                    if ($del) {
                        $del->bind_param('i', $eventId);
                        $del->execute();
                        $del->close();
                    }
                }
            } catch (Throwable $e) {
                // ignore missing tables
            }
        }

        $delEvent = $conn->prepare('DELETE FROM events WHERE id = ? LIMIT 1');
        if (!$delEvent) {
            throw new RuntimeException('Failed to prepare event delete.');
        }
        $delEvent->bind_param('i', $eventId);
        if (!$delEvent->execute() || $delEvent->affected_rows < 1) {
            $delEvent->close();
            throw new RuntimeException('Failed to delete event.');
        }
        $delEvent->close();

        log_activity(
            $conn,
            $actorId,
            $actorRole,
            'event_deleted',
            'event',
            $eventId,
            'Deleted event "' . $title . '" (RSVPs=' . $impact['rsvp_count']
                . ', attendance=' . $impact['attendance_count']
                . ', paid_orders=' . $impact['paid_order_count'] . ')'
        );

        if ($organizerId > 0 && $organizerId !== $actorId) {
            try {
                $notifTitle = 'Event deleted';
                $notifMsg = 'Admin permanently deleted "' . $title . '".';
                $ins = $conn->prepare(
                    "INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'event_deleted', ?, ?)"
                );
                if ($ins) {
                    $ins->bind_param('iss', $organizerId, $notifTitle, $notifMsg);
                    $ins->execute();
                    $ins->close();
                }
            } catch (Throwable $e) {
                // keep delete successful
            }
        }

        $conn->commit();
        return [
            'ok' => true,
            'title' => $title,
            'impact' => $impact,
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['ok' => false, 'error' => $e->getMessage() ?: 'Delete failed.'];
    }
}
