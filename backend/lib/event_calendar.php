<?php

/**
 * Calendar, date-range, and specific schedule-day helpers for events.
 */

function eventify_events_has_end_date(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'end_date'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        // keep false
    }
    return $cache;
}

function eventify_event_schedule_dates_table_exists(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $res = $conn->query("SHOW TABLES LIKE 'event_schedule_dates'");
        if ($res && $res->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        // keep false
    }
    return $cache;
}

function eventify_event_schedule_dates_ensure_table(mysqli $conn): bool
{
    if (eventify_event_schedule_dates_table_exists($conn)) {
        return true;
    }
    $sql = @file_get_contents(__DIR__ . '/../../migrations/add_event_schedule_dates.sql');
    if ($sql === false || $sql === '') {
        return false;
    }
    try {
        if ($conn->multi_query($sql)) {
            while ($conn->more_results() && $conn->next_result()) {
                // drain
            }
        }
        return eventify_event_schedule_dates_table_exists($conn);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @return list<string> Y-m-d sorted unique
 */
function eventify_parse_schedule_dates_from_request(array $post): array
{
    $raw = $post['schedule_dates'] ?? [];
    if (!is_array($raw)) {
        $raw = $raw !== '' ? [$raw] : [];
    }
    $out = [];
    foreach ($raw as $d) {
        $d = substr(trim((string) $d), 0, 10);
        if ($d === '') {
            continue;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $d);
        if ($dt && $dt->format('Y-m-d') === $d) {
            $out[$d] = $d;
        }
    }
    $dates = array_values($out);
    sort($dates);
    return $dates;
}

/** @return 'single'|'range'|'specific' */
function eventify_resolve_schedule_mode_from_request(array $post): string
{
    $mode = strtolower(trim((string) ($post['schedule_mode'] ?? 'single')));
    if (!in_array($mode, ['single', 'range', 'specific'], true)) {
        $mode = 'single';
    }
    if ($mode === 'specific') {
        $dates = eventify_parse_schedule_dates_from_request($post);
        if (count($dates) > 1) {
            return 'specific';
        }
        if (count($dates) === 1) {
            return 'single';
        }
    }
    if ($mode === 'range') {
        $end = substr(trim((string) ($post['end_date'] ?? '')), 0, 10);
        $start = substr(trim((string) ($post['date'] ?? '')), 0, 10);
        if ($end !== '' && $start !== '' && $end > $start) {
            return 'range';
        }
    }
    return 'single';
}

function eventify_events_has_end_time_na(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'end_time_na'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        // keep false
    }
    return $cache;
}

function eventify_schedule_dates_have_end_time_columns(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM event_schedule_dates WHERE Field = 'end_time_na'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        // keep false
    }
    return $cache;
}

function eventify_schedule_dates_have_start_time_columns(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM event_schedule_dates WHERE Field = 'start_time'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        // keep false
    }
    return $cache;
}

/**
 * @return array{end_time: ?string, end_time_na: bool}
 */
function eventify_parse_event_end_time_from_request(array $post): array
{
    $option = strtolower(trim((string) ($post['end_time_option'] ?? 'none')));
    if ($option === 'na') {
        return ['end_time' => null, 'end_time_na' => true];
    }
    if ($option === 'time') {
        $t = trim((string) ($post['end_time'] ?? ''));
        if ($t !== '' && preg_match('/^\d{2}:\d{2}$/', $t)) {
            return ['end_time' => $t, 'end_time_na' => false];
        }
    }
    return ['end_time' => null, 'end_time_na' => false];
}

/**
 * @return array<string, string> Y-m-d => H:i
 */
function eventify_parse_schedule_day_start_times_from_request(array $post): array
{
    $raw = $post['schedule_day_start'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $ymd => $time) {
        $ymd = substr(trim((string) $ymd), 0, 10);
        if ($ymd === '') {
            continue;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $ymd);
        if (!$dt || $dt->format('Y-m-d') !== $ymd) {
            continue;
        }
        $t = trim((string) $time);
        if ($t !== '' && preg_match('/^\d{2}:\d{2}$/', $t)) {
            $out[$ymd] = $t;
        }
    }
    return $out;
}

/**
 * @return array<string, array{end_time: ?string, end_time_na: bool}>
 */
function eventify_parse_schedule_day_end_times_from_request(array $post): array
{
    $raw = $post['schedule_day_end'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $ymd => $row) {
        $ymd = substr(trim((string) $ymd), 0, 10);
        if ($ymd === '' || !is_array($row)) {
            continue;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $ymd);
        if (!$dt || $dt->format('Y-m-d') !== $ymd) {
            continue;
        }
        $mode = strtolower(trim((string) ($row['mode'] ?? 'none')));
        if ($mode === 'na') {
            $out[$ymd] = ['end_time' => null, 'end_time_na' => true];
            continue;
        }
        if ($mode === 'time') {
            $t = trim((string) ($row['time'] ?? ''));
            if ($t !== '' && preg_match('/^\d{2}:\d{2}$/', $t)) {
                $out[$ymd] = ['end_time' => $t, 'end_time_na' => false];
            }
        }
    }
    return $out;
}

function eventify_format_end_time_label(?string $endTime, bool $endTimeNa = false): string
{
    if ($endTimeNa) {
        return 'N/A';
    }
    $endTime = trim((string) $endTime);
    if ($endTime === '') {
        return '';
    }
    $ts = strtotime($endTime);
    return $ts ? date('g:i A', $ts) : $endTime;
}

/**
 * @param list<string> $scheduleDates
 * @param array<string, array{end_time: ?string, end_time_na: bool}> $dayEndTimes
 * @param array<string, string> $dayStartTimes Y-m-d => H:i
 */
function eventify_save_event_schedule_dates(mysqli $conn, int $eventId, array $scheduleDates, array $dayEndTimes = [], array $dayStartTimes = []): void
{
    if ($eventId < 1 || !eventify_event_schedule_dates_ensure_table($conn)) {
        return;
    }
    $del = $conn->prepare('DELETE FROM event_schedule_dates WHERE event_id = ?');
    if ($del) {
        $del->bind_param('i', $eventId);
        $del->execute();
        $del->close();
    }
    if (count($scheduleDates) < 2) {
        return;
    }

    $hasEndCols = eventify_schedule_dates_have_end_time_columns($conn);
    $hasStartCols = eventify_schedule_dates_have_start_time_columns($conn);
    if ($hasStartCols && $hasEndCols) {
        $ins = $conn->prepare('INSERT INTO event_schedule_dates (event_id, schedule_date, start_time, end_time, end_time_na) VALUES (?, ?, ?, ?, ?)');
    } elseif ($hasEndCols) {
        $ins = $conn->prepare('INSERT INTO event_schedule_dates (event_id, schedule_date, end_time, end_time_na) VALUES (?, ?, ?, ?)');
    } else {
        $ins = $conn->prepare('INSERT INTO event_schedule_dates (event_id, schedule_date) VALUES (?, ?)');
    }
    if (!$ins) {
        return;
    }

    foreach ($scheduleDates as $ymd) {
        $ymd = substr(trim((string) $ymd), 0, 10);
        if ($ymd === '') {
            continue;
        }
        $dayEnd = $dayEndTimes[$ymd] ?? ['end_time' => null, 'end_time_na' => false];
        $et = $dayEnd['end_time'] ?? null;
        $na = !empty($dayEnd['end_time_na']) ? 1 : 0;
        $st = isset($dayStartTimes[$ymd]) && $dayStartTimes[$ymd] !== '' ? $dayStartTimes[$ymd] : null;
        if ($hasStartCols && $hasEndCols) {
            $ins->bind_param('isssi', $eventId, $ymd, $st, $et, $na);
        } elseif ($hasEndCols) {
            $ins->bind_param('issi', $eventId, $ymd, $et, $na);
        } else {
            $ins->bind_param('is', $eventId, $ymd);
        }
        $ins->execute();
    }
    $ins->close();
}

/**
 * @param list<int> $eventIds
 * @return array<int, list<string>>
 */
function eventify_load_schedule_dates_map(mysqli $conn, array $eventIds): array
{
    $daysMap = eventify_load_schedule_days_map($conn, $eventIds);
    $map = [];
    foreach ($daysMap as $eid => $days) {
        $map[$eid] = array_column($days, 'schedule_date');
    }
    return $map;
}

/**
 * @param list<int> $eventIds
 * @return array<int, list<array{schedule_date: string, start_time: ?string, end_time: ?string, end_time_na: bool}>>
 */
function eventify_load_schedule_days_map(mysqli $conn, array $eventIds): array
{
    $map = [];
    $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds), static function ($id) {
        return $id > 0;
    })));
    if ($eventIds === [] || !eventify_event_schedule_dates_table_exists($conn)) {
        return $map;
    }
    $hasEndCols = eventify_schedule_dates_have_end_time_columns($conn);
    $hasStartCols = eventify_schedule_dates_have_start_time_columns($conn);
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $types = str_repeat('i', count($eventIds));
    $cols = 'event_id, schedule_date';
    if ($hasStartCols) {
        $cols .= ', start_time';
    }
    if ($hasEndCols) {
        $cols .= ', end_time, end_time_na';
    }
    $sql = "SELECT {$cols} FROM event_schedule_dates WHERE event_id IN ($placeholders) ORDER BY schedule_date ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $map;
    }
    $stmt->bind_param($types, ...$eventIds);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $eid = (int) ($row['event_id'] ?? 0);
        $ymd = substr(trim((string) ($row['schedule_date'] ?? '')), 0, 10);
        if ($eid > 0 && $ymd !== '') {
            if (!isset($map[$eid])) {
                $map[$eid] = [];
            }
            $map[$eid][] = [
                'schedule_date' => $ymd,
                'start_time' => $hasStartCols ? (trim((string) ($row['start_time'] ?? '')) ?: null) : null,
                'end_time' => $hasEndCols ? (trim((string) ($row['end_time'] ?? '')) ?: null) : null,
                'end_time_na' => $hasEndCols && !empty($row['end_time_na']),
            ];
        }
    }
    $stmt->close();
    return $map;
}

/**
 * @param array<string, mixed> $e
 * @return list<string>
 */
/**
 * @return list<string> Y-m-d
 */
function eventify_dates_between_inclusive(string $startYmd, string $endYmd): array
{
    $start = DateTime::createFromFormat('Y-m-d', substr($startYmd, 0, 10));
    $end = DateTime::createFromFormat('Y-m-d', substr($endYmd, 0, 10));
    if (!$start || !$end || $start->format('Y-m-d') !== substr($startYmd, 0, 10) || $end->format('Y-m-d') !== substr($endYmd, 0, 10)) {
        return [];
    }
    if ($end < $start) {
        return [];
    }
    $out = [];
    $cur = clone $start;
    while ($cur <= $end) {
        $out[] = $cur->format('Y-m-d');
        $cur->modify('+1 day');
    }
    return $out;
}

function eventify_event_get_schedule_dates(array $e): array
{
    if (!empty($e['schedule_days']) && is_array($e['schedule_days'])) {
        $dates = [];
        foreach ($e['schedule_days'] as $day) {
            $d = substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10);
            if ($d !== '') {
                $dates[$d] = $d;
            }
        }
        $dates = array_values($dates);
        sort($dates);
        if (count($dates) > 0) {
            return $dates;
        }
    }
    if (!empty($e['schedule_dates']) && is_array($e['schedule_dates'])) {
        $dates = [];
        foreach ($e['schedule_dates'] as $d) {
            $d = substr(trim((string) $d), 0, 10);
            if ($d !== '') {
                $dates[$d] = $d;
            }
        }
        $dates = array_values($dates);
        sort($dates);
        if (count($dates) > 0) {
            return $dates;
        }
    }
    return [];
}

/**
 * Every calendar day that should show this event (picked days or date–end_date range).
 *
 * @return list<string> Y-m-d
 */
function eventify_event_calendar_display_dates(array $e): array
{
    $scheduleDates = eventify_event_get_schedule_dates($e);
    if ($scheduleDates !== []) {
        return $scheduleDates;
    }
    $start = substr(trim((string) ($e['date'] ?? '')), 0, 10);
    if ($start === '') {
        return [];
    }
    $end = eventify_event_resolve_end_date($e);
    if ($end === '' || $end <= $start) {
        return [$start];
    }

    return eventify_dates_between_inclusive($start, $end);
}

/** @param list<string> $dates */
function eventify_schedule_dates_are_consecutive_range(array $dates): bool
{
    $dates = array_values(array_unique(array_filter(array_map(static function ($d) {
        return substr(trim((string) $d), 0, 10);
    }, $dates))));
    sort($dates);
    if (count($dates) < 2) {
        return false;
    }
    $filled = eventify_dates_between_inclusive($dates[0], $dates[count($dates) - 1]);
    return count($filled) === count($dates);
}

/**
 * Split schedule dates into maximal consecutive runs (e.g. Jun 9–13 and Jun 15 when 14 is skipped).
 *
 * @param list<string> $dates
 * @return list<list<string>>
 */
function eventify_schedule_dates_consecutive_segments(array $dates): array
{
    $dates = array_values(array_unique(array_filter(array_map(static function ($d) {
        return substr(trim((string) $d), 0, 10);
    }, $dates))));
    sort($dates);
    if ($dates === []) {
        return [];
    }
    if (count($dates) === 1) {
        return [$dates];
    }

    $segments = [];
    $current = [$dates[0]];
    for ($i = 1, $n = count($dates); $i < $n; $i++) {
        $prev = $dates[$i - 1];
        $next = $dates[$i];
        $gap = eventify_dates_between_inclusive($prev, $next);
        if (count($gap) === 2) {
            $current[] = $next;
            continue;
        }
        $segments[] = $current;
        $current = [$next];
    }
    $segments[] = $current;

    return $segments;
}

/** Non-consecutive picked days (intramurals-style) — may still use connected bars per consecutive run. */
function eventify_event_has_specific_schedule(array $e): bool
{
    $dates = eventify_event_get_schedule_dates($e);
    return count($dates) > 1 && !eventify_schedule_dates_are_consecutive_range($dates);
}

/** @deprecated Use segment-aware calendar entries instead. */
function eventify_event_use_per_day_calendar_entries(array $e): bool
{
    return eventify_event_has_specific_schedule($e);
}

/** @return array<string, array{start_time: ?string, end_time: ?string, end_time_na: bool}> */
function eventify_event_schedule_days_by_ymd(array $e): array
{
    $map = [];
    foreach ($e['schedule_days'] ?? [] as $day) {
        if (!is_array($day)) {
            continue;
        }
        $ymd = substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10);
        if ($ymd === '') {
            continue;
        }
        $map[$ymd] = [
            'start_time' => isset($day['start_time']) && $day['start_time'] !== '' ? trim((string) $day['start_time']) : null,
            'end_time' => isset($day['end_time']) && $day['end_time'] !== '' ? trim((string) $day['end_time']) : null,
            'end_time_na' => !empty($day['end_time_na']),
        ];
    }
    return $map;
}

function eventify_event_resolve_end_date(array $e): string
{
    if (eventify_event_has_specific_schedule($e)) {
        $dates = eventify_event_get_schedule_dates($e);
        return (string) end($dates);
    }
    $start = substr(trim((string) ($e['date'] ?? '')), 0, 10);
    $end = substr(trim((string) ($e['end_date'] ?? '')), 0, 10);
    $scheduleDates = eventify_event_get_schedule_dates($e);
    if (count($scheduleDates) > 1 && eventify_schedule_dates_are_consecutive_range($scheduleDates)) {
        $lastSchedule = $scheduleDates[count($scheduleDates) - 1];
        if ($end === '' || $end < $lastSchedule) {
            $end = $lastSchedule;
        }
    }
    if ($start === '') {
        return $end;
    }
    if ($end === '' || $end < $start) {
        return $start;
    }
    return $end;
}

function eventify_event_is_multi_day(array $e): bool
{
    if (eventify_event_has_specific_schedule($e)) {
        return true;
    }
    $start = substr(trim((string) ($e['date'] ?? '')), 0, 10);
    $end = eventify_event_resolve_end_date($e);
    return $start !== '' && $end > $start;
}

/**
 * FullCalendar start, end, and allDay for one calendar day of an event.
 *
 * @return array{start: string, end: string|null, allDay: bool}
 */
function eventify_event_fullcalendar_times(array $e, ?string $forDate = null): array
{
    $startDate = $forDate !== null && $forDate !== ''
        ? substr($forDate, 0, 10)
        : substr(trim((string) ($e['date'] ?? '')), 0, 10);
    $endDate = eventify_event_resolve_end_date($e);
    $startTime = trim((string) ($e['start_time'] ?? ''));
    $endTimeNa = !empty($e['end_time_na']);
    $endTime = $endTimeNa ? '' : trim((string) ($e['end_time'] ?? ''));

    if ($startDate === '') {
        return ['start' => '', 'end' => null, 'allDay' => true];
    }

    $hasStartTime = $startTime !== '';
    if ($hasStartTime) {
        $start = $startDate . 'T' . (strlen($startTime) === 5 ? $startTime . ':00' : $startTime);
        if ($endTime !== '') {
            $end = $startDate . 'T' . (strlen($endTime) === 5 ? $endTime . ':00' : $endTime);
        } else {
            $startDt = DateTime::createFromFormat('Y-m-d H:i:s', $startDate . ' ' . $startTime);
            if (!$startDt) {
                $startDt = DateTime::createFromFormat('Y-m-d H:i', $startDate . ' ' . $startTime);
            }
            $end = $startDt ? $startDt->modify('+1 hour')->format('Y-m-d\TH:i:s') : ($startDate . 'T23:59:59');
        }

        return ['start' => $start, 'end' => $end, 'allDay' => false];
    }

    return ['start' => $startDate, 'end' => null, 'allDay' => true];
}

/** @return array{bg: string, border: string, text: string} */
function eventify_calendar_colors_for_lifecycle_state(string $state): array
{
    return match ($state) {
        'pending' => ['bg' => '#d97706', 'border' => '#d97706', 'text' => '#ffffff'],
        'upcoming' => ['bg' => '#f59e0b', 'border' => '#f59e0b', 'text' => '#ffffff'],
        'active' => ['bg' => '#16a34a', 'border' => '#16a34a', 'text' => '#ffffff'],
        'rejected' => ['bg' => '#dc2626', 'border' => '#dc2626', 'text' => '#ffffff'],
        default => ['bg' => '#6b7280', 'border' => '#6b7280', 'text' => '#ffffff'],
    };
}

/**
 * Calendar pill color for one day of an event (today = green only while that day's window is still open).
 *
 * @param array<string, mixed>|null $scheduleDay schedule_date, start_time, end_time, end_time_na
 */
function eventify_calendar_lifecycle_state_for_day(string $status, string $ymd, ?string $startTime = null, ?array $scheduleDay = null): string
{
    $status = strtolower(trim($status));
    if ($status === 'rejected') {
        return 'rejected';
    }
    if ($status === 'pending') {
        return 'pending';
    }
    if ($status === 'closed' || $status === 'completed') {
        return 'closed';
    }

    $today = (new DateTimeImmutable('now', eventify_calendar_app_timezone()))->format('Y-m-d');
    $day = substr(trim($ymd), 0, 10);
    if ($day > $today) {
        return 'upcoming';
    }
    if ($day < $today) {
        return 'closed';
    }

    if ($status === 'active' && is_array($scheduleDay) && eventify_schedule_day_has_ended($scheduleDay)) {
        return 'closed';
    }

    return 'active';
}

/**
 * @param array<string, mixed> $entry
 * @param array<string, mixed> $e
 */
function eventify_event_fullcalendar_apply_day_colors(array &$entry, array $e, string $ymd): void
{
    $props = $entry['extendedProps'] ?? [];
    $status = (string) ($props['status'] ?? $e['status'] ?? 'active');
    $startTime = isset($props['start_time']) ? (string) $props['start_time'] : null;
    $daysByYmd = eventify_event_schedule_days_by_ymd($e);
    $scheduleDay = [
        'schedule_date' => $ymd,
        'start_time' => $startTime ?? ($e['start_time'] ?? ''),
        'end_time' => $props['end_time'] ?? ($e['end_time'] ?? ''),
        'end_time_na' => !empty($props['end_time_na']) || !empty($e['end_time_na']),
    ];
    if (isset($daysByYmd[$ymd]) && is_array($daysByYmd[$ymd])) {
        $scheduleDay = array_merge($scheduleDay, $daysByYmd[$ymd]);
        $scheduleDay['schedule_date'] = $ymd;
    }
    $state = eventify_calendar_lifecycle_state_for_day($status, $ymd, $startTime, $scheduleDay);
    $colors = eventify_calendar_colors_for_lifecycle_state($state);
    $entry['backgroundColor'] = $colors['bg'];
    $entry['borderColor'] = $colors['border'];
    $entry['textColor'] = $colors['text'];
    if (!isset($entry['extendedProps']) || !is_array($entry['extendedProps'])) {
        $entry['extendedProps'] = [];
    }
    $entry['extendedProps']['calendar_segment_state'] = $state;
    $entry['extendedProps']['calendar_color_locked'] = true;
}

/**
 * @param array<string, mixed> $e
 * @param array<string, mixed> $baseProps
 * @return array<string, mixed>
 */
function eventify_event_fullcalendar_single_day_entry(
    array $e,
    string $ymd,
    string $title,
    array $baseProps,
    array $daysByYmd
): array {
    $eid = (int) ($e['id'] ?? 0);
    $groupId = $eid > 0 ? 'event-' . $eid : null;
    $dayEvent = $e;
    $dayStart = null;
    $dayEnd = null;
    $dayEndNa = false;
    if (isset($daysByYmd[$ymd])) {
        if (!empty($daysByYmd[$ymd]['start_time'])) {
            $dayEvent['start_time'] = $daysByYmd[$ymd]['start_time'];
            $dayStart = $daysByYmd[$ymd]['start_time'];
        }
        $dayEvent['end_time'] = $daysByYmd[$ymd]['end_time'];
        $dayEvent['end_time_na'] = $daysByYmd[$ymd]['end_time_na'];
        $dayEnd = $daysByYmd[$ymd]['end_time'];
        $dayEndNa = $daysByYmd[$ymd]['end_time_na'];
    }
    $fc = eventify_event_fullcalendar_times($dayEvent, $ymd);
    $entry = [
        'id' => $eid > 0 ? ($eid . '-' . $ymd) : $ymd,
        'title' => $title,
        'start' => $fc['start'],
        'end' => $fc['end'],
        'allDay' => $fc['allDay'],
        'extendedProps' => array_merge($baseProps, [
            'event_id' => $eid,
            'schedule_date_ymd' => $ymd,
            'start_time' => $dayStart ?? ($e['start_time'] ?? null),
            'end_time' => $dayEnd,
            'end_time_na' => $dayEndNa,
            'event_allows_checkin' => eventify_schedule_day_allows_checkin_now([
                'schedule_date' => $ymd,
                'start_time' => $dayStart ?? ($e['start_time'] ?? ''),
                'end_time' => $dayEnd ?? '',
                'end_time_na' => $dayEndNa,
            ], new DateTimeImmutable('now', eventify_calendar_app_timezone())),
        ]),
    ];
    if ($groupId !== null) {
        $entry['groupId'] = $groupId;
    }
    eventify_event_fullcalendar_apply_day_colors($entry, $e, $ymd);

    return $entry;
}

/**
 * Per-day lifecycle states for a consecutive date run (used by connected range bars).
 *
 * @param list<string> $segment
 * @return array<string, string>
 */
function eventify_calendar_segment_day_states(array $e, array $segment, array $baseProps): array
{
    $status = (string) ($baseProps['status'] ?? $e['status'] ?? 'active');
    $daysByYmd = eventify_event_schedule_days_by_ymd($e);
    $out = [];
    foreach ($segment as $ymd) {
        $startTime = null;
        if (isset($daysByYmd[$ymd]['start_time']) && trim((string) $daysByYmd[$ymd]['start_time']) !== '') {
            $startTime = (string) $daysByYmd[$ymd]['start_time'];
        } elseif (isset($baseProps['start_time']) && trim((string) $baseProps['start_time']) !== '') {
            $startTime = (string) $baseProps['start_time'];
        }
        $scheduleDay = [
            'schedule_date' => $ymd,
            'start_time' => $startTime ?? ($e['start_time'] ?? ''),
            'end_time' => $daysByYmd[$ymd]['end_time'] ?? ($e['end_time'] ?? ''),
            'end_time_na' => !empty($daysByYmd[$ymd]['end_time_na']) || !empty($e['end_time_na']),
        ];
        if (isset($daysByYmd[$ymd]) && is_array($daysByYmd[$ymd])) {
            $scheduleDay = array_merge($scheduleDay, $daysByYmd[$ymd]);
            $scheduleDay['schedule_date'] = $ymd;
        }
        $out[$ymd] = eventify_calendar_lifecycle_state_for_day($status, $ymd, $startTime, $scheduleDay);
    }

    return $out;
}

/**
 * Consecutive days render as one connected bar; isolated days stay as single blocks.
 *
 * @param list<string> $scheduleDates
 * @return list<array<string, mixed>>
 */
function eventify_event_fullcalendar_segment_entries(
    array $e,
    array $scheduleDates,
    string $title,
    array $baseProps
): array {
    $eid = (int) ($e['id'] ?? 0);
    $groupId = $eid > 0 ? 'event-' . $eid : null;
    $daysByYmd = eventify_event_schedule_days_by_ymd($e);
    $entries = [];

    foreach (eventify_schedule_dates_consecutive_segments($scheduleDates) as $segment) {
        if (count($segment) >= 2) {
            $segmentStart = $segment[0];
            $segmentEnd = $segment[count($segment) - 1];
            $dt = DateTime::createFromFormat('Y-m-d', $segmentEnd);
            $exclusiveEnd = $dt ? $dt->modify('+1 day')->format('Y-m-d') : null;
            $entry = [
                'id' => $eid > 0 ? ($eid . '-' . $segmentStart . '_' . $segmentEnd) : ($segmentStart . '_' . $segmentEnd),
                'title' => $title,
                'start' => $segmentStart,
                'end' => $exclusiveEnd,
                'allDay' => true,
                'classNames' => ['eventify-fc-range-event'],
                'extendedProps' => array_merge($baseProps, [
                    'event_id' => $eid,
                    'schedule_date_ymd' => $segmentStart,
                    'segment_dates' => $segment,
                    'segment_day_states' => eventify_calendar_segment_day_states($e, $segment, $baseProps),
                    'calendar_range_multiday' => true,
                    'start_time' => $daysByYmd[$segmentStart]['start_time'] ?? ($e['start_time'] ?? null),
                    'end_time' => $daysByYmd[$segmentEnd]['end_time'] ?? null,
                    'end_time_na' => !empty($daysByYmd[$segmentEnd]['end_time_na']),
                ]),
            ];
            if ($groupId !== null) {
                $entry['groupId'] = $groupId;
            }
            $entries[] = $entry;
            continue;
        }

        foreach ($segment as $ymd) {
            $entries[] = eventify_event_fullcalendar_single_day_entry($e, $ymd, $title, $baseProps, $daysByYmd);
        }
    }

    return $entries;
}

/**
 * Build one or more FullCalendar event objects (specific days => one block per day).
 *
 * @param array<string, mixed> $e
 * @param callable|null $extendedPropsBuilder fn(array $e): array
 * @return list<array<string, mixed>>
 */
function eventify_event_fullcalendar_entries(array $e, ?callable $extendedPropsBuilder = null): array
{
    $eid = (int) ($e['id'] ?? 0);
    $title = (string) ($e['title'] ?? 'Untitled');
    $baseProps = $extendedPropsBuilder ? $extendedPropsBuilder($e) : [];
    $displayDates = eventify_event_calendar_display_dates($e);
    $scheduleDates = $displayDates !== [] ? $displayDates : eventify_event_get_schedule_dates($e);

    $startYmd = !empty($e['date']) ? substr(trim((string) $e['date']), 0, 10) : '';
    $endYmd = eventify_event_resolve_end_date($e);
    $baseProps['event_date_ymd'] = $startYmd;
    $baseProps['event_end_ymd'] = $endYmd;
    $regMode = strtolower(trim((string) ($e['registration_mode'] ?? 'rsvp')));
    $baseProps['event_allows_rsvp'] = eventify_event_is_upcoming($e)
        && !in_array($regMode, ['paid_ticket', 'open'], true);
    $baseProps['event_allows_checkin'] = eventify_event_allows_checkin($e);
    $baseProps['schedule_dates'] = $scheduleDates;
    if (count($scheduleDates) > 1) {
        $baseProps['schedule_mode'] = eventify_event_has_specific_schedule($e) ? 'specific' : 'range';
    } else {
        $baseProps['schedule_mode'] = eventify_event_is_multi_day($e) ? 'range' : 'single';
    }
    $baseProps['schedule_days'] = $e['schedule_days'] ?? [];

    if (count($displayDates) > 1) {
        return eventify_event_fullcalendar_segment_entries($e, $displayDates, $title, $baseProps);
    }

    $fc = eventify_event_fullcalendar_times($e);
    $entry = [
        'id' => $eid > 0 ? $eid : null,
        'title' => $title,
        'start' => $fc['start'],
        'end' => $fc['end'],
        'allDay' => $fc['allDay'],
        'extendedProps' => array_merge($baseProps, [
            'event_id' => $eid,
            'schedule_date_ymd' => $startYmd,
        ]),
    ];
    if ($eid > 0) {
        $entry['groupId'] = 'event-' . $eid;
    }
    eventify_event_fullcalendar_apply_day_colors($entry, $e, $startYmd);

    return [$entry];
}

/**
 * @param list<array<string, mixed>> $events
 * @return list<array<string, mixed>>
 */
function eventify_events_to_fullcalendar_list(array $events, ?callable $extendedPropsBuilder = null): array
{
    $out = [];
    foreach ($events as $e) {
        foreach (eventify_event_fullcalendar_entries($e, $extendedPropsBuilder) as $entry) {
            $out[] = $entry;
        }
    }
    return $out;
}

/** @param list<string> $dates Y-m-d */
function eventify_format_schedule_dates_list(array $dates): string
{
    $dates = array_values(array_unique(array_filter(array_map(static function ($d) {
        return substr(trim((string) $d), 0, 10);
    }, $dates))));
    sort($dates);
    if ($dates === []) {
        return '';
    }
    if (count($dates) === 1) {
        $t = strtotime($dates[0]);
        return $t ? date('M j, Y', $t) : $dates[0];
    }

    $byMonth = [];
    foreach ($dates as $ymd) {
        $t = strtotime($ymd);
        if (!$t) {
            continue;
        }
        $key = date('Y-m', $t);
        $byMonth[$key][] = (int) date('j', $t);
    }

    $parts = [];
    foreach ($byMonth as $ym => $days) {
        $t = strtotime($ym . '-01');
        $monthLabel = $t ? date('M', $t) : $ym;
        $year = $t ? date('Y', $t) : '';
        $dayStr = implode(', ', $days);
        $parts[] = $monthLabel . ' ' . $dayStr . ', ' . $year;
    }

    return implode(' · ', $parts);
}

/** Human-readable date (and optional time) range for display pages. */
function eventify_format_event_date_range(array $e, bool $includeTimes = true): string
{
    $scheduleDates = eventify_event_get_schedule_dates($e);
    $startDate = substr(trim((string) ($e['date'] ?? '')), 0, 10);
    if ($startDate === '' && $scheduleDates === []) {
        return '';
    }

    if (count($scheduleDates) > 1) {
        $datePart = eventify_format_schedule_dates_list($scheduleDates);
    } else {
        $endDate = eventify_event_resolve_end_date($e);
        $fmtDate = static function (string $ymd): string {
            $t = strtotime($ymd);
            return $t ? date('M j, Y', $t) : $ymd;
        };
        $datePart = $fmtDate($startDate);
        if ($endDate > $startDate && !eventify_event_has_specific_schedule($e)) {
            $datePart = $fmtDate($startDate) . ' – ' . $fmtDate($endDate);
        }
    }

    if (!$includeTimes) {
        return $datePart;
    }

    $startTime = trim((string) ($e['start_time'] ?? ''));
    $endTimeNa = !empty($e['end_time_na']);
    $endTime = trim((string) ($e['end_time'] ?? ''));
    $tEndLabel = eventify_format_end_time_label($endTime, $endTimeNa);

    if ($startTime === '') {
        return $datePart;
    }

    $tStart = date('g:i A', strtotime($startTime));
    $allScheduleDates = eventify_event_get_schedule_dates($e);
    if (count($allScheduleDates) > 1 && !empty($e['schedule_days']) && is_array($e['schedule_days'])) {
        $dayParts = [];
        $hasPerDayStart = false;
        foreach ($e['schedule_days'] as $day) {
            if (!is_array($day)) {
                continue;
            }
            $ymd = substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10);
            if ($ymd === '') {
                continue;
            }
            $t = strtotime($ymd);
            $lbl = $t ? date('M j', $t) : $ymd;
            $daySt = trim((string) ($day['start_time'] ?? ''));
            $stLbl = $daySt !== '' ? date('g:i A', strtotime($daySt)) : '';
            if ($stLbl !== '') {
                $hasPerDayStart = true;
            }
            $et = eventify_format_end_time_label($day['end_time'] ?? null, !empty($day['end_time_na']));
            $segment = $lbl;
            if ($stLbl !== '') {
                $segment .= ' ' . $stLbl;
            }
            if ($et !== '') {
                $segment .= '–' . $et;
            }
            $dayParts[] = $segment;
        }
        if ($dayParts !== []) {
            if ($hasPerDayStart) {
                return $datePart . ' · ' . implode('; ', $dayParts);
            }
            return $datePart . ' · Starts ' . $tStart . ' · ' . implode('; ', $dayParts);
        }
    }

    if ($tEndLabel !== '') {
        if (count($scheduleDates) > 1 || eventify_event_is_multi_day($e)) {
            return $datePart . ' · Starts ' . $tStart . ' · Ends ' . $tEndLabel;
        }
        return $datePart . ' · ' . $tStart . ' – ' . $tEndLabel;
    }

    return $datePart . ' · ' . $tStart;
}

/**
 * Attach schedule_dates onto each event row.
 *
 * @param list<array<string, mixed>> $events
 */
function eventify_events_attach_schedule_dates(mysqli $conn, array &$events): void
{
    $ids = array_column($events, 'id');
    $daysMap = eventify_load_schedule_days_map($conn, $ids);
    foreach ($events as &$e) {
        $eid = (int) ($e['id'] ?? 0);
        $days = $daysMap[$eid] ?? [];
        $e['schedule_days'] = $days;
        $e['schedule_dates'] = array_column($days, 'schedule_date');
    }
    unset($e);
}

/**
 * Last moment the event is considered "on" (last schedule day + end time).
 */
function eventify_event_effective_end_datetime(array $e): ?DateTimeImmutable
{
    $lastYmd = '';
    $lastEndTime = '';
    $lastEndTimeNa = false;
    $scheduleDays = $e['schedule_days'] ?? [];
    if (is_array($scheduleDays) && $scheduleDays !== []) {
        usort($scheduleDays, static function ($a, $b) {
            return strcmp(
                substr(trim((string) ($a['schedule_date'] ?? '')), 0, 10),
                substr(trim((string) ($b['schedule_date'] ?? '')), 0, 10)
            );
        });
        $lastDay = $scheduleDays[count($scheduleDays) - 1];
        if (is_array($lastDay)) {
            $lastYmd = substr(trim((string) ($lastDay['schedule_date'] ?? '')), 0, 10);
            $lastEndTimeNa = !empty($lastDay['end_time_na']);
            $lastEndTime = trim((string) ($lastDay['end_time'] ?? ''));
        }
    }
    if ($lastYmd === '') {
        $lastYmd = eventify_event_resolve_end_date($e);
        $lastEndTimeNa = !empty($e['end_time_na']);
        $lastEndTime = trim((string) ($e['end_time'] ?? ''));
    }
    if ($lastYmd === '') {
        return null;
    }
    if ($lastEndTimeNa || $lastEndTime === '') {
        $timePart = '23:59:59';
    } else {
        $timePart = strlen($lastEndTime) === 5 ? $lastEndTime . ':00' : substr($lastEndTime, 0, 8);
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $lastYmd . ' ' . $timePart, eventify_calendar_app_timezone());
    if ($dt instanceof DateTimeImmutable) {
        return $dt;
    }
    $dayOnly = DateTimeImmutable::createFromFormat('Y-m-d', $lastYmd);
    return $dayOnly ? $dayOnly->setTime(23, 59, 59) : null;
}

function eventify_calendar_app_timezone(): DateTimeZone
{
    $tzId = defined('EVENTIFY_APP_TIMEZONE') ? (string) EVENTIFY_APP_TIMEZONE : 'Asia/Manila';
    try {
        return new DateTimeZone($tzId);
    } catch (Throwable $e) {
        return new DateTimeZone('Asia/Manila');
    }
}

/**
 * Whether main-event QR check-in is open today (correct schedule day + within that day's time window).
 *
 * @param array<string, mixed> $e Event row with schedule_days attached when applicable
 */
function eventify_event_allows_checkin(array $e, ?DateTimeInterface $now = null): bool
{
    if (strtolower(trim((string) ($e['status'] ?? ''))) !== 'active') {
        return false;
    }
    $tz = eventify_calendar_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $todayYmd = $now->format('Y-m-d');

    $scheduleDays = $e['schedule_days'] ?? [];
    if (is_array($scheduleDays) && $scheduleDays !== []) {
        foreach ($scheduleDays as $day) {
            if (!is_array($day)) {
                continue;
            }
            $ymd = substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10);
            if ($ymd !== $todayYmd) {
                continue;
            }
            return eventify_schedule_day_allows_checkin_now($day, $now);
        }
        return false;
    }

    $startYmd = substr(trim((string) ($e['date'] ?? '')), 0, 10);
    $endYmd = eventify_event_resolve_end_date($e);
    if ($startYmd === '' || $todayYmd < $startYmd || ($endYmd !== '' && $todayYmd > $endYmd)) {
        return false;
    }

    $dayTimes = [
        'start_time' => trim((string) ($e['start_time'] ?? '')),
        'end_time' => !empty($e['end_time_na']) ? '' : trim((string) ($e['end_time'] ?? '')),
        'end_time_na' => !empty($e['end_time_na']),
        'schedule_date' => $todayYmd,
    ];
    return eventify_schedule_day_allows_checkin_now($dayTimes, $now);
}

/**
 * Human-readable reason when main-event check-in is closed, or null when open.
 *
 * @param array<string, mixed> $e
 */
function eventify_event_checkin_unavailable_reason(array $e, ?DateTimeInterface $now = null): ?string
{
    if (eventify_event_allows_checkin($e, $now)) {
        return null;
    }
    $st = strtolower(trim((string) ($e['status'] ?? '')));
    if ($st !== 'active') {
        return $st === 'closed' || $st === 'completed'
            ? 'This event has ended. Check-in is no longer available.'
            : 'Check-in is only available for approved, active events.';
    }

    $tz = eventify_calendar_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $todayYmd = $now->format('Y-m-d');
    $displayDates = eventify_event_calendar_display_dates($e);

    if ($displayDates === []) {
        $startYmd = substr(trim((string) ($e['date'] ?? '')), 0, 10);
        if ($startYmd !== '') {
            $endYmd = eventify_event_resolve_end_date($e);
            if ($todayYmd < $startYmd) {
                return 'Check-in opens on ' . date('M j, Y', strtotime($startYmd)) . '.';
            }
            if ($endYmd !== '' && $todayYmd > $endYmd) {
                return 'Check-in closed — this event ended on ' . date('M j, Y', strtotime($endYmd)) . '.';
            }
        }
        return 'Check-in is not available right now (' . $now->format('g:i A') . ').';
    }

    $firstDay = $displayDates[0];
    $lastDay = $displayDates[count($displayDates) - 1];

    if ($todayYmd < $firstDay) {
        return 'Check-in opens on ' . date('M j, Y', strtotime($firstDay))
            . ' during the scheduled event time (event runs through ' . date('M j, Y', strtotime($lastDay)) . ').';
    }
    if ($todayYmd > $lastDay) {
        return 'Check-in closed — this event ended on ' . date('M j, Y', strtotime($lastDay)) . '.';
    }
    if (!in_array($todayYmd, $displayDates, true)) {
        $nextDay = null;
        foreach ($displayDates as $ymd) {
            if ($ymd > $todayYmd) {
                $nextDay = $ymd;
                break;
            }
        }
        if ($nextDay !== null) {
            return 'No check-in today. The next event day is ' . date('M j, Y', strtotime($nextDay)) . '.';
        }
        return 'Check-in is not available on this date.';
    }

    $scheduleDays = $e['schedule_days'] ?? [];
    $dayRow = null;
    if (is_array($scheduleDays)) {
        foreach ($scheduleDays as $day) {
            if (!is_array($day)) {
                continue;
            }
            if (substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10) === $todayYmd) {
                $dayRow = $day;
                break;
            }
        }
    }
    if ($dayRow === null) {
        $dayRow = [
            'schedule_date' => $todayYmd,
            'start_time' => trim((string) ($e['start_time'] ?? '')),
            'end_time' => !empty($e['end_time_na']) ? '' : trim((string) ($e['end_time'] ?? '')),
            'end_time_na' => !empty($e['end_time_na']),
        ];
    }

    $start = trim((string) ($dayRow['start_time'] ?? ''));
    $end = !empty($dayRow['end_time_na']) ? '' : trim((string) ($dayRow['end_time'] ?? ''));
    if ($start !== '') {
        $stDt = eventify_calendar_datetime($todayYmd, $start, $tz);
        if ($stDt instanceof DateTimeImmutable && $now < $stDt) {
            return 'Check-in opens today at ' . $stDt->format('g:i A') . '.';
        }
    }
    if ($end !== '') {
        $etDt = eventify_calendar_datetime($todayYmd, $end, $tz);
        if ($etDt instanceof DateTimeImmutable && $now > $etDt) {
            $nextDay = null;
            foreach ($displayDates as $ymd) {
                if ($ymd > $todayYmd) {
                    $nextDay = $ymd;
                    break;
                }
            }
            if ($nextDay !== null) {
                return 'Check-in for today ended at ' . $etDt->format('g:i A')
                    . '. Come back on ' . date('M j, Y', strtotime($nextDay)) . '.';
            }
            return 'Check-in for today ended at ' . $etDt->format('g:i A') . '.';
        }
    }

    $window = eventify_event_today_checkin_window_label($e, $now);
    if ($window !== '') {
        return 'Check-in is not open right now (' . $now->format('g:i A') . '). Today\'s window: ' . $window . '.';
    }

    return 'Check-in is not open right now (' . $now->format('g:i A') . '). Please scan again during a scheduled event day.';
}

/**
 * Today's allowed check-in time window for display (main event).
 *
 * @param array<string, mixed> $e
 */
function eventify_event_today_checkin_window_label(array $e, ?DateTimeInterface $now = null): string
{
    $tz = eventify_calendar_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $todayYmd = $now->format('Y-m-d');
    $dayRow = null;
    $scheduleDays = $e['schedule_days'] ?? [];
    if (is_array($scheduleDays)) {
        foreach ($scheduleDays as $day) {
            if (!is_array($day)) {
                continue;
            }
            if (substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10) === $todayYmd) {
                $dayRow = $day;
                break;
            }
        }
    }
    if ($dayRow === null) {
        $displayDates = eventify_event_calendar_display_dates($e);
        if (!in_array($todayYmd, $displayDates, true)) {
            return '';
        }
        $dayRow = [
            'schedule_date' => $todayYmd,
            'start_time' => trim((string) ($e['start_time'] ?? '')),
            'end_time' => !empty($e['end_time_na']) ? '' : trim((string) ($e['end_time'] ?? '')),
            'end_time_na' => !empty($e['end_time_na']),
        ];
    }

    $start = trim((string) ($dayRow['start_time'] ?? ''));
    $end = !empty($dayRow['end_time_na']) ? '' : trim((string) ($dayRow['end_time'] ?? ''));
    $startLabel = '';
    $endLabel = '';
    if ($start !== '') {
        $stDt = eventify_calendar_datetime($todayYmd, $start, $tz);
        $startLabel = $stDt instanceof DateTimeImmutable ? $stDt->format('g:i A') : $start;
    }
    if ($end !== '') {
        $etDt = eventify_calendar_datetime($todayYmd, $end, $tz);
        $endLabel = $etDt instanceof DateTimeImmutable ? $etDt->format('g:i A') : $end;
    }
    if ($startLabel !== '' && $endLabel !== '') {
        return $startLabel . ' – ' . $endLabel;
    }
    if ($startLabel !== '') {
        return 'from ' . $startLabel;
    }
    if ($endLabel !== '') {
        return 'until ' . $endLabel;
    }

    return 'any time today';
}

/**
 * Student-facing check-in block context when main-event QR check-in is closed.
 *
 * @param array<string, mixed> $e
 * @return array{reason:string,schedule_label:string,today_date_label:string,now_label:string,today_window:string,timezone_short:string}
 */
function eventify_event_checkin_student_details(array $e, ?DateTimeInterface $now = null): array
{
    $tz = eventify_calendar_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);

    $displayDates = eventify_event_calendar_display_dates($e);
    $scheduleLabel = '';
    if (count($displayDates) > 1) {
        $scheduleLabel = date('M j', strtotime($displayDates[0]))
            . ' – ' . date('M j, Y', strtotime($displayDates[count($displayDates) - 1]));
    } elseif (count($displayDates) === 1) {
        $scheduleLabel = date('M j, Y', strtotime($displayDates[0]));
    } elseif (!empty($e['date'])) {
        $scheduleLabel = date('M j, Y', strtotime((string) $e['date']));
    }

    $reason = eventify_event_checkin_unavailable_reason($e, $now)
        ?? 'Check-in is not available right now.';

    return [
        'reason' => $reason,
        'schedule_label' => $scheduleLabel,
        'today_date_label' => $now->format('l, M j, Y'),
        'now_label' => $now->format('g:i A'),
        'today_window' => eventify_event_today_checkin_window_label($e, $now),
        'timezone_short' => $now->format('T'),
    ];
}

/**
 * @param array<string, mixed> $day schedule_date, start_time, end_time, end_time_na
 */
function eventify_schedule_day_allows_checkin_now(array $day, DateTimeImmutable $now): bool
{
    $ymd = substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10);
    if ($ymd === '' || $ymd !== $now->format('Y-m-d')) {
        return false;
    }
    if (eventify_schedule_day_has_ended($day, $now)) {
        return false;
    }
    $tz = $now->getTimezone();
    $start = trim((string) ($day['start_time'] ?? ''));
    $endNa = !empty($day['end_time_na']);
    $end = $endNa ? '' : trim((string) ($day['end_time'] ?? ''));
    if ($start === '' && $end === '') {
        return true;
    }
    if ($start !== '') {
        $st = eventify_calendar_datetime($ymd, $start, $tz);
        if ($st !== null && $now < $st) {
            return false;
        }
    }
    if ($end !== '') {
        $et = eventify_calendar_datetime($ymd, $end, $tz);
        if ($et !== null && $now > $et) {
            return false;
        }
    }
    return true;
}

/**
 * Whether a schedule day's time window has passed (used for calendar gray-out and auto-close).
 *
 * @param array<string, mixed>|null $day
 */
function eventify_schedule_day_has_ended(?array $day, ?DateTimeInterface $now = null): bool
{
    if (!is_array($day)) {
        return false;
    }
    $tz = eventify_calendar_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $ymd = substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10);
    if ($ymd === '') {
        return false;
    }
    $todayYmd = $now->format('Y-m-d');
    if ($ymd < $todayYmd) {
        return true;
    }
    if ($ymd > $todayYmd) {
        return false;
    }
    $endNa = !empty($day['end_time_na']);
    $end = $endNa ? '' : trim((string) ($day['end_time'] ?? ''));
    if ($end === '') {
        return false;
    }
    $et = eventify_calendar_datetime($ymd, $end, $tz);
    return $et instanceof DateTimeImmutable && $now > $et;
}

function eventify_calendar_datetime(string $dayYmd, string $time, DateTimeZone $tz): ?DateTimeImmutable
{
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $dayYmd . ' ' . $time, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
    }
    return null;
}

/** Whether an event should appear in student "upcoming" lists (active and not yet ended). */
function eventify_event_is_upcoming(array $e, ?DateTimeInterface $now = null): bool
{
    $status = strtolower(trim((string) ($e['status'] ?? '')));
    if ($status !== 'active') {
        return false;
    }
    $tz = eventify_calendar_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $end = eventify_event_effective_end_datetime($e);
    if ($end instanceof DateTimeInterface) {
        $end = $end->setTimezone($tz);
        return $end >= $now;
    }
    $start = substr(trim((string) ($e['date'] ?? '')), 0, 10);
    return $start !== '' && $start >= $now->format('Y-m-d');
}

/** Whether post-event evaluation should be offered (after effective end or organizer marked ended). */
function eventify_event_ended_for_feedback(array $e, ?DateTimeInterface $now = null): bool
{
    $st = strtolower(trim((string) ($e['status'] ?? '')));
    if ($st === 'closed' || $st === 'completed') {
        return true;
    }
    if ($st !== 'active') {
        return false;
    }
    $tz = eventify_calendar_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $end = eventify_event_effective_end_datetime($e);
    if ($end instanceof DateTimeInterface) {
        return $end->setTimezone($tz) < $now;
    }
    $start = substr(trim((string) ($e['date'] ?? '')), 0, 10);
    if ($start === '') {
        return false;
    }
    return $start < $now->format('Y-m-d');
}

/** Alias: active and before effective end — tickets, RSVP, and QR check-in allowed. */
function eventify_event_is_live(array $e, ?DateTimeInterface $now = null): bool
{
    return eventify_event_is_upcoming($e, $now);
}

/**
 * Label + badge for organizer/student UI.
 *
 * @return array{label: string, badge: string, is_live: bool}
 */
function eventify_event_status_ui(array $e): array
{
    $st = strtolower(trim((string) ($e['status'] ?? '')));
    if ($st === 'active' && eventify_event_is_live($e)) {
        return ['label' => 'Active', 'badge' => 'success', 'is_live' => true];
    }
    if ($st === 'active') {
        return ['label' => 'Ended', 'badge' => 'warning', 'is_live' => false];
    }
    if (in_array($st, ['closed', 'completed'], true)) {
        return ['label' => 'Closed', 'badge' => 'secondary', 'is_live' => false];
    }
    if ($st === 'pending') {
        return ['label' => 'Pending', 'badge' => 'warning', 'is_live' => false];
    }
    if ($st === 'rejected') {
        return ['label' => 'Rejected', 'badge' => 'danger', 'is_live' => false];
    }
    return ['label' => $st !== '' ? ucfirst($st) : 'Unknown', 'badge' => 'secondary', 'is_live' => false];
}

/**
 * Count published day activities for one event (0 when sessions table is unavailable).
 */
function eventify_event_hub_activity_count(mysqli $conn, int $eventId): int
{
    if ($eventId < 1) {
        return 0;
    }
    static $tableExists = null;
    if ($tableExists === null) {
        $tableExists = false;
        try {
            $t = $conn->query("SHOW TABLES LIKE 'event_day_sessions'");
            $tableExists = ($t && $t->num_rows > 0);
        } catch (Throwable $e) {
            $tableExists = false;
        }
    }
    if (!$tableExists) {
        return 0;
    }
    $st = $conn->prepare('SELECT COUNT(*) AS c FROM event_day_sessions WHERE event_id = ?');
    if (!$st) {
        return 0;
    }
    $st->bind_param('i', $eventId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    return (int) ($row['c'] ?? 0);
}

/**
 * @param list<array<string, mixed>> $events
 * @return list<array<string, mixed>>
 */
function eventify_merge_hub_events_by_id(array $events): array
{
    $byId = [];
    foreach ($events as $ev) {
        $id = (int) ($ev['id'] ?? 0);
        if ($id > 0) {
            $byId[$id] = array_merge($byId[$id] ?? [], $ev);
        }
    }
    $merged = array_values($byId);
    usort($merged, static function ($a, $b) {
        $da = substr(trim((string) ($a['date'] ?? '')), 0, 10);
        $db = substr(trim((string) ($b['date'] ?? '')), 0, 10);
        if ($da === $db) {
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        }

        return strcmp($da, $db);
    });

    return array_slice($merged, 0, 50);
}

/**
 * Active events for a student's activities hub — same department rules as the dashboard calendar.
 * Works without day-session migrations; events show before activities are published.
 *
 * @return list<array<string, mixed>>
 */
function eventify_load_student_activities_hub_list(mysqli $conn, int $userId, ?string $studentDepartment): array
{
    if ($userId < 1) {
        return [];
    }

    require_once __DIR__ . '/../../config/departments.php';

    $hasRegMode = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'registration_mode'");
        $hasRegMode = ($col && $col->num_rows > 0);
    } catch (Throwable $e) {
        $hasRegMode = false;
    }
    $regCol = $hasRegMode ? ', registration_mode' : '';

    $dept = trim((string) $studentDepartment);
    $rows = [];

    if ($dept !== '') {
        $deptSql = eventify_department_match_sql('department');
        $sql = "SELECT id, title, date, end_date, location, department, status{$regCol}
                FROM events
                WHERE status = 'active'
                  AND title NOT LIKE 'sample%'
                  AND {$deptSql}
                ORDER BY date ASC, id ASC
                LIMIT 50";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ss', $dept, $dept);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        }
    } else {
        $res = $conn->query(
            "SELECT id, title, date, end_date, location, department, status{$regCol}
             FROM events
             WHERE status = 'active' AND title NOT LIKE 'sample%'
             ORDER BY date ASC, id ASC
             LIMIT 50"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
    }

    $out = [];
    foreach ($rows as $row) {
        if (!eventify_student_sees_event_department((string) ($row['department'] ?? 'ALL'), $studentDepartment)) {
            continue;
        }
        $row['activity_count'] = eventify_event_hub_activity_count($conn, (int) ($row['id'] ?? 0));
        $out[] = $row;
    }

    // RSVP'd active events (even when department SQL above missed them)
    $regSql = "SELECT e.id, e.title, e.date, e.end_date, e.location, e.department, e.status{$regCol}
               FROM events e
               INNER JOIN registrations r ON r.event_id = e.id AND r.user_id = ?
               WHERE e.status = 'active'
               ORDER BY e.date ASC, e.id ASC
               LIMIT 24";
    $regStmt = $conn->prepare($regSql);
    if ($regStmt) {
        $regStmt->bind_param('i', $userId);
        $regStmt->execute();
        $regRes = $regStmt->get_result();
        while ($row = $regRes->fetch_assoc()) {
            if (!eventify_student_sees_event_department((string) ($row['department'] ?? 'ALL'), $studentDepartment)) {
                continue;
            }
            $row['activity_count'] = eventify_event_hub_activity_count($conn, (int) ($row['id'] ?? 0));
            $out[] = $row;
        }
        $regStmt->close();
    }

    return eventify_merge_hub_events_by_id($out);
}
