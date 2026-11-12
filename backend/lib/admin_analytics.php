<?php
/**
 * Admin "Analytics & insights" numbers.
 *
 * Definitions used everywhere below (so the numbers agree with each other):
 * - Approved event : status is not 'pending' or 'rejected'.
 * - Ended event    : approved, and closed/completed or its last day is before today.
 * - Walk-in        : a registrations row created at the moment of check-in (open-entry
 *                    events insert the row on scan), i.e. registration_date >= time_in - 10s.
 * - RSVP           : any registrations row that is not a walk-in.
 * - Show-up rate   : RSVPs marked present ÷ all RSVPs, for ENDED events only
 *                    (upcoming events would unfairly pull the rate down).
 */

/** @return bool */
function eventify_analytics_table_exists(mysqli $conn, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    try {
        $safe = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
        $cache[$table] = (bool) ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $cache[$table] = false;
    }
    return $cache[$table];
}

function eventify_analytics_column_exists(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $t = $conn->real_escape_string($table);
        $c = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
        $cache[$key] = (bool) ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

/** @return list<array<string, mixed>> */
function eventify_analytics_rows(mysqli $conn, string $sql): array
{
    try {
        $res = $conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function eventify_analytics_pct(int $part, int $whole): ?float
{
    return $whole > 0 ? round(($part / $whole) * 100, 1) : null;
}

/**
 * Split a stored events.department value into individual departments.
 * Stored as 'ALL', a single code, or a JSON array of codes.
 *
 * @return list<string>
 */
function eventify_analytics_split_departments(string $stored): array
{
    $s = trim($stored);
    if ($s === '' || strtoupper($s) === 'ALL') {
        return ['ALL'];
    }
    if ($s[0] === '[') {
        $arr = json_decode($s, true);
        if (is_array($arr)) {
            $out = [];
            foreach ($arr as $d) {
                $d = trim((string) $d);
                if ($d !== '') {
                    $out[] = $d;
                }
            }
            return $out !== [] ? array_values(array_unique($out)) : ['ALL'];
        }
    }
    return [$s];
}

/**
 * @return array<string, mixed>
 */
function eventify_admin_analytics(mysqli $conn): array
{
    $out = [
        'participation' => [],
        'reach' => [],
        'trend' => ['labels' => [], 'events' => [], 'attendees' => []],
        'dept_events' => ['labels' => [], 'counts' => []],
        'dept_participation' => [],
        'year_participation' => [],
        'top_events' => [],
        'organizers' => [],
        'weekday' => ['labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], 'counts' => [0, 0, 0, 0, 0, 0, 0]],
        'hours' => ['labels' => [], 'counts' => []],
        'activities' => [],
        'feedback' => [],
        'insights' => [],
    ];

    $hasEndDate = eventify_analytics_column_exists($conn, 'events', 'end_date');
    $hasCapacity = eventify_analytics_column_exists($conn, 'events', 'max_capacity');
    $lastDay = $hasEndDate ? 'COALESCE(e.end_date, e.date)' : 'e.date';
    $approved = "e.status NOT IN ('pending','rejected')";
    $ended = "({$approved} AND (e.status IN ('closed','completed') OR {$lastDay} < CURDATE()))";
    $walkIn = "(r.time_in IS NOT NULL AND r.registration_date >= r.time_in - INTERVAL 10 SECOND)";
    $present = "(r.status = 'present' AND r.time_in IS NOT NULL)";
    $hasFeedback = eventify_analytics_table_exists($conn, 'event_feedback');

    // ---- Participation (approved events only) ---------------------------------------
    $p = eventify_analytics_rows($conn, "
        SELECT
            SUM(CASE WHEN NOT {$walkIn} THEN 1 ELSE 0 END) AS rsvps,
            SUM(CASE WHEN {$present} THEN 1 ELSE 0 END) AS checkins,
            SUM(CASE WHEN {$present} AND {$walkIn} THEN 1 ELSE 0 END) AS walkins,
            SUM(CASE WHEN {$ended} AND NOT {$walkIn} THEN 1 ELSE 0 END) AS ended_rsvps,
            SUM(CASE WHEN {$ended} AND NOT {$walkIn} AND {$present} THEN 1 ELSE 0 END) AS ended_rsvps_present,
            COUNT(DISTINCT CASE WHEN {$present} THEN r.user_id END) AS unique_attendees
        FROM registrations r
        JOIN events e ON e.id = r.event_id
        WHERE {$approved}
    ")[0] ?? [];
    $rsvps = (int) ($p['rsvps'] ?? 0);
    $checkins = (int) ($p['checkins'] ?? 0);
    $endedRsvps = (int) ($p['ended_rsvps'] ?? 0);
    $endedPresent = (int) ($p['ended_rsvps_present'] ?? 0);

    $heldEvents = (int) (eventify_analytics_rows($conn, "SELECT COUNT(*) AS c FROM events e WHERE {$ended}")[0]['c'] ?? 0);
    $heldWithAttendance = (int) (eventify_analytics_rows($conn, "
        SELECT COUNT(DISTINCT r.event_id) AS c FROM registrations r JOIN events e ON e.id = r.event_id
        WHERE {$ended} AND {$present}
    ")[0]['c'] ?? 0);
    $heldCheckins = (int) (eventify_analytics_rows($conn, "
        SELECT COUNT(*) AS c FROM registrations r JOIN events e ON e.id = r.event_id WHERE {$ended} AND {$present}
    ")[0]['c'] ?? 0);

    $out['participation'] = [
        'registrations' => $rsvps,
        'checkins' => $checkins,
        'walkins' => (int) ($p['walkins'] ?? 0),
        'ended_rsvps' => $endedRsvps,
        'no_shows' => max(0, $endedRsvps - $endedPresent),
        'show_up_rate' => eventify_analytics_pct($endedPresent, $endedRsvps),
        'unique_attendees' => (int) ($p['unique_attendees'] ?? 0),
        'held_events' => $heldEvents,
        'avg_attendance' => $heldEvents > 0 ? round($heldCheckins / $heldEvents, 1) : null,
        'events_no_attendance' => max(0, $heldEvents - $heldWithAttendance),
    ];

    // ---- Student reach ----------------------------------------------------------------
    $s = eventify_analytics_rows($conn, "
        SELECT COUNT(*) AS students,
               SUM(CASE WHEN u.created_at >= (NOW() - INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS new_30d
        FROM users u WHERE u.role = 'student' AND u.status = 'active'
    ")[0] ?? [];
    $students = (int) ($s['students'] ?? 0);
    $activeStudents = (int) (eventify_analytics_rows($conn, "
        SELECT COUNT(DISTINCT r.user_id) AS c
        FROM registrations r JOIN events e ON e.id = r.event_id JOIN users u ON u.id = r.user_id
        WHERE {$approved} AND {$present} AND u.role = 'student' AND u.status = 'active'
    ")[0]['c'] ?? 0);
    $active30 = (int) (eventify_analytics_rows($conn, "
        SELECT COUNT(DISTINCT r.user_id) AS c
        FROM registrations r JOIN events e ON e.id = r.event_id JOIN users u ON u.id = r.user_id
        WHERE {$approved} AND {$present} AND r.time_in >= (NOW() - INTERVAL 30 DAY)
          AND u.role = 'student' AND u.status = 'active'
    ")[0]['c'] ?? 0);
    $out['reach'] = [
        'students' => $students,
        'new_30d' => (int) ($s['new_30d'] ?? 0),
        'participating' => $activeStudents,
        'participation_rate' => eventify_analytics_pct($activeStudents, $students),
        'never_attended' => max(0, $students - $activeStudents),
        'active_30d' => $active30,
        'avg_events_per_participant' => $activeStudents > 0
            ? round((int) (eventify_analytics_rows($conn, "
                SELECT COUNT(*) AS c FROM registrations r JOIN events e ON e.id = r.event_id JOIN users u ON u.id = r.user_id
                WHERE {$approved} AND {$present} AND u.role = 'student' AND u.status = 'active'
            ")[0]['c'] ?? 0) / $activeStudents, 1)
            : null,
    ];

    // ---- 12-month trend: approved events by start month, check-ins by check-in month --
    $months = [];
    $cursor = new DateTime('first day of this month');
    $cursor->modify('-11 months');
    for ($i = 0; $i < 12; $i++) {
        $months[$cursor->format('Y-m')] = ['label' => $cursor->format('M Y'), 'events' => 0, 'attendees' => 0];
        $cursor->modify('+1 month');
    }
    $firstMonth = array_key_first($months) . '-01';
    foreach (eventify_analytics_rows($conn, "
        SELECT DATE_FORMAT(e.date, '%Y-%m') AS ym, COUNT(*) AS c FROM events e
        WHERE {$approved} AND e.date >= '{$firstMonth}' GROUP BY ym
    ") as $r) {
        if (isset($months[$r['ym']])) {
            $months[$r['ym']]['events'] = (int) $r['c'];
        }
    }
    foreach (eventify_analytics_rows($conn, "
        SELECT DATE_FORMAT(r.time_in, '%Y-%m') AS ym, COUNT(*) AS c
        FROM registrations r JOIN events e ON e.id = r.event_id
        WHERE {$approved} AND {$present} AND r.time_in >= '{$firstMonth}' GROUP BY ym
    ") as $r) {
        if (isset($months[$r['ym']])) {
            $months[$r['ym']]['attendees'] = (int) $r['c'];
        }
    }
    foreach ($months as $m) {
        $out['trend']['labels'][] = $m['label'];
        $out['trend']['events'][] = $m['events'];
        $out['trend']['attendees'][] = $m['attendees'];
    }

    // ---- Events by department (multi-department events count once per department) ----
    $deptCounts = [];
    foreach (eventify_analytics_rows($conn, "SELECT department FROM events e WHERE {$approved}") as $r) {
        foreach (eventify_analytics_split_departments((string) ($r['department'] ?? '')) as $d) {
            $deptCounts[$d] = ($deptCounts[$d] ?? 0) + 1;
        }
    }
    arsort($deptCounts);
    foreach ($deptCounts as $d => $c) {
        $out['dept_events']['labels'][] = $d === 'ALL' ? 'All Departments (school-wide)' : (string) $d;
        $out['dept_events']['counts'][] = (int) $c;
    }

    // ---- Participation by student department -----------------------------------------
    foreach (eventify_analytics_rows($conn, "
        SELECT COALESCE(NULLIF(TRIM(u.department), ''), 'Unassigned') AS dept,
               COUNT(*) AS students,
               COUNT(DISTINCT a.user_id) AS participants,
               COALESCE(SUM(a.cnt), 0) AS checkins
        FROM users u
        LEFT JOIN (
            SELECT r.user_id, COUNT(*) AS cnt FROM registrations r JOIN events e ON e.id = r.event_id
            WHERE {$approved} AND {$present} GROUP BY r.user_id
        ) a ON a.user_id = u.id
        WHERE u.role = 'student' AND u.status = 'active'
        GROUP BY dept ORDER BY students DESC
    ") as $r) {
        $st = (int) $r['students'];
        $pa = (int) $r['participants'];
        $out['dept_participation'][] = [
            'dept' => (string) $r['dept'],
            'students' => $st,
            'participants' => $pa,
            'checkins' => (int) $r['checkins'],
            'rate' => eventify_analytics_pct($pa, $st),
        ];
    }

    // ---- Participation by year level ---------------------------------------------------
    if (eventify_analytics_column_exists($conn, 'users', 'student_year_level')) {
        foreach (eventify_analytics_rows($conn, "
            SELECT COALESCE(NULLIF(TRIM(u.student_year_level), ''), 'Not set') AS yl,
                   COUNT(*) AS students, COUNT(DISTINCT a.user_id) AS participants
            FROM users u
            LEFT JOIN (
                SELECT DISTINCT r.user_id FROM registrations r JOIN events e ON e.id = r.event_id
                WHERE {$approved} AND {$present}
            ) a ON a.user_id = u.id
            WHERE u.role = 'student' AND u.status = 'active'
            GROUP BY yl ORDER BY yl
        ") as $r) {
            $out['year_participation'][] = [
                'year' => (string) $r['yl'],
                'students' => (int) $r['students'],
                'participants' => (int) $r['participants'],
                'rate' => eventify_analytics_pct((int) $r['participants'], (int) $r['students']),
            ];
        }
    }

    // ---- Per-event stats (top events + organizer rollup) ------------------------------
    $fbJoin = $hasFeedback
        ? 'LEFT JOIN (SELECT event_id, AVG(rating) AS avg_rating, COUNT(*) AS fb_count FROM event_feedback GROUP BY event_id) fb ON fb.event_id = e.id'
        : '';
    $fbCols = $hasFeedback ? 'fb.avg_rating, fb.fb_count' : 'NULL AS avg_rating, 0 AS fb_count';
    $capCol = $hasCapacity ? 'e.max_capacity' : 'NULL AS max_capacity';
    $eventRows = eventify_analytics_rows($conn, "
        SELECT e.id, e.title, e.date, e.status, e.department, e.organizer_id, {$capCol},
               COALESCE(org.name, 'Unknown') AS organizer_name,
               CASE WHEN {$ended} THEN 1 ELSE 0 END AS is_ended,
               COALESCE(SUM(CASE WHEN r.id IS NOT NULL AND NOT {$walkIn} THEN 1 ELSE 0 END), 0) AS rsvps,
               COALESCE(SUM(CASE WHEN r.id IS NOT NULL AND {$present} THEN 1 ELSE 0 END), 0) AS attended,
               COALESCE(SUM(CASE WHEN r.id IS NOT NULL AND {$present} AND NOT {$walkIn} THEN 1 ELSE 0 END), 0) AS rsvp_attended,
               {$fbCols}
        FROM events e
        LEFT JOIN users org ON org.id = e.organizer_id
        LEFT JOIN registrations r ON r.event_id = e.id
        {$fbJoin}
        WHERE {$approved}
        GROUP BY e.id
    ");
    $events = [];
    foreach ($eventRows as $r) {
        $rs = (int) $r['rsvps'];
        $at = (int) $r['attended'];
        $cap = isset($r['max_capacity']) && (int) $r['max_capacity'] > 0 ? (int) $r['max_capacity'] : null;
        $events[] = [
            'id' => (int) $r['id'],
            'title' => (string) $r['title'],
            'date' => (string) $r['date'],
            'organizer_id' => (int) $r['organizer_id'],
            'organizer' => (string) $r['organizer_name'],
            'is_ended' => (int) $r['is_ended'] === 1,
            'rsvps' => $rs,
            'attended' => $at,
            'walkins' => $at - (int) $r['rsvp_attended'],
            'show_up' => (int) $r['is_ended'] === 1 ? eventify_analytics_pct((int) $r['rsvp_attended'], $rs) : null,
            'capacity' => $cap,
            'fill_rate' => $cap ? eventify_analytics_pct($rs, $cap) : null,
            'avg_rating' => $r['avg_rating'] !== null ? round((float) $r['avg_rating'], 2) : null,
            'fb_count' => (int) $r['fb_count'],
        ];
    }
    $top = $events;
    usort($top, static function ($a, $b) {
        return [$b['attended'], $b['rsvps']] <=> [$a['attended'], $a['rsvps']];
    });
    $out['top_events'] = array_slice(array_values(array_filter($top, static function ($e) {
        return $e['attended'] > 0 || $e['rsvps'] > 0;
    })), 0, 10);

    $orgs = [];
    foreach ($events as $e) {
        $id = $e['organizer_id'];
        if (!isset($orgs[$id])) {
            $orgs[$id] = ['name' => $e['organizer'], 'events' => 0, 'attended' => 0, 'ended_rsvps' => 0, 'ended_present' => 0, 'rating_sum' => 0.0, 'rating_n' => 0];
        }
        $orgs[$id]['events']++;
        $orgs[$id]['attended'] += $e['attended'];
        if ($e['is_ended']) {
            $orgs[$id]['ended_rsvps'] += $e['rsvps'];
            $orgs[$id]['ended_present'] += $e['attended'] - $e['walkins'];
        }
        if ($e['avg_rating'] !== null && $e['fb_count'] > 0) {
            $orgs[$id]['rating_sum'] += $e['avg_rating'] * $e['fb_count'];
            $orgs[$id]['rating_n'] += $e['fb_count'];
        }
    }
    foreach ($orgs as $o) {
        $out['organizers'][] = [
            'name' => $o['name'],
            'events' => $o['events'],
            'attended' => $o['attended'],
            'show_up' => eventify_analytics_pct($o['ended_present'], $o['ended_rsvps']),
            'avg_rating' => $o['rating_n'] > 0 ? round($o['rating_sum'] / $o['rating_n'], 2) : null,
            'fb_count' => $o['rating_n'],
        ];
    }
    usort($out['organizers'], static function ($a, $b) {
        return [$b['attended'], $b['events']] <=> [$a['attended'], $a['events']];
    });

    // ---- When students check in: weekday and hour -------------------------------------
    foreach (eventify_analytics_rows($conn, "
        SELECT WEEKDAY(r.time_in) AS wd, COUNT(*) AS c FROM registrations r JOIN events e ON e.id = r.event_id
        WHERE {$approved} AND {$present} GROUP BY wd
    ") as $r) {
        $wd = (int) $r['wd'];
        if ($wd >= 0 && $wd <= 6) {
            $out['weekday']['counts'][$wd] = (int) $r['c'];
        }
    }
    $hours = array_fill(0, 24, 0);
    foreach (eventify_analytics_rows($conn, "
        SELECT HOUR(r.time_in) AS h, COUNT(*) AS c FROM registrations r JOIN events e ON e.id = r.event_id
        WHERE {$approved} AND {$present} GROUP BY h
    ") as $r) {
        $hours[(int) $r['h']] = (int) $r['c'];
    }
    $nonZero = array_keys(array_filter($hours));
    if ($nonZero !== []) {
        $from = max(0, min($nonZero) - 1);
        $to = min(23, max($nonZero) + 1);
        for ($h = $from; $h <= $to; $h++) {
            $out['hours']['labels'][] = date('g A', mktime($h, 0, 0));
            $out['hours']['counts'][] = $hours[$h];
        }
    }

    // ---- Activities (sub-events inside events) ----------------------------------------
    if (eventify_analytics_table_exists($conn, 'event_day_sessions')) {
        $act = eventify_analytics_rows($conn, "
            SELECT COUNT(*) AS activities FROM event_day_sessions s JOIN events e ON e.id = s.event_id WHERE {$approved}
        ")[0] ?? [];
        $actCheckins = eventify_analytics_table_exists($conn, 'event_day_session_attendance')
            ? (int) (eventify_analytics_rows($conn, "
                SELECT COUNT(*) AS c FROM event_day_session_attendance a
                JOIN event_day_sessions s ON s.id = a.session_id JOIN events e ON e.id = s.event_id WHERE {$approved}
            ")[0]['c'] ?? 0)
            : 0;
        $actRsvps = eventify_analytics_table_exists($conn, 'event_day_session_rsvps')
            ? (int) (eventify_analytics_rows($conn, "
                SELECT COUNT(*) AS c FROM event_day_session_rsvps v
                JOIN event_day_sessions s ON s.id = v.session_id JOIN events e ON e.id = s.event_id WHERE {$approved}
            ")[0]['c'] ?? 0)
            : 0;
        $actCount = (int) ($act['activities'] ?? 0);
        $out['activities'] = [
            'activities' => $actCount,
            'rsvps' => $actRsvps,
            'checkins' => $actCheckins,
            'avg_checkins' => $actCount > 0 ? round($actCheckins / $actCount, 1) : null,
        ];
    }

    // ---- Feedback response rate --------------------------------------------------------
    if ($hasFeedback) {
        $fbRow = eventify_analytics_rows($conn, "
            SELECT COUNT(*) AS c, SUM(CASE WHEN ef.rating <= 2 THEN 1 ELSE 0 END) AS low,
                   SUM(CASE WHEN ef.rating >= 4 THEN 1 ELSE 0 END) AS high
            FROM event_feedback ef JOIN events e ON e.id = ef.event_id WHERE {$ended}
        ")[0] ?? [];
        $fbEnded = (int) ($fbRow['c'] ?? 0);
        $fbTotal = (int) ($fbRow['c'] ?? 0);
        $out['feedback'] = [
            'responses' => $fbEnded,
            'response_rate' => eventify_analytics_pct($fbEnded, $heldCheckins),
            'satisfied_pct' => eventify_analytics_pct((int) ($fbRow['high'] ?? 0), $fbTotal),
            'unhappy_pct' => eventify_analytics_pct((int) ($fbRow['low'] ?? 0), $fbTotal),
        ];
    }

    $out['insights'] = eventify_admin_analytics_insights($conn, $out, $events);
    return $out;
}

/**
 * Plain-language observations generated from the numbers above.
 *
 * @param array<string, mixed> $a
 * @param list<array<string, mixed>> $events
 * @return list<array{tone: string, icon: string, text: string}>
 */
function eventify_admin_analytics_insights(mysqli $conn, array $a, array $events): array
{
    $tips = [];
    $add = static function (string $tone, string $icon, string $text) use (&$tips): void {
        $tips[] = ['tone' => $tone, 'icon' => $icon, 'text' => $text];
    };

    $p = $a['participation'];
    if ($p['show_up_rate'] !== null) {
        if ($p['show_up_rate'] < 50) {
            $add('bad', 'fa-user-xmark', "Only {$p['show_up_rate']}% of students who RSVP actually attend ({$p['no_shows']} no-shows on finished events). Consider reminders the day before.");
        } elseif ($p['show_up_rate'] >= 80) {
            $add('good', 'fa-user-check', "Strong show-up: {$p['show_up_rate']}% of RSVPs attended their event.");
        }
    }
    if ($p['events_no_attendance'] > 0) {
        $add('warn', 'fa-calendar-xmark', "{$p['events_no_attendance']} finished event(s) recorded zero check-ins — check if the QR was used or attendance was missed.");
    }

    $r = $a['reach'];
    if ($r['students'] > 0 && $r['participation_rate'] !== null) {
        $add($r['participation_rate'] >= 50 ? 'good' : 'warn', 'fa-users',
            "{$r['participating']} of {$r['students']} active students ({$r['participation_rate']}%) have attended at least one event; {$r['never_attended']} have never attended.");
    }

    $depts = array_values(array_filter($a['dept_participation'], static function ($d) {
        return $d['students'] >= 5 && $d['rate'] !== null && $d['dept'] !== 'Unassigned';
    }));
    if (count($depts) >= 2) {
        usort($depts, static function ($x, $y) {
            return $y['rate'] <=> $x['rate'];
        });
        $best = $depts[0];
        $worst = $depts[count($depts) - 1];
        if ($best['rate'] - $worst['rate'] >= 10) {
            $add('info', 'fa-building-columns', "{$best['dept']} is the most engaged department ({$best['rate']}% of students attended). {$worst['dept']} is the least ({$worst['rate']}%) — a good target for the next event.");
        }
    }

    $rated = array_values(array_filter($events, static function ($e) {
        return $e['fb_count'] >= 3 && $e['avg_rating'] !== null;
    }));
    if ($rated !== []) {
        usort($rated, static function ($x, $y) {
            return $y['avg_rating'] <=> $x['avg_rating'];
        });
        $topE = $rated[0];
        $add('good', 'fa-star', "Best-rated event: \"{$topE['title']}\" ({$topE['avg_rating']}/5 from {$topE['fb_count']} responses).");
        $low = $rated[count($rated) - 1];
        if (count($rated) > 1 && $low['avg_rating'] < 3.5) {
            $add('bad', 'fa-face-frown', "Lowest-rated event: \"{$low['title']}\" ({$low['avg_rating']}/5). Review its feedback comments.");
        }
    }

    $full = array_values(array_filter($events, static function ($e) {
        return $e['fill_rate'] !== null && $e['fill_rate'] >= 90 && !$e['is_ended'];
    }));
    if ($full !== []) {
        $add('info', 'fa-chair', count($full) . ' upcoming event(s) are at least 90% full — consider raising capacity or adding a second run.');
    }

    $wd = $a['weekday'];
    if (array_sum($wd['counts']) >= 10) {
        $max = max($wd['counts']);
        $idx = array_search($max, $wd['counts'], true);
        $full = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $add('info', 'fa-calendar-day', "Most check-ins happen on {$full[$idx]}s — schedule big events then for higher turnout.");
    }

    $fb = $a['feedback'];
    if (!empty($fb) && $fb['response_rate'] !== null && $fb['response_rate'] < 30 && $p['checkins'] >= 10) {
        $add('warn', 'fa-comment-dots', "Only {$fb['response_rate']}% of attendees leave feedback. Remind students to rate events after they attend.");
    }

    try {
        $old = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'pending' AND created_at < (NOW() - INTERVAL 3 DAY)");
        $oldCount = $old ? (int) ($old->fetch_assoc()['c'] ?? 0) : 0;
        if ($oldCount > 0) {
            $add('warn', 'fa-hourglass-half', "{$oldCount} event(s) have been waiting for approval for more than 3 days.");
        }
    } catch (Throwable $e) {
        // created_at missing on very old databases — skip this tip.
    }

    if ($tips === []) {
        $add('info', 'fa-circle-info', 'Not enough activity yet for insights. They will appear as events run and students check in.');
    }
    return $tips;
}
