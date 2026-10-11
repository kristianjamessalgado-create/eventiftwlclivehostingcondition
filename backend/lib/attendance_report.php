<?php
/**
 * Staff attendance reports: department / course / year / section filters,
 * present-only or full class roster, and print/CSV helpers.
 * Read-only — never writes to registrations or session attendance.
 */

if (!function_exists('eventify_attendance_report_silence_mysqli')) {
    function eventify_attendance_report_silence_mysqli(): void
    {
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }
    }
}

if (!function_exists('eventify_attendance_report_users_has_column')) {
    function eventify_attendance_report_users_has_column(mysqli $conn, string $field): bool
    {
        static $cache = [];
        if (!preg_match('/^[a-z_]+$/', $field)) {
            return false;
        }
        if (array_key_exists($field, $cache)) {
            return $cache[$field];
        }
        $cache[$field] = false;
        try {
            $esc = $conn->real_escape_string($field);
            // Field = is an exact match. LIKE would treat "_" in student_course as a wildcard.
            $r = $conn->query("SHOW COLUMNS FROM users WHERE Field = '{$esc}'");
            $cache[$field] = (bool) ($r && $r->num_rows > 0);
        } catch (Throwable $e) {
            $cache[$field] = false;
        }
        return $cache[$field];
    }
}

if (!function_exists('eventify_attendance_report_user_select_sql')) {
    function eventify_attendance_report_user_select_sql(mysqli $conn, string $alias = 'u'): string
    {
        $alias = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias) ? $alias : 'u';
        if (function_exists('eventify_users_ensure_student_profile_fields')) {
            try {
                eventify_users_ensure_student_profile_fields($conn);
            } catch (Throwable $e) {
                // keep selecting whatever columns already exist
            }
        }
        $parts = [
            "{$alias}.name",
            "{$alias}.user_id AS student_school_id",
        ];
        foreach (['student_course', 'student_year_level', 'student_academic_year', 'department', 'student_section'] as $col) {
            if (eventify_attendance_report_users_has_column($conn, $col)) {
                $parts[] = "{$alias}.{$col}";
            } else {
                $parts[] = "NULL AS {$col}";
            }
        }
        return implode(', ', $parts);
    }
}

if (!function_exists('eventify_attendance_report_backfill_profile_from_registration')) {
    /**
     * Copy course and year level from the landing-page registration payload
     * when the student account row never received them.
     */
    function eventify_attendance_report_backfill_profile_from_registration(mysqli $conn): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;
        if (!eventify_attendance_report_users_has_column($conn, 'student_course')
            || !eventify_attendance_report_users_has_column($conn, 'student_year_level')) {
            return;
        }
        try {
            $otpTable = $conn->query("SHOW TABLES LIKE 'account_email_otps'");
            if (!$otpTable || $otpTable->num_rows < 1) {
                return;
            }
            $res = $conn->query(
                "SELECT u.id, u.student_course, u.student_year_level, o.payload_json
                 FROM users u
                 INNER JOIN account_email_otps o
                   ON o.email = u.email AND o.purpose = 'register' AND o.payload_json IS NOT NULL AND o.payload_json <> ''
                 WHERE u.role = 'student'
                   AND (
                        u.student_course IS NULL OR TRIM(u.student_course) = ''
                        OR u.student_year_level IS NULL OR TRIM(u.student_year_level) = ''
                   )
                 ORDER BY o.id DESC"
            );
            if (!$res) {
                return;
            }
            $pending = [];
            while ($row = $res->fetch_assoc()) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1 || isset($pending[$id])) {
                    continue;
                }
                $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
                if (!is_array($payload)) {
                    continue;
                }
                $course = trim((string) ($row['student_course'] ?? ''));
                $year = trim((string) ($row['student_year_level'] ?? ''));
                if ($course === '') {
                    $fromPayload = trim((string) ($payload['student_course'] ?? ''));
                    if ($fromPayload !== '' && (!function_exists('eventify_student_course_program_valid') || eventify_student_course_program_valid($fromPayload))) {
                        $course = $fromPayload;
                    }
                }
                if ($year === '') {
                    $fromPayload = trim((string) ($payload['student_year_level'] ?? ''));
                    if ($fromPayload !== '' && (!function_exists('eventify_student_year_level_options') || array_key_exists($fromPayload, eventify_student_year_level_options()))) {
                        $year = $fromPayload;
                    }
                }
                if ($course === trim((string) ($row['student_course'] ?? '')) && $year === trim((string) ($row['student_year_level'] ?? ''))) {
                    continue;
                }
                $pending[$id] = ['course' => $course, 'year' => $year];
            }
            if ($pending === []) {
                return;
            }
            $upd = $conn->prepare('UPDATE users SET student_course = ?, student_year_level = ? WHERE id = ? AND role = \'student\'');
            if (!$upd) {
                return;
            }
            foreach ($pending as $id => $profile) {
                $course = $profile['course'];
                $year = $profile['year'];
                $upd->bind_param('ssi', $course, $year, $id);
                $upd->execute();
            }
            $upd->close();
        } catch (Throwable $e) {
            // report still loads whatever is already on the account
        }
    }
}

if (!function_exists('eventify_attendance_report_stmt_rows')) {
    /**
     * @param list<int|string> $params
     * @return list<array<string, mixed>>|null  null = query failed
     */
    function eventify_attendance_report_stmt_rows(mysqli $conn, string $sql, string $types, array $params, bool $timeInOut): ?array
    {
        try {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return null;
            }
            if ($types !== '' && $params !== []) {
                // PHP 8+ bind_param is by-reference; passing the list to execute()
                // avoids a failed bind that would drop the whole attendance query.
                $executed = $stmt->execute(array_values($params));
            } else {
                $executed = $stmt->execute();
            }
            if (!$executed) {
                $stmt->close();
                return null;
            }
            $res = $stmt->get_result();
            if (!$res) {
                $stmt->close();
                return null;
            }
            $rows = [];
            while ($row = $res->fetch_assoc()) {
                $rows[] = eventify_attendance_report_normalize_row($row, $timeInOut);
            }
            $stmt->close();
            return $rows;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('eventify_attendance_report_parse_filters')) {
    /**
     * @param array<string, mixed> $get
     * @return array{dept:string,course:string,year:string,section:string,view:string,view_param:string,has_filter:bool}
     */
    function eventify_attendance_report_parse_filters(array $get): array
    {
        $dept = trim((string) ($get['dept'] ?? ''));
        $course = trim((string) ($get['course'] ?? ''));
        $year = trim((string) ($get['year'] ?? ''));
        $section = trim((string) ($get['section'] ?? ''));
        $viewRaw = strtolower(trim((string) ($get['view'] ?? 'auto')));

        if ($dept === 'ALL') {
            $dept = '';
        }
        if (function_exists('mb_substr')) {
            $dept = mb_substr($dept, 0, 200);
            $course = mb_substr($course, 0, 120);
            $year = mb_substr($year, 0, 40);
            $section = mb_substr($section, 0, 80);
        } else {
            $dept = substr($dept, 0, 200);
            $course = substr($course, 0, 120);
            $year = substr($year, 0, 40);
            $section = substr($section, 0, 80);
        }

        $hasFilter = $dept !== '' || $course !== '' || $year !== '' || $section !== '';
        $viewParam = ($viewRaw === 'roster' || $viewRaw === 'present') ? $viewRaw : 'auto';
        if ($viewParam === 'present' || $viewParam === 'roster') {
            $view = $viewParam;
        } else {
            $view = $hasFilter ? 'roster' : 'present';
        }
        if ($view === 'roster' && !$hasFilter) {
            $view = 'present';
        }

        return [
            'dept' => $dept,
            'course' => $course,
            'year' => $year,
            'section' => $section,
            'view' => $view,
            'view_param' => $viewParam,
            'has_filter' => $hasFilter,
        ];
    }
}

if (!function_exists('eventify_attendance_report_query_string')) {
    /**
     * @param array{dept?:string,course?:string,year?:string,section?:string,view?:string} $filters
     * @param array<string, scalar> $extra
     */
    function eventify_attendance_report_query_string(array $filters, array $extra = []): string
    {
        $q = $extra;
        foreach (['dept', 'course', 'year', 'section'] as $key) {
            $val = trim((string) ($filters[$key] ?? ''));
            if ($val !== '') {
                $q[$key] = $val;
            }
        }
        if (!array_key_exists('view', $extra)) {
            $view = strtolower(trim((string) ($filters['view_param'] ?? $filters['view'] ?? '')));
            if ($view === 'present' || $view === 'roster') {
                $q['view'] = $view;
            }
        }
        return $q === [] ? '' : http_build_query($q);
    }
}

if (!function_exists('eventify_attendance_report_user_filter')) {
    /**
     * @param array{dept:string,course:string,year:string,section:string} $filters
     * @return array{clauses:list<string>,types:string,params:list<string>}
     */
    function eventify_attendance_report_user_filter(array $filters, string $alias = 'u', ?mysqli $conn = null): array
    {
        $alias = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias) ? $alias : 'u';
        $clauses = [];
        $types = '';
        $params = [];
        $has = static function (string $col) use ($conn): bool {
            return $conn instanceof mysqli ? eventify_attendance_report_users_has_column($conn, $col) : true;
        };

        if (($filters['dept'] ?? '') !== '' && $has('department')) {
            $clauses[] = "TRIM({$alias}.department) = ?";
            $types .= 's';
            $params[] = (string) $filters['dept'];
        }
        if (($filters['course'] ?? '') !== '' && $has('student_course')) {
            $clauses[] = "TRIM({$alias}.student_course) = ?";
            $types .= 's';
            $params[] = (string) $filters['course'];
        }
        if (($filters['year'] ?? '') !== '' && $has('student_year_level')) {
            $clauses[] = "TRIM({$alias}.student_year_level) = ?";
            $types .= 's';
            $params[] = (string) $filters['year'];
        }

        $section = (string) ($filters['section'] ?? '');
        if ($has('student_section')) {
            if ($section === '__unassigned__') {
                $clauses[] = "({$alias}.student_section IS NULL OR TRIM({$alias}.student_section) = '')";
            } elseif ($section !== '') {
                $clauses[] = "LOWER(TRIM({$alias}.student_section)) = ?";
                $types .= 's';
                $key = function_exists('eventify_section_match_key')
                    ? eventify_section_match_key($section)
                    : strtolower(trim($section));
                $params[] = $key;
            }
        }

        return ['clauses' => $clauses, 'types' => $types, 'params' => $params];
    }
}

if (!function_exists('eventify_attendance_report_row_status')) {
    function eventify_attendance_report_row_status(?string $timeIn, ?string $timeOut, bool $timeInOut): string
    {
        $in = trim((string) $timeIn);
        $out = trim((string) $timeOut);
        if ($in === '') {
            return 'absent';
        }
        if ($timeInOut && $out === '') {
            return 'incomplete';
        }
        return 'present';
    }
}

if (!function_exists('eventify_attendance_report_normalize_row')) {
    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    function eventify_attendance_report_normalize_row(array $row, bool $timeInOut): array
    {
        $timeIn = (string) ($row['time_in'] ?? $row['checked_in_at'] ?? '');
        $timeOut = (string) ($row['time_out'] ?? $row['checked_out_at'] ?? '');
        if (empty($row['time_in']) && !empty($row['checked_in_at'])) {
            $timeIn = (string) $row['checked_in_at'];
        }
        if (empty($row['time_out']) && !empty($row['checked_out_at'])) {
            $timeOut = (string) $row['checked_out_at'];
        }
        $deptRaw = trim((string) ($row['department'] ?? ''));
        $deptLabel = $deptRaw === ''
            ? ''
            : (function_exists('eventify_format_department_label')
                ? eventify_format_department_label($deptRaw)
                : $deptRaw);

        return [
            'name' => trim((string) ($row['name'] ?? '')),
            'student_school_id' => trim((string) ($row['student_school_id'] ?? '')),
            'student_course' => trim((string) ($row['student_course'] ?? '')),
            'student_year_level' => trim((string) ($row['student_year_level'] ?? '')),
            'student_academic_year' => trim((string) ($row['student_academic_year'] ?? '')),
            'department' => $deptRaw,
            'department_label' => $deptLabel,
            'student_section' => trim((string) ($row['student_section'] ?? '')),
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'proof_photo' => (string) ($row['proof_photo'] ?? ''),
            'status' => eventify_attendance_report_row_status($timeIn, $timeOut, $timeInOut),
        ];
    }
}

if (!function_exists('eventify_attendance_report_summarize')) {
    /**
     * @param list<array<string, mixed>> $rows
     * @return array{total:int,present:int,absent:int,incomplete:int,unassigned:int}
     */
    function eventify_attendance_report_summarize(array $rows): array
    {
        $out = ['total' => count($rows), 'present' => 0, 'absent' => 0, 'incomplete' => 0, 'unassigned' => 0];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'present') {
                $out['present']++;
            } elseif ($status === 'incomplete') {
                $out['incomplete']++;
            } else {
                $out['absent']++;
            }
            if (trim((string) ($row['student_section'] ?? '')) === '') {
                $out['unassigned']++;
            }
        }
        return $out;
    }
}

if (!function_exists('eventify_attendance_report_format_when')) {
    function eventify_attendance_report_format_when(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }
        return date('M j, Y g:i A', $ts);
    }
}

if (!function_exists('eventify_attendance_report_status_label')) {
    function eventify_attendance_report_status_label(string $status): string
    {
        if ($status === 'present') {
            return 'Present';
        }
        if ($status === 'incomplete') {
            return 'Incomplete';
        }
        return 'Absent';
    }
}

if (!function_exists('eventify_attendance_report_filter_options')) {
    /**
     * @param array{dept:string,course:string,year:string,section:string} $filters
     * @return array{departments:array<string,string>,courses:array<string,string>,years:array<string,string>,sections:array<string,string>}
     */
    function eventify_attendance_report_filter_options(mysqli $conn, array $filters): array
    {
        $departments = [];
        if (is_file(__DIR__ . '/../../config/organizer_departments.php')) {
            require_once __DIR__ . '/../../config/organizer_departments.php';
        }
        if (function_exists('eventify_organizer_department_choices')) {
            foreach (eventify_organizer_department_choices() as $val => $label) {
                if ((string) $val === 'ALL') {
                    continue;
                }
                $departments[(string) $val] = (string) $label;
            }
        }
        if (function_exists('eventify_student_course_program_department_map')) {
            foreach (eventify_student_course_program_department_map() as $course => $dept) {
                $dept = trim((string) $dept);
                if ($dept !== '' && !isset($departments[$dept])) {
                    $departments[$dept] = $dept;
                }
            }
        }

        $courses = [];
        $map = function_exists('eventify_student_course_program_department_map')
            ? eventify_student_course_program_department_map()
            : [];
        $deptFilter = (string) ($filters['dept'] ?? '');
        foreach ($map as $course => $dept) {
            if ($deptFilter !== '' && trim((string) $dept) !== $deptFilter) {
                continue;
            }
            $courses[(string) $course] = (string) $course;
        }

        $years = [];
        if (function_exists('eventify_student_year_level_options')) {
            foreach (eventify_student_year_level_options() as $val => $label) {
                if ((string) $val === '') {
                    continue;
                }
                $years[(string) $val] = (string) $label;
            }
        }

        $sections = [];
        try {
            if (function_exists('eventify_list_class_sections')) {
                foreach (eventify_list_class_sections($conn) as $sec) {
                    $label = trim((string) ($sec['label'] ?? ''));
                    if ($label !== '') {
                        $sections[$label] = $label;
                    }
                }
            }
        } catch (Throwable $e) {
            $sections = [];
        }

        try {
            $select = ['department'];
            foreach (['student_course', 'student_year_level', 'student_section'] as $col) {
                if (eventify_attendance_report_users_has_column($conn, $col)) {
                    $select[] = $col;
                }
            }
            $res = $conn->query(
                'SELECT DISTINCT ' . implode(', ', $select) . " FROM users WHERE role = 'student'"
            );
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $d = trim((string) ($row['department'] ?? ''));
                    if ($d !== '' && $d !== 'ALL' && !isset($departments[$d])) {
                        $departments[$d] = function_exists('eventify_format_department_label')
                            ? eventify_format_department_label($d)
                            : $d;
                    }
                    $c = trim((string) ($row['student_course'] ?? ''));
                    if ($c !== '') {
                        $courseDept = function_exists('eventify_student_course_program_department')
                            ? eventify_student_course_program_department($c)
                            : '';
                        if ($deptFilter === '' || $courseDept === $deptFilter || $d === $deptFilter) {
                            $courses[$c] = $c;
                        }
                    }
                    $y = trim((string) ($row['student_year_level'] ?? ''));
                    if ($y !== '' && !isset($years[$y])) {
                        $years[$y] = $y;
                    }
                    $s = trim((string) ($row['student_section'] ?? ''));
                    if ($s !== '') {
                        $sections[$s] = $s;
                    }
                }
            }
        } catch (Throwable $e) {
            // keep canonical lists
        }

        if (($filters['course'] ?? '') !== '' && !isset($courses[$filters['course']])) {
            $courses[$filters['course']] = $filters['course'];
        }
        if (($filters['section'] ?? '') !== '' && $filters['section'] !== '__unassigned__' && !isset($sections[$filters['section']])) {
            $sections[$filters['section']] = $filters['section'];
        }

        ksort($departments, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($courses, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($years, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($sections, SORT_NATURAL | SORT_FLAG_CASE);

        $sections = ['__unassigned__' => 'Unassigned / no section'] + $sections;

        return [
            'departments' => $departments,
            'courses' => $courses,
            'years' => $years,
            'sections' => $sections,
            'course_departments' => $map,
        ];
    }
}

if (!function_exists('eventify_attendance_report_count_unassigned_present')) {
    function eventify_attendance_report_count_unassigned_present(array $presentRows): int
    {
        $n = 0;
        foreach ($presentRows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (($status === 'present' || $status === 'incomplete') && trim((string) ($row['student_section'] ?? '')) === '') {
                $n++;
            }
        }
        return $n;
    }
}

if (!function_exists('eventify_attendance_report_load_event_rows')) {
    /**
     * @param array{dept:string,course:string,year:string,section:string,view:string,has_filter:bool} $filters
     * @return list<array<string, mixed>>
     */
    function eventify_attendance_report_load_event_rows(
        mysqli $conn,
        int $eventId,
        array $filters,
        bool $timeInOut
    ): array {
        if ($eventId < 1) {
            return [];
        }
        eventify_attendance_report_silence_mysqli();
        try {
            if (function_exists('eventify_users_ensure_student_profile_fields')) {
                eventify_users_ensure_student_profile_fields($conn);
            }
        } catch (Throwable $e) {
            // continue with whatever columns exist
        }
        eventify_attendance_report_backfill_profile_from_registration($conn);
        try {
            if (function_exists('eventify_attendance_proof_ensure_columns')) {
                eventify_attendance_proof_ensure_columns($conn);
            }
        } catch (Throwable $e) {
            // photos optional
        }

        $userCols = eventify_attendance_report_user_select_sql($conn);
        $hasSection = eventify_attendance_report_users_has_column($conn, 'student_section');
        $hasUserStatus = eventify_attendance_report_users_has_column($conn, 'status');
        $filter = eventify_attendance_report_user_filter($filters, 'u', $conn);
        $filterSql = $filter['clauses'] !== [] ? ' AND ' . implode(' AND ', $filter['clauses']) : '';

        if (($filters['view'] ?? 'present') === 'roster') {
            $statusOk = $hasUserStatus ? "u.status = 'active'" : '1=1';
            $order = $hasSection
                ? "ORDER BY (u.student_section IS NULL OR TRIM(u.student_section) = '') ASC, u.student_section ASC, u.name ASC"
                : 'ORDER BY u.name ASC';
            $sqlBody = "FROM users u
                    LEFT JOIN registrations r
                      ON r.user_id = u.id AND r.event_id = ?
                    WHERE u.role = 'student'
                      AND (
                            {$statusOk}
                            OR r.status = 'present'
                            OR r.time_in IS NOT NULL
                          )
                      {$filterSql}
                    {$order}";
        } else {
            $sqlBody = "FROM registrations r
                    JOIN users u ON u.id = r.user_id
                    WHERE r.event_id = ?
                      AND (r.status = 'present' OR r.time_in IS NOT NULL)
                      {$filterSql}
                    ORDER BY r.time_in ASC, u.name ASC";
        }
        $types = 'i' . $filter['types'];
        $params = array_merge([$eventId], $filter['params']);
        $rows = eventify_attendance_report_stmt_rows(
            $conn,
            "SELECT {$userCols}, r.time_in, r.time_out, r.proof_photo {$sqlBody}",
            $types,
            $params,
            $timeInOut
        );
        if ($rows === null) {
            $rows = eventify_attendance_report_stmt_rows(
                $conn,
                "SELECT {$userCols}, r.time_in, r.time_out, NULL AS proof_photo {$sqlBody}",
                $types,
                $params,
                $timeInOut
            );
        }
        if (is_array($rows) && ($rows !== [] || ($filters['view'] ?? 'present') === 'roster')) {
            return $rows;
        }

        $fallback = eventify_attendance_report_stmt_rows(
            $conn,
            "SELECT {$userCols}, r.time_in, r.time_out, r.proof_photo
             FROM registrations r
             JOIN users u ON u.id = r.user_id
             WHERE r.event_id = ? AND (r.status = 'present' OR r.time_in IS NOT NULL)
             ORDER BY r.time_in ASC",
            'i',
            [$eventId],
            $timeInOut
        );
        if ($fallback === null) {
            $fallback = eventify_attendance_report_stmt_rows(
                $conn,
                "SELECT {$userCols}, r.time_in, r.time_out, NULL AS proof_photo
                 FROM registrations r
                 JOIN users u ON u.id = r.user_id
                 WHERE r.event_id = ? AND (r.status = 'present' OR r.time_in IS NOT NULL)
                 ORDER BY r.time_in ASC",
                'i',
                [$eventId],
                $timeInOut
            );
        }
        return is_array($fallback) ? $fallback : [];
    }
}

if (!function_exists('eventify_attendance_report_load_activity_rows')) {
    /**
     * @param array{dept:string,course:string,year:string,section:string,view:string,has_filter:bool} $filters
     * @return list<array<string, mixed>>
     */
    function eventify_attendance_report_load_activity_rows(
        mysqli $conn,
        int $sessionId,
        array $filters,
        bool $timeInOut,
        bool $hasOutCol
    ): array {
        if ($sessionId < 1) {
            return [];
        }
        eventify_attendance_report_silence_mysqli();
        try {
            if (function_exists('eventify_users_ensure_student_profile_fields')) {
                eventify_users_ensure_student_profile_fields($conn);
            }
        } catch (Throwable $e) {
            // continue
        }
        eventify_attendance_report_backfill_profile_from_registration($conn);
        try {
            if (function_exists('eventify_attendance_proof_ensure_columns')) {
                eventify_attendance_proof_ensure_columns($conn);
            }
        } catch (Throwable $e) {
            // photos optional
        }
        $userCols = eventify_attendance_report_user_select_sql($conn);
        $hasSection = eventify_attendance_report_users_has_column($conn, 'student_section');
        $hasUserStatus = eventify_attendance_report_users_has_column($conn, 'status');
        $outSql = $hasOutCol ? ', a.checked_out_at' : ', NULL AS checked_out_at';
        $filter = eventify_attendance_report_user_filter($filters, 'u', $conn);
        $filterSql = $filter['clauses'] !== [] ? ' AND ' . implode(' AND ', $filter['clauses']) : '';

        if (($filters['view'] ?? 'present') === 'roster') {
            $statusOk = $hasUserStatus ? "u.status = 'active'" : '1=1';
            $order = $hasSection
                ? "ORDER BY (u.student_section IS NULL OR TRIM(u.student_section) = '') ASC, u.student_section ASC, u.name ASC"
                : 'ORDER BY u.name ASC';
            $sqlBody = "FROM users u
                    LEFT JOIN event_day_session_attendance a
                      ON a.user_id = u.id AND a.session_id = ?
                    WHERE u.role = 'student'
                      AND (
                            {$statusOk}
                            OR a.checked_in_at IS NOT NULL
                          )
                      {$filterSql}
                    {$order}";
        } else {
            $sqlBody = "FROM event_day_session_attendance a
                    JOIN users u ON u.id = a.user_id
                    WHERE a.session_id = ?
                      {$filterSql}
                    ORDER BY a.checked_in_at ASC, u.name ASC";
        }
        $types = 'i' . $filter['types'];
        $params = array_merge([$sessionId], $filter['params']);
        $rows = eventify_attendance_report_stmt_rows(
            $conn,
            "SELECT {$userCols}, a.checked_in_at{$outSql}, a.proof_photo {$sqlBody}",
            $types,
            $params,
            $timeInOut
        );
        if ($rows === null) {
            $rows = eventify_attendance_report_stmt_rows(
                $conn,
                "SELECT {$userCols}, a.checked_in_at{$outSql}, NULL AS proof_photo {$sqlBody}",
                $types,
                $params,
                $timeInOut
            );
        }
        if (is_array($rows) && ($rows !== [] || ($filters['view'] ?? 'present') === 'roster')) {
            return $rows;
        }

        $fallback = eventify_attendance_report_stmt_rows(
            $conn,
            "SELECT {$userCols}, a.checked_in_at{$outSql}, a.proof_photo
             FROM event_day_session_attendance a
             JOIN users u ON u.id = a.user_id
             WHERE a.session_id = ?
             ORDER BY a.checked_in_at ASC",
            'i',
            [$sessionId],
            $timeInOut
        );
        if ($fallback === null) {
            $fallback = eventify_attendance_report_stmt_rows(
                $conn,
                "SELECT {$userCols}, a.checked_in_at{$outSql}, NULL AS proof_photo
                 FROM event_day_session_attendance a
                 JOIN users u ON u.id = a.user_id
                 WHERE a.session_id = ?
                 ORDER BY a.checked_in_at ASC",
                'i',
                [$sessionId],
                $timeInOut
            );
        }
        return is_array($fallback) ? $fallback : [];
    }
}

if (!function_exists('eventify_attendance_report_output_csv')) {
    /**
     * @param list<array<string, mixed>> $rows
     */
    function eventify_attendance_report_output_csv(string $filename, array $rows, bool $showTimeOut): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        $headers = ['#', 'Student Name', 'Student ID', 'Course', 'Year level', 'School year (AY)', 'Department / college', 'Section', 'Time in'];
        if ($showTimeOut) {
            $headers[] = 'Time out';
        }
        $headers[] = 'Status';
        fputcsv($out, $headers);
        $i = 1;
        foreach ($rows as $row) {
            $line = [
                $i++,
                $row['name'] ?? '',
                $row['student_school_id'] ?? '',
                $row['student_course'] ?? '',
                $row['student_year_level'] ?? '',
                $row['student_academic_year'] ?? '',
                $row['department_label'] ?? '',
                $row['student_section'] ?? '',
                $row['time_in'] ?? '',
            ];
            if ($showTimeOut) {
                $line[] = $row['time_out'] ?? '';
            }
            $line[] = eventify_attendance_report_status_label((string) ($row['status'] ?? 'absent'));
            fputcsv($out, $line);
        }
        fclose($out);
    }
}

if (!function_exists('eventify_attendance_report_filter_labels')) {
    /**
     * @param array{dept:string,course:string,year:string,section:string} $filters
     * @return list<string>
     */
    function eventify_attendance_report_filter_labels(array $filters): array
    {
        $labels = [];
        if (($filters['dept'] ?? '') !== '') {
            $labels[] = function_exists('eventify_format_department_label')
                ? eventify_format_department_label((string) $filters['dept'])
                : (string) $filters['dept'];
        }
        if (($filters['course'] ?? '') !== '') {
            $labels[] = (string) $filters['course'];
        }
        if (($filters['year'] ?? '') !== '') {
            $labels[] = (string) $filters['year'];
        }
        if (($filters['section'] ?? '') === '__unassigned__') {
            $labels[] = 'Unassigned / no section';
        } elseif (($filters['section'] ?? '') !== '') {
            $labels[] = (string) $filters['section'];
        }
        return $labels;
    }
}

if (!function_exists('eventify_attendance_report_safe_filename')) {
    function eventify_attendance_report_safe_filename(string $title, string $suffix = ''): string
    {
        $base = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $title) ?: 'attendance';
        $base = trim($base, '_');
        $extra = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $suffix) ?: '';
        $extra = trim((string) $extra, '_');
        $name = $extra !== '' ? $base . '_' . $extra : $base;
        return substr($name, 0, 80);
    }
}
