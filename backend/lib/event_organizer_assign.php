<?php

require_once __DIR__ . '/event_approval_otp.php';
require_once __DIR__ . '/activity_logger.php';

/**
 * Reassign a pending event to another active organizer (admin / super admin).
 *
 * @return array{ok:bool,error?:string,noop?:bool,event_title?:string,old_organizer_name?:string,new_organizer_name?:string}
 */
function eventify_assign_event_organizer(mysqli $conn, int $eventId, int $newOrganizerId, int $actorId, string $actorRole): array
{
    if ($eventId < 1 || $newOrganizerId < 1) {
        return ['ok' => false, 'error' => 'Invalid event or organizer.'];
    }

    $evStmt = $conn->prepare(
        'SELECT e.id, e.title, e.status, e.organizer_id, u.name AS organizer_name
         FROM events e
         JOIN users u ON u.id = e.organizer_id
         WHERE e.id = ?
         LIMIT 1'
    );
    if (!$evStmt) {
        return ['ok' => false, 'error' => 'Failed to load event.'];
    }
    $evStmt->bind_param('i', $eventId);
    $evStmt->execute();
    $event = $evStmt->get_result()->fetch_assoc();
    $evStmt->close();

    if (!$event) {
        return ['ok' => false, 'error' => 'Event not found.'];
    }
    if (strtolower((string) ($event['status'] ?? '')) !== 'pending') {
        return ['ok' => false, 'error' => 'Only pending events can be reassigned.'];
    }

    $currentOrganizerId = (int) ($event['organizer_id'] ?? 0);
    if ($currentOrganizerId === $newOrganizerId) {
        return [
            'ok' => true,
            'noop' => true,
            'event_title' => (string) ($event['title'] ?? 'Event'),
            'old_organizer_name' => (string) ($event['organizer_name'] ?? ''),
            'new_organizer_name' => (string) ($event['organizer_name'] ?? ''),
        ];
    }

    $orgStmt = $conn->prepare(
        "SELECT id, name, email FROM users WHERE id = ? AND role = 'organizer' AND status = 'active' LIMIT 1"
    );
    if (!$orgStmt) {
        return ['ok' => false, 'error' => 'Failed to validate organizer.'];
    }
    $orgStmt->bind_param('i', $newOrganizerId);
    $orgStmt->execute();
    $newOrg = $orgStmt->get_result()->fetch_assoc();
    $orgStmt->close();

    if (!$newOrg) {
        return ['ok' => false, 'error' => 'Selected user is not an active organizer.'];
    }

    $eventTitle = (string) ($event['title'] ?? 'Event');
    $oldName = (string) ($event['organizer_name'] ?? 'Organizer');
    $newName = (string) ($newOrg['name'] ?? 'Organizer');

    $conn->begin_transaction();
    try {
        $up = $conn->prepare('UPDATE events SET organizer_id = ? WHERE id = ? AND status = ?');
        if (!$up) {
            throw new RuntimeException('Failed to update event owner.');
        }
        $pending = 'pending';
        $up->bind_param('iis', $newOrganizerId, $eventId, $pending);
        $up->execute();
        if ($up->affected_rows < 1) {
            $up->close();
            throw new RuntimeException('Event could not be reassigned.');
        }
        $up->close();

        if (eventify_event_otp_table_ready($conn)) {
            $clearOtp = $conn->prepare(
                'UPDATE event_approval_otps SET used_at = NOW() WHERE event_id = ? AND used_at IS NULL'
            );
            if ($clearOtp) {
                $clearOtp->bind_param('i', $eventId);
                $clearOtp->execute();
                $clearOtp->close();
            }
        }

        $assignMsg = 'You were assigned as organizer for pending event "' . $eventTitle . '". Wait for admin to send an OTP, then verify under My Events.';
        $insNew = $conn->prepare(
            "INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_pending_review', ?, ?, ?)"
        );
        if ($insNew) {
            $title = 'Event assigned to you';
            $insNew->bind_param('issi', $newOrganizerId, $title, $assignMsg, $eventId);
            $insNew->execute();
            $insNew->close();
        }

        if ($currentOrganizerId > 0) {
            $removedMsg = 'Admin reassigned pending event "' . $eventTitle . '" to ' . $newName . '.';
            $insOld = $conn->prepare(
                "INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_organizer_reassigned', ?, ?, ?)"
            );
            if ($insOld) {
                $oldTitle = 'Event reassigned';
                $insOld->bind_param('issi', $currentOrganizerId, $oldTitle, $removedMsg, $eventId);
                $insOld->execute();
                $insOld->close();
            }
        }

        log_activity(
            $conn,
            $actorId,
            $actorRole,
            'event_organizer_assigned',
            'event',
            $eventId,
            'Reassigned "' . $eventTitle . '" from ' . $oldName . ' to ' . $newName
        );

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return ['ok' => false, 'error' => 'Could not assign organizer. Please try again.'];
    }

    return [
        'ok' => true,
        'event_title' => $eventTitle,
        'old_organizer_name' => $oldName,
        'new_organizer_name' => $newName,
    ];
}

function eventify_fetch_assignable_organizers(mysqli $conn): array
{
    $rows = [];
    $res = $conn->query(
        "SELECT id, name, email FROM users WHERE role = 'organizer' AND status = 'active' ORDER BY name ASC LIMIT 500"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}
