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
    ?int $eventId = null,
    bool $sendPush = true,
    ?int $announcementId = null
): bool {
    if ($userId < 1 || trim($type) === '' || trim($title) === '') {
        return false;
    }

    if (eventify_user_notification_already_exists($conn, $userId, $type, $message, $eventId)) {
        return true;
    }

    $announcementId = ($announcementId !== null && $announcementId > 0) ? $announcementId : null;
    $hasAnnouncementId = $announcementId !== null
        && eventify_notifications_ensure_announcement_id($conn);

    try {
        if ($hasAnnouncementId) {
            $stmt = $conn->prepare(
                'INSERT INTO notifications (user_id, type, title, message, announcement_id) VALUES (?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('isssi', $userId, $type, $title, $message, $announcementId);
        } elseif ($eventId !== null && $eventId > 0) {
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
        if ($ok && $sendPush) {
            eventify_push_notify_user($conn, $userId, $type, $title, $message, $eventId);
        }
        return (bool) $ok;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Ensure notifications.announcement_id exists (links bell rows to admin_announcements).
 * Returns false when the hosting database does not permit ALTER TABLE; callers
 * must then use title/message fallback matching.
 */
function eventify_notifications_ensure_announcement_id(mysqli $conn): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    try {
        $r = $conn->query("SHOW COLUMNS FROM notifications LIKE 'announcement_id'");
        if ($r && $r->num_rows === 0) {
            $conn->query('ALTER TABLE notifications ADD COLUMN announcement_id INT(11) NULL DEFAULT NULL AFTER event_id');
            @$conn->query('ALTER TABLE notifications ADD KEY announcement_id (announcement_id)');
        }

        $check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'announcement_id'");
        $available = (bool) ($check && $check->num_rows > 0);
    } catch (Throwable $e) {
        $available = false;
    }

    return $available;
}

/**
 * Active students who should see this event on their calendar (department audience).
 *
 * @return list<int>
 */
function eventify_student_ids_for_event_audience(mysqli $conn, array $event): array
{
    require_once __DIR__ . '/../../config/departments.php';
    if (is_file(__DIR__ . '/../../config/student_sections.php')) {
        require_once __DIR__ . '/../../config/student_sections.php';
    }
    if (function_exists('eventify_sections_schema_ensure')) {
        eventify_sections_schema_ensure($conn);
    }

    $ids = [];
    $res = @$conn->query(
        "SELECT id, department, student_section FROM users WHERE role = 'student' AND status = 'active'"
    );
    if (!$res) {
        $res = $conn->query(
            "SELECT id, department FROM users WHERE role = 'student' AND status = 'active'"
        );
    }
    if (!$res) {
        return [];
    }
    while ($row = $res->fetch_assoc()) {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        if (function_exists('eventify_student_may_access_event')) {
            if (eventify_student_may_access_event($event, $row)) {
                $ids[] = $id;
            }
        } elseif (function_exists('eventify_student_sees_event_department')) {
            if (eventify_student_sees_event_department((string) ($event['department'] ?? 'ALL'), $row['department'] ?? null)) {
                $ids[] = $id;
            }
        }
    }
    return array_values(array_unique($ids));
}

/**
 * @return list<int>
 */
function eventify_student_ids_registered_for_event(mysqli $conn, int $eventId): array
{
    if ($eventId < 1) {
        return [];
    }
    $ids = [];
    $stmt = $conn->prepare(
        "SELECT DISTINCT r.user_id
         FROM registrations r
         INNER JOIN users u ON u.id = r.user_id
         WHERE r.event_id = ? AND u.role = 'student' AND u.status = 'active'"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $id = (int) ($row['user_id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    $stmt->close();
    return array_values(array_unique($ids));
}

function eventify_format_event_date_label(?string $rawDate): string
{
    $rawDate = substr(trim((string) $rawDate), 0, 10);
    if ($rawDate === '') {
        return '';
    }
    $ts = strtotime($rawDate);
    return $ts ? date('M j, Y', $ts) : $rawDate;
}

function eventify_format_event_time_label(?string $rawTime): string
{
    $st = trim((string) $rawTime);
    if ($st === '') {
        return '';
    }
    $tts = strtotime('1970-01-01 ' . $st);
    return $tts ? date('g:i A', $tts) : substr($st, 0, 5);
}

function eventify_normalize_event_time_value(?string $rawTime): string
{
    $st = trim((string) $rawTime);
    if ($st === '') {
        return '';
    }
    if (preg_match('/^\d{1,2}:\d{2}/', $st, $m)) {
        $parts = explode(':', $m[0]);
        return sprintf('%02d:%02d', (int) $parts[0], (int) $parts[1]);
    }
    return $st;
}

/**
 * @param array{date?:string,start_time?:string,end_time?:string,location?:string,title?:string} $event
 */
function eventify_event_schedule_summary(array $event): string
{
    $dateLabel = eventify_format_event_date_label($event['date'] ?? '');
    $startLabel = eventify_format_event_time_label($event['start_time'] ?? '');
    $endLabel = eventify_format_event_time_label($event['end_time'] ?? '');
    $location = trim((string) ($event['location'] ?? ''));

    $when = $dateLabel;
    if ($when !== '' && $startLabel !== '') {
        $when .= ' · ' . $startLabel;
        if ($endLabel !== '') {
            $when .= '–' . $endLabel;
        }
    } elseif ($startLabel !== '') {
        $when = $startLabel;
        if ($endLabel !== '') {
            $when .= '–' . $endLabel;
        }
    }

    $bits = [];
    if ($when !== '') {
        $bits[] = $when;
    }
    if ($location !== '') {
        $bits[] = 'Venue: ' . $location;
    }
    return implode(' · ', $bits);
}

/**
 * @return array{date:bool,start_time:bool,end_time:bool,location:bool,any:bool}
 */
function eventify_event_schedule_fields_changed(array $before, array $after): array
{
    $dateChanged = substr(trim((string) ($before['date'] ?? '')), 0, 10)
        !== substr(trim((string) ($after['date'] ?? '')), 0, 10);
    $startChanged = eventify_normalize_event_time_value($before['start_time'] ?? '')
        !== eventify_normalize_event_time_value($after['start_time'] ?? '');
    $endChanged = eventify_normalize_event_time_value($before['end_time'] ?? '')
        !== eventify_normalize_event_time_value($after['end_time'] ?? '');
    $locChanged = trim((string) ($before['location'] ?? '')) !== trim((string) ($after['location'] ?? ''));

    return [
        'date' => $dateChanged,
        'start_time' => $startChanged,
        'end_time' => $endChanged,
        'location' => $locChanged,
        'any' => $dateChanged || $startChanged || $endChanged || $locChanged,
    ];
}

/**
 * @return array<string,mixed>|null
 */
function eventify_fetch_event_notify_row(mysqli $conn, int $eventId): ?array
{
    if ($eventId < 1) {
        return null;
    }
    $stmt = $conn->prepare(
        'SELECT id, title, date, start_time, end_time, location, department, status, registration_mode
         FROM events WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $event ?: null;
}

function eventify_event_already_published_notified(mysqli $conn, int $eventId): bool
{
    try {
        $dup = $conn->prepare(
            "SELECT 1 FROM notifications WHERE event_id = ? AND type = 'event_published' LIMIT 1"
        );
        if (!$dup) {
            return false;
        }
        $dup->bind_param('i', $eventId);
        $dup->execute();
        $already = (bool) $dup->get_result()->fetch_assoc();
        $dup->close();
        return $already;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @param list<int> $recipientIds
 */
function eventify_notify_student_ids(
    mysqli $conn,
    array $recipientIds,
    string $type,
    string $title,
    string $message,
    ?int $eventId = null,
    ?int $announcementId = null
): int {
    @ignore_user_abort(true);
    if (function_exists('set_time_limit')) {
        @set_time_limit(180);
    }

    // Insert bell notifications first (fast), then push so a slow FCM/APNs round-trip
    // cannot leave most students without an in-app alert.
    $delivered = [];
    foreach ($recipientIds as $rid) {
        $rid = (int) $rid;
        if ($rid < 1) {
            continue;
        }
        if (eventify_insert_user_notification($conn, $rid, $type, $title, $message, $eventId, false, $announcementId)) {
            $delivered[] = $rid;
        }
    }

    foreach ($delivered as $rid) {
        try {
            eventify_push_notify_user($conn, $rid, $type, $title, $message, $eventId);
        } catch (Throwable $e) {
            // Keep going — other devices should still get push.
        }
    }

    return count($delivered);
}

/**
 * Bell + push when an event becomes live on student calendars (admin publish or OTP/admin approve).
 * If the event was already published before (e.g. organizer edit re-approved), sends an update instead.
 *
 * @param array<string,mixed>|null $eventOverride optional row (e.g. right after create) to avoid stale reads
 * @return int Number of students notified
 */
function eventify_notify_students_event_published(
    mysqli $conn,
    int $eventId,
    string $source = 'published',
    ?array $eventOverride = null
): int {
    if ($eventId < 1) {
        return 0;
    }

    $event = $eventOverride;
    if ($event === null) {
        $event = eventify_fetch_event_notify_row($conn, $eventId);
    }
    if (!$event) {
        return 0;
    }

    $status = strtolower(trim((string) ($event['status'] ?? '')));
    if ($status !== '' && $status !== 'active') {
        return 0;
    }
    // If caller already set live but row wasn't refreshed, trust override.
    if ($status === '' && $eventOverride !== null) {
        $event['status'] = 'active';
    }

    $wasPublishedBefore = eventify_event_already_published_notified($conn, $eventId);
    if ($wasPublishedBefore) {
        // Re-approval after edits: tell audience the latest schedule/venue.
        return eventify_notify_students_event_details_changed(
            $conn,
            $eventId,
            [],
            $event,
            'live_refresh',
            true
        );
    }

    $title = trim((string) ($event['title'] ?? 'Event'));
    $eventLabel = $title !== '' ? $title : 'a new event';
    $summary = eventify_event_schedule_summary($event);

    $notifTitle = 'New event on the calendar';
    if ($source === 'admin') {
        $message = 'Admin published "' . $eventLabel . '"'
            . ($summary !== '' ? ' — ' . $summary : '')
            . '. Open your calendar for details.';
    } elseif ($source === 'otp') {
        $message = 'Organizer published "' . $eventLabel . '"'
            . ($summary !== '' ? ' — ' . $summary : '')
            . '. Open your dashboard for details.';
    } else {
        $message = '"' . $eventLabel . '" is now live on the calendar'
            . ($summary !== '' ? ' — ' . $summary : '')
            . '. Open your dashboard for details.';
    }

    $recipientIds = eventify_student_ids_for_event_audience($conn, $event);
    if ($recipientIds === []) {
        return 0;
    }

    return eventify_notify_student_ids(
        $conn,
        $recipientIds,
        'event_published',
        $notifTitle,
        $message,
        $eventId
    );
}

/**
 * Notify students when date / time / venue change (or force a live-details refresh).
 *
 * @param array<string,mixed> $before
 * @param array<string,mixed> $after
 * @param string $mode live|pending|live_refresh
 * @param bool $forceIgnoreChangeCheck when true, always notify using $after summary
 * @return int
 */
function eventify_notify_students_event_details_changed(
    mysqli $conn,
    int $eventId,
    array $before,
    array $after,
    string $mode = 'live',
    bool $forceIgnoreChangeCheck = false
): int {
    if ($eventId < 1) {
        return 0;
    }

    $afterRow = $after;
    if (!isset($afterRow['title']) || !isset($afterRow['department'])) {
        $fresh = eventify_fetch_event_notify_row($conn, $eventId);
        if ($fresh) {
            $afterRow = array_merge($fresh, $afterRow);
        }
    }

    $changes = eventify_event_schedule_fields_changed($before, $afterRow);
    if (!$forceIgnoreChangeCheck && !$changes['any']) {
        return 0;
    }

    $title = trim((string) ($afterRow['title'] ?? ($before['title'] ?? 'Event')));
    $eventLabel = $title !== '' ? $title : 'an event';
    $summary = eventify_event_schedule_summary($afterRow);

    $changedBits = [];
    if ($forceIgnoreChangeCheck || $changes['date'] || $changes['start_time'] || $changes['end_time']) {
        $dateLabel = eventify_format_event_date_label($afterRow['date'] ?? '');
        $startLabel = eventify_format_event_time_label($afterRow['start_time'] ?? '');
        $endLabel = eventify_format_event_time_label($afterRow['end_time'] ?? '');
        $when = $dateLabel;
        if ($when !== '' && $startLabel !== '') {
            $when .= ' · ' . $startLabel;
            if ($endLabel !== '') {
                $when .= '–' . $endLabel;
            }
        } elseif ($startLabel !== '') {
            $when = $startLabel;
        }
        if ($when !== '') {
            $changedBits[] = 'Date/time: ' . $when;
        }
    }
    if ($forceIgnoreChangeCheck || $changes['location']) {
        $loc = trim((string) ($afterRow['location'] ?? ''));
        if ($loc !== '') {
            $changedBits[] = 'Venue: ' . $loc;
        }
    }
    if ($changedBits === [] && $summary !== '') {
        $changedBits[] = $summary;
    }

    $detailText = $changedBits !== [] ? implode(' · ', $changedBits) : 'latest details';

    if ($mode === 'pending') {
        $notifType = 'event_updated_pending';
        $notifTitle = 'Event update pending';
        $message = '"' . $eventLabel . '" was updated and is pending admin approval. '
            . $detailText
            . '.';
        // Registered students care most while event is briefly off-calendar.
        $recipientIds = eventify_student_ids_registered_for_event($conn, $eventId);
        if ($recipientIds === []) {
            $recipientIds = eventify_student_ids_for_event_audience($conn, $afterRow);
        }
    } else {
        $notifType = 'event_update';
        $notifTitle = $mode === 'live_refresh' ? 'Event details updated' : 'Schedule or venue updated';
        $message = '"' . $eventLabel . '" was updated. '
            . $detailText
            . '. Open your calendar for details.';
        $recipientIds = eventify_student_ids_for_event_audience($conn, $afterRow);
    }

    if ($recipientIds === []) {
        return 0;
    }

    return eventify_notify_student_ids(
        $conn,
        $recipientIds,
        $notifType,
        $notifTitle,
        $message,
        $eventId
    );
}
