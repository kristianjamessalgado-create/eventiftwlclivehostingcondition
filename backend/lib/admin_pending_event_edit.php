<?php

require_once __DIR__ . '/../../config/departments.php';
require_once __DIR__ . '/activity_logger.php';

/**
 * Admin correction of a pending event (title, date, location, department, description).
 *
 * @return array{ok:bool,error?:string,event_title?:string,organizer_id?:int}
 */
function eventify_admin_edit_pending_event(
    mysqli $conn,
    int $eventId,
    array $fields,
    int $actorId,
    string $actorRole
): array {
    if ($eventId < 1) {
        return ['ok' => false, 'error' => 'Invalid event.'];
    }

    eventify_events_department_ensure_varchar($conn);

    $evStmt = $conn->prepare(
        'SELECT id, title, description, date, location, department, status, organizer_id
         FROM events WHERE id = ? LIMIT 1'
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
        return ['ok' => false, 'error' => 'Only pending events can be edited by admin.'];
    }

    $title = trim((string) ($fields['title'] ?? ''));
    $description = trim((string) ($fields['description'] ?? ''));
    $date = trim((string) ($fields['date'] ?? ''));
    $location = trim((string) ($fields['location'] ?? ''));

    if ($title === '') {
        return ['ok' => false, 'error' => 'Title is required.'];
    }
    if ($date === '') {
        return ['ok' => false, 'error' => 'Date is required.'];
    }
    if ($location === '') {
        return ['ok' => false, 'error' => 'Location is required.'];
    }
    if (strlen($title) > 150) {
        return ['ok' => false, 'error' => 'Title must be 150 characters or less.'];
    }
    if (strlen($location) > 100) {
        return ['ok' => false, 'error' => 'Location must be 100 characters or less.'];
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        return ['ok' => false, 'error' => 'Invalid date format.'];
    }

    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $eventDate = new DateTime($date);
    $eventDate->setTime(0, 0, 0);
    if ($eventDate < $today) {
        return ['ok' => false, 'error' => 'Event date cannot be in the past.'];
    }

    $deptPost = isset($fields['department']) && is_array($fields['department'])
        ? ['department' => $fields['department']]
        : ['department' => $fields['department'] ?? 'ALL'];
    $parsedDept = eventify_parse_event_departments_from_request($deptPost);
    if (!$parsedDept['ok']) {
        return ['ok' => false, 'error' => $parsedDept['error'] ?? 'Invalid department selection.'];
    }
    $department = (string) ($parsedDept['department'] ?? 'ALL');

    $organizerId = (int) ($event['organizer_id'] ?? 0);
    $eventTitle = (string) ($event['title'] ?? 'Event');

    $up = $conn->prepare(
        'UPDATE events SET title = ?, description = ?, date = ?, location = ?, department = ?
         WHERE id = ? AND status = ?'
    );
    if (!$up) {
        return ['ok' => false, 'error' => 'Failed to save changes.'];
    }
    $pending = 'pending';
    $up->bind_param('sssssis', $title, $description, $date, $location, $department, $eventId, $pending);
    $up->execute();
    if ($up->affected_rows < 1) {
        $unchanged = (
            (string) ($event['title'] ?? '') === $title
            && (string) ($event['description'] ?? '') === $description
            && (string) ($event['date'] ?? '') === $date
            && (string) ($event['location'] ?? '') === $location
            && (string) ($event['department'] ?? 'ALL') === $department
        );
        $up->close();
        if ($unchanged) {
            return ['ok' => true, 'event_title' => $title, 'organizer_id' => $organizerId, 'unchanged' => true];
        }
        return ['ok' => false, 'error' => 'Event could not be updated.'];
    }
    $up->close();

    $detail = 'Admin corrected pending event "' . $eventTitle . '" → "' . $title . '".';
    log_activity($conn, $actorId, $actorRole, 'event_admin_corrected', 'event', $eventId, $detail);

    if ($organizerId > 0) {
        $msg = 'An admin updated details for your pending event "' . $title . '". Review before OTP approval.';
        $ins = $conn->prepare(
            "INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_admin_corrected', ?, ?, ?)"
        );
        if ($ins) {
            $notifTitle = 'Event details updated';
            $ins->bind_param('issi', $organizerId, $notifTitle, $msg, $eventId);
            $ins->execute();
            $ins->close();
        }
    }

    return ['ok' => true, 'event_title' => $title, 'organizer_id' => $organizerId];
}
