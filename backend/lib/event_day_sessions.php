<?php

/**
 * Day activities (sub-events) within a parent event — e.g. badminton @ Gym A on Jun 3.
 */

require_once __DIR__ . '/../../config/departments.php';
if (is_file(__DIR__ . '/../../config/student_sections.php')) {
    require_once __DIR__ . '/../../config/student_sections.php';
}
require_once __DIR__ . '/web_push.php';

function eventify_event_day_sessions_table_exists(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $res = $conn->query("SHOW TABLES LIKE 'event_day_sessions'");
        if ($res && $res->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function eventify_event_day_sessions_ensure_table(mysqli $conn): bool
{
    if (eventify_event_day_sessions_table_exists($conn)) {
        return true;
    }
    $sql = @file_get_contents(__DIR__ . '/../../migrations/add_event_day_sessions.sql');
    if ($sql === false || trim($sql) === '') {
        return false;
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt !== '') {
            @$conn->query($stmt);
        }
    }
    return eventify_event_day_sessions_table_exists($conn);
}

function eventify_day_sessions_have_geo_columns(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    if (!eventify_event_day_sessions_table_exists($conn)) {
        return false;
    }
    try {
        $col = $conn->query("SHOW COLUMNS FROM event_day_sessions WHERE Field = 'latitude'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function eventify_day_sessions_have_enhanced_columns(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    if (!eventify_event_day_sessions_table_exists($conn)) {
        return false;
    }
    try {
        $col = $conn->query("SHOW COLUMNS FROM event_day_sessions WHERE Field = 'category'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function eventify_day_sessions_have_checkin_time_columns(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    if (!eventify_event_day_sessions_table_exists($conn)) {
        return false;
    }
    try {
        $col = $conn->query("SHOW COLUMNS FROM event_day_sessions WHERE Field = 'checkin_start_time'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function eventify_day_sessions_have_attendance_mode_columns(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    if (!eventify_event_day_sessions_table_exists($conn)) {
        return false;
    }
    try {
        $col = $conn->query("SHOW COLUMNS FROM event_day_sessions WHERE Field = 'attendance_mode'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function eventify_save_session_attendance_settings(
    mysqli $conn,
    int $sessionId,
    string $attendanceMode,
    ?string $checkoutEnd
): void {
    if ($sessionId < 1) {
        return;
    }
    if (!function_exists('eventify_events_ensure_attendance_mode')) {
        require_once __DIR__ . '/event_calendar.php';
    }
    eventify_events_ensure_attendance_mode($conn);
    if (!eventify_day_sessions_have_attendance_mode_columns($conn)) {
        return;
    }
    $mode = $attendanceMode === 'time_in_out' ? 'time_in_out' : 'single';
    $stmt = $conn->prepare('UPDATE event_day_sessions SET attendance_mode = ?, checkout_end_time = ? WHERE id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ssi', $mode, $checkoutEnd, $sessionId);
    $stmt->execute();
    $stmt->close();
}

function eventify_event_day_sessions_ensure_enhanced(mysqli $conn): bool
{
    if (!eventify_event_day_sessions_ensure_table($conn)) {
        return false;
    }
    if (eventify_day_sessions_have_enhanced_columns($conn)) {
        eventify_event_day_sessions_ensure_rsvp_tables($conn);
        eventify_event_day_sessions_ensure_access_columns($conn);
        eventify_day_sessions_ensure_completed_status($conn);
        eventify_event_day_sessions_ensure_audience_columns($conn);
        if (!eventify_day_sessions_have_checkin_time_columns($conn)) {
            try {
                @$conn->query('ALTER TABLE event_day_sessions ADD COLUMN checkin_start_time time DEFAULT NULL AFTER end_time');
                @$conn->query('ALTER TABLE event_day_sessions ADD COLUMN checkin_end_time time DEFAULT NULL AFTER checkin_start_time');
            } catch (Throwable $e) {
                /* column may already exist */
            }
        }
        if (!function_exists('eventify_events_ensure_attendance_mode')) {
            require_once __DIR__ . '/event_calendar.php';
        }
        eventify_events_ensure_attendance_mode($conn);
        return true;
    }
    $alters = [
        "ALTER TABLE event_day_sessions ADD COLUMN category varchar(80) DEFAULT NULL AFTER title",
        "ALTER TABLE event_day_sessions ADD COLUMN notes text DEFAULT NULL AFTER location",
        "ALTER TABLE event_day_sessions ADD COLUMN status enum('scheduled','cancelled','delayed') NOT NULL DEFAULT 'scheduled' AFTER notes",
        "ALTER TABLE event_day_sessions ADD COLUMN max_capacity int(11) DEFAULT NULL AFTER end_time",
        "ALTER TABLE event_day_sessions ADD COLUMN contact_name varchar(100) DEFAULT NULL AFTER max_capacity",
        "ALTER TABLE event_day_sessions ADD COLUMN contact_phone varchar(50) DEFAULT NULL AFTER contact_name",
        "ALTER TABLE event_day_sessions ADD COLUMN checkin_token varchar(64) DEFAULT NULL AFTER contact_phone",
        "ALTER TABLE event_day_sessions ADD COLUMN checkin_start_time time DEFAULT NULL AFTER end_time",
        "ALTER TABLE event_day_sessions ADD COLUMN checkin_end_time time DEFAULT NULL AFTER checkin_start_time",
    ];
    foreach ($alters as $sql) {
        try {
            @$conn->query($sql);
        } catch (Throwable $e) {
            /* column may already exist */
        }
    }
    eventify_event_day_sessions_ensure_rsvp_tables($conn);
    eventify_event_day_sessions_ensure_access_columns($conn);
    eventify_day_sessions_ensure_completed_status($conn);
    eventify_event_day_sessions_ensure_audience_columns($conn);
    return eventify_day_sessions_have_enhanced_columns($conn);
}

function eventify_day_sessions_have_access_columns(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $res = $conn->query("SHOW COLUMNS FROM event_day_sessions LIKE 'access_mode'");
        if ($res && $res->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function eventify_event_day_sessions_ensure_access_columns(mysqli $conn): bool
{
    if (eventify_day_sessions_have_access_columns($conn)) {
        return true;
    }
    $sql = @file_get_contents(__DIR__ . '/../../migrations/add_session_access_mode.sql');
    if ($sql !== false && trim($sql) !== '') {
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt !== '') {
                try {
                    @$conn->query($stmt);
                } catch (Throwable $e) {
                    /* ignore */
                }
            }
        }
    }
    try {
        @$conn->query("ALTER TABLE event_day_sessions ADD COLUMN access_mode VARCHAR(20) NOT NULL DEFAULT 'free' AFTER status");
        @$conn->query("ALTER TABLE event_day_sessions ADD COLUMN ticket_type_id INT NULL DEFAULT NULL AFTER access_mode");
    } catch (Throwable $e) {
        /* ignore */
    }
    return eventify_day_sessions_have_access_columns($conn);
}

function eventify_day_sessions_have_audience_columns(mysqli $conn): bool
{
    static $cache = null;
    if ($cache === true) {
        return true;
    }
    if (!eventify_event_day_sessions_table_exists($conn)) {
        return false;
    }
    try {
        $res = $conn->query("SHOW COLUMNS FROM event_day_sessions LIKE 'department'");
        if ($res && $res->num_rows >= 1) {
            $cache = true;
            return true;
        }
    } catch (Throwable $e) {
        /* ignore */
    }
    return false;
}

function eventify_event_day_sessions_ensure_audience_columns(mysqli $conn): bool
{
    if (!eventify_event_day_sessions_ensure_table($conn)) {
        return false;
    }
    if (eventify_day_sessions_have_audience_columns($conn)) {
        return true;
    }
    eventify_events_department_ensure_varchar($conn);
    eventify_events_ensure_target_sections($conn);
    $sql = @file_get_contents(__DIR__ . '/../../migrations/add_session_audience_columns.sql');
    if ($sql !== false && trim($sql) !== '') {
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt !== '') {
                try {
                    @$conn->query($stmt);
                } catch (Throwable $e) {
                    /* column may already exist */
                }
            }
        }
    }
    try {
        @$conn->query('ALTER TABLE event_day_sessions ADD COLUMN department VARCHAR(800) NULL DEFAULT NULL AFTER title');
        @$conn->query('ALTER TABLE event_day_sessions ADD COLUMN target_sections VARCHAR(800) NULL DEFAULT NULL AFTER department');
    } catch (Throwable $e) {
        /* ignore */
    }
    try {
        $res = $conn->query("SHOW COLUMNS FROM event_day_sessions LIKE 'department'");
        return (bool) ($res && $res->num_rows > 0);
    } catch (Throwable $e) {
        return false;
    }
}

/** @param array<string, mixed> $session */
function eventify_session_audience_inherited(array $session): bool
{
    $dept = $session['department'] ?? null;
    $sec = $session['target_sections'] ?? null;
    return ($dept === null || trim((string) $dept) === '')
        && ($sec === null || trim((string) $sec) === '');
}

/**
 * Merge session overrides with parent event audience. NULL session fields inherit parent.
 *
 * @param array<string, mixed> $session
 * @param array<string, mixed> $parentEvent
 * @return array{department:string, target_sections:?string}
 */
function eventify_session_effective_audience(array $session, array $parentEvent): array
{
    $dept = $session['department'] ?? null;
    if ($dept === null || trim((string) $dept) === '') {
        $dept = (string) ($parentEvent['department'] ?? 'ALL');
    } else {
        $dept = trim((string) $dept);
    }
    $sec = $session['target_sections'] ?? null;
    if ($sec === null || trim((string) $sec) === '') {
        $sec = $parentEvent['target_sections'] ?? null;
    } else {
        $sec = trim((string) $sec);
    }
    if ($sec === '') {
        $sec = null;
    }

    return [
        'department' => $dept !== '' ? $dept : 'ALL',
        'target_sections' => $sec,
    ];
}

/**
 * @param array<string, mixed> $session
 * @param array<string, mixed> $parentEvent
 * @param array<string, mixed> $student
 */
function eventify_student_may_access_session(array $session, array $parentEvent, array $student): bool
{
    if (!eventify_student_may_access_event($parentEvent, $student)) {
        return false;
    }
    $effective = eventify_session_effective_audience($session, $parentEvent);
    if (eventify_session_audience_inherited($session)) {
        return true;
    }

    return eventify_student_may_access_event($effective, $student);
}

/**
 * @param array<string, mixed> $parentEvent
 * @return array{ok:bool, error?:string}
 */
function eventify_validate_session_audience_within_parent(
    ?string $sessionDepartment,
    ?string $sessionTargetSections,
    array $parentEvent
): array {
    if ($sessionDepartment === null && $sessionTargetSections === null) {
        return ['ok' => true];
    }
    $parentDept = (string) ($parentEvent['department'] ?? 'ALL');
    $parentDeptList = eventify_parse_stored_departments_list($parentDept);
    $parentIsAll = $parentDeptList === [] || in_array('ALL', $parentDeptList, true);

    if ($sessionDepartment !== null && trim($sessionDepartment) !== '') {
        $sessionDeptList = eventify_parse_stored_departments_list($sessionDepartment);
        if (!$parentIsAll) {
            foreach ($sessionDeptList as $sd) {
                if ($sd === 'ALL') {
                    return ['ok' => false, 'error' => 'This activity cannot target more students than the main event allows.'];
                }
                if (!in_array($sd, $parentDeptList, true)) {
                    return ['ok' => false, 'error' => 'Pick departments that are already included in the main event audience.'];
                }
            }
        }
    }

    $parentSections = eventify_parse_target_sections_list($parentEvent['target_sections'] ?? null);
    $sessionSections = eventify_parse_target_sections_list($sessionTargetSections);
    if ($parentSections !== [] && $sessionSections !== []) {
        $parentKeys = [];
        foreach ($parentSections as $ps) {
            $parentKeys[eventify_section_match_key($ps)] = true;
        }
        foreach ($sessionSections as $ss) {
            $sk = eventify_section_match_key($ss);
            if ($sk === '' || !isset($parentKeys[$sk])) {
                return ['ok' => false, 'error' => 'Pick class sections that are already included in the main event audience.'];
            }
        }
    }

    return ['ok' => true];
}

/**
 * @return array{ok:bool, department:?string, target_sections:?string, inherit:bool, error?:string}
 */
function eventify_parse_session_audience_from_data(mysqli $conn, array $data, ?int $createdBy = null): array
{
    eventify_event_day_sessions_ensure_audience_columns($conn);
    $inheritRaw = $data['audience_inherit'] ?? '1';
    $inherit = !in_array(strtolower(trim((string) $inheritRaw)), ['0', 'false', 'no'], true);
    if ($inherit) {
        return [
            'ok' => true,
            'department' => null,
            'target_sections' => null,
            'inherit' => true,
        ];
    }

    $deptParse = eventify_parse_event_departments_from_request($data);
    if (!$deptParse['ok']) {
        return [
            'ok' => false,
            'department' => null,
            'target_sections' => null,
            'inherit' => false,
            'error' => $deptParse['error'] ?? 'Invalid department selection.',
        ];
    }
    $secParse = eventify_parse_event_sections_from_request($conn, $data, $createdBy);
    if (!$secParse['ok']) {
        return [
            'ok' => false,
            'department' => null,
            'target_sections' => null,
            'inherit' => false,
            'error' => $secParse['error'] ?? 'Invalid class section.',
        ];
    }

    $dept = $deptParse['department'];
    $sections = $secParse['target_sections'];
    $hasDeptInPost = isset($data['department']) && (
        (is_array($data['department']) && array_filter(array_map('strval', $data['department'])) !== [])
        || (is_string($data['department']) && trim($data['department']) !== '')
    );
    $hasSecInPost = isset($data['section']) && is_array($data['section'])
        && array_filter(array_map('strval', $data['section'])) !== [];
    $hasNewSec = trim((string) ($data['new_section'] ?? '')) !== '';
    if (!$hasDeptInPost && !$hasSecInPost && !$hasNewSec) {
        return [
            'ok' => false,
            'department' => null,
            'target_sections' => null,
            'inherit' => false,
            'error' => 'Choose at least one department, All departments, or a class section for this activity.',
        ];
    }

    return [
        'ok' => true,
        'department' => $dept,
        'target_sections' => $sections,
        'inherit' => false,
    ];
}

function eventify_save_session_audience(
    mysqli $conn,
    int $sessionId,
    ?string $department,
    ?string $targetSections
): bool {
    if ($sessionId < 1 || !eventify_day_sessions_have_audience_columns($conn)) {
        return true;
    }
    $stmt = $conn->prepare('UPDATE event_day_sessions SET department = ?, target_sections = ? WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $deptParam = $department;
    $secParam = $targetSections;
    $stmt->bind_param('ssi', $deptParam, $secParam, $sessionId);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool) $ok;
}

/**
 * Active students who match parent event + optional session audience.
 *
 * @param array<string, mixed> $session
 * @param array<string, mixed> $parentEvent
 * @return list<int>
 */
function eventify_student_ids_for_session_audience(mysqli $conn, array $session, array $parentEvent): array
{
    require_once __DIR__ . '/notifications_service.php';
    $parentIds = eventify_student_ids_for_event_audience($conn, $parentEvent);
    if (eventify_session_audience_inherited($session)) {
        return $parentIds;
    }
    if ($parentIds === []) {
        return [];
    }
    $studentProfile = static function (mysqli $c, int $uid): array {
        return function_exists('eventify_load_student_audience_profile')
            ? eventify_load_student_audience_profile($c, $uid)
            : ['department' => null, 'student_section' => null];
    };
    $out = [];
    foreach ($parentIds as $uid) {
        $uid = (int) $uid;
        if ($uid < 1) {
            continue;
        }
        if (eventify_student_may_access_session($session, $parentEvent, $studentProfile($conn, $uid))) {
            $out[] = $uid;
        }
    }
    return $out;
}

function eventify_should_filter_sessions_for_student(mysqli $conn, ?int $viewerUserId): bool
{
    if ($viewerUserId === null || $viewerUserId < 1) {
        return false;
    }
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $viewerUserId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($row['role'] ?? '') === 'student';
}

/**
 * @param list<array<string, mixed>> $sessions
 * @return list<array<string, mixed>>
 */
function eventify_filter_sessions_for_student(
    mysqli $conn,
    array $sessions,
    int $studentUserId,
    array $parentEvent
): array {
    if ($sessions === [] || $studentUserId < 1) {
        return $sessions;
    }
    $student = eventify_load_student_audience_profile($conn, $studentUserId);
    $out = [];
    foreach ($sessions as $session) {
        if (eventify_student_may_access_session($session, $parentEvent, $student)) {
            $out[] = $session;
        }
    }
    return $out;
}

/** @param array<string, mixed> $session */
function eventify_format_session_audience_label(array $session): string
{
    if (eventify_session_audience_inherited($session)) {
        return 'Same as whole event';
    }
    $parts = [];
    $dept = $session['department'] ?? null;
    if ($dept !== null && trim((string) $dept) !== '') {
        $parts[] = eventify_format_department_label((string) $dept);
    }
    $secLabel = eventify_format_target_sections_label($session['target_sections'] ?? null);
    if ($secLabel !== '') {
        $parts[] = $secLabel;
    }
    if ($parts === []) {
        return 'Custom audience';
    }
    return implode(' · ', $parts);
}

function eventify_event_day_sessions_ensure_session_ticket_types_table(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $res = $conn->query("SHOW TABLES LIKE 'event_session_ticket_types'");
        if ($res && $res->num_rows >= 1) {
            $cache = true;
            return true;
        }
    } catch (Throwable $e) {
        /* fall through to create */
    }
    $sql = @file_get_contents(__DIR__ . '/../../migrations/add_session_ticket_types.sql');
    if ($sql !== false && trim($sql) !== '') {
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt !== '') {
                try {
                    @$conn->query($stmt);
                } catch (Throwable $e) {
                    /* ignore */
                }
            }
        }
    } else {
        try {
            @$conn->query(
                "CREATE TABLE IF NOT EXISTS event_session_ticket_types (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    session_id INT NOT NULL,
                    ticket_type_id INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_session_type (session_id, ticket_type_id),
                    KEY idx_session (session_id),
                    KEY idx_ticket_type (ticket_type_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Throwable $e) {
            /* ignore */
        }
    }
    try {
        $res = $conn->query("SHOW TABLES LIKE 'event_session_ticket_types'");
        $cache = (bool) ($res && $res->num_rows >= 1);
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

/**
 * Load allowed ticket type ids for a set of sessions in one query.
 *
 * @param list<int> $sessionIds
 * @return array<int, list<int>> sessionId => [ticketTypeId, ...]
 */
function eventify_load_session_ticket_type_map(mysqli $conn, array $sessionIds): array
{
    $sessionIds = array_values(array_unique(array_filter(array_map('intval', $sessionIds), static fn($v) => $v > 0)));
    if ($sessionIds === [] || !eventify_event_day_sessions_ensure_session_ticket_types_table($conn)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
    $types = str_repeat('i', count($sessionIds));
    $stmt = $conn->prepare(
        "SELECT session_id, ticket_type_id FROM event_session_ticket_types WHERE session_id IN ($placeholders)"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param($types, ...$sessionIds);
    $stmt->execute();
    $res = $stmt->get_result();
    $map = [];
    while ($row = $res->fetch_assoc()) {
        $sid = (int) ($row['session_id'] ?? 0);
        $tid = (int) ($row['ticket_type_id'] ?? 0);
        if ($sid > 0 && $tid > 0) {
            $map[$sid][] = $tid;
        }
    }
    $stmt->close();
    return $map;
}

/** @return list<int> */
function eventify_load_session_ticket_type_ids(mysqli $conn, int $sessionId): array
{
    $map = eventify_load_session_ticket_type_map($conn, [$sessionId]);
    return $map[$sessionId] ?? [];
}

/**
 * Resolve the set of ticket type ids that grant access to a session.
 * Prefers the junction list, falls back to the legacy single column.
 *
 * @param array<string, mixed> $session
 * @return list<int>
 */
function eventify_session_allowed_ticket_type_ids(mysqli $conn, array $session): array
{
    if (array_key_exists('ticket_type_ids', $session) && is_array($session['ticket_type_ids'])) {
        $ids = array_values(array_filter(array_map('intval', $session['ticket_type_ids']), static fn($v) => $v > 0));
        if ($ids !== []) {
            return $ids;
        }
    }
    $sessionId = (int) ($session['id'] ?? 0);
    if ($sessionId > 0) {
        $ids = eventify_load_session_ticket_type_ids($conn, $sessionId);
        if ($ids !== []) {
            return $ids;
        }
    }
    $legacy = (int) ($session['ticket_type_id'] ?? 0);
    return $legacy > 0 ? [$legacy] : [];
}

function eventify_event_day_sessions_ensure_rsvp_tables(mysqli $conn): bool
{
    static $done = false;
    if ($done) {
        return true;
    }
    $sql = @file_get_contents(__DIR__ . '/../../migrations/add_event_day_sessions_phase2.sql');
    if ($sql === false || trim($sql) === '') {
        return false;
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt !== '') {
            try {
                @$conn->query($stmt);
            } catch (Throwable $e) {
                /* ignore */
            }
        }
    }
    $done = true;
    return true;
}

function eventify_session_rsvps_table_exists(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $res = $conn->query("SHOW TABLES LIKE 'event_day_session_rsvps'");
        if ($res && $res->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

/** @return list<string> */
function eventify_day_session_category_options(): array
{
    return ['Sports', 'Ceremony', 'Workshop', 'Competition', 'Social', 'Other'];
}

/** @return list<string> */
function eventify_day_session_status_options(): array
{
    return ['scheduled', 'cancelled', 'delayed'];
}

/** Status values stored in DB (includes organizer "end early"). */
function eventify_day_session_status_values(): array
{
    return ['scheduled', 'cancelled', 'delayed', 'completed'];
}

function eventify_day_sessions_ensure_completed_status(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $col = $conn->query("SHOW COLUMNS FROM event_day_sessions LIKE 'status'");
        if ($col && ($row = $col->fetch_assoc())) {
            $type = strtolower((string) ($row['Type'] ?? ''));
            if (strpos($type, "'completed'") === false) {
                @$conn->query(
                    "ALTER TABLE event_day_sessions MODIFY COLUMN status
                     enum('scheduled','cancelled','delayed','completed') NOT NULL DEFAULT 'scheduled'"
                );
            }
        }
    } catch (Throwable $e) {
        /* keep going */
    }
}

/** @param array<string, mixed> $session */
function eventify_session_is_completed(array $session): bool
{
    return strtolower(trim((string) ($session['status'] ?? ''))) === 'completed';
}

function eventify_day_sessions_select_columns(mysqli $conn): string
{
    $cols = 'id, event_id, schedule_date, title, location, start_time, end_time, sort_order';
    if (eventify_day_sessions_have_geo_columns($conn)) {
        $cols = 'id, event_id, schedule_date, title, location, latitude, longitude, start_time, end_time, sort_order';
    }
    if (eventify_day_sessions_have_enhanced_columns($conn)) {
        $base = 'id, event_id, schedule_date, title, category, location, notes, status, start_time, end_time, max_capacity, contact_name, contact_phone, checkin_token, sort_order';
        if (eventify_day_sessions_have_geo_columns($conn)) {
            $base = 'id, event_id, schedule_date, title, category, location, latitude, longitude, notes, status, start_time, end_time, max_capacity, contact_name, contact_phone, checkin_token, sort_order';
        }
        if (eventify_day_sessions_have_access_columns($conn)) {
            $base = str_replace(', status, start_time,', ', status, access_mode, ticket_type_id, start_time,', $base);
        }
        if (eventify_day_sessions_have_audience_columns($conn)) {
            $base = str_replace('title, category,', 'title, department, target_sections, category,', $base);
            if (strpos($base, 'title, location,') !== false && strpos($base, 'department,') === false) {
                $base = str_replace('title, location,', 'title, department, target_sections, location,', $base);
            }
        }
        if (eventify_day_sessions_have_checkin_time_columns($conn)) {
            $base = str_replace(', end_time, max_capacity,', ', end_time, checkin_start_time, checkin_end_time, max_capacity,', $base);
        }
        if (eventify_day_sessions_have_attendance_mode_columns($conn)) {
            $base = str_replace(', checkin_end_time, max_capacity,', ', checkin_end_time, attendance_mode, checkout_end_time, max_capacity,', $base);
        }
        return $base;
    }
    return $cols;
}

function eventify_ensure_session_checkin_token(mysqli $conn, int $sessionId): ?string
{
    if ($sessionId < 1 || !eventify_day_sessions_have_enhanced_columns($conn)) {
        return null;
    }
    $stmt = $conn->prepare('SELECT checkin_token FROM event_day_sessions WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $token = trim((string) ($row['checkin_token'] ?? ''));
    if ($token !== '') {
        return $token;
    }
    $token = bin2hex(random_bytes(16));
    $up = $conn->prepare('UPDATE event_day_sessions SET checkin_token = ? WHERE id = ?');
    if (!$up) {
        return null;
    }
    $up->bind_param('si', $token, $sessionId);
    $up->execute();
    $up->close();
    return $token;
}

/** @return array<string, mixed> */
function eventify_map_day_session_row(array $row): array
{
    $lat = isset($row['latitude']) && $row['latitude'] !== '' && $row['latitude'] !== null
        ? (float) $row['latitude'] : null;
    $lng = isset($row['longitude']) && $row['longitude'] !== '' && $row['longitude'] !== null
        ? (float) $row['longitude'] : null;
    $maxCap = isset($row['max_capacity']) && $row['max_capacity'] !== '' && $row['max_capacity'] !== null
        ? (int) $row['max_capacity'] : null;
    $sessionDept = array_key_exists('department', $row) ? ($row['department'] ?? null) : null;
    if ($sessionDept !== null && trim((string) $sessionDept) === '') {
        $sessionDept = null;
    }
    $sessionSections = array_key_exists('target_sections', $row) ? ($row['target_sections'] ?? null) : null;
    if ($sessionSections !== null && trim((string) $sessionSections) === '') {
        $sessionSections = null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'event_id' => (int) ($row['event_id'] ?? 0),
        'schedule_date' => substr(trim((string) ($row['schedule_date'] ?? '')), 0, 10),
        'title' => trim((string) ($row['title'] ?? '')),
        'department' => $sessionDept,
        'target_sections' => $sessionSections,
        'audience_inherit' => eventify_session_audience_inherited([
            'department' => $sessionDept,
            'target_sections' => $sessionSections,
        ]),
        'audience_departments' => eventify_parse_stored_departments_list($sessionDept),
        'audience_sections' => eventify_parse_target_sections_list($sessionSections),
        'audience_label' => eventify_format_session_audience_label([
            'department' => $sessionDept,
            'target_sections' => $sessionSections,
        ]),
        'category' => trim((string) ($row['category'] ?? '')) ?: null,
        'location' => trim((string) ($row['location'] ?? '')),
        'latitude' => $lat,
        'longitude' => $lng,
        'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
        'status' => trim((string) ($row['status'] ?? 'scheduled')) ?: 'scheduled',
        'start_time' => trim((string) ($row['start_time'] ?? '')) ?: null,
        'end_time' => trim((string) ($row['end_time'] ?? '')) ?: null,
        'checkin_start_time' => trim((string) ($row['checkin_start_time'] ?? '')) ?: null,
        'checkin_end_time' => trim((string) ($row['checkin_end_time'] ?? '')) ?: null,
        'attendance_mode' => strtolower(trim((string) ($row['attendance_mode'] ?? 'single'))) === 'time_in_out' ? 'time_in_out' : 'single',
        'checkout_end_time' => trim((string) ($row['checkout_end_time'] ?? '')) ?: null,
        'max_capacity' => $maxCap !== null && $maxCap > 0 ? $maxCap : null,
        'contact_name' => trim((string) ($row['contact_name'] ?? '')) ?: null,
        'contact_phone' => trim((string) ($row['contact_phone'] ?? '')) ?: null,
        'checkin_token' => trim((string) ($row['checkin_token'] ?? '')) ?: null,
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'access_mode' => eventify_session_access_mode($row),
        'ticket_type_id' => isset($row['ticket_type_id']) && $row['ticket_type_id'] !== '' && $row['ticket_type_id'] !== null
            ? (int) $row['ticket_type_id'] : null,
        'rsvp_count' => isset($row['rsvp_count']) ? (int) $row['rsvp_count'] : null,
        'user_rsvped' => isset($row['user_rsvped']) ? (bool) $row['user_rsvped'] : null,
        'user_checked_in' => isset($row['user_checked_in']) ? (bool) $row['user_checked_in'] : null,
        'checked_in_at' => $row['checked_in_at'] ?? null,
    ];
}

/** @param array<string, mixed> $session */
function eventify_session_access_mode(array $session): string
{
    $mode = strtolower(trim((string) ($session['access_mode'] ?? 'open')));
    return in_array($mode, ['open', 'free', 'ticket_required'], true) ? $mode : 'open';
}

/** @param array<string, mixed> $session */
function eventify_session_is_open_access(array $session): bool
{
    return eventify_session_access_mode($session) === 'open';
}

/** @param array<string, mixed> $session */
function eventify_session_requires_activity_rsvp(array $session): bool
{
    return eventify_session_access_mode($session) === 'free';
}

/** @param array<string, mixed> $session */
function eventify_session_requires_ticket(array $session): bool
{
    return eventify_session_access_mode($session) === 'ticket_required';
}

/** @param array<string, mixed> $session */
function eventify_student_has_session_ticket(mysqli $conn, int $userId, array $session): bool
{
    if ($userId < 1 || !eventify_session_requires_ticket($session)) {
        return true;
    }
    require_once __DIR__ . '/event_ticketing.php';
    if (!eventify_ticketing_ensure_schema($conn)) {
        return false;
    }
    $eventId = (int) ($session['event_id'] ?? 0);
    if ($eventId < 1) {
        return false;
    }
    $allowedTypeIds = eventify_session_allowed_ticket_type_ids($conn, $session);
    if ($allowedTypeIds !== []) {
        // Valid for the activity if the student holds ANY of the accepted tiers.
        $placeholders = implode(',', array_fill(0, count($allowedTypeIds), '?'));
        $types = 'ii' . str_repeat('i', count($allowedTypeIds));
        $params = array_merge([$userId, $eventId], $allowedTypeIds);
        $stmt = $conn->prepare(
            "SELECT id FROM event_tickets WHERE user_id = ? AND event_id = ? AND ticket_type_id IN ($placeholders) AND status = 'valid' LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt = $conn->prepare(
            "SELECT id FROM event_tickets WHERE user_id = ? AND event_id = ? AND status = 'valid' LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $userId, $eventId);
    }
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

/** @param list<array<string, mixed>> $sessions */
function eventify_attach_session_ticket_meta(mysqli $conn, array &$sessions, ?int $viewerUserId): void
{
    $sessionIds = [];
    foreach ($sessions as $s) {
        $sid = (int) ($s['id'] ?? 0);
        if ($sid > 0) {
            $sessionIds[] = $sid;
        }
    }
    $typeMap = eventify_load_session_ticket_type_map($conn, $sessionIds);
    foreach ($sessions as &$s) {
        $sid = (int) ($s['id'] ?? 0);
        $ids = $typeMap[$sid] ?? [];
        if ($ids === []) {
            $legacy = (int) ($s['ticket_type_id'] ?? 0);
            if ($legacy > 0) {
                $ids = [$legacy];
            }
        }
        $s['ticket_type_ids'] = $ids;
        $s['requires_ticket'] = eventify_session_requires_ticket($s);
        $s['user_has_activity_ticket'] = ($viewerUserId !== null && $viewerUserId > 0)
            ? eventify_student_has_session_ticket($conn, $viewerUserId, $s)
            : false;
    }
    unset($s);
}

/**
 * @return array{ok: bool, error?: string}
 */
function eventify_save_session_access_meta(
    mysqli $conn,
    int $sessionId,
    int $eventId,
    string $accessMode,
    array $ticketTypeIds
): array {
    if ($sessionId < 1 || !eventify_event_day_sessions_ensure_access_columns($conn)) {
        return ['ok' => true];
    }
    $accessMode = strtolower(trim($accessMode));
    if (!in_array($accessMode, ['open', 'free', 'ticket_required'], true)) {
        $accessMode = 'open';
    }
    $ticketTypeIds = array_values(array_unique(array_filter(
        array_map('intval', $ticketTypeIds),
        static fn($v) => $v > 0
    )));
    if ($accessMode === 'ticket_required') {
        if ($ticketTypeIds === []) {
            return ['ok' => false, 'error' => 'Select at least one ticket type for paid activities.'];
        }
        require_once __DIR__ . '/event_ticketing.php';
        foreach ($ticketTypeIds as $tid) {
            $type = eventify_load_ticket_type_for_event($conn, $tid, $eventId);
            if (!$type) {
                return ['ok' => false, 'error' => 'One of the selected ticket types is invalid for this event.'];
            }
        }
    } else {
        $ticketTypeIds = [];
    }

    // Legacy single column kept in sync (first tier) for backward compatibility.
    $primaryTypeId = $ticketTypeIds[0] ?? null;
    if ($primaryTypeId === null) {
        $stmt = $conn->prepare('UPDATE event_day_sessions SET access_mode = ?, ticket_type_id = NULL WHERE id = ? AND event_id = ?');
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save activity access settings.'];
        }
        $stmt->bind_param('sii', $accessMode, $sessionId, $eventId);
    } else {
        $stmt = $conn->prepare('UPDATE event_day_sessions SET access_mode = ?, ticket_type_id = ? WHERE id = ? AND event_id = ?');
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save activity access settings.'];
        }
        $stmt->bind_param('siii', $accessMode, $primaryTypeId, $sessionId, $eventId);
    }
    $stmt->execute();
    $stmt->close();

    // Replace the junction rows (the source of truth for multi-tier access).
    if (eventify_event_day_sessions_ensure_session_ticket_types_table($conn)) {
        $del = $conn->prepare('DELETE FROM event_session_ticket_types WHERE session_id = ?');
        if ($del) {
            $del->bind_param('i', $sessionId);
            $del->execute();
            $del->close();
        }
        if ($ticketTypeIds !== []) {
            $ins = $conn->prepare('INSERT IGNORE INTO event_session_ticket_types (session_id, ticket_type_id) VALUES (?, ?)');
            if ($ins) {
                foreach ($ticketTypeIds as $tid) {
                    $ins->bind_param('ii', $sessionId, $tid);
                    $ins->execute();
                }
                $ins->close();
            }
        }
    }
    return ['ok' => true];
}

/**
 * @return list<array<string, mixed>>
 */
function eventify_load_event_day_sessions(mysqli $conn, int $eventId, ?string $scheduleDate = null, ?int $viewerUserId = null): array
{
    if ($eventId < 1 || !eventify_event_day_sessions_ensure_table($conn)) {
        return [];
    }
    eventify_event_day_sessions_ensure_enhanced($conn);
    $cols = eventify_day_sessions_select_columns($conn);
    if ($scheduleDate !== null && $scheduleDate !== '') {
        $scheduleDate = substr(trim($scheduleDate), 0, 10);
        $stmt = $conn->prepare(
            "SELECT {$cols} FROM event_day_sessions WHERE event_id = ? AND schedule_date = ?
             ORDER BY sort_order ASC, start_time ASC, id ASC"
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('is', $eventId, $scheduleDate);
    } else {
        $stmt = $conn->prepare(
            "SELECT {$cols} FROM event_day_sessions WHERE event_id = ?
             ORDER BY schedule_date ASC, sort_order ASC, start_time ASC, id ASC"
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $eventId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = eventify_map_day_session_row($row);
    }
    $stmt->close();
    $eventStatus = '';
    $stEv = $conn->prepare('SELECT status FROM events WHERE id = ? LIMIT 1');
    if ($stEv) {
        $stEv->bind_param('i', $eventId);
        $stEv->execute();
        $evRow = $stEv->get_result()->fetch_assoc();
        $stEv->close();
        $eventStatus = strtolower(trim((string) ($evRow['status'] ?? '')));
    }
    if ($out !== [] && $eventStatus !== '') {
        foreach ($out as &$sessionRow) {
            $sessionRow['event_status'] = $eventStatus;
        }
        unset($sessionRow);
    }
    if ($out !== [] && eventify_session_rsvps_table_exists($conn)) {
        eventify_attach_session_rsvp_meta($conn, $out, $viewerUserId);
    }
    if ($out !== [] && $viewerUserId !== null && $viewerUserId > 0) {
        eventify_attach_session_checkin_meta($conn, $out, $viewerUserId);
    }
    if ($out !== []) {
        eventify_attach_session_student_flags($out);
        eventify_attach_session_ticket_meta($conn, $out, $viewerUserId);
    }
    if ($out !== [] && eventify_should_filter_sessions_for_student($conn, $viewerUserId)) {
        $parentEvent = eventify_load_event_for_activities_hub($conn, $eventId);
        if ($parentEvent) {
            $out = eventify_filter_sessions_for_student($conn, $out, (int) $viewerUserId, $parentEvent);
        }
    }
    return $out;
}

/**
 * @return list<string> sorted unique Y-m-d
 */
function eventify_normalize_schedule_dates(array $dates): array
{
    $out = [];
    foreach ($dates as $d) {
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

/**
 * @return list<string>
 */
function eventify_event_day_session_schedule_dates(mysqli $conn, int $eventId): array
{
    if ($eventId < 1 || !eventify_event_day_sessions_ensure_table($conn)) {
        return [];
    }
    $stmt = $conn->prepare('SELECT DISTINCT schedule_date FROM event_day_sessions WHERE event_id = ? ORDER BY schedule_date ASC');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $res = $stmt->get_result();
    $dates = [];
    while ($row = $res->fetch_assoc()) {
        $d = substr(trim((string) ($row['schedule_date'] ?? '')), 0, 10);
        if ($d !== '') {
            $dates[] = $d;
        }
    }
    $stmt->close();

    return eventify_normalize_schedule_dates($dates);
}

/**
 * Align activity dates to the parent event schedule by day index (day 1 -> day 1).
 *
 * @param list<string> $newDisplayDates
 */
function eventify_realign_event_day_sessions_to_event_schedule(
    mysqli $conn,
    int $eventId,
    array $newDisplayDates
): int {
    if ($eventId < 1 || !eventify_event_day_sessions_ensure_table($conn)) {
        return 0;
    }

    $newDisplayDates = eventify_normalize_schedule_dates($newDisplayDates);
    $activityDates = eventify_event_day_session_schedule_dates($conn, $eventId);
    if ($newDisplayDates === [] || $activityDates === [] || $activityDates === $newDisplayDates) {
        return 0;
    }

    $dateMap = [];
    foreach ($activityDates as $i => $oldDate) {
        if (!isset($newDisplayDates[$i])) {
            break;
        }
        $newDate = $newDisplayDates[$i];
        if ($newDate !== $oldDate) {
            $dateMap[$oldDate] = $newDate;
        }
    }
    if ($dateMap === []) {
        return 0;
    }

    $stmt = $conn->prepare('SELECT id, schedule_date FROM event_day_sessions WHERE event_id = ?');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    if ($rows === []) {
        return 0;
    }

    $up = $conn->prepare('UPDATE event_day_sessions SET schedule_date = ? WHERE id = ? AND event_id = ?');
    if (!$up) {
        return 0;
    }

    $updated = 0;
    foreach ($rows as $row) {
        $sessionId = (int) ($row['id'] ?? 0);
        $curDate = substr(trim((string) ($row['schedule_date'] ?? '')), 0, 10);
        if ($sessionId < 1 || $curDate === '' || !isset($dateMap[$curDate])) {
            continue;
        }
        $newDate = $dateMap[$curDate];
        $up->bind_param('sii', $newDate, $sessionId, $eventId);
        if ($up->execute()) {
            $updated++;
        }
    }
    $up->close();

    return $updated;
}

/**
 * Build old-date → new-date map when an event schedule changes (day 1 → day 1, etc.).
 *
 * @param list<string> $oldDisplayDates
 * @param list<string> $newDisplayDates
 * @param list<string> $activityDates
 * @return array<string, string>
 */
function eventify_schedule_change_build_date_map(
    array $oldDisplayDates,
    array $newDisplayDates,
    array $activityDates
): array {
    $oldDisplayDates = eventify_normalize_schedule_dates($oldDisplayDates);
    $newDisplayDates = eventify_normalize_schedule_dates($newDisplayDates);
    $activityDates = eventify_normalize_schedule_dates($activityDates);
    $dateMap = [];

    $useRealign = false;
    if ($activityDates !== []) {
        $activityMismatch = $activityDates !== $newDisplayDates;
        if (!$activityMismatch) {
            foreach ($activityDates as $ad) {
                if (!in_array($ad, $oldDisplayDates, true)) {
                    $activityMismatch = true;
                    break;
                }
            }
        }
        $useRealign = $activityMismatch;
    }

    if ($useRealign) {
        foreach ($activityDates as $i => $oldDate) {
            if (!isset($newDisplayDates[$i])) {
                break;
            }
            $dateMap[$oldDate] = $newDisplayDates[$i];
        }
    } else {
        foreach ($oldDisplayDates as $i => $oldDate) {
            if (!isset($newDisplayDates[$i])) {
                continue;
            }
            $dateMap[$oldDate] = $newDisplayDates[$i];
        }
    }

    return $dateMap;
}

/**
 * Preview which sub-activities fall outside the new schedule after a date change.
 *
 * @param list<string> $oldDisplayDates
 * @param list<string> $newDisplayDates
 * @return array{
 *   remap: array<int, string>,
 *   dropped: list<array{id: int, title: string, schedule_date: string}>,
 *   last_new_day: string
 * }
 */
function eventify_schedule_change_plan(
    mysqli $conn,
    int $eventId,
    array $oldDisplayDates,
    array $newDisplayDates
): array {
    $oldDisplayDates = eventify_normalize_schedule_dates($oldDisplayDates);
    $newDisplayDates = eventify_normalize_schedule_dates($newDisplayDates);
    $lastNewDay = $newDisplayDates !== [] ? $newDisplayDates[count($newDisplayDates) - 1] : '';
    $empty = ['remap' => [], 'dropped' => [], 'last_new_day' => $lastNewDay];

    if ($eventId < 1 || !eventify_event_day_sessions_ensure_table($conn)) {
        return $empty;
    }

    $activityDates = eventify_event_day_session_schedule_dates($conn, $eventId);
    $dateMap = eventify_schedule_change_build_date_map($oldDisplayDates, $newDisplayDates, $activityDates);

    $stmt = $conn->prepare('SELECT id, schedule_date, title FROM event_day_sessions WHERE event_id = ?');
    if (!$stmt) {
        return $empty;
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    $remap = [];
    $dropped = [];
    foreach ($rows as $row) {
        $sessionId = (int) ($row['id'] ?? 0);
        $curDate = substr(trim((string) ($row['schedule_date'] ?? '')), 0, 10);
        $title = trim((string) ($row['title'] ?? ''));
        if ($sessionId < 1 || $curDate === '') {
            continue;
        }
        if ($title === '') {
            $title = 'Activity';
        }

        if (isset($dateMap[$curDate])) {
            $newDate = $dateMap[$curDate];
            if ($newDate !== $curDate) {
                $remap[$sessionId] = $newDate;
            }
            continue;
        }

        $dropped[] = [
            'id' => $sessionId,
            'title' => $title,
            'schedule_date' => $curDate,
        ];
    }

    return [
        'remap' => $remap,
        'dropped' => $dropped,
        'last_new_day' => $lastNewDay,
    ];
}

/**
 * Apply schedule remap plus optional handling for activities on removed days.
 *
 * @param list<string> $oldDisplayDates
 * @param list<string> $newDisplayDates
 * @param 'leave'|'delete'|'keep' $droppedAction keep = move to last new day
 * @return array{shifted: int, dropped_handled: int, dropped_action: string}
 */
function eventify_apply_schedule_change_to_sessions(
    mysqli $conn,
    int $eventId,
    array $oldDisplayDates,
    array $newDisplayDates,
    string $droppedAction = 'leave'
): array {
    $result = ['shifted' => 0, 'dropped_handled' => 0, 'dropped_action' => $droppedAction];
    if ($eventId < 1 || !eventify_event_day_sessions_ensure_table($conn)) {
        return $result;
    }

    $oldDisplayDates = eventify_normalize_schedule_dates($oldDisplayDates);
    $newDisplayDates = eventify_normalize_schedule_dates($newDisplayDates);
    if ($oldDisplayDates === $newDisplayDates) {
        return $result;
    }

    if (!in_array($droppedAction, ['leave', 'delete', 'keep'], true)) {
        $droppedAction = 'leave';
    }
    $result['dropped_action'] = $droppedAction;

    $plan = eventify_schedule_change_plan($conn, $eventId, $oldDisplayDates, $newDisplayDates);

    if ($plan['remap'] !== []) {
        $up = $conn->prepare('UPDATE event_day_sessions SET schedule_date = ? WHERE id = ? AND event_id = ?');
        if ($up) {
            foreach ($plan['remap'] as $sessionId => $newDate) {
                $sessionId = (int) $sessionId;
                if ($sessionId < 1) {
                    continue;
                }
                $up->bind_param('sii', $newDate, $sessionId, $eventId);
                if ($up->execute()) {
                    $result['shifted']++;
                }
            }
            $up->close();
        }
    }

    if ($plan['dropped'] === [] || $droppedAction === 'leave') {
        return $result;
    }

    if ($droppedAction === 'keep') {
        $lastDay = (string) ($plan['last_new_day'] ?? '');
        if ($lastDay === '') {
            return $result;
        }
        $up = $conn->prepare('UPDATE event_day_sessions SET schedule_date = ? WHERE id = ? AND event_id = ?');
        if (!$up) {
            return $result;
        }
        foreach ($plan['dropped'] as $row) {
            $sessionId = (int) ($row['id'] ?? 0);
            if ($sessionId < 1) {
                continue;
            }
            $up->bind_param('sii', $lastDay, $sessionId, $eventId);
            if ($up->execute()) {
                $result['dropped_handled']++;
            }
        }
        $up->close();
        return $result;
    }

    $del = $conn->prepare('DELETE FROM event_day_sessions WHERE id = ? AND event_id = ?');
    if (!$del) {
        return $result;
    }
    foreach ($plan['dropped'] as $row) {
        $sessionId = (int) ($row['id'] ?? 0);
        if ($sessionId < 1) {
            continue;
        }
        $del->bind_param('ii', $sessionId, $eventId);
        if ($del->execute() && $del->affected_rows > 0) {
            $result['dropped_handled']++;
        }
    }
    $del->close();

    return $result;
}

/**
 * Move day activities when a main event schedule changes (day 1 stays day 1, etc.).
 *
 * @param list<string> $oldDisplayDates
 * @param list<string> $newDisplayDates
 * @param 'leave'|'delete'|'keep' $droppedAction
 */
function eventify_shift_event_day_sessions_for_schedule_change(
    mysqli $conn,
    int $eventId,
    array $oldDisplayDates,
    array $newDisplayDates,
    string $droppedAction = 'leave'
): int {
    $applied = eventify_apply_schedule_change_to_sessions(
        $conn,
        $eventId,
        $oldDisplayDates,
        $newDisplayDates,
        $droppedAction
    );

    return (int) ($applied['shifted'] + $applied['dropped_handled']);
}

/** @param list<int> $eventIds @return array<int, list<array<string, mixed>>> */
function eventify_load_day_sessions_map(mysqli $conn, array $eventIds): array
{
    $map = [];
    $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds), static function ($id) {
        return $id > 0;
    })));
    if ($eventIds === [] || !eventify_event_day_sessions_ensure_table($conn)) {
        return $map;
    }
    eventify_event_day_sessions_ensure_enhanced($conn);
    $cols = eventify_day_sessions_select_columns($conn);
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $types = str_repeat('i', count($eventIds));
    $sql = "SELECT {$cols} FROM event_day_sessions WHERE event_id IN ($placeholders)
            ORDER BY schedule_date ASC, sort_order ASC, start_time ASC, id ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $map;
    }
    $stmt->bind_param($types, ...$eventIds);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $eid = (int) ($row['event_id'] ?? 0);
        if ($eid < 1) {
            continue;
        }
        if (!isset($map[$eid])) {
            $map[$eid] = [];
        }
        $mapped = eventify_map_day_session_row($row);
        unset($mapped['event_id']);
        $map[$eid][] = $mapped;
    }
    $stmt->close();
    return $map;
}

function eventify_organizer_owns_event(mysqli $conn, int $eventId, int $organizerId): bool
{
    if ($eventId < 1 || $organizerId < 1) {
        return false;
    }
    $stmt = $conn->prepare('SELECT id FROM events WHERE id = ? AND organizer_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $eventId, $organizerId);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

/**
 * True when the user is the assigned organizer_id and may manage the event
 * (organizer role, or admin/super_admin who assigned the event to themselves).
 */
function eventify_user_can_manage_owned_event(string $role, int $userId, array $event): bool
{
    if ($userId < 1) {
        return false;
    }
    if ((int) ($event['organizer_id'] ?? 0) !== $userId) {
        return false;
    }
    return in_array($role, ['organizer', 'admin', 'super_admin'], true);
}

/**
 * Organizer (owner), admin, or super_admin may add/edit/delete sub-activities.
 */
function eventify_user_can_manage_event_activities(string $role, int $userId, array $event): bool
{
    if ($userId < 1) {
        return false;
    }
    if (in_array($role, ['admin', 'super_admin'], true)) {
        return true;
    }
    return eventify_user_can_manage_owned_event($role, $userId, $event);
}

/**
 * API gate: staff may manage activities on this event id.
 */
function eventify_user_may_manage_event_activities(mysqli $conn, string $role, int $userId, int $eventId): bool
{
    if ($userId < 1 || $eventId < 1) {
        return false;
    }
    if (in_array($role, ['admin', 'super_admin'], true)) {
        return true;
    }
    if ($role === 'organizer') {
        return eventify_organizer_owns_event($conn, $eventId, $userId);
    }
    return false;
}

/**
 * Whether a role is allowed to edit day activities when they own the event.
 */
function eventify_role_can_edit_owned_event_activities(string $role): bool
{
    return in_array($role, ['organizer', 'admin', 'super_admin'], true);
}

/**
 * Whether an organizer may add/edit/delete activities (optionally for one schedule day).
 *
 * @return array{ok: bool, error: string, locked: bool}
 */
function eventify_organizer_can_edit_event_schedule(mysqli $conn, int $eventId, ?string $scheduleDate = null): array
{
    $locked = static function (string $message): array {
        return ['ok' => false, 'error' => $message, 'locked' => true];
    };

    if ($eventId < 1) {
        return $locked('Invalid event.');
    }

    $event = eventify_load_event_for_activities_hub($conn, $eventId);
    if (!$event) {
        return $locked('Event not found.');
    }

    $status = strtolower(trim((string) ($event['status'] ?? '')));
    if (in_array($status, ['closed', 'completed', 'rejected'], true)) {
        return $locked('This event has ended. The schedule is read-only.');
    }

    if ($scheduleDate !== null && trim($scheduleDate) !== '') {
        $scheduleDate = substr(trim($scheduleDate), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduleDate)) {
            return $locked('Invalid date.');
        }
        if ($scheduleDate < eventify_today_ymd()) {
            return $locked('This day has passed. The schedule is read-only.');
        }
    }

    return ['ok' => true, 'error' => '', 'locked' => false];
}

/** @return array{ok: bool, error: string, locked: bool} */
function eventify_organizer_can_edit_event_schedule_by_session(mysqli $conn, int $sessionId, int $eventId): array
{
    if ($sessionId < 1 || $eventId < 1) {
        return ['ok' => false, 'error' => 'Invalid activity.', 'locked' => true];
    }
    $stmt = $conn->prepare('SELECT schedule_date FROM event_day_sessions WHERE id = ? AND event_id = ? LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not verify activity.', 'locked' => true];
    }
    $stmt->bind_param('ii', $sessionId, $eventId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return ['ok' => false, 'error' => 'Activity not found.', 'locked' => true];
    }

    return eventify_organizer_can_edit_event_schedule(
        $conn,
        $eventId,
        (string) ($row['schedule_date'] ?? '')
    );
}

/** @return array{ok: bool, error: string, locked: bool} */
function eventify_organizer_event_has_editable_schedule_day(mysqli $conn, int $eventId, array $scheduleDates): array
{
    $eventLock = eventify_organizer_can_edit_event_schedule($conn, $eventId, null);
    if (!$eventLock['ok']) {
        return $eventLock;
    }

    $scheduleDates = eventify_normalize_schedule_dates($scheduleDates);
    if ($scheduleDates === []) {
        $scheduleDates = eventify_event_day_session_schedule_dates($conn, $eventId);
    }

    $today = eventify_today_ymd();
    foreach ($scheduleDates as $ymd) {
        if ($ymd >= $today) {
            return ['ok' => true, 'error' => '', 'locked' => false];
        }
    }

    return [
        'ok' => false,
        'error' => 'All event days have passed. The schedule is read-only.',
        'locked' => true,
    ];
}

/** @return array{ok: bool, error: string, locked: bool} */
function eventify_save_event_day_session(mysqli $conn, int $eventId, string $scheduleDate, array $data, ?int $sessionId = null): array
{
    if (!eventify_event_day_sessions_ensure_table($conn)) {
        return ['ok' => false, 'error' => 'Activities feature is not available. Run the database migration.'];
    }
    eventify_event_day_sessions_ensure_enhanced($conn);
    $scheduleDate = substr(trim($scheduleDate), 0, 10);
    $dt = DateTime::createFromFormat('Y-m-d', $scheduleDate);
    if (!$dt || $dt->format('Y-m-d') !== $scheduleDate) {
        return ['ok' => false, 'error' => 'Invalid date.'];
    }
    $scheduleLock = eventify_organizer_can_edit_event_schedule($conn, $eventId, $scheduleDate);
    if (!$scheduleLock['ok']) {
        return ['ok' => false, 'error' => $scheduleLock['error']];
    }
    $title = trim((string) ($data['title'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));
    $startTime = trim((string) ($data['start_time'] ?? ''));
    $endTime = trim((string) ($data['end_time'] ?? ''));
    $category = trim((string) ($data['category'] ?? ''));
    $notes = trim((string) ($data['notes'] ?? ''));
    $status = strtolower(trim((string) ($data['status'] ?? 'scheduled')));
    $contactName = trim((string) ($data['contact_name'] ?? ''));
    $contactPhone = trim((string) ($data['contact_phone'] ?? ''));
    $maxCapRaw = trim((string) ($data['max_capacity'] ?? ''));
    $maxCapacity = null;
    if ($maxCapRaw !== '' && is_numeric($maxCapRaw)) {
        $v = (int) $maxCapRaw;
        if ($v > 0) {
            $maxCapacity = $v;
        }
    }
    $maxCapacityBind = $maxCapacity !== null ? (string) $maxCapacity : null;
    if ($title === '') {
        return ['ok' => false, 'error' => 'Activity title is required.'];
    }
    eventify_event_day_sessions_ensure_audience_columns($conn);
    $audienceParse = eventify_parse_session_audience_from_data($conn, $data, null);
    if (!$audienceParse['ok']) {
        return ['ok' => false, 'error' => $audienceParse['error'] ?? 'Invalid activity audience.'];
    }
    $parentEvent = eventify_load_event_for_activities_hub($conn, $eventId);
    if (!$parentEvent) {
        return ['ok' => false, 'error' => 'Event not found.'];
    }
    $audienceValidate = eventify_validate_session_audience_within_parent(
        $audienceParse['inherit'] ? null : $audienceParse['department'],
        $audienceParse['inherit'] ? null : $audienceParse['target_sections'],
        $parentEvent
    );
    if (!$audienceValidate['ok']) {
        return ['ok' => false, 'error' => $audienceValidate['error'] ?? 'Activity audience is too broad for this event.'];
    }
    $sessionDepartment = $audienceParse['inherit'] ? null : $audienceParse['department'];
    $sessionTargetSections = $audienceParse['inherit'] ? null : $audienceParse['target_sections'];
    if (strlen($title) > 150) {
        return ['ok' => false, 'error' => 'Title must be 150 characters or less.'];
    }
    if (strlen($location) > 255) {
        return ['ok' => false, 'error' => 'Location must be 255 characters or less.'];
    }
    if ($location === '') {
        return ['ok' => false, 'error' => 'Location is required.'];
    }
    if ($category !== '' && strlen($category) > 80) {
        return ['ok' => false, 'error' => 'Category must be 80 characters or less.'];
    }
    if ($category === '') {
        $category = null;
    }
    if ($notes !== '' && strlen($notes) > 5000) {
        return ['ok' => false, 'error' => 'Notes are too long.'];
    }
    if ($notes === '') {
        $notes = null;
    }
    if (!in_array($status, eventify_day_session_status_options(), true)) {
        $status = 'scheduled';
    }
    if ($contactName !== '' && strlen($contactName) > 100) {
        return ['ok' => false, 'error' => 'Contact name must be 100 characters or less.'];
    }
    if ($contactName === '') {
        $contactName = null;
    }
    if ($contactPhone !== '' && strlen($contactPhone) > 50) {
        return ['ok' => false, 'error' => 'Contact phone must be 50 characters or less.'];
    }
    if ($contactPhone === '') {
        $contactPhone = null;
    }
    if ($startTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $startTime)) {
        return ['ok' => false, 'error' => 'Invalid start time.'];
    }
    if ($endTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        return ['ok' => false, 'error' => 'Invalid end time.'];
    }
    if ($startTime !== '' && $endTime !== '' && $endTime <= $startTime) {
        return ['ok' => false, 'error' => 'End time must be after start time.'];
    }
    $stParam = $startTime !== '' ? $startTime : null;
    $etParam = $endTime !== '' ? $endTime : null;
    $checkinStart = trim((string) ($data['checkin_start_time'] ?? ''));
    $checkinEnd = trim((string) ($data['checkin_end_time'] ?? ''));
    if ($checkinStart !== '' && !preg_match('/^\d{2}:\d{2}$/', $checkinStart)) {
        return ['ok' => false, 'error' => 'Invalid attendance open time.'];
    }
    if ($checkinEnd !== '' && !preg_match('/^\d{2}:\d{2}$/', $checkinEnd)) {
        return ['ok' => false, 'error' => 'Invalid attendance close time.'];
    }
    $ciStartParam = $checkinStart !== '' ? $checkinStart : null;
    $ciEndParam = $checkinEnd !== '' ? $checkinEnd : null;
    if (!function_exists('eventify_validate_event_checkin_times')) {
        require_once __DIR__ . '/event_calendar.php';
    }
    $checkinErr = eventify_validate_event_checkin_times($ciStartParam, $ciEndParam, $stParam, $etParam);
    if ($checkinErr !== null) {
        return ['ok' => false, 'error' => $checkinErr];
    }
    $attendanceMode = eventify_parse_attendance_mode_from_request($data);
    $checkoutEndParam = eventify_parse_checkout_end_time_from_request($data);
    if ($attendanceMode === 'time_in_out') {
        $checkoutErr = eventify_validate_checkout_end_time($checkoutEndParam, $etParam);
        if ($checkoutErr !== null) {
            return ['ok' => false, 'error' => $checkoutErr];
        }
    } else {
        $checkoutEndParam = null;
    }
    $hasGeo = eventify_day_sessions_have_geo_columns($conn);
    $hasEnhanced = eventify_day_sessions_have_enhanced_columns($conn);
    $hasCheckin = eventify_day_sessions_have_checkin_time_columns($conn);
    $latParam = null;
    $lngParam = null;
    if ($hasGeo) {
        $latRaw = trim((string) ($data['latitude'] ?? ''));
        $lngRaw = trim((string) ($data['longitude'] ?? ''));
        if ($latRaw === '' || $lngRaw === '') {
            return ['ok' => false, 'error' => 'Please set the venue on the map, search for a place, or use your location.'];
        }
        if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
            return ['ok' => false, 'error' => 'Invalid map coordinates.'];
        }
        $latParam = (float) $latRaw;
        $lngParam = (float) $lngRaw;
        if (abs($latParam) > 90 || abs($lngParam) > 180) {
            return ['ok' => false, 'error' => 'Invalid map coordinates.'];
        }
    }

    $oldSession = null;
    if ($sessionId !== null && $sessionId > 0) {
        $existing = eventify_load_event_day_sessions($conn, $eventId, $scheduleDate);
        foreach ($existing as $ex) {
            if ((int) ($ex['id'] ?? 0) === $sessionId) {
                $oldSession = $ex;
                break;
            }
        }
    }

    if ($sessionId !== null && $sessionId > 0) {
        if ($hasEnhanced && $hasGeo && $hasCheckin) {
            $stmt = $conn->prepare(
                'UPDATE event_day_sessions SET title = ?, category = ?, location = ?, latitude = ?, longitude = ?, notes = ?, status = ?,
                 start_time = ?, end_time = ?, checkin_start_time = ?, checkin_end_time = ?, max_capacity = ?, contact_name = ?, contact_phone = ?, sort_order = ?
                 WHERE id = ? AND event_id = ? AND schedule_date = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not update activity.'];
            }
            $sortOrder = (int) ($data['sort_order'] ?? ($oldSession['sort_order'] ?? 0));
            $stmt->bind_param(
                'sssddsssssssssiiis',
                $title,
                $category,
                $location,
                $latParam,
                $lngParam,
                $notes,
                $status,
                $stParam,
                $etParam,
                $ciStartParam,
                $ciEndParam,
                $maxCapacityBind,
                $contactName,
                $contactPhone,
                $sortOrder,
                $sessionId,
                $eventId,
                $scheduleDate
            );
        } elseif ($hasEnhanced && $hasGeo) {
            $stmt = $conn->prepare(
                'UPDATE event_day_sessions SET title = ?, category = ?, location = ?, latitude = ?, longitude = ?, notes = ?, status = ?,
                 start_time = ?, end_time = ?, max_capacity = ?, contact_name = ?, contact_phone = ?, sort_order = ?
                 WHERE id = ? AND event_id = ? AND schedule_date = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not update activity.'];
            }
            $sortOrder = (int) ($data['sort_order'] ?? ($oldSession['sort_order'] ?? 0));
            $stmt->bind_param(
                'sssddsssssssiiis',
                $title,
                $category,
                $location,
                $latParam,
                $lngParam,
                $notes,
                $status,
                $stParam,
                $etParam,
                $maxCapacityBind,
                $contactName,
                $contactPhone,
                $sortOrder,
                $sessionId,
                $eventId,
                $scheduleDate
            );
        } elseif ($hasEnhanced && $hasCheckin) {
            $stmt = $conn->prepare(
                'UPDATE event_day_sessions SET title = ?, category = ?, location = ?, notes = ?, status = ?,
                 start_time = ?, end_time = ?, checkin_start_time = ?, checkin_end_time = ?, max_capacity = ?, contact_name = ?, contact_phone = ?, sort_order = ?
                 WHERE id = ? AND event_id = ? AND schedule_date = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not update activity.'];
            }
            $sortOrder = (int) ($data['sort_order'] ?? ($oldSession['sort_order'] ?? 0));
            $stmt->bind_param(
                'ssssssssssssiiis',
                $title,
                $category,
                $location,
                $notes,
                $status,
                $stParam,
                $etParam,
                $ciStartParam,
                $ciEndParam,
                $maxCapacityBind,
                $contactName,
                $contactPhone,
                $sortOrder,
                $sessionId,
                $eventId,
                $scheduleDate
            );
        } elseif ($hasEnhanced) {
            $stmt = $conn->prepare(
                'UPDATE event_day_sessions SET title = ?, category = ?, location = ?, notes = ?, status = ?,
                 start_time = ?, end_time = ?, max_capacity = ?, contact_name = ?, contact_phone = ?, sort_order = ?
                 WHERE id = ? AND event_id = ? AND schedule_date = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not update activity.'];
            }
            $sortOrder = (int) ($data['sort_order'] ?? ($oldSession['sort_order'] ?? 0));
            $stmt->bind_param(
                'ssssssssssiiis',
                $title,
                $category,
                $location,
                $notes,
                $status,
                $stParam,
                $etParam,
                $maxCapacityBind,
                $contactName,
                $contactPhone,
                $sortOrder,
                $sessionId,
                $eventId,
                $scheduleDate
            );
        } elseif ($hasGeo) {
            $stmt = $conn->prepare(
                'UPDATE event_day_sessions SET title = ?, location = ?, latitude = ?, longitude = ?, start_time = ?, end_time = ?
                 WHERE id = ? AND event_id = ? AND schedule_date = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not update activity.'];
            }
            $stmt->bind_param('ssddssiis', $title, $location, $latParam, $lngParam, $stParam, $etParam, $sessionId, $eventId, $scheduleDate);
        } else {
            $stmt = $conn->prepare(
                'UPDATE event_day_sessions SET title = ?, location = ?, start_time = ?, end_time = ?
                 WHERE id = ? AND event_id = ? AND schedule_date = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not update activity.'];
            }
            $stmt->bind_param('ssssiis', $title, $location, $stParam, $etParam, $sessionId, $eventId, $scheduleDate);
        }
        $ok = $stmt->execute();
        if (!$ok && $stmt->errno) {
            $err = $stmt->error ?: 'Database error.';
            $stmt->close();
            return ['ok' => false, 'error' => 'Could not update activity. ' . $err];
        }
        $stmt->close();
        if ($ok && $oldSession !== null) {
            eventify_notify_session_changes($conn, $eventId, $oldSession, [
                'title' => $title,
                'status' => $status,
                'start_time' => $stParam,
                'end_time' => $etParam,
                'schedule_date' => $scheduleDate,
                'department' => $sessionDepartment,
                'target_sections' => $sessionTargetSections,
            ]);
        }
        if ($ok && $hasEnhanced) {
            eventify_ensure_session_checkin_token($conn, $sessionId);
        }
        if ($ok) {
            $accessResult = eventify_apply_session_access_from_data($conn, $sessionId, $eventId, $data);
            if (!$accessResult['ok']) {
                return $accessResult;
            }
            eventify_save_session_audience($conn, $sessionId, $sessionDepartment, $sessionTargetSections);
            eventify_save_session_attendance_settings($conn, $sessionId, $attendanceMode, $checkoutEndParam);
        }
        return $ok ? ['ok' => true, 'id' => $sessionId] : ['ok' => false, 'error' => 'Activity not found.'];
    }

    $sortOrder = (int) ($data['sort_order'] ?? 0);
    if ($hasEnhanced && $hasGeo && $hasCheckin) {
        $stmt = $conn->prepare(
            'INSERT INTO event_day_sessions (event_id, schedule_date, title, category, location, latitude, longitude, notes, status, start_time, end_time, checkin_start_time, checkin_end_time, max_capacity, contact_name, contact_phone, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save activity.'];
        }
        $stmt->bind_param(
            'issssddsssssssssi',
            $eventId,
            $scheduleDate,
            $title,
            $category,
            $location,
            $latParam,
            $lngParam,
            $notes,
            $status,
            $stParam,
            $etParam,
            $ciStartParam,
            $ciEndParam,
            $maxCapacityBind,
            $contactName,
            $contactPhone,
            $sortOrder
        );
    } elseif ($hasEnhanced && $hasGeo) {
        $stmt = $conn->prepare(
            'INSERT INTO event_day_sessions (event_id, schedule_date, title, category, location, latitude, longitude, notes, status, start_time, end_time, max_capacity, contact_name, contact_phone, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save activity.'];
        }
        $stmt->bind_param(
            'issssddsssssssi',
            $eventId,
            $scheduleDate,
            $title,
            $category,
            $location,
            $latParam,
            $lngParam,
            $notes,
            $status,
            $stParam,
            $etParam,
            $maxCapacityBind,
            $contactName,
            $contactPhone,
            $sortOrder
        );
    } elseif ($hasEnhanced && $hasCheckin) {
        $stmt = $conn->prepare(
            'INSERT INTO event_day_sessions (event_id, schedule_date, title, category, location, notes, status, start_time, end_time, checkin_start_time, checkin_end_time, max_capacity, contact_name, contact_phone, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save activity.'];
        }
        $stmt->bind_param(
            'isssssssssssssi',
            $eventId,
            $scheduleDate,
            $title,
            $category,
            $location,
            $notes,
            $status,
            $stParam,
            $etParam,
            $ciStartParam,
            $ciEndParam,
            $maxCapacityBind,
            $contactName,
            $contactPhone,
            $sortOrder
        );
    } elseif ($hasEnhanced) {
        $stmt = $conn->prepare(
            'INSERT INTO event_day_sessions (event_id, schedule_date, title, category, location, notes, status, start_time, end_time, max_capacity, contact_name, contact_phone, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save activity.'];
        }
        $stmt->bind_param(
            'isssssssssssi',
            $eventId,
            $scheduleDate,
            $title,
            $category,
            $location,
            $notes,
            $status,
            $stParam,
            $etParam,
            $maxCapacityBind,
            $contactName,
            $contactPhone,
            $sortOrder
        );
    } elseif ($hasGeo) {
        $stmt = $conn->prepare(
            'INSERT INTO event_day_sessions (event_id, schedule_date, title, location, latitude, longitude, start_time, end_time, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save activity.'];
        }
        $stmt->bind_param('issddsssi', $eventId, $scheduleDate, $title, $location, $latParam, $lngParam, $stParam, $etParam, $sortOrder);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO event_day_sessions (event_id, schedule_date, title, location, start_time, end_time, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save activity.'];
        }
        $stmt->bind_param('isssssi', $eventId, $scheduleDate, $title, $location, $stParam, $etParam, $sortOrder);
    }
    $stmt->execute();
    if ($stmt->errno) {
        $err = $stmt->error ?: 'Database error.';
        $stmt->close();
        return ['ok' => false, 'error' => 'Could not save activity. ' . $err];
    }
    $newId = (int) $conn->insert_id;
    $stmt->close();
    if ($newId > 0 && $hasEnhanced) {
        eventify_ensure_session_checkin_token($conn, $newId);
    }
    if ($newId > 0) {
        $accessResult = eventify_apply_session_access_from_data($conn, $newId, $eventId, $data);
        if (!$accessResult['ok']) {
            return $accessResult;
        }
        eventify_save_session_audience($conn, $newId, $sessionDepartment, $sessionTargetSections);
        eventify_save_session_attendance_settings($conn, $newId, $attendanceMode, $checkoutEndParam);
        try {
            eventify_notify_students_activity_created($conn, $eventId, $newId, [
                'title' => $title,
                'schedule_date' => $scheduleDate,
                'start_time' => $stParam,
                'end_time' => $etParam,
                'location' => $location,
                'department' => $sessionDepartment,
                'target_sections' => $sessionTargetSections,
            ]);
        } catch (Throwable $e) {
            /* activity saved; notification is best-effort */
        }
    }
    return $newId > 0 ? ['ok' => true, 'id' => $newId] : ['ok' => false, 'error' => 'Could not save activity.'];
}

/**
 * @param array<string, mixed> $data
 * @return array{ok: bool, error?: string}
 */
function eventify_apply_session_access_from_data(mysqli $conn, int $sessionId, int $eventId, array $data): array
{
    if (!array_key_exists('access_mode', $data)) {
        return ['ok' => true];
    }
    $accessMode = strtolower(trim((string) ($data['access_mode'] ?? 'open')));
    $ticketTypeIds = [];
    // Preferred: an array of ids (multi-tier). Accept arrays or comma-separated strings.
    if (array_key_exists('ticket_type_ids', $data)) {
        $raw = $data['ticket_type_ids'];
        if (is_array($raw)) {
            foreach ($raw as $v) {
                $ticketTypeIds[] = (int) $v;
            }
        } elseif (is_string($raw) && $raw !== '') {
            foreach (explode(',', $raw) as $v) {
                $ticketTypeIds[] = (int) trim($v);
            }
        }
    }
    // Backward compatibility: a single ticket_type_id field.
    if ($ticketTypeIds === [] && array_key_exists('ticket_type_id', $data)) {
        $rawType = trim((string) ($data['ticket_type_id'] ?? ''));
        if ($rawType !== '' && ctype_digit($rawType)) {
            $ticketTypeIds[] = (int) $rawType;
        }
    }
    return eventify_save_session_access_meta($conn, $sessionId, $eventId, $accessMode, $ticketTypeIds);
}

/** @return array{ok: bool, error?: string} */
function eventify_delete_event_day_session(mysqli $conn, int $sessionId, int $eventId): array
{
    if ($sessionId < 1 || !eventify_event_day_sessions_ensure_table($conn)) {
        return ['ok' => false, 'error' => 'Invalid activity.'];
    }
    $scheduleLock = eventify_organizer_can_edit_event_schedule_by_session($conn, $sessionId, $eventId);
    if (!$scheduleLock['ok']) {
        return ['ok' => false, 'error' => $scheduleLock['error']];
    }
    $stmt = $conn->prepare('DELETE FROM event_day_sessions WHERE id = ? AND event_id = ?');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not delete activity.'];
    }
    $stmt->bind_param('ii', $sessionId, $eventId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Activity not found.'];
}

/** @return array{ok: bool, error?: string} */
function eventify_end_session_early(mysqli $conn, int $sessionId, int $eventId, int $organizerId): array
{
    if ($sessionId < 1 || $eventId < 1) {
        return ['ok' => false, 'error' => 'Invalid activity.'];
    }
    if (!eventify_user_may_manage_event_activities($conn, (string) ($_SESSION['role'] ?? ''), $organizerId, $eventId)) {
        return ['ok' => false, 'error' => 'Access denied.'];
    }
    eventify_day_sessions_ensure_completed_status($conn);

    $session = eventify_load_activity_session($conn, $sessionId, $eventId, null);
    if (!$session) {
        return ['ok' => false, 'error' => 'Activity not found.'];
    }

    require_once __DIR__ . '/event_calendar.php';
    $event = eventify_load_event_for_activities_hub($conn, $eventId);
    if (!$event || !eventify_event_is_live($event)) {
        return ['ok' => false, 'error' => 'The main event is not active. Reopen the main event first if you need to manage activities.'];
    }

    $status = strtolower(trim((string) ($session['status'] ?? '')));
    if ($status === 'completed') {
        return ['ok' => false, 'error' => 'This activity is already ended.'];
    }
    if ($status === 'cancelled') {
        return ['ok' => false, 'error' => 'This activity was cancelled.'];
    }
    if (eventify_session_has_ended($session)) {
        return ['ok' => false, 'error' => 'This activity has already passed its scheduled time.'];
    }

    $stmt = $conn->prepare(
        "UPDATE event_day_sessions SET status = 'completed' WHERE id = ? AND event_id = ? AND status IN ('scheduled','delayed')"
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not end activity.'];
    }
    $stmt->bind_param('ii', $sessionId, $eventId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'error' => 'Activity not found or already ended.'];
    }

    $updated = array_merge($session, ['status' => 'completed']);
    eventify_notify_session_changes($conn, $eventId, $session, $updated);

    return ['ok' => true];
}

function eventify_format_session_time_range(?string $start, ?string $end): string
{
    $fmt = static function (?string $t): string {
        $t = trim((string) $t);
        if ($t === '') {
            return '';
        }
        $ts = strtotime($t);
        return $ts ? date('g:i A', $ts) : $t;
    };
    $a = $fmt($start);
    $b = $fmt($end);
    if ($a !== '' && $b !== '') {
        return $a . ' – ' . $b;
    }
    if ($a !== '') {
        return $a;
    }
    return '';
}

/** @param list<array<string, mixed>> $sessions */
function eventify_attach_session_rsvp_meta(mysqli $conn, array &$sessions, ?int $viewerUserId): void
{
    if ($sessions === [] || !eventify_session_rsvps_table_exists($conn)) {
        return;
    }
    $ids = array_values(array_filter(array_map(static function ($s) {
        return (int) ($s['id'] ?? 0);
    }, $sessions), static function ($id) {
        return $id > 0;
    }));
    if ($ids === []) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $counts = [];
    $sql = "SELECT session_id, COUNT(*) AS c FROM event_day_session_rsvps WHERE session_id IN ($placeholders) GROUP BY session_id";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $counts[(int) $row['session_id']] = (int) $row['c'];
        }
        $stmt->close();
    }
    $userRsvped = [];
    if ($viewerUserId !== null && $viewerUserId > 0) {
        $types2 = 'i' . str_repeat('i', count($ids));
        $sql2 = "SELECT session_id FROM event_day_session_rsvps WHERE user_id = ? AND session_id IN ($placeholders)";
        $stmt2 = $conn->prepare($sql2);
        if ($stmt2) {
            $params = array_merge([$viewerUserId], $ids);
            $stmt2->bind_param($types2, ...$params);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            while ($row = $res2->fetch_assoc()) {
                $userRsvped[(int) $row['session_id']] = true;
            }
            $stmt2->close();
        }
    }
    foreach ($sessions as &$s) {
        $sid = (int) ($s['id'] ?? 0);
        $s['rsvp_count'] = $counts[$sid] ?? 0;
        $s['user_rsvped'] = !empty($userRsvped[$sid]);
    }
    unset($s);
}

/** @param list<array<string, mixed>> $sessions */
function eventify_attach_session_checkin_meta(mysqli $conn, array &$sessions, int $viewerUserId): void
{
    if ($sessions === [] || $viewerUserId < 1) {
        return;
    }
    if (!function_exists('eventify_attendance_session_has_checkout_column')) {
        require_once __DIR__ . '/event_checkin_security.php';
    }
    try {
        $chk = $conn->query("SHOW TABLES LIKE 'event_day_session_attendance'");
        if (!$chk || $chk->num_rows < 1) {
            return;
        }
    } catch (Throwable $e) {
        return;
    }
    $ids = array_values(array_filter(array_map(static function ($s) {
        return (int) ($s['id'] ?? 0);
    }, $sessions), static function ($id) {
        return $id > 0;
    }));
    if ($ids === []) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = 'i' . str_repeat('i', count($ids));
    $checkedIn = [];
    $checkedOut = [];
    $hasOutCol = eventify_attendance_session_has_checkout_column($conn);
    $sql = $hasOutCol
        ? "SELECT session_id, checked_in_at, checked_out_at FROM event_day_session_attendance WHERE user_id = ? AND session_id IN ($placeholders)"
        : "SELECT session_id, checked_in_at FROM event_day_session_attendance WHERE user_id = ? AND session_id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $params = array_merge([$viewerUserId], $ids);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $sid = (int) ($row['session_id'] ?? 0);
            $checkedIn[$sid] = (string) ($row['checked_in_at'] ?? '');
            if ($hasOutCol && !empty($row['checked_out_at'])) {
                $checkedOut[$sid] = (string) $row['checked_out_at'];
            }
        }
        $stmt->close();
    }
    foreach ($sessions as &$s) {
        $sid = (int) ($s['id'] ?? 0);
        $s['user_checked_in'] = isset($checkedIn[$sid]);
        $s['checked_in_at'] = $checkedIn[$sid] ?? null;
        $s['checked_out_at'] = $checkedOut[$sid] ?? null;
        $s['user_attendance_complete'] = isset($checkedIn[$sid]) && (
            !eventify_entity_uses_time_in_out($s) || isset($checkedOut[$sid])
        );
    }
    unset($s);
}

/** @param array<string, mixed> $session */
function eventify_session_allows_cancel_rsvp(array $session): bool
{
    if (empty($session['user_rsvped'])) {
        return false;
    }
    if (!empty($session['user_checked_in'])) {
        return false;
    }
    return eventify_session_allows_rsvp($session);
}

/** @return array{ok: bool, error?: string} */
function eventify_register_session_rsvp(mysqli $conn, int $sessionId, int $userId): array
{
    if ($sessionId < 1 || $userId < 1 || !eventify_session_rsvps_table_exists($conn)) {
        return ['ok' => false, 'error' => 'Invalid activity.'];
    }
    $accessCols = eventify_day_sessions_have_access_columns($conn)
        ? 's.access_mode, s.ticket_type_id,'
        : '';
    $sessionAudienceCols = eventify_day_sessions_have_audience_columns($conn)
        ? 's.department AS session_department, s.target_sections AS session_target_sections,'
        : '';
    if (is_file(__DIR__ . '/../../config/student_sections.php')) {
        require_once __DIR__ . '/../../config/student_sections.php';
    }
    if (function_exists('eventify_sections_schema_ensure')) {
        eventify_sections_schema_ensure($conn);
    }
    $tsCol = (function_exists('eventify_events_has_target_sections') && eventify_events_has_target_sections($conn))
        ? 'e.target_sections,'
        : '';
    $stuSecCol = 'u.department AS student_department';
    try {
        $sc = $conn->query("SHOW COLUMNS FROM users LIKE 'student_section'");
        if ($sc && $sc->num_rows > 0) {
            $stuSecCol .= ', u.student_section';
        }
    } catch (Throwable $e) {
        /* ignore */
    }
    $stmt = $conn->prepare(
        "SELECT s.id, s.event_id, s.title, s.status, {$accessCols} {$sessionAudienceCols} s.max_capacity, s.schedule_date, s.start_time, s.end_time,
                e.status AS event_status, e.department, {$tsCol}
                {$stuSecCol}
         FROM event_day_sessions s
         JOIN events e ON e.id = s.event_id
         JOIN users u ON u.id = ?
         WHERE s.id = ? LIMIT 1"
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Server error.'];
    }
    $stmt->bind_param('ii', $userId, $sessionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return ['ok' => false, 'error' => 'Activity not found.'];
    }
    if (($row['event_status'] ?? '') !== 'active') {
        return ['ok' => false, 'error' => 'This event is not open for registration.'];
    }
    if (($row['status'] ?? '') === 'cancelled') {
        return ['ok' => false, 'error' => 'This activity has been cancelled.'];
    }
    $mayAccess = true;
    $parentEvent = [
        'department' => $row['department'] ?? 'ALL',
        'target_sections' => $row['target_sections'] ?? null,
    ];
    $sessionForGate = [
        'department' => $row['session_department'] ?? null,
        'target_sections' => $row['session_target_sections'] ?? null,
    ];
    if (function_exists('eventify_student_may_access_session')) {
        $mayAccess = eventify_student_may_access_session($sessionForGate, $parentEvent, [
            'department' => $row['student_department'] ?? null,
            'student_section' => $row['student_section'] ?? null,
        ]);
    } elseif (function_exists('eventify_student_may_access_event')) {
        $mayAccess = eventify_student_may_access_event($row, [
            'department' => $row['student_department'] ?? null,
            'student_section' => $row['student_section'] ?? null,
        ]);
    } elseif (function_exists('eventify_student_sees_event_department')) {
        $mayAccess = eventify_student_sees_event_department(
            (string) ($row['department'] ?? 'ALL'),
            $row['student_department'] ?? null
        );
    }
    if (!$mayAccess) {
        return ['ok' => false, 'error' => 'This activity is not available for your department or class section.'];
    }
    if (!eventify_session_allows_rsvp($row)) {
        return ['ok' => false, 'error' => 'This activity has ended. RSVP is no longer available.'];
    }
    if (eventify_session_is_open_access($row)) {
        return ['ok' => false, 'error' => 'This activity uses open entry — scan the check-in QR at the venue. No RSVP needed.'];
    }
    $eventId = (int) ($row['event_id'] ?? 0);
    require_once __DIR__ . '/event_checkin_security.php';
    require_once __DIR__ . '/event_ticketing.php';
    if (!eventify_student_has_main_event_access($conn, $userId, $eventId)) {
        $evStmt = $conn->prepare('SELECT registration_mode FROM events WHERE id = ? LIMIT 1');
        $regMode = 'rsvp';
        if ($evStmt) {
            $evStmt->bind_param('i', $eventId);
            $evStmt->execute();
            $evRow = $evStmt->get_result()->fetch_assoc();
            $evStmt->close();
            if ($evRow) {
                $regMode = eventify_event_registration_mode($evRow);
            }
        }
        if ($regMode === 'paid_ticket') {
            return ['ok' => false, 'error' => 'Buy a ticket for this event first, then RSVP for activities in the hub.'];
        }
        return ['ok' => false, 'error' => 'RSVP for the main event first, then you can join activities in the hub.'];
    }
    if (eventify_session_requires_ticket($row) && !eventify_student_has_session_ticket($conn, $userId, $row)) {
        return ['ok' => false, 'error' => 'Buy a ticket for this paid activity first, then RSVP here.'];
    }
    $chk = $conn->prepare('SELECT id FROM event_day_session_rsvps WHERE session_id = ? AND user_id = ? LIMIT 1');
    if (!$chk) {
        return ['ok' => false, 'error' => 'Server error.'];
    }
    $chk->bind_param('ii', $sessionId, $userId);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        $chk->close();
        return ['ok' => false, 'error' => 'You already RSVP\'d for this activity.'];
    }
    $chk->close();
    $maxCap = isset($row['max_capacity']) && $row['max_capacity'] !== null && $row['max_capacity'] !== ''
        ? (int) $row['max_capacity'] : null;
    if ($maxCap !== null && $maxCap > 0) {
        $cStmt = $conn->prepare('SELECT COUNT(*) AS c FROM event_day_session_rsvps WHERE session_id = ?');
        if ($cStmt) {
            $cStmt->bind_param('i', $sessionId);
            $cStmt->execute();
            $cStmt->bind_result($cnt);
            $cStmt->fetch();
            $cStmt->close();
            if ((int) $cnt >= $maxCap) {
                return ['ok' => false, 'error' => 'This activity is full.'];
            }
        }
    }
    $ins = $conn->prepare('INSERT INTO event_day_session_rsvps (session_id, user_id) VALUES (?, ?)');
    if (!$ins) {
        return ['ok' => false, 'error' => 'Could not save RSVP.'];
    }
    $ins->bind_param('ii', $sessionId, $userId);
    $ok = $ins->execute();
    $ins->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Could not save RSVP.'];
}

/** @return array{ok: bool, error?: string} */
function eventify_cancel_session_rsvp(mysqli $conn, int $sessionId, int $userId): array
{
    if ($sessionId < 1 || $userId < 1 || !eventify_session_rsvps_table_exists($conn)) {
        return ['ok' => false, 'error' => 'Invalid activity.'];
    }
    $st = $conn->prepare(
        'SELECT s.id, s.status, s.schedule_date, s.start_time, s.end_time
         FROM event_day_sessions s WHERE s.id = ? LIMIT 1'
    );
    if ($st) {
        $st->bind_param('i', $sessionId);
        $st->execute();
        $sessionRow = $st->get_result()->fetch_assoc();
        $st->close();
        if ($sessionRow) {
            $session = eventify_map_day_session_row($sessionRow);
            $session['user_rsvped'] = true;
            $session['user_checked_in'] = false;
            try {
                $chk = $conn->query("SHOW TABLES LIKE 'event_day_session_attendance'");
                if ($chk && $chk->num_rows > 0) {
                    $att = $conn->prepare('SELECT 1 FROM event_day_session_attendance WHERE session_id = ? AND user_id = ? LIMIT 1');
                    if ($att) {
                        $att->bind_param('ii', $sessionId, $userId);
                        $att->execute();
                        $session['user_checked_in'] = (bool) $att->get_result()->fetch_assoc();
                        $att->close();
                    }
                }
            } catch (Throwable $e) {
                /* ignore */
            }
            if (!empty($session['user_checked_in'])) {
                return ['ok' => false, 'error' => 'You already checked in — RSVP cannot be cancelled.'];
            }
            if (!eventify_session_allows_rsvp($session)) {
                return ['ok' => false, 'error' => 'This activity has ended — RSVP can no longer be cancelled.'];
            }
        }
    }
    $stmt = $conn->prepare('DELETE FROM event_day_session_rsvps WHERE session_id = ? AND user_id = ?');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not cancel RSVP.'];
    }
    $stmt->bind_param('ii', $sessionId, $userId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'You are not RSVP\'d for this activity.'];
}

/**
 * Remove all activity RSVPs for a user under one parent event (e.g. when main event RSVP is cancelled).
 *
 * @return int Number of activity RSVPs removed
 */
function eventify_cancel_all_session_rsvps_for_event(mysqli $conn, int $eventId, int $userId): int
{
    if ($eventId < 1 || $userId < 1 || !eventify_session_rsvps_table_exists($conn)) {
        return 0;
    }
    $stmt = $conn->prepare(
        'DELETE r FROM event_day_session_rsvps r
         INNER JOIN event_day_sessions s ON s.id = r.session_id
         WHERE r.user_id = ? AND s.event_id = ?'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('ii', $userId, $eventId);
    $stmt->execute();
    $removed = $stmt->affected_rows;
    $stmt->close();
    return max(0, (int) $removed);
}

/**
 * @return list<array<string, mixed>>
 */
function eventify_load_student_today_activities(mysqli $conn, int $userId, ?string $studentDepartment, string $todayYmd): array
{
    if ($userId < 1 || !eventify_event_day_sessions_ensure_table($conn)) {
        return [];
    }
    eventify_event_day_sessions_ensure_enhanced($conn);
    eventify_sections_schema_ensure($conn);
    $studentSection = null;
    $u = $conn->prepare('SELECT student_section FROM users WHERE id = ? LIMIT 1');
    if ($u) {
        $u->bind_param('i', $userId);
        $u->execute();
        $ur = $u->get_result()->fetch_assoc();
        $u->close();
        $studentSection = $ur['student_section'] ?? null;
    }
    $todayYmd = substr(trim($todayYmd), 0, 10);
    $rawCols = explode(', ', eventify_day_sessions_select_columns($conn));
    $colList = implode(', ', array_map(static function ($c) {
        return 's.' . trim($c);
    }, $rawCols));
    $tsCol = eventify_events_has_target_sections($conn) ? ', e.target_sections' : '';
    $sql = "SELECT {$colList}, e.title AS event_title, e.department AS event_department{$tsCol}, e.status AS event_status
            FROM event_day_sessions s
            INNER JOIN events e ON e.id = s.event_id
            WHERE s.schedule_date = ? AND e.status = 'active'
            ORDER BY s.start_time ASC, s.sort_order ASC, s.id ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $todayYmd);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    $studentProfile = [
        'department' => $studentDepartment,
        'student_section' => $studentSection,
    ];
    while ($row = $res->fetch_assoc()) {
        $parentEvent = [
            'department' => $row['event_department'] ?? 'ALL',
            'target_sections' => $row['target_sections'] ?? null,
        ];
        $sessionForGate = [
            'department' => $row['department'] ?? null,
            'target_sections' => $row['target_sections'] ?? null,
        ];
        if (!eventify_student_may_access_event($parentEvent, $studentProfile)) {
            continue;
        }
        if (!eventify_student_may_access_session($sessionForGate, $parentEvent, $studentProfile)) {
            continue;
        }
        $mapped = eventify_map_day_session_row($row);
        $mapped['event_title'] = trim((string) ($row['event_title'] ?? ''));
        $mapped['event_status'] = trim((string) ($row['event_status'] ?? ''));
        $out[] = $mapped;
    }
    $stmt->close();
    if ($out !== [] && eventify_session_rsvps_table_exists($conn)) {
        eventify_attach_session_rsvp_meta($conn, $out, $userId);
    }
    return $out;
}

/**
 * Bell + push when a new sub-activity is added to a live parent event.
 * Audience uses session targeting when set, otherwise parent event audience.
 *
 * @param array<string, mixed> $session expects title, schedule_date, start_time, end_time, optional department, target_sections
 * @return int students notified
 */
function eventify_notify_students_activity_created(
    mysqli $conn,
    int $eventId,
    int $sessionId,
    array $session
): int {
    if ($eventId < 1 || $sessionId < 1) {
        return 0;
    }

    require_once __DIR__ . '/notifications_service.php';

    $event = eventify_load_event_for_activities_hub($conn, $eventId);
    if (!$event) {
        return 0;
    }
    if (strtolower(trim((string) ($event['status'] ?? ''))) !== 'active') {
        return 0;
    }

    $sessionAudience = [
        'department' => $session['department'] ?? null,
        'target_sections' => $session['target_sections'] ?? null,
    ];
    $recipientIds = eventify_student_ids_for_session_audience($conn, $sessionAudience, $event);
    if ($recipientIds === []) {
        return 0;
    }

    $activityLabel = trim((string) ($session['title'] ?? ''));
    if ($activityLabel === '') {
        $activityLabel = 'New activity';
    }
    $parentLabel = trim((string) ($event['title'] ?? ''));
    if ($parentLabel === '') {
        $parentLabel = 'an event';
    }

    $scheduleDate = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    $dateLabel = function_exists('eventify_format_event_date_label')
        ? eventify_format_event_date_label($scheduleDate)
        : $scheduleDate;
    $timeStr = eventify_format_session_time_range(
        isset($session['start_time']) ? (string) $session['start_time'] : null,
        isset($session['end_time']) ? (string) $session['end_time'] : null
    );
    $when = $dateLabel;
    if ($timeStr !== '') {
        $when .= ($when !== '' ? ' · ' : '') . $timeStr;
    }

    $notifTitle = 'New activity';
    $message = '"' . $activityLabel . '" was added to "' . $parentLabel . '"'
        . ($when !== '' ? ' on ' . $when : '')
        . '. Open activities for details.';

    return eventify_notify_student_ids(
        $conn,
        $recipientIds,
        'activity_created',
        $notifTitle,
        $message,
        $eventId
    );
}

/** @param array<string, mixed> $oldSession @param array<string, mixed> $newData */
function eventify_notify_session_changes(mysqli $conn, int $eventId, array $oldSession, array $newData): void
{
    $sessionId = (int) ($oldSession['id'] ?? 0);
    if ($sessionId < 1) {
        return;
    }
    $oldStatus = (string) ($oldSession['status'] ?? 'scheduled');
    $newStatus = (string) ($newData['status'] ?? 'scheduled');
    $oldStart = trim((string) ($oldSession['start_time'] ?? ''));
    $newStart = trim((string) ($newData['start_time'] ?? ''));
    $oldEnd = trim((string) ($oldSession['end_time'] ?? ''));
    $newEnd = trim((string) ($newData['end_time'] ?? ''));
    $title = trim((string) ($newData['title'] ?? $oldSession['title'] ?? 'Activity'));
    $dateLabel = trim((string) ($newData['schedule_date'] ?? $oldSession['schedule_date'] ?? ''));

    $notify = false;
    $msg = '';
    if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
        $notify = true;
        $msg = '"' . $title . '" on ' . $dateLabel . ' has been cancelled.';
    } elseif ($newStatus === 'completed' && $oldStatus !== 'completed') {
        $notify = true;
        $msg = '"' . $title . '" on ' . $dateLabel . ' has ended.';
    } elseif ($newStatus === 'delayed' && $oldStatus !== 'delayed') {
        $notify = true;
        $msg = '"' . $title . '" on ' . $dateLabel . ' has been delayed.';
    } elseif ($oldStart !== $newStart || $oldEnd !== $newEnd) {
        $notify = true;
        $timeStr = eventify_format_session_time_range($newStart ?: null, $newEnd ?: null);
        $msg = '"' . $title . '" on ' . $dateLabel . ' schedule updated' . ($timeStr !== '' ? ' to ' . $timeStr : '') . '.';
    }
    if (!$notify || $msg === '') {
        return;
    }
    $event = eventify_load_event_for_activities_hub($conn, $eventId);
    if (!$event) {
        return;
    }
    $sessionAudience = [
        'department' => $newData['department'] ?? $oldSession['department'] ?? null,
        'target_sections' => $newData['target_sections'] ?? $oldSession['target_sections'] ?? null,
    ];
    $userIds = [];
    foreach (eventify_student_ids_for_session_audience($conn, $sessionAudience, $event) as $uid) {
        $userIds[(int) $uid] = true;
    }
    if (eventify_session_rsvps_table_exists($conn)) {
        $rs = $conn->prepare('SELECT user_id FROM event_day_session_rsvps WHERE session_id = ?');
        if ($rs) {
            $rs->bind_param('i', $sessionId);
            $rs->execute();
            $rRes = $rs->get_result();
            while ($r = $rRes->fetch_assoc()) {
                $uid = (int) ($r['user_id'] ?? 0);
                if ($uid > 0) {
                    $userIds[$uid] = true;
                }
            }
            $rs->close();
        }
    }
    if ($userIds === []) {
        return;
    }
    $notifTitle = 'Activity update';
    try {
        $ins = $conn->prepare('INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, ?, ?, ?, ?)');
        if (!$ins) {
            return;
        }
        $type = 'activity_update';
        foreach (array_keys($userIds) as $uid) {
            $ins->bind_param('isssi', $uid, $type, $notifTitle, $msg, $eventId);
            if ($ins->execute()) {
                eventify_push_notify_user($conn, $uid, $type, $notifTitle, $msg, $eventId);
            }
        }
        $ins->close();
    } catch (Throwable $e) {
        /* ignore */
    }
}

/** @return array<string, mixed>|null */
function eventify_load_session_by_checkin_token(mysqli $conn, string $token): ?array
{
    $token = trim($token);
    if ($token === '' || !eventify_event_day_sessions_ensure_enhanced($conn)) {
        return null;
    }
    $rawCols = explode(', ', eventify_day_sessions_select_columns($conn));
    $colList = implode(', ', array_map(static function ($c) {
        return 's.' . trim($c);
    }, $rawCols));
    $eventGeoCols = '';
    try {
        $geoColCheck = $conn->query("SHOW COLUMNS FROM events WHERE Field IN ('latitude','longitude')");
        if ($geoColCheck && $geoColCheck->num_rows >= 2) {
            $eventGeoCols = ', e.latitude AS event_latitude, e.longitude AS event_longitude';
        }
    } catch (Throwable $e) {
        /* events table may not have map columns yet */
    }
    $tsJoin = '';
    try {
        $tsCol = $conn->query("SHOW COLUMNS FROM events LIKE 'target_sections'");
        if ($tsCol && $tsCol->num_rows > 0) {
            $tsJoin = ', e.target_sections AS event_target_sections';
        }
    } catch (Throwable $e) {
        /* ignore */
    }
    $stmt = $conn->prepare("SELECT {$colList}, e.title AS event_title, e.status AS event_status, e.organizer_id, e.department AS event_department{$tsJoin}{$eventGeoCols} FROM event_day_sessions s JOIN events e ON e.id = s.event_id WHERE s.checkin_token = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $mapped = eventify_map_day_session_row($row);
    $mapped['event_title'] = trim((string) ($row['event_title'] ?? ''));
    $mapped['event_status'] = trim((string) ($row['event_status'] ?? ''));
    $mapped['organizer_id'] = (int) ($row['organizer_id'] ?? 0);
    $mapped['event_department'] = $row['event_department'] ?? 'ALL';
    $mapped['event_target_sections'] = $row['event_target_sections'] ?? null;
    $mapped['event_latitude'] = $row['event_latitude'] ?? null;
    $mapped['event_longitude'] = $row['event_longitude'] ?? null;
    return $mapped;
}

/** @return array<string, mixed>|null */
function eventify_load_event_for_activities_hub(mysqli $conn, int $eventId): ?array
{
    if ($eventId < 1) {
        return null;
    }
    $cols = 'id, title, description, date, end_date, location, status, department, organizer_id';
    try {
        $mcCol = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'max_capacity'");
        if ($mcCol && $mcCol->num_rows >= 1) {
            $cols .= ', max_capacity';
        }
        $rmCol = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'registration_mode'");
        if ($rmCol && $rmCol->num_rows >= 1) {
            $cols .= ', registration_mode';
        }
        eventify_events_ensure_target_sections($conn);
        $tsCol = $conn->query("SHOW COLUMNS FROM events LIKE 'target_sections'");
        if ($tsCol && $tsCol->num_rows >= 1) {
            $cols .= ', target_sections';
        }
    } catch (Throwable $e) {
        /* ignore */
    }
    $stmt = $conn->prepare('SELECT ' . $cols . ' FROM events WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function eventify_user_can_view_event_activities(mysqli $conn, array $event, int $userId, string $role, ?string $studentDepartment = null): bool
{
    if ($userId < 1) {
        return false;
    }
    if (in_array($role, ['super_admin', 'admin'], true)) {
        return true;
    }
    if ($role === 'organizer') {
        return (int) ($event['organizer_id'] ?? 0) === $userId;
    }
    if ($role === 'student') {
        $st = strtolower((string) ($event['status'] ?? ''));
        if (!in_array($st, ['active', 'closed', 'completed'], true)) {
            return false;
        }
        if (is_file(__DIR__ . '/../../config/student_sections.php')) {
            require_once __DIR__ . '/../../config/student_sections.php';
        }
        if (!function_exists('eventify_student_may_access_event')) {
            return eventify_student_sees_event_department((string) ($event['department'] ?? 'ALL'), $studentDepartment);
        }
        $studentSection = null;
        if ($studentDepartment === null && $userId > 0) {
            $u = $conn->prepare('SELECT department, student_section FROM users WHERE id = ? LIMIT 1');
            if ($u) {
                $u->bind_param('i', $userId);
                $u->execute();
                $ur = $u->get_result()->fetch_assoc();
                $u->close();
                $studentDepartment = $ur['department'] ?? null;
                $studentSection = $ur['student_section'] ?? null;
            }
        } elseif ($userId > 0) {
            $u = $conn->prepare('SELECT student_section FROM users WHERE id = ? LIMIT 1');
            if ($u) {
                $u->bind_param('i', $userId);
                $u->execute();
                $ur = $u->get_result()->fetch_assoc();
                $u->close();
                $studentSection = $ur['student_section'] ?? null;
            }
        }
        return eventify_student_may_access_event($event, [
            'department' => $studentDepartment,
            'student_section' => $studentSection,
        ]);
    }
    if ($role === 'multimedia') {
        $st = strtolower((string) ($event['status'] ?? ''));
        if (in_array($st, ['active', 'closed', 'completed'], true)) {
            return true;
        }
        $eventId = (int) ($event['id'] ?? 0);
        if ($eventId < 1) {
            return false;
        }
        $photoChk = $conn->prepare('SELECT 1 FROM event_photos WHERE event_id = ? LIMIT 1');
        if (!$photoChk) {
            return false;
        }
        $photoChk->bind_param('i', $eventId);
        $photoChk->execute();
        $hasPhotos = (bool) $photoChk->get_result()->fetch_row();
        $photoChk->close();
        return $hasPhotos;
    }
    return false;
}

/**
 * Events the user can open in the activities hub (must have at least one day session).
 *
 * @return list<array<string, mixed>>
 */
function eventify_load_activities_hub_picker_events(mysqli $conn, int $userId, string $role, ?string $userDepartment = null): array
{
    if ($userId < 1 || !in_array($role, ['super_admin', 'admin', 'organizer', 'student', 'multimedia'], true)) {
        return [];
    }
    if (!eventify_event_day_sessions_ensure_table($conn)) {
        return [];
    }

    if ($userDepartment === null && in_array($role, ['student', 'multimedia'], true)) {
        $u = $conn->prepare('SELECT department FROM users WHERE id = ? LIMIT 1');
        if ($u) {
            $u->bind_param('i', $userId);
            $u->execute();
            $ur = $u->get_result()->fetch_assoc();
            $u->close();
            $userDepartment = $ur['department'] ?? null;
        }
    }

    $sessionCountSql = '(SELECT COUNT(*) FROM event_day_sessions s WHERE s.event_id = e.id)';
    $isOrganizer = $role === 'organizer';
    $isStaff = in_array($role, ['super_admin', 'admin', 'organizer'], true);
    $tsCol = eventify_events_has_target_sections($conn) ? ', e.target_sections' : '';
    $sql = "SELECT e.id, e.title, e.date, e.location, e.department{$tsCol}, e.status, e.organizer_id,
                   {$sessionCountSql} AS activity_count
            FROM events e
            WHERE 1=1";

    if ($isStaff && $role !== 'organizer') {
        // Closed history stays visible even when an event never got day activities.
        // Active events still need at least one activity.
        $sql .= " AND e.title NOT LIKE 'sample%'";
        $sql .= " AND (
            {$sessionCountSql} > 0
            OR e.status IN ('closed', 'completed', 'pending', 'rejected')
        )";
    } elseif (!$isOrganizer) {
        $sql .= " AND {$sessionCountSql} > 0";
        if ($role !== 'multimedia') {
            $sql .= " AND e.title NOT LIKE 'sample%'";
        }
    }

    if ($isOrganizer) {
        $sql .= ' AND e.organizer_id = ?';
    } elseif ($isStaff) {
        $sql .= " AND e.status IN ('active', 'pending', 'closed', 'completed', 'rejected')";
    } else {
        $sql .= " AND e.status IN ('active', 'closed', 'completed')";
    }

    $sql .= ' ORDER BY e.date DESC, e.id DESC';
    if (!$isStaff) {
        $sql .= ' LIMIT 80';
    }

    $res = null;
    $stmt = null;
    if ($isOrganizer) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }

    $out = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (!eventify_user_can_view_event_activities($conn, $row, $userId, $role, $userDepartment)) {
                continue;
            }
            $out[] = $row;
        }
    }
    if ($stmt) {
        $stmt->close();
    }

    if ($isStaff) {
        return $out;
    }

    return array_slice($out, 0, 50);
}

/**
 * Student's RSVP'd events for the activities hub landing (includes events without sessions yet).
 *
 * @return list<array<string, mixed>>
 */
function eventify_load_student_registered_hub_events(mysqli $conn, int $userId, ?string $studentDepartment): array
{
    if ($userId < 1) {
        return [];
    }
    $sessionCountSql = eventify_event_activity_count_sql($conn);
    $tsCol = eventify_events_has_target_sections($conn) ? ', e.target_sections' : '';
    $sql = "SELECT e.id, e.title, e.date, e.location, e.department{$tsCol}, e.status, e.end_date,
                   {$sessionCountSql} AS activity_count
            FROM events e
            INNER JOIN registrations r ON r.event_id = e.id AND r.user_id = ?
            WHERE e.status = 'active'
            ORDER BY e.date ASC, e.id ASC
            LIMIT 24";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        if (!eventify_user_can_view_event_activities($conn, $row, $userId, 'student', $studentDepartment)) {
            continue;
        }
        $out[] = $row;
    }
    $stmt->close();

    return $out;
}

function eventify_event_activity_count_sql(mysqli $conn, string $eventAlias = 'e'): string
{
    if (!eventify_event_day_sessions_table_exists($conn)) {
        return '0';
    }
    $alias = preg_replace('/[^a-zA-Z0-9_.]/', '', $eventAlias) ?: 'e';

    return "(SELECT COUNT(*) FROM event_day_sessions s WHERE s.event_id = {$alias}.id)";
}

function eventify_events_select_has_registration_mode(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        require_once __DIR__ . '/event_ticketing.php';
        eventify_ticketing_ensure_registration_mode_column($conn);
        $col = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'registration_mode'");
        $cache = ($col && $col->num_rows >= 1);
    } catch (Throwable $e) {
        $cache = false;
    }

    return $cache;
}

/**
 * Active events for the student's department (same visibility as dashboard calendar).
 * No RSVP required — includes open entry and events before activities are published.
 *
 * @return list<array<string, mixed>>
 */
function eventify_load_student_department_hub_events(mysqli $conn, int $userId, ?string $studentDepartment): array
{
    if ($userId < 1) {
        return [];
    }

    $sessionCountSql = eventify_event_activity_count_sql($conn);
    $regModeCol = eventify_events_select_has_registration_mode($conn) ? ', e.registration_mode' : '';
    $tsCol = eventify_events_has_target_sections($conn) ? ', e.target_sections' : '';
    $dept = trim((string) $studentDepartment);
    $out = [];

    if ($dept !== '') {
        $deptSql = eventify_department_match_sql('e.department');
        $sql = "SELECT e.id, e.title, e.date, e.location, e.department{$tsCol}, e.status, e.end_date{$regModeCol},
                       {$sessionCountSql} AS activity_count
                FROM events e
                WHERE e.status = 'active'
                  AND e.title NOT LIKE 'sample%'
                  AND {$deptSql}
                ORDER BY e.date ASC, e.id ASC
                LIMIT 40";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ss', $dept, $dept);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (eventify_user_can_view_event_activities($conn, $row, $userId, 'student', $studentDepartment)) {
                $out[] = $row;
            }
        }
        $stmt->close();
    } else {
        $sql = "SELECT e.id, e.title, e.date, e.location, e.department{$tsCol}, e.status, e.end_date{$regModeCol},
                       {$sessionCountSql} AS activity_count
                FROM events e
                WHERE e.status = 'active'
                  AND e.title NOT LIKE 'sample%'
                ORDER BY e.date ASC, e.id ASC
                LIMIT 40";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (eventify_user_can_view_event_activities($conn, $row, $userId, 'student', $studentDepartment)) {
                    $out[] = $row;
                }
            }
        }
    }

    return $out;
}

/** @deprecated Use eventify_load_student_department_hub_events() */
function eventify_load_student_open_hub_events(mysqli $conn, int $userId, ?string $studentDepartment): array
{
    require_once __DIR__ . '/event_ticketing.php';
    $all = eventify_load_student_department_hub_events($conn, $userId, $studentDepartment);
    return array_values(array_filter($all, static function (array $ev): bool {
        return eventify_event_registration_mode($ev) === 'open';
    }));
}

/**
 * Merge registered events into hub picker list (student landing).
 *
 * @param list<array<string, mixed>> $pickerEvents
 * @param list<array<string, mixed>> $registeredEvents
 * @return list<array<string, mixed>>
 */
function eventify_merge_registered_events_into_hub_picker(array $pickerEvents, array $registeredEvents): array
{
    $byId = [];
    foreach ($pickerEvents as $ev) {
        $id = (int) ($ev['id'] ?? 0);
        if ($id > 0) {
            $byId[$id] = $ev;
        }
    }
    foreach ($registeredEvents as $ev) {
        $id = (int) ($ev['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        if (!isset($byId[$id])) {
            $byId[$id] = $ev;
        }
    }
    $merged = array_values($byId);
    usort($merged, static function ($a, $b) {
        $da = substr(trim((string) ($a['date'] ?? '')), 0, 10);
        $db = substr(trim((string) ($b['date'] ?? '')), 0, 10);
        if ($da === $db) {
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        }
        return strcmp($db, $da);
    });

    return array_slice($merged, 0, 50);
}

/**
 * Map event status to activities hub filter bucket (active, pending, closed, rejected).
 */
function eventify_hub_event_status_bucket(string $status): string
{
    $st = strtolower(trim($status));
    if ($st === 'completed') {
        return 'closed';
    }
    if (in_array($st, ['active', 'pending', 'closed', 'rejected'], true)) {
        return $st;
    }

    return 'closed';
}

/**
 * Count hub picker events in the given status buckets (students: active only for badges).
 *
 * @param list<array<string, mixed>> $events
 * @param list<string> $buckets
 */
function eventify_count_hub_events_in_statuses(array $events, array $buckets = ['active']): int
{
    if ($events === [] || $buckets === []) {
        return 0;
    }
    $n = 0;
    foreach ($events as $ev) {
        if (in_array(eventify_hub_event_status_bucket((string) ($ev['status'] ?? '')), $buckets, true)) {
            $n++;
        }
    }

    return $n;
}

/**
 * Merged picker + registered events for a student (hub navigation).
 *
 * @return list<array<string, mixed>>
 */
function eventify_load_student_merged_hub_events(mysqli $conn, int $userId, ?string $studentDepartment): array
{
    if ($userId < 1) {
        return [];
    }
    $picker = eventify_load_activities_hub_picker_events($conn, $userId, 'student', $studentDepartment);
    $registered = eventify_load_student_registered_hub_events($conn, $userId, $studentDepartment);
    $open = eventify_load_student_department_hub_events($conn, $userId, $studentDepartment);
    $merged = eventify_merge_registered_events_into_hub_picker($picker, $registered);

    return eventify_merge_registered_events_into_hub_picker($merged, $open);
}

/**
 * Pick the primary event hub from a list (richest active schedule wins).
 */
function eventify_pick_primary_hub_event_id(array $events, int $preferEventId = 0, int $preferActivityCount = 0): int
{
    if ($preferEventId > 0) {
        foreach ($events as $ev) {
            if ((int) ($ev['id'] ?? 0) === $preferEventId) {
                return $preferEventId;
            }
        }
        if ($preferActivityCount > 0) {
            return $preferEventId;
        }
    }

    if ($events === []) {
        return $preferEventId > 0 ? $preferEventId : 0;
    }

    $candidates = [];
    foreach ($events as $ev) {
        if ((int) ($ev['activity_count'] ?? 0) < 1) {
            continue;
        }
        if (strtolower((string) ($ev['status'] ?? '')) !== 'active') {
            continue;
        }
        $candidates[] = $ev;
    }
    if ($candidates === []) {
        foreach ($events as $ev) {
            if ((int) ($ev['activity_count'] ?? 0) > 0) {
                $candidates[] = $ev;
            }
        }
    }
    if ($candidates === []) {
        return (int) ($events[0]['id'] ?? 0) ?: ($preferEventId > 0 ? $preferEventId : 0);
    }

    usort($candidates, static function ($a, $b) {
        $ca = (int) ($a['activity_count'] ?? 0);
        $cb = (int) ($b['activity_count'] ?? 0);
        if ($ca !== $cb) {
            return $cb <=> $ca;
        }
        $da = substr(trim((string) ($a['date'] ?? '')), 0, 10);
        $db = substr(trim((string) ($b['date'] ?? '')), 0, 10);

        return strcmp($db, $da);
    });

    return (int) ($candidates[0]['id'] ?? 0);
}

/**
 * Primary event hub for a student (registered active event with the richest schedule).
 */
function eventify_resolve_student_hub_home_event_id(
    mysqli $conn,
    int $userId,
    ?string $studentDepartment,
    int $currentEventId = 0,
    int $currentActivityCount = 0
): int {
    $merged = eventify_load_student_merged_hub_events($conn, $userId, $studentDepartment);
    if ($merged === []) {
        return $currentEventId > 0 ? $currentEventId : 0;
    }

    return eventify_pick_primary_hub_event_id($merged, $currentEventId, $currentActivityCount);
}

/**
 * Main hub URL for activities navigation (event hub page, never dashboard or index).
 */
function eventify_activities_hub_main_url(
    mysqli $conn,
    int $userId,
    string $role,
    ?string $userDepartment = null,
    int $preferEventId = 0,
    int $preferActivityCount = 0
): string {
    $base = defined('BASE_URL') ? BASE_URL : '';
    $listUrl = $base . '/activities_hub.php';
    if ($userId < 1) {
        return $listUrl;
    }

    $role = strtolower(trim($role));

    if ($preferEventId < 1) {
        $sessionPrefer = (int) ($_SESSION['eventify_main_hub_event_id'] ?? 0);
        if ($sessionPrefer > 0) {
            $preferEventId = $sessionPrefer;
        }
    }

    if ($role === 'student') {
        return eventify_student_activities_hub_home_url(
            $conn,
            $userId,
            $userDepartment,
            $preferEventId,
            $preferActivityCount,
            $preferEventId > 0 ? 'hub' : null
        );
    }

    if ($preferEventId > 0) {
        $event = eventify_load_event_for_activities_hub($conn, $preferEventId);
        if ($event && eventify_user_can_view_event_activities($conn, $event, $userId, $role, $userDepartment)) {
            return $base . '/event_activities.php?id=' . $preferEventId;
        }
    }

    $events = eventify_load_activities_hub_picker_events($conn, $userId, $role, $userDepartment);
    $eventId = eventify_pick_primary_hub_event_id($events, $preferEventId, $preferActivityCount);
    if ($eventId > 0) {
        return $base . '/event_activities.php?id=' . $eventId;
    }

    return $listUrl;
}

/**
 * Student top-bar home target: main event hub (e.g. sample intrams), not dashboard or bare picker.
 */
function eventify_student_activities_hub_home_url(
    mysqli $conn,
    int $userId,
    ?string $studentDepartment,
    int $currentEventId = 0,
    int $currentActivityCount = 0,
    ?string $view = null
): string {
    $base = defined('BASE_URL') ? BASE_URL : '';
    $listUrl = $base . '/activities_hub.php';
    if ($userId < 1) {
        return $listUrl;
    }

    if ($currentEventId > 0 && $view !== null && $view !== 'hub') {
        return $base . '/event_activities.php?id=' . $currentEventId;
    }

    $homeEventId = eventify_resolve_student_hub_home_event_id(
        $conn,
        $userId,
        $studentDepartment,
        $currentEventId,
        $currentActivityCount
    );
    if ($homeEventId > 0) {
        return $base . '/event_activities.php?id=' . $homeEventId;
    }

    return $listUrl;
}

function eventify_activity_icon(string $title, ?string $category = null): string
{
    $t = strtolower($title);
    $map = [
        'badminton' => '🏸', 'basketball' => '🏀', 'volleyball' => '🏐', 'soccer' => '⚽', 'football' => '🏈',
        'tennis' => '🎾', 'table tennis' => '🏓', 'chess' => '♟️', 'swimming' => '🏊', 'track' => '🏃',
        'boxing' => '🥊', 'dance' => '💃', 'singing' => '🎤', 'debate' => '🗣️', 'quiz' => '❓',
    ];
    foreach ($map as $key => $icon) {
        if (strpos($t, $key) !== false) {
            return $icon;
        }
    }
    $cat = strtolower(trim((string) $category));
    $catIcons = [
        'sports' => '🏅', 'ceremony' => '🎓', 'workshop' => '🛠️',
        'competition' => '🏆', 'social' => '🎉', 'other' => '📋',
    ];
    return $catIcons[$cat] ?? '📅';
}

/** Font Awesome 6 icon class for activity cards (no fa- prefix). */
function eventify_activity_fa_icon(string $title, ?string $category = null): string
{
    $t = strtolower($title);
    $map = [
        'badminton' => 'shuttlecock',
        'basketball' => 'basketball',
        'volleyball' => 'volleyball',
        'soccer' => 'futbol',
        'football' => 'football-ball',
        'tennis' => 'table-tennis-paddle-ball',
        'chess' => 'chess',
        'swimming' => 'person-swimming',
        'track' => 'person-running',
        'dance' => 'music',
        'singing' => 'microphone',
        'debate' => 'comments',
        'quiz' => 'circle-question',
    ];
    foreach ($map as $key => $icon) {
        if (strpos($t, $key) !== false) {
            return $icon;
        }
    }
    $cat = strtolower(trim((string) $category));
    $catIcons = [
        'sports' => 'medal',
        'ceremony' => 'graduation-cap',
        'workshop' => 'screwdriver-wrench',
        'competition' => 'trophy',
        'social' => 'champagne-glasses',
        'other' => 'clipboard-list',
    ];
    return $catIcons[$cat] ?? 'calendar-day';
}

function eventify_short_activity_location(?string $location): string
{
    $loc = trim((string) $location);
    if ($loc === '' || $loc === '0' || !preg_match('/[a-zA-Z\x{00C0}-\x{024F}]/u', $loc)) {
        return '';
    }
    if (strlen($loc) > 52) {
        return substr($loc, 0, 49) . '…';
    }
    return $loc;
}

function eventify_app_timezone(): DateTimeZone
{
    static $tz = null;
    if ($tz instanceof DateTimeZone) {
        return $tz;
    }
    $name = defined('EVENTIFY_APP_TIMEZONE') ? (string) EVENTIFY_APP_TIMEZONE : 'Asia/Manila';
    try {
        $tz = new DateTimeZone($name);
    } catch (Exception $e) {
        $tz = new DateTimeZone('UTC');
    }
    return $tz;
}

/** Normalize MySQL TIME to H:i:s for parsing. */
function eventify_normalize_session_time(string $time): string
{
    $time = trim($time);
    if ($time === '') {
        return '';
    }
    if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
        return $time . ':00';
    }
    return $time;
}

/**
 * Parse schedule date + time in the app timezone.
 */
function eventify_session_datetime(string $dayYmd, string $time): ?DateTimeImmutable
{
    $dayYmd = substr(trim($dayYmd), 0, 10);
    $time = eventify_normalize_session_time($time);
    if ($dayYmd === '' || $time === '') {
        return null;
    }
    $tz = eventify_app_timezone();
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $dayYmd . ' ' . $time, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
    }
    return null;
}

function eventify_today_ymd(): string
{
    return (new DateTimeImmutable('now', eventify_app_timezone()))->format('Y-m-d');
}

/** @param array<string, mixed> $session */
function eventify_session_effective_end_datetime(array $session): ?DateTimeImmutable
{
    $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    if ($day === '') {
        return null;
    }
    $end = trim((string) ($session['end_time'] ?? ''));
    if ($end !== '') {
        $et = eventify_session_datetime($day, $end);
        if ($et instanceof DateTimeImmutable) {
            return $et;
        }
    }
    $tz = eventify_app_timezone();
    $dayOnly = DateTimeImmutable::createFromFormat('Y-m-d', $day, $tz);
    return $dayOnly ? $dayOnly->setTime(23, 59, 59) : null;
}

/** Whether the activity's scheduled window has passed (by date or end time). */
function eventify_session_has_ended(array $session, ?DateTimeInterface $now = null): bool
{
    if (eventify_session_is_completed($session)) {
        return true;
    }
    if (($session['status'] ?? '') === 'cancelled') {
        return false;
    }
    $tz = eventify_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $todayYmd = $now->format('Y-m-d');
    $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    if ($day === '') {
        return false;
    }
    if ($day < $todayYmd) {
        return true;
    }
    if ($day > $todayYmd) {
        return false;
    }
    $end = trim((string) ($session['end_time'] ?? ''));
    if ($end === '') {
        return false;
    }
    $et = eventify_session_datetime($day, $end);
    return $et instanceof DateTimeInterface && $now > $et;
}

/** Whether students may RSVP for this activity (not cancelled, day not passed, not past end time). */
function eventify_session_allows_rsvp(array $session, ?DateTimeInterface $now = null, ?array $parentEvent = null): bool
{
    if (!eventify_session_parent_event_allows_live($session, $parentEvent)) {
        return false;
    }
    if (eventify_session_is_completed($session) || ($session['status'] ?? '') === 'cancelled') {
        return false;
    }
    if (eventify_session_is_open_access($session)) {
        return false;
    }
    $tz = eventify_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $todayYmd = $now->format('Y-m-d');
    $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    if ($day === '' || $day < $todayYmd) {
        return false;
    }
    $endDt = eventify_session_effective_end_datetime($session);
    if ($endDt instanceof DateTimeInterface && $now > $endDt) {
        return false;
    }
    return true;
}

/** Whether QR / manual activity check-in is open (activity day, within time window + early grace). */
function eventify_session_allows_checkin(array $session, ?DateTimeInterface $now = null, ?array $parentEvent = null): bool
{
    return eventify_session_is_checkin_window_open($session, $now, $parentEvent);
}

/**
 * Human-readable reason when activity check-in is closed, or null when open.
 *
 * @param array<string, mixed> $session
 */
function eventify_session_checkin_unavailable_reason(array $session, ?DateTimeInterface $now = null): ?string
{
    if (eventify_session_is_checkin_window_open($session, $now)) {
        return null;
    }
    if (($session['status'] ?? '') === 'cancelled') {
        return 'This activity has been cancelled.';
    }
    if (eventify_session_is_completed($session)) {
        return 'This activity has ended.';
    }
    $tz = eventify_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $todayYmd = $now->format('Y-m-d');
    $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    if ($day === '') {
        return 'Check-in is not available for this activity yet.';
    }
    if ($day !== $todayYmd) {
        $dayLabel = DateTimeImmutable::createFromFormat('Y-m-d', $day, $tz);
        $prettyDay = $dayLabel instanceof DateTimeImmutable ? $dayLabel->format('M j, Y') : $day;
        if ($day < $todayYmd) {
            return 'Check-in closed — this activity was scheduled for ' . $prettyDay . '.';
        }
        return 'Check-in opens on ' . $prettyDay . ' during the scheduled activity time.';
    }
    $bounds = eventify_session_checkin_bounds($session);
    $openTime = $bounds['open'];
    $closeTime = $bounds['close'];
    if ($openTime !== '') {
        $openDt = eventify_session_datetime($day, $openTime);
        if ($openDt instanceof DateTimeImmutable) {
            if (!$bounds['open_uses_explicit']) {
                $early = function_exists('eventify_checkin_early_minutes') ? eventify_checkin_early_minutes() : 0;
                if ($early > 0) {
                    $openDt = $openDt->modify('-' . $early . ' minutes');
                }
            }
            if ($now < $openDt) {
                return 'Check-in opens at ' . $openDt->format('g:i A') . '.';
            }
        }
    }
    if ($closeTime !== '') {
        $closeDt = eventify_session_datetime($day, $closeTime);
        if ($closeDt instanceof DateTimeImmutable && $now > $closeDt) {
            return 'Check-in closed — attendance ended at ' . $closeDt->format('g:i A') . '.';
        }
    }
    return 'Check-in is not open right now (' . $now->format('g:i A') . '). Please scan again during the scheduled activity time.';
}

/**
 * Student-facing context when activity check-in is closed.
 *
 * @param array<string, mixed> $session
 * @return array{reason:string,activity_date_label:string,time_window:string,now_label:string,today_date_label:string,timezone_short:string}
 */
function eventify_session_checkin_student_details(array $session, ?DateTimeInterface $now = null): array
{
    $tz = eventify_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    $activityDateLabel = '';
    if ($day !== '') {
        $dayDt = DateTimeImmutable::createFromFormat('Y-m-d', $day, $tz);
        $activityDateLabel = $dayDt instanceof DateTimeImmutable ? $dayDt->format('l, M j, Y') : $day;
    }

    return [
        'reason' => eventify_session_checkin_unavailable_reason($session, $now)
            ?? 'Check-in is not available right now.',
        'activity_date_label' => $activityDateLabel,
        'time_window' => eventify_format_session_time_range($session['start_time'] ?? null, $session['end_time'] ?? null),
        'now_label' => $now->format('g:i A'),
        'today_date_label' => $now->format('l, M j, Y'),
        'timezone_short' => $now->format('T'),
    ];
}

/** @return array{open: string, close: string, open_uses_explicit: bool, close_uses_explicit: bool} */
function eventify_session_checkin_bounds(array $session): array
{
    $checkinStart = trim((string) ($session['checkin_start_time'] ?? ''));
    $checkinEnd = trim((string) ($session['checkin_end_time'] ?? ''));
    $start = trim((string) ($session['start_time'] ?? ''));
    $end = trim((string) ($session['end_time'] ?? ''));

    return [
        'open' => $checkinStart !== '' ? $checkinStart : $start,
        'close' => $checkinEnd !== '' ? $checkinEnd : $end,
        'open_uses_explicit' => $checkinStart !== '',
        'close_uses_explicit' => $checkinEnd !== '',
    ];
}

/** @param array<string, mixed> $session */
function eventify_session_is_checkin_window_open(array $session, ?DateTimeInterface $now = null, ?array $parentEvent = null): bool
{
    if (!eventify_session_parent_event_allows_live($session, $parentEvent)) {
        return false;
    }
    if (eventify_session_is_completed($session) || ($session['status'] ?? '') === 'cancelled') {
        return false;
    }
    if (!function_exists('eventify_checkin_early_minutes')) {
        require_once __DIR__ . '/event_checkin_security.php';
    }
    $tz = eventify_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $todayYmd = $now->format('Y-m-d');
    $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    if ($day !== $todayYmd) {
        return false;
    }
    $bounds = eventify_session_checkin_bounds($session);
    $openTime = $bounds['open'];
    $closeTime = $bounds['close'];
    if ($openTime === '' && $closeTime === '') {
        return true;
    }
    if ($openTime !== '') {
        $openDt = eventify_session_datetime($day, $openTime);
        if ($openDt instanceof DateTimeImmutable) {
            if (!$bounds['open_uses_explicit']) {
                $early = eventify_checkin_early_minutes();
                if ($early > 0) {
                    $openDt = $openDt->modify('-' . $early . ' minutes');
                }
            }
            if ($now < $openDt) {
                return false;
            }
        }
    }
    if ($closeTime !== '') {
        $closeDt = eventify_session_datetime($day, $closeTime);
        if ($closeDt instanceof DateTimeImmutable && $now > $closeDt) {
            return false;
        }
    }
    return true;
}

/** @param list<array<string, mixed>> $sessions */
function eventify_attach_session_student_flags(array &$sessions): void
{
    foreach ($sessions as &$s) {
        $s['allows_rsvp'] = eventify_session_allows_rsvp($s);
        $s['allows_checkin'] = eventify_session_allows_checkin($s);
        $s['allows_cancel_rsvp'] = eventify_session_allows_cancel_rsvp($s);
    }
    unset($s);
}

/** Whether the parent main event allows day activities to run (RSVP, check-in, "live" UI). */
function eventify_session_parent_event_allows_live(array $session, ?array $parentEvent = null): bool
{
    if ($parentEvent !== null) {
        if (!function_exists('eventify_event_is_live')) {
            require_once __DIR__ . '/event_calendar.php';
        }
        return eventify_event_is_live($parentEvent);
    }
    $st = strtolower(trim((string) ($session['event_status'] ?? '')));
    if ($st === '') {
        return true;
    }
    return $st === 'active';
}

/** @param array<string, mixed> $session */
function eventify_session_is_live_now(array $session, ?string $todayYmd = null, ?DateTimeInterface $now = null, ?array $parentEvent = null): bool
{
    if (!eventify_session_parent_event_allows_live($session, $parentEvent)) {
        return false;
    }
    if (eventify_session_is_completed($session) || ($session['status'] ?? '') === 'cancelled') {
        return false;
    }
    $tz = eventify_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $todayYmd = $todayYmd ?? $now->format('Y-m-d');
    $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    if ($day !== $todayYmd) {
        return false;
    }
    $start = trim((string) ($session['start_time'] ?? ''));
    $end = trim((string) ($session['end_time'] ?? ''));
    if ($start === '' && $end === '') {
        return true;
    }
    if ($start !== '') {
        $st = eventify_session_datetime($day, $start);
        if ($st !== null && $now < $st) {
            return false;
        }
    }
    if ($end !== '') {
        $et = eventify_session_datetime($day, $end);
        if ($et !== null && $now > $et) {
            return false;
        }
    }
    return true;
}

/**
 * @param list<array<string, mixed>> $sessions
 * @return array<string, list<array<string, mixed>>>
 */
function eventify_group_sessions_by_category(array $sessions): array
{
    $groups = [];
    foreach ($sessions as $s) {
        $cat = trim((string) ($s['category'] ?? ''));
        if ($cat === '') {
            $cat = 'Other';
        }
        if (!isset($groups[$cat])) {
            $groups[$cat] = [];
        }
        $groups[$cat][] = $s;
    }
    uksort($groups, static function ($a, $b) {
        if ($a === 'Other') {
            return 1;
        }
        if ($b === 'Other') {
            return -1;
        }
        return strcasecmp($a, $b);
    });
    return $groups;
}

/**
 * @param list<array<string, mixed>> $sessions
 * @return array<string, list<array<string, mixed>>>
 */
function eventify_group_sessions_by_date(array $sessions): array
{
    $groups = [];
    foreach ($sessions as $s) {
        $day = substr(trim((string) ($s['schedule_date'] ?? '')), 0, 10);
        if ($day === '') {
            continue;
        }
        if (!isset($groups[$day])) {
            $groups[$day] = [];
        }
        $groups[$day][] = $s;
    }
    ksort($groups);
    return $groups;
}

/** @return array<string, mixed>|null */
function eventify_load_activity_session(mysqli $conn, int $sessionId, int $eventId, ?int $viewerUserId = null): ?array
{
    if ($sessionId < 1 || $eventId < 1) {
        return null;
    }
    $sessions = eventify_load_event_day_sessions($conn, $eventId, null, $viewerUserId);
    foreach ($sessions as $s) {
        if ((int) ($s['id'] ?? 0) === $sessionId) {
            return $s;
        }
    }
    return null;
}

function eventify_ics_escape(string $text): string
{
    $text = str_replace(["\r\n", "\r", "\n"], '\n', $text);
    return str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $text);
}

/**
 * Build an iCalendar (.ics) document for hub activities.
 *
 * @param list<array<string, mixed>> $sessions
 */
function eventify_build_ics_for_sessions(array $sessions, string $calendarName, string $eventTitle = ''): string
{
    $tz = eventify_app_timezone();
    $tzName = $tz->getName();
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//EVENTIFY//Activities Hub//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:' . eventify_ics_escape($calendarName),
    ];
    if ($eventTitle !== '') {
        $lines[] = 'X-WR-CALDESC:' . eventify_ics_escape($eventTitle);
    }

    $stamp = (new DateTimeImmutable('now', $tz))->format('Ymd\THis');
    foreach ($sessions as $session) {
        $sid = (int) ($session['id'] ?? 0);
        $title = trim((string) ($session['title'] ?? 'Activity'));
        if ($title === '') {
            $title = 'Activity';
        }
        $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
        if ($day === '') {
            continue;
        }
        $startTime = trim((string) ($session['start_time'] ?? ''));
        $endDt = eventify_session_effective_end_datetime($session);
        $startDt = $startTime !== ''
            ? eventify_session_datetime($day, $startTime)
            : DateTimeImmutable::createFromFormat('Y-m-d', $day, $tz);

        if (!$startDt instanceof DateTimeImmutable) {
            continue;
        }
        if (!$endDt instanceof DateTimeImmutable || $endDt <= $startDt) {
            $endDt = $startDt->modify('+1 hour');
        }

        $host = preg_replace('/[^a-z0-9.-]+/i', '-', $_SERVER['HTTP_HOST'] ?? 'eventify.local');
        $uid = 'eventify-activity-' . $sid . '@' . $host;
        $loc = trim((string) ($session['location'] ?? ''));
        $notes = trim((string) ($session['notes'] ?? ''));
        $descParts = [];
        if ($eventTitle !== '') {
            $descParts[] = $eventTitle;
        }
        if ($notes !== '') {
            $descParts[] = $notes;
        }
        $description = implode("\n", $descParts);

        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $uid;
        $lines[] = 'DTSTAMP:' . $stamp;
        $lines[] = 'DTSTART;TZID=' . $tzName . ':' . $startDt->format('Ymd\THis');
        $lines[] = 'DTEND;TZID=' . $tzName . ':' . $endDt->format('Ymd\THis');
        $lines[] = 'SUMMARY:' . eventify_ics_escape($title);
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . eventify_ics_escape($description);
        }
        if ($loc !== '') {
            $lines[] = 'LOCATION:' . eventify_ics_escape($loc);
        }
        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';
    return implode("\r\n", $lines) . "\r\n";
}
