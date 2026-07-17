<?php

require_once __DIR__ . '/event_calendar.php';
require_once __DIR__ . '/notifications_service.php';
require_once __DIR__ . '/event_day_sessions.php';
require_once __DIR__ . '/event_ticketing.php';

/** Default start time when an event day has no start_time (all-day). */
const EVENTIFY_REMINDER_ALL_DAY_START = '09:00:00';

/** Minutes after the ideal remind moment we still send (cron interval tolerance). */
const EVENTIFY_REMINDER_GRACE_MINUTES = 25;

/** "Starting now" window: a few minutes before through shortly after start. */
const EVENTIFY_REMINDER_AT_START_BEFORE_MINUTES = 2;
const EVENTIFY_REMINDER_AT_START_AFTER_MINUTES = 12;

/**
 * @return array<string, int> reminder_timing => seconds before start
 */
function eventify_reminder_timing_offsets(): array
{
    return [
        '30_min' => 30 * 60,
        '1_hour' => 60 * 60,
        '1_day' => 24 * 60 * 60,
        'at_start' => 0,
    ];
}

function eventify_event_reminders_ensure_tables(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS event_reminder_sent (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_id INT NOT NULL,
            schedule_date DATE NOT NULL,
            reminder_timing VARCHAR(20) NOT NULL,
            sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_event_reminder_sent (user_id, event_id, schedule_date, reminder_timing),
            KEY idx_reminder_event (event_id),
            KEY idx_reminder_sent_at (sent_at),
            CONSTRAINT fk_reminder_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_reminder_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS activity_reminder_sent (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id INT NOT NULL,
            reminder_timing VARCHAR(20) NOT NULL,
            sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_activity_reminder_sent (user_id, session_id, reminder_timing),
            KEY idx_activity_reminder_session (session_id),
            CONSTRAINT fk_activity_reminder_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_activity_reminder_session FOREIGN KEY (session_id) REFERENCES event_day_sessions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/**
 * @param array<string, mixed> $day
 * @param array<string, mixed> $eventFallback
 */
function eventify_schedule_day_start_datetime(array $day, array $eventFallback, ?DateTimeZone $tz = null): ?DateTimeImmutable
{
    $tz = $tz ?? eventify_calendar_app_timezone();
    $ymd = substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10);
    if ($ymd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return null;
    }

    $startTime = trim((string) ($day['start_time'] ?? ''));
    if ($startTime === '') {
        $startTime = trim((string) ($eventFallback['start_time'] ?? ''));
    }
    if ($startTime === '') {
        $startTime = EVENTIFY_REMINDER_ALL_DAY_START;
    }

    $timePart = strlen($startTime) === 5 ? $startTime . ':00' : substr($startTime, 0, 8);
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ymd . ' ' . $timePart, $tz);

    return $dt instanceof DateTimeImmutable ? $dt : null;
}

/**
 * Upcoming (or just-started) occurrence start times for reminders.
 * Includes a short look-back so "starting now" can still fire after start.
 *
 * @return list<array{schedule_date: string, starts_at: DateTimeImmutable}>
 */
function eventify_event_reminder_occurrence_starts(array $event, ?DateTimeInterface $now = null): array
{
    $tz = eventify_calendar_app_timezone();
    $nowDt = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $lookBack = $nowDt->modify('-' . EVENTIFY_REMINDER_AT_START_AFTER_MINUTES . ' minutes');

    $out = [];
    $scheduleDays = $event['schedule_days'] ?? [];
    if (is_array($scheduleDays) && $scheduleDays !== []) {
        foreach ($scheduleDays as $day) {
            if (!is_array($day)) {
                continue;
            }
            $ymd = substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10);
            $startsAt = eventify_schedule_day_start_datetime($day, $event, $tz);
            if ($startsAt !== null && $startsAt >= $lookBack) {
                $out[] = [
                    'schedule_date' => $ymd,
                    'starts_at' => $startsAt,
                ];
            }
        }
    } else {
        foreach (eventify_event_calendar_display_dates($event) as $ymd) {
            $day = [
                'schedule_date' => $ymd,
                'start_time' => trim((string) ($event['start_time'] ?? '')),
            ];
            $startsAt = eventify_schedule_day_start_datetime($day, $event, $tz);
            if ($startsAt !== null && $startsAt >= $lookBack) {
                $out[] = [
                    'schedule_date' => $ymd,
                    'starts_at' => $startsAt,
                ];
            }
        }
    }

    usort($out, static function ($a, $b) {
        return $a['starts_at'] <=> $b['starts_at'];
    });

    return $out;
}

/**
 * Keep legacy helper for tools; prefer reminder-aware occurrence list.
 *
 * @return list<array{schedule_date: string, starts_at: DateTimeImmutable}>
 */
function eventify_event_upcoming_occurrence_starts(array $event, ?DateTimeInterface $now = null): array
{
    $tz = eventify_calendar_app_timezone();
    $nowDt = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);

    $out = [];
    foreach (eventify_event_reminder_occurrence_starts($event, $nowDt) as $occ) {
        if ($occ['starts_at'] > $nowDt) {
            $out[] = $occ;
        }
    }
    return $out;
}

function eventify_reminder_was_sent(mysqli $conn, int $userId, int $eventId, string $scheduleDate, string $timing): bool
{
    eventify_event_reminders_ensure_tables($conn);
    $stmt = $conn->prepare('
        SELECT 1 FROM event_reminder_sent
        WHERE user_id = ? AND event_id = ? AND schedule_date = ? AND reminder_timing = ?
        LIMIT 1
    ');
    if (!$stmt) {
        return true;
    }
    $stmt->bind_param('iiss', $userId, $eventId, $scheduleDate, $timing);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();

    return $exists;
}

function eventify_mark_reminder_sent(mysqli $conn, int $userId, int $eventId, string $scheduleDate, string $timing): void
{
    eventify_event_reminders_ensure_tables($conn);
    $stmt = $conn->prepare('
        INSERT IGNORE INTO event_reminder_sent (user_id, event_id, schedule_date, reminder_timing)
        VALUES (?, ?, ?, ?)
    ');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('iiss', $userId, $eventId, $scheduleDate, $timing);
    $stmt->execute();
    $stmt->close();
}

function eventify_reminder_human_lead(string $timing): string
{
    return match ($timing) {
        '30_min' => '30 minutes',
        '1_hour' => '1 hour',
        '1_day' => '1 day',
        'at_start' => 'now',
        default => 'soon',
    };
}

function eventify_build_event_reminder_message(
    string $eventTitle,
    DateTimeImmutable $startsAt,
    string $timing,
    string $registrationMode = 'rsvp'
): string {
    $title = trim($eventTitle) !== '' ? trim($eventTitle) : 'your event';
    $when = $startsAt->format('M j, Y \a\t g:i A');
    $isOpen = strtolower(trim($registrationMode)) === 'open';
    $prefix = $isOpen
        ? 'Event "' . $title . '"'
        : 'The event you confirmed ("' . $title . '")';

    if ($timing === 'at_start') {
        return $prefix . ' is starting now (' . $when . ').';
    }
    if ($timing === '30_min') {
        return $prefix . ' will be starting in 30 minutes (' . $when . ').';
    }

    $lead = eventify_reminder_human_lead($timing);

    return $prefix . ' will be starting in ' . $lead . ' (' . $when . ').';
}

function eventify_build_activity_reminder_message(
    string $activityTitle,
    string $parentEventTitle,
    DateTimeImmutable $startsAt,
    string $timing
): string {
    $activity = trim($activityTitle) !== '' ? trim($activityTitle) : 'Activity';
    $parent = trim($parentEventTitle) !== '' ? trim($parentEventTitle) : 'Event';
    $when = $startsAt->format('M j, Y \a\t g:i A');
    $lead = eventify_reminder_human_lead($timing);

    return $activity . ' (' . $parent . ') starts in ' . $lead . ' (' . $when . ').';
}

/**
 * @param array<string, mixed> $session
 */
function eventify_session_start_datetime(array $session, ?DateTimeZone $tz = null): ?DateTimeImmutable
{
    $startTime = trim((string) ($session['start_time'] ?? ''));
    if ($startTime === '') {
        return null;
    }
    $day = [
        'schedule_date' => substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10),
        'start_time' => $startTime,
    ];

    return eventify_schedule_day_start_datetime($day, $session, $tz);
}

function eventify_activity_reminder_was_sent(mysqli $conn, int $userId, int $sessionId, string $timing): bool
{
    eventify_event_reminders_ensure_tables($conn);
    $stmt = $conn->prepare('
        SELECT 1 FROM activity_reminder_sent
        WHERE user_id = ? AND session_id = ? AND reminder_timing = ?
        LIMIT 1
    ');
    if (!$stmt) {
        return true;
    }
    $stmt->bind_param('iis', $userId, $sessionId, $timing);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();

    return $exists;
}

function eventify_mark_activity_reminder_sent(mysqli $conn, int $userId, int $sessionId, string $timing): void
{
    eventify_event_reminders_ensure_tables($conn);
    $stmt = $conn->prepare('
        INSERT IGNORE INTO activity_reminder_sent (user_id, session_id, reminder_timing)
        VALUES (?, ?, ?)
    ');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('iis', $userId, $sessionId, $timing);
    $stmt->execute();
    $stmt->close();
}

/**
 * @param array<string, mixed> $session
 * @return list<int>
 */
function eventify_activity_reminder_recipient_ids(mysqli $conn, array $session): array
{
    $sessionId = (int) ($session['id'] ?? 0);
    $eventId = (int) ($session['event_id'] ?? 0);
    if ($sessionId < 1 || $eventId < 1) {
        return [];
    }

    eventify_event_day_sessions_ensure_enhanced($conn);
    $ids = [];

    if (eventify_session_is_open_access($session)) {
        $stmt = $conn->prepare("
            SELECT DISTINCT r.user_id
            FROM registrations r
            INNER JOIN users u ON u.id = r.user_id AND u.role = 'student'
            WHERE r.event_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param('i', $eventId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int) ($row['user_id'] ?? 0);
            }
            $stmt->close();
        }
    } else {
        $stmt = $conn->prepare("
            SELECT sr.user_id
            FROM event_day_session_rsvps sr
            INNER JOIN users u ON u.id = sr.user_id AND u.role = 'student'
            WHERE sr.session_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param('i', $sessionId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int) ($row['user_id'] ?? 0);
            }
            $stmt->close();
        }
    }

    return array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
}

/**
 * @param array{ok: bool, checked: int, sent: int, skipped: int, errors: list<string>} $result
 */
function eventify_run_activity_reminders(mysqli $conn, DateTimeImmutable $nowDt, array &$result): void
{
    $graceSeconds = EVENTIFY_REMINDER_GRACE_MINUTES * 60;
    $offsets = eventify_reminder_timing_offsets();
    $maxOffset = max($offsets);
    $todayYmd = $nowDt->format('Y-m-d');

    if (!eventify_event_day_sessions_table_exists($conn)) {
        return;
    }

    eventify_event_day_sessions_ensure_enhanced($conn);
    eventify_event_reminders_ensure_tables($conn);

    $sql = "
        SELECT s.*, e.title AS parent_event_title
        FROM event_day_sessions s
        INNER JOIN events e ON e.id = s.event_id AND e.status = 'active'
        WHERE s.start_time IS NOT NULL
          AND s.schedule_date >= ?
          AND LOWER(COALESCE(s.status, 'scheduled')) = 'scheduled'
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $result['errors'][] = 'activity_query_failed';
        return;
    }
    $stmt->bind_param('s', $todayYmd);
    $stmt->execute();
    $res = $stmt->get_result();
    $sessions = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    if ($sessions === []) {
        return;
    }

    $allUserIds = [];
    $recipientsBySession = [];
    foreach ($sessions as $session) {
        $recipients = eventify_activity_reminder_recipient_ids($conn, $session);
        $recipientsBySession[(int) ($session['id'] ?? 0)] = $recipients;
        foreach ($recipients as $uid) {
            $allUserIds[] = $uid;
        }
    }

    $settingsMap = eventify_load_student_reminder_settings_map($conn, $allUserIds);

    foreach ($sessions as $session) {
        $sessionId = (int) ($session['id'] ?? 0);
        $eventId = (int) ($session['event_id'] ?? 0);
        if ($sessionId < 1 || $eventId < 1) {
            continue;
        }

        $startsAt = eventify_session_start_datetime($session);
        if ($startsAt === null || $startsAt <= $nowDt) {
            continue;
        }

        $secondsUntilStart = $startsAt->getTimestamp() - $nowDt->getTimestamp();
        if ($secondsUntilStart > $maxOffset + $graceSeconds) {
            continue;
        }

        $recipients = $recipientsBySession[$sessionId] ?? [];
        foreach ($recipients as $userId) {
            $settings = $settingsMap[$userId] ?? ['event_reminders' => 1, 'reminder_timing' => '30_min'];

            foreach ($offsets as $timing => $offsetSeconds) {
                $result['checked']++;
                if (!eventify_student_wants_event_reminder($settings, $timing)) {
                    $result['skipped']++;
                    continue;
                }

                $remindAt = $startsAt->getTimestamp() - $offsetSeconds;
                $windowEnd = $remindAt + $graceSeconds;
                $nowTs = $nowDt->getTimestamp();
                if ($nowTs < $remindAt || $nowTs >= $windowEnd) {
                    $result['skipped']++;
                    continue;
                }

                if (eventify_activity_reminder_was_sent($conn, $userId, $sessionId, $timing)) {
                    $result['skipped']++;
                    continue;
                }

                $title = 'Activity reminder';
                $message = eventify_build_activity_reminder_message(
                    (string) ($session['title'] ?? 'Activity'),
                    (string) ($session['parent_event_title'] ?? 'Event'),
                    $startsAt,
                    $timing
                );
                $ok = eventify_insert_user_notification(
                    $conn,
                    $userId,
                    'activity_reminder',
                    $title,
                    $message,
                    $eventId
                );
                if ($ok) {
                    eventify_mark_activity_reminder_sent($conn, $userId, $sessionId, $timing);
                    $result['sent']++;
                } else {
                    $result['errors'][] = 'notify_failed:user_' . $userId . ':session_' . $sessionId;
                }
            }
        }
    }
}

/**
 * @param array{event_reminders?: mixed, reminder_timing?: mixed} $settings
 */
function eventify_student_wants_event_reminder(array $settings, string $timing): bool
{
    if (empty($settings['event_reminders'])) {
        return false;
    }
    // Always fire the "starting now" ping when reminders are enabled.
    if ($timing === 'at_start') {
        return true;
    }
    $pref = trim((string) ($settings['reminder_timing'] ?? '30_min'));
    $allowed = ['30_min', '1_hour', '1_day'];
    if (!in_array($pref, $allowed, true)) {
        $pref = '30_min';
    }

    return $pref === $timing;
}

/**
 * Students who should get start reminders for this event.
 *
 * @param array<string, mixed> $event
 * @return list<int>
 */
function eventify_event_reminder_recipient_ids(mysqli $conn, array $event): array
{
    $eventId = (int) ($event['id'] ?? 0);
    if ($eventId < 1) {
        return [];
    }

    $ids = [];
    $mode = eventify_event_registration_mode($event);

    // RSVP / paid: remind students who registered (or bought).
    $stmt = $conn->prepare("
        SELECT DISTINCT r.user_id
        FROM registrations r
        INNER JOIN users u ON u.id = r.user_id AND u.role = 'student' AND u.status = 'active'
        WHERE r.event_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) ($row['user_id'] ?? 0);
        }
        $stmt->close();
    }

    // Open entry: also remind the calendar audience (students who can see it).
    if ($mode === 'open') {
        foreach (eventify_student_ids_for_event_audience($conn, $event) as $uid) {
            $ids[] = (int) $uid;
        }
    }

    return array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
}

function eventify_reminder_window_hit(
    DateTimeImmutable $startsAt,
    DateTimeImmutable $nowDt,
    string $timing,
    int $offsetSeconds,
    int $graceSeconds
): bool {
    $nowTs = $nowDt->getTimestamp();
    $startTs = $startsAt->getTimestamp();

    if ($timing === 'at_start') {
        $windowStart = $startTs - (EVENTIFY_REMINDER_AT_START_BEFORE_MINUTES * 60);
        $windowEnd = $startTs + (EVENTIFY_REMINDER_AT_START_AFTER_MINUTES * 60);
        return $nowTs >= $windowStart && $nowTs < $windowEnd;
    }

    $remindAt = $startTs - $offsetSeconds;
    $windowEnd = $remindAt + $graceSeconds;

    return $nowTs >= $remindAt && $nowTs < $windowEnd;
}

/**
 * @return array<int, array{event_reminders: int, reminder_timing: string}>
 */
function eventify_load_student_reminder_settings_map(mysqli $conn, array $userIds): array
{
    $defaults = [
        'event_reminders' => 1,
        'reminder_timing' => '30_min',
    ];
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn ($id) => $id > 0)));
    if ($userIds === []) {
        return [];
    }

    $map = [];
    foreach ($userIds as $uid) {
        $map[$uid] = $defaults;
    }

    try {
        eventify_event_reminders_ensure_tables($conn);
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $types = str_repeat('i', count($userIds));
        $sql = "
            SELECT user_id, event_reminders, reminder_timing
            FROM student_settings
            WHERE user_id IN ($placeholders)
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $map;
        }
        $stmt->bind_param($types, ...$userIds);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $map[$uid] = [
                'event_reminders' => (int) ($row['event_reminders'] ?? 1),
                'reminder_timing' => trim((string) ($row['reminder_timing'] ?? '30_min')),
            ];
        }
        $stmt->close();
    } catch (Throwable $e) {
        /* defaults */
    }

    return $map;
}

/**
 * @return array{ok: bool, checked: int, sent: int, skipped: int, errors: list<string>}
 */
function eventify_run_event_reminders(mysqli $conn, ?DateTimeInterface $now = null): array
{
    $result = [
        'ok' => true,
        'checked' => 0,
        'sent' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    $tz = eventify_calendar_app_timezone();
    $nowDt = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $graceSeconds = EVENTIFY_REMINDER_GRACE_MINUTES * 60;
    $offsets = eventify_reminder_timing_offsets();
    $leadOffsets = [];
    foreach ($offsets as $timingKey => $seconds) {
        if ($timingKey !== 'at_start') {
            $leadOffsets[] = (int) $seconds;
        }
    }
    $maxOffset = $leadOffsets !== [] ? max($leadOffsets) : (30 * 60);

    try {
        eventify_event_reminders_ensure_tables($conn);
        eventify_ticketing_ensure_registration_mode_column($conn);

        $hasEndDate = false;
        $hasEndTimeNa = false;
        $hasDept = false;
        try {
            $c1 = $conn->query("SHOW COLUMNS FROM events LIKE 'end_date'");
            $hasEndDate = $c1 && $c1->num_rows > 0;
            $c2 = $conn->query("SHOW COLUMNS FROM events LIKE 'end_time_na'");
            $hasEndTimeNa = $c2 && $c2->num_rows > 0;
            $c3 = $conn->query("SHOW COLUMNS FROM events LIKE 'department'");
            $hasDept = $c3 && $c3->num_rows > 0;
        } catch (Throwable $e) {
            // ignore
        }

        $cols = 'id, title, status, date, start_time, end_time, registration_mode';
        if ($hasEndDate) {
            $cols .= ', end_date';
        }
        if ($hasEndTimeNa) {
            $cols .= ', end_time_na';
        }
        if ($hasDept) {
            $cols .= ', department';
        }

        // Active events — include ones whose primary date is slightly in the past
        // (multi-day / schedule_days may still have upcoming starts).
        $sql = "SELECT {$cols} FROM events WHERE status = 'active' ORDER BY date ASC, id ASC LIMIT 500";
        $res = $conn->query($sql);
        if (!$res) {
            $result['ok'] = false;
            $result['errors'][] = 'events_query_failed';
            eventify_run_activity_reminders($conn, $nowDt, $result);
            return $result;
        }
        $eventList = $res->fetch_all(MYSQLI_ASSOC);

        if ($eventList === []) {
            eventify_run_activity_reminders($conn, $nowDt, $result);
            return $result;
        }

        eventify_events_attach_schedule_dates($conn, $eventList);

        $recipientsByEvent = [];
        $allUserIds = [];
        foreach ($eventList as $event) {
            $eid = (int) ($event['id'] ?? 0);
            if ($eid < 1) {
                continue;
            }
            $recipients = eventify_event_reminder_recipient_ids($conn, $event);
            $recipientsByEvent[$eid] = $recipients;
            foreach ($recipients as $uid) {
                $allUserIds[] = $uid;
            }
        }

        $settingsMap = eventify_load_student_reminder_settings_map($conn, $allUserIds);

        foreach ($eventList as $event) {
            $eventId = (int) ($event['id'] ?? 0);
            if ($eventId < 1) {
                continue;
            }
            if (!eventify_event_is_upcoming($event, $nowDt) && eventify_event_reminder_occurrence_starts($event, $nowDt) === []) {
                continue;
            }

            $recipients = $recipientsByEvent[$eventId] ?? [];
            if ($recipients === []) {
                continue;
            }

            $mode = eventify_event_registration_mode($event);
            $occurrences = eventify_event_reminder_occurrence_starts($event, $nowDt);

            foreach ($occurrences as $occ) {
                /** @var DateTimeImmutable $startsAt */
                $startsAt = $occ['starts_at'];
                $scheduleDate = (string) $occ['schedule_date'];
                $secondsUntilStart = $startsAt->getTimestamp() - $nowDt->getTimestamp();

                // Far from any remind window — skip early.
                if ($secondsUntilStart > $maxOffset + $graceSeconds) {
                    continue;
                }

                foreach ($offsets as $timing => $offsetSeconds) {
                    foreach ($recipients as $userId) {
                        $result['checked']++;
                        $settings = $settingsMap[$userId] ?? ['event_reminders' => 1, 'reminder_timing' => '30_min'];

                        if (!eventify_student_wants_event_reminder($settings, $timing)) {
                            $result['skipped']++;
                            continue;
                        }

                        if (!eventify_reminder_window_hit($startsAt, $nowDt, $timing, (int) $offsetSeconds, $graceSeconds)) {
                            $result['skipped']++;
                            continue;
                        }

                        if (eventify_reminder_was_sent($conn, $userId, $eventId, $scheduleDate, $timing)) {
                            $result['skipped']++;
                            continue;
                        }

                        $title = $timing === 'at_start' ? 'Event starting' : 'Event reminder';
                        $message = eventify_build_event_reminder_message(
                            (string) ($event['title'] ?? 'Event'),
                            $startsAt,
                            $timing,
                            $mode
                        );
                        $ok = eventify_insert_user_notification(
                            $conn,
                            $userId,
                            'event_reminder',
                            $title,
                            $message,
                            $eventId
                        );
                        if ($ok) {
                            eventify_mark_reminder_sent($conn, $userId, $eventId, $scheduleDate, $timing);
                            $result['sent']++;
                        } else {
                            $result['errors'][] = 'notify_failed:user_' . $userId . ':event_' . $eventId;
                        }
                    }
                }
            }
        }

        eventify_run_activity_reminders($conn, $nowDt, $result);
    } catch (Throwable $e) {
        $result['ok'] = false;
        $result['errors'][] = $e->getMessage();
    }

    return $result;
}

/**
 * Throttled reminder run for dashboard maintenance (no cron on local dev).
 */
/**
 * Parse reminder lead time encoded in a reminder notification message.
 */
function eventify_parse_reminder_timing_from_message(string $message): ?string
{
    if (preg_match('/is starting now/i', $message)) {
        return 'at_start';
    }
    if (preg_match('/starting in 30 minutes|starts in 30 minutes|in 30 mins/i', $message)) {
        return '30_min';
    }
    if (preg_match('/starting in 1 hour|starts in 1 hour/i', $message)) {
        return '1_hour';
    }
    if (preg_match('/starting in 1 day|starts in 1 day/i', $message)) {
        return '1_day';
    }

    return null;
}

/**
 * Parse schedule date from reminder message body, e.g. "(Jul 11, 2026 at 7:30 AM)".
 */
function eventify_parse_reminder_schedule_date_from_message(string $message): ?string
{
    if (!preg_match('/\(([A-Za-z]{3} \d{1,2}, \d{4})/', $message, $match)) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('M j, Y', $match[1]);

    return $dt instanceof DateTimeImmutable ? $dt->format('Y-m-d') : null;
}

/**
 * Before deleting in-app notifications, record reminder sends so cron/dashboard
 * does not recreate the same event/activity reminders on the next page load.
 */
function eventify_backfill_reminder_ledger_for_user(mysqli $conn, int $userId): void
{
    if ($userId < 1) {
        return;
    }

    eventify_event_reminders_ensure_tables($conn);

    $stmt = $conn->prepare("
        SELECT type, message, event_id
        FROM notifications
        WHERE user_id = ? AND type IN ('event_reminder', 'activity_reminder')
    ");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    foreach ($rows as $row) {
        $type = (string) ($row['type'] ?? '');
        $message = (string) ($row['message'] ?? '');
        $eventId = (int) ($row['event_id'] ?? 0);
        $timing = eventify_parse_reminder_timing_from_message($message);
        if ($timing === null) {
            continue;
        }

        if ($type === 'event_reminder' && $eventId > 0) {
            $scheduleDate = eventify_parse_reminder_schedule_date_from_message($message);
            if ($scheduleDate !== null) {
                eventify_mark_reminder_sent($conn, $userId, $eventId, $scheduleDate, $timing);
            }
            continue;
        }

        if ($type === 'activity_reminder' && $eventId > 0) {
            $activityTitle = '';
            if (preg_match('/^(.+?) \([^)]+\) starts in /', $message, $titleMatch)) {
                $activityTitle = trim($titleMatch[1]);
            }
            if ($activityTitle === '') {
                continue;
            }
            $sessionStmt = $conn->prepare(
                'SELECT id FROM event_day_sessions WHERE event_id = ? AND title = ? LIMIT 1'
            );
            if (!$sessionStmt) {
                continue;
            }
            $sessionStmt->bind_param('is', $eventId, $activityTitle);
            $sessionStmt->execute();
            $sessionRow = $sessionStmt->get_result()->fetch_assoc();
            $sessionStmt->close();
            $sessionId = (int) ($sessionRow['id'] ?? 0);
            if ($sessionId > 0) {
                eventify_mark_activity_reminder_sent($conn, $userId, $sessionId, $timing);
            }
        }
    }
}

function eventify_maybe_run_event_reminders(mysqli $conn): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $minInterval = 120;
    if (function_exists('eventify_env')) {
        $cfg = (int) (eventify_env('EVENTIFY_REMINDER_MIN_INTERVAL', '120') ?? '120');
        if ($cfg >= 60) {
            $minInterval = $cfg;
        }
    }

    $stampFile = dirname(__DIR__) . '/cron/.reminder_last_run';
    $lastRun = is_readable($stampFile) ? (int) trim((string) file_get_contents($stampFile)) : 0;
    if ($lastRun > 0 && (time() - $lastRun) < $minInterval) {
        return;
    }

    eventify_run_event_reminders($conn);

    $dir = dirname($stampFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($stampFile, (string) time());
}
