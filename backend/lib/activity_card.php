<?php
/**
 * Student activity card — event / activity rows for print and later teacher sign.
 */

if (!function_exists('eventify_activity_card_format_clock')) {
    function eventify_activity_card_format_clock(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '00:00:00') {
            return '';
        }
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)) {
            $ts = strtotime('1970-01-01 ' . $value);
            return $ts ? date('g:i A', $ts) : '';
        }
        $ts = strtotime($value);
        return $ts ? date('g:i A', $ts) : '';
    }
}

if (!function_exists('eventify_activity_card_has_column')) {
    function eventify_activity_card_has_column(mysqli $conn, string $table, string $column): bool
    {
        $table = preg_replace('/[^a-z0-9_]/i', '', $table) ?? '';
        $column = preg_replace('/[^a-z0-9_]/i', '', $column) ?? '';
        if ($table === '' || $column === '') {
            return false;
        }
        $chk = @$conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        $ok = $chk && $chk->num_rows > 0;
        if ($chk) {
            $chk->free();
        }
        return $ok;
    }
}

/**
 * @return list<array{kind:string,event_id:int,session_id:int,date:string,time_in:string,time_out:string,event:string,event_title:string,activity_title:string,signed:bool,signer:string}>
 */
if (!function_exists('eventify_activity_card_load_rows')) {
    function eventify_activity_card_load_rows(mysqli $conn, int $userId, ?string $department): array
    {
        $rows = [];
        $seen = [];
        if ($userId < 1) {
            return $rows;
        }

        $hasRegOut = eventify_activity_card_has_column($conn, 'registrations', 'time_out');
        $outSql = $hasRegOut ? ', r.time_out' : '';
        $st = $conn->prepare(
            "SELECT r.event_id, r.time_in{$outSql}, e.title, e.date
             FROM registrations r
             INNER JOIN events e ON e.id = r.event_id
             WHERE r.user_id = ? AND r.status = 'present' AND r.time_in IS NOT NULL
             ORDER BY r.time_in ASC"
        );
        if ($st) {
            $st->bind_param('i', $userId);
            if ($st->execute()) {
                $res = $st->get_result();
                while ($res && ($row = $res->fetch_assoc())) {
                    $eventId = (int) ($row['event_id'] ?? 0);
                    $ymd = substr((string) ($row['date'] ?? $row['time_in'] ?? ''), 0, 10);
                    $key = 'event:' . $eventId . ':' . $ymd;
                    $seen[$key] = true;
                    $eventTitle = (string) ($row['title'] ?? 'Event');
                    $rows[] = [
                        'kind' => 'event',
                        'event_id' => $eventId,
                        'session_id' => 0,
                        'date' => $ymd,
                        'time_in' => eventify_activity_card_format_clock((string) ($row['time_in'] ?? '')),
                        'time_out' => $hasRegOut ? eventify_activity_card_format_clock((string) ($row['time_out'] ?? '')) : '',
                        'event' => $eventTitle,
                        'event_title' => $eventTitle,
                        'activity_title' => '',
                        'signed' => false,
                        'signer' => '',
                    ];
                }
            }
            $st->close();
        }

        if (!function_exists('eventify_event_day_sessions_ensure_enhanced')) {
            $sessionsLib = __DIR__ . '/event_day_sessions.php';
            if (is_file($sessionsLib)) {
                require_once $sessionsLib;
            }
        }
        if (function_exists('eventify_event_day_sessions_ensure_enhanced')) {
            try {
                eventify_event_day_sessions_ensure_enhanced($conn);
            } catch (Throwable $e) {
                /* table may be missing on a fresh host */
            }
        }

        $hasActOut = eventify_activity_card_has_column($conn, 'event_day_session_attendance', 'checked_out_at');
        $actOutSql = $hasActOut ? ', a.checked_out_at' : '';
        $stA = @$conn->prepare(
            "SELECT a.session_id{$actOutSql}, a.checked_in_at, s.title AS activity_title, s.schedule_date,
                    e.id AS event_id, e.title AS event_title
             FROM event_day_session_attendance a
             INNER JOIN event_day_sessions s ON s.id = a.session_id
             INNER JOIN events e ON e.id = s.event_id
             WHERE a.user_id = ?
             ORDER BY a.checked_in_at ASC"
        );
        if ($stA) {
            $stA->bind_param('i', $userId);
            if ($stA->execute()) {
                $resA = $stA->get_result();
                while ($resA && ($row = $resA->fetch_assoc())) {
                    $eventId = (int) ($row['event_id'] ?? 0);
                    $sessionId = (int) ($row['session_id'] ?? 0);
                    $ymd = substr((string) ($row['schedule_date'] ?? $row['checked_in_at'] ?? ''), 0, 10);
                    $key = 'activity:' . $sessionId . ':' . $ymd;
                    $seen[$key] = true;
                    $activity = trim((string) ($row['activity_title'] ?? 'Activity'));
                    $eventTitle = trim((string) ($row['event_title'] ?? ''));
                    $label = $activity;
                    if ($eventTitle !== '' && strcasecmp($eventTitle, $activity) !== 0) {
                        $label = $activity . ' — ' . $eventTitle;
                    }
                    $rows[] = [
                        'kind' => 'activity',
                        'event_id' => $eventId,
                        'session_id' => $sessionId,
                        'date' => $ymd,
                        'time_in' => eventify_activity_card_format_clock((string) ($row['checked_in_at'] ?? '')),
                        'time_out' => $hasActOut ? eventify_activity_card_format_clock((string) ($row['checked_out_at'] ?? '')) : '',
                        'event' => $label,
                        'event_title' => $eventTitle !== '' ? $eventTitle : $activity,
                        'activity_title' => $activity,
                        'signed' => false,
                        'signer' => '',
                    ];
                }
            }
            $stA->close();
        }

        $deptSql = '1=1';
        $deptParams = [];
        $deptTypes = '';
        $department = trim((string) $department);
        if ($department !== '' && function_exists('eventify_department_match_sql')) {
            $deptSql = eventify_department_match_sql('e.department');
            $deptParams = [$department, $department];
            $deptTypes = 'ss';
        }
        $today = date('Y-m-d');
        $hasEndDate = eventify_activity_card_has_column($conn, 'events', 'end_date');
        $dateWhere = $hasEndDate
            ? '(e.date >= ? OR (e.end_date IS NOT NULL AND e.end_date >= ?))'
            : 'e.date >= ?';
        $sqlUp = "SELECT e.id, e.title, e.date, e.start_time, e.end_time
                  FROM events e
                  WHERE e.status IN ('active') AND {$deptSql} AND {$dateWhere}
                  ORDER BY e.date ASC, e.start_time ASC
                  LIMIT 40";
        $stU = $conn->prepare($sqlUp);
        if ($stU) {
            $dateParams = $hasEndDate ? [$today, $today] : [$today];
            $types = $deptTypes . str_repeat('s', count($dateParams));
            $params = array_merge($deptParams, $dateParams);
            $stU->bind_param($types, ...$params);
            if ($stU->execute()) {
                $resU = $stU->get_result();
                while ($resU && ($row = $resU->fetch_assoc())) {
                    $eventId = (int) ($row['id'] ?? 0);
                    $ymd = substr((string) ($row['date'] ?? ''), 0, 10);
                    $key = 'event:' . $eventId . ':' . $ymd;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $eventTitle = (string) ($row['title'] ?? 'Event');
                    $rows[] = [
                        'kind' => 'event',
                        'event_id' => $eventId,
                        'session_id' => 0,
                        'date' => $ymd,
                        'time_in' => '',
                        'time_out' => '',
                        'event' => $eventTitle,
                        'event_title' => $eventTitle,
                        'activity_title' => '',
                        'signed' => false,
                        'signer' => '',
                    ];
                }
            }
            $stU->close();
        }

        usort($rows, static function (array $a, array $b): int {
            $ad = (string) ($a['date'] ?? '');
            $bd = (string) ($b['date'] ?? '');
            if ($ad !== $bd) {
                return strcmp($ad, $bd);
            }
            return strcmp((string) ($a['event'] ?? ''), (string) ($b['event'] ?? ''));
        });

        return $rows;
    }
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array{dates: array<string, string>, events: array<int, string>, activities: array<int, string>}
 */
if (!function_exists('eventify_activity_card_filter_options')) {
    function eventify_activity_card_filter_options(array $rows): array
    {
        $dates = [];
        $events = [];
        $activities = [];
        foreach ($rows as $row) {
            $ymd = trim((string) ($row['date'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) === 1) {
                $dates[$ymd] = $ymd;
            }
            $eventId = (int) ($row['event_id'] ?? 0);
            $eventTitle = trim((string) ($row['event_title'] ?? $row['event'] ?? ''));
            if ($eventId > 0 && $eventTitle !== '') {
                $events[$eventId] = $eventTitle;
            }
            $sessionId = (int) ($row['session_id'] ?? 0);
            if ($sessionId > 0) {
                $activityTitle = trim((string) ($row['activity_title'] ?? ''));
                if ($activityTitle === '') {
                    $activityTitle = trim((string) ($row['event'] ?? 'Activity'));
                }
                $activities[$sessionId] = $activityTitle;
            }
        }
        ksort($dates);
        natcasesort($events);
        natcasesort($activities);

        return [
            'dates' => $dates,
            'events' => $events,
            'activities' => $activities,
        ];
    }
}

/**
 * @param list<array<string, mixed>> $rows
 * @param array{kind?:string,date?:string,event_id?:int,session_id?:int} $filters
 * @return list<array<string, mixed>>
 */
if (!function_exists('eventify_activity_card_apply_filters')) {
    function eventify_activity_card_apply_filters(array $rows, array $filters): array
    {
        $kind = strtolower(trim((string) ($filters['kind'] ?? 'all')));
        $date = trim((string) ($filters['date'] ?? ''));
        $eventId = (int) ($filters['event_id'] ?? 0);
        $sessionId = (int) ($filters['session_id'] ?? 0);

        $out = [];
        foreach ($rows as $row) {
            if ($sessionId > 0 && (int) ($row['session_id'] ?? 0) !== $sessionId) {
                continue;
            }
            if ($eventId > 0 && (int) ($row['event_id'] ?? 0) !== $eventId) {
                continue;
            }
            if ($date !== '' && trim((string) ($row['date'] ?? '')) !== $date) {
                continue;
            }
            $rowKind = (string) ($row['kind'] ?? '');
            if ($sessionId < 1) {
                if ($kind === 'events' && $rowKind !== 'event') {
                    continue;
                }
                if ($kind === 'activities' && $rowKind !== 'activity') {
                    continue;
                }
            }
            $out[] = $row;
        }

        return $out;
    }
}

/* =====================================================================
 * Teacher QR view + digital stamps
 * ---------------------------------------------------------------------
 * Each student gets a random share token (printed as a QR on the card).
 * Admins (teachers) scan it, sign in, see the card and apply a digital
 * stamp per row. Stamps are stored per row key so both card formats can
 * show them.
 * ===================================================================== */

if (!function_exists('eventify_activity_card_ensure_tables')) {
    function eventify_activity_card_ensure_tables(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        @$conn->query(
            "CREATE TABLE IF NOT EXISTS activity_card_share_tokens (
                user_id INT NOT NULL PRIMARY KEY,
                token CHAR(40) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_activity_card_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        @$conn->query(
            "CREATE TABLE IF NOT EXISTS activity_card_stamps (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                row_key VARCHAR(80) NOT NULL,
                stamped_by INT NOT NULL,
                stamped_by_name VARCHAR(150) NOT NULL DEFAULT '',
                stamped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_activity_card_stamp (student_id, row_key),
                KEY idx_activity_card_stamp_student (student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

/** Stable key for one card row: kind:event:session:date. */
if (!function_exists('eventify_activity_card_row_key')) {
    function eventify_activity_card_row_key(array $row): string
    {
        $kind = (string) ($row['kind'] ?? '');
        if ($kind !== 'event' && $kind !== 'activity') {
            return '';
        }
        $date = substr(trim((string) ($row['date'] ?? '')), 0, 10);
        return $kind . ':' . (int) ($row['event_id'] ?? 0) . ':' . (int) ($row['session_id'] ?? 0) . ':' . $date;
    }
}

/** Returns the student's share token, creating one on first use. */
if (!function_exists('eventify_activity_card_share_token')) {
    function eventify_activity_card_share_token(mysqli $conn, int $userId): string
    {
        if ($userId < 1) {
            return '';
        }
        eventify_activity_card_ensure_tables($conn);
        $st = $conn->prepare('SELECT token FROM activity_card_share_tokens WHERE user_id = ? LIMIT 1');
        if ($st) {
            $st->bind_param('i', $userId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if ($row && !empty($row['token'])) {
                return (string) $row['token'];
            }
        }
        $token = bin2hex(random_bytes(20));
        $ins = $conn->prepare('INSERT IGNORE INTO activity_card_share_tokens (user_id, token) VALUES (?, ?)');
        if ($ins) {
            $ins->bind_param('is', $userId, $token);
            $ins->execute();
            $ins->close();
        }
        return $token;
    }
}

/** Student id for a share token, or 0. */
if (!function_exists('eventify_activity_card_user_by_token')) {
    function eventify_activity_card_user_by_token(mysqli $conn, string $token): int
    {
        $token = strtolower(trim($token));
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
            return 0;
        }
        eventify_activity_card_ensure_tables($conn);
        $st = $conn->prepare('SELECT user_id FROM activity_card_share_tokens WHERE token = ? LIMIT 1');
        if (!$st) {
            return 0;
        }
        $st->bind_param('s', $token);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return (int) ($row['user_id'] ?? 0);
    }
}

/**
 * @return array<string, array{by:string, at:string}> row_key => stamp
 */
if (!function_exists('eventify_activity_card_load_stamps')) {
    function eventify_activity_card_load_stamps(mysqli $conn, int $studentId): array
    {
        $out = [];
        if ($studentId < 1) {
            return $out;
        }
        eventify_activity_card_ensure_tables($conn);
        $st = $conn->prepare('SELECT row_key, stamped_by_name, stamped_at FROM activity_card_stamps WHERE student_id = ?');
        if (!$st) {
            return $out;
        }
        $st->bind_param('i', $studentId);
        $st->execute();
        $res = $st->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $out[(string) $r['row_key']] = [
                'by' => (string) ($r['stamped_by_name'] ?? ''),
                'at' => (string) ($r['stamped_at'] ?? ''),
            ];
        }
        $st->close();
        return $out;
    }
}

/** Attach 'row_key', 'signed', 'signer', 'signed_at' to each row. */
if (!function_exists('eventify_activity_card_attach_stamps')) {
    function eventify_activity_card_attach_stamps(array $rows, array $stamps): array
    {
        foreach ($rows as &$row) {
            $key = eventify_activity_card_row_key($row);
            $row['row_key'] = $key;
            $stamp = $key !== '' ? ($stamps[$key] ?? null) : null;
            $row['signed'] = $stamp !== null;
            $row['signer'] = $stamp['by'] ?? '';
            $row['signed_at'] = $stamp['at'] ?? '';
        }
        unset($row);
        return $rows;
    }
}

/** Apply (true) or remove (false) a stamp. */
if (!function_exists('eventify_activity_card_set_stamp')) {
    function eventify_activity_card_set_stamp(mysqli $conn, int $studentId, string $rowKey, int $adminId, string $adminName, bool $on): bool
    {
        if ($studentId < 1 || $adminId < 1 || !preg_match('/^(event|activity):\d+:\d+:\d{4}-\d{2}-\d{2}$/', $rowKey)) {
            return false;
        }
        eventify_activity_card_ensure_tables($conn);
        if (!$on) {
            $del = $conn->prepare('DELETE FROM activity_card_stamps WHERE student_id = ? AND row_key = ?');
            if (!$del) {
                return false;
            }
            $del->bind_param('is', $studentId, $rowKey);
            $ok = $del->execute();
            $del->close();
            return $ok;
        }
        $ins = $conn->prepare(
            'INSERT INTO activity_card_stamps (student_id, row_key, stamped_by, stamped_by_name)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE stamped_by = VALUES(stamped_by), stamped_by_name = VALUES(stamped_by_name), stamped_at = CURRENT_TIMESTAMP'
        );
        if (!$ins) {
            return false;
        }
        $name = mb_substr($adminName, 0, 150);
        $ins->bind_param('isis', $studentId, $rowKey, $adminId, $name);
        $ok = $ins->execute();
        $ins->close();
        return $ok;
    }
}

/** Absolute URL a teacher's phone opens from the card QR. */
if (!function_exists('eventify_activity_card_view_url')) {
    function eventify_activity_card_view_url(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $base = $host !== '' ? $scheme . '://' . $host . BASE_URL : BASE_URL;
        return $base . '/activity_card_view.php?t=' . rawurlencode($token);
    }
}
