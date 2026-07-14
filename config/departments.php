<?php
/**
 * Centralized department options/validation for event audience targeting.
 */

if (!function_exists('eventify_allowed_departments')) {
    function eventify_allowed_departments(): array
    {
        return [
            'ALL',
            'BSIT',
            'BSHM',
            'CONAHS',
            'Senior High',
            'High school department',
            'College of Communication, Information and Technology',
            'College of Accountancy and Business',
            'School of Law and Political Science',
            'College of Education',
            'College of Nursing and Allied health sciences',
            'College of Hospitality Management',
        ];
    }
}

if (!function_exists('eventify_normalize_department')) {
    function eventify_normalize_department(?string $department): string
    {
        $d = trim((string) $department);
        $allowed = eventify_allowed_departments();
        return in_array($d, $allowed, true) ? $d : 'ALL';
    }
}

/**
 * If events.department is still ENUM, multi-audience JSON cannot be stored — widen to VARCHAR (idempotent for VARCHAR).
 */
if (!function_exists('eventify_events_department_ensure_varchar')) {
    function eventify_events_department_ensure_varchar(mysqli $conn): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;
        try {
            $r = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'department'");
            if (!$r || !($row = $r->fetch_assoc())) {
                return;
            }
            $type = strtolower((string) ($row['Type'] ?? ''));
            if (strpos($type, 'enum') !== false) {
                $conn->query("ALTER TABLE events MODIFY COLUMN department VARCHAR(800) NOT NULL DEFAULT 'ALL'");
            }
        } catch (Throwable $e) {
            // leave column as-is; inserts may fail until DBA runs migration
        }
    }
}

/**
 * From POST: single `department` or multiple `department[]`. Returns ALL, one allowed string, or JSON array for 2+.
 *
 * @return array{ok:bool, department:string, error:?string}
 */
if (!function_exists('eventify_parse_event_departments_from_request')) {
    function eventify_parse_event_departments_from_request(array $post): array
    {
        if (isset($post['department']) && is_array($post['department'])) {
            $parts = [];
            foreach ($post['department'] as $x) {
                $t = trim((string) $x);
                if ($t !== '') {
                    $parts[] = $t;
                }
            }
            $parts = array_values(array_unique($parts));
        } else {
            $raw = trim((string) ($post['department'] ?? 'ALL'));
            $parts = $raw === '' ? ['ALL'] : [$raw];
        }
        if ($parts === [] || in_array('ALL', $parts, true)) {
            return ['ok' => true, 'department' => 'ALL', 'error' => null];
        }
        $allowed = eventify_allowed_departments();
        foreach ($parts as $p) {
            if (!in_array($p, $allowed, true)) {
                return ['ok' => false, 'department' => 'ALL', 'error' => 'Invalid department selection.'];
            }
        }
        if (count($parts) === 1) {
            return ['ok' => true, 'department' => $parts[0], 'error' => null];
        }
        sort($parts);
        $json = json_encode(array_values($parts), JSON_UNESCAPED_UNICODE);
        if ($json === false || strlen($json) > 780) {
            return ['ok' => false, 'department' => 'ALL', 'error' => 'Too many departments selected.'];
        }
        return ['ok' => true, 'department' => $json, 'error' => null];
    }
}

/** Whether a student (by profile department) may see an event audience field. */
if (!function_exists('eventify_student_sees_event_department')) {
    function eventify_student_sees_event_department(string $eventDepartment, ?string $studentDepartment): bool
    {
        $ev = trim($eventDepartment);
        if ($ev === '' || $ev === 'ALL') {
            return true;
        }
        $stu = trim((string) $studentDepartment);
        if ($stu === '') {
            return true;
        }
        if ($ev !== '' && $ev[0] === '[') {
            $arr = json_decode($ev, true);
            if (is_array($arr)) {
                return in_array($stu, $arr, true);
            }
        }
        return $ev === $stu;
    }
}

/** Human-readable label for admin/organizer lists (never JSON raw in UI). */
if (!function_exists('eventify_format_department_label')) {
    function eventify_format_department_label(string $stored): string
    {
        $s = trim($stored);
        if ($s === '' || $s === 'ALL') {
            return 'All Departments';
        }
        if ($s !== '' && $s[0] === '[') {
            $arr = json_decode($s, true);
            if (is_array($arr) && $arr !== []) {
                return implode(' · ', $arr);
            }
        }
        return $s;
    }
}

/**
 * Checkbox UI state for organizer create/edit forms.
 * When $postDepartmentArray is null, derive from stored DB value (ALL, one dept, or JSON array).
 *
 * @return array{all:bool, specific:string[]}
 */
if (!function_exists('eventify_organizer_department_form_checkbox_state')) {
    function eventify_organizer_department_form_checkbox_state(?array $postDepartmentArray, string $storedDepartment): array
    {
        if ($postDepartmentArray !== null) {
            $parts = [];
            foreach ($postDepartmentArray as $x) {
                $t = trim((string) $x);
                if ($t !== '') {
                    $parts[] = $t;
                }
            }
            $parts = array_values(array_unique($parts));
            if ($parts === [] || in_array('ALL', $parts, true)) {
                return ['all' => true, 'specific' => []];
            }

            return ['all' => false, 'specific' => $parts];
        }
        $s = trim($storedDepartment);
        if ($s === '' || $s === 'ALL') {
            return ['all' => true, 'specific' => []];
        }
        if ($s !== '' && $s[0] === '[') {
            $arr = json_decode($s, true);
            if (is_array($arr) && $arr !== []) {
                return ['all' => false, 'specific' => array_values(array_map('strval', $arr))];
            }
        }

        return ['all' => false, 'specific' => [$s]];
    }
}

/**
 * SQL fragment: event visible to student department. Bind the same department string twice.
 */
if (!function_exists('eventify_department_match_sql')) {
    function eventify_department_match_sql(string $col = 'department'): string
    {
        $c = preg_replace('/[^a-zA-Z0-9_.]/', '', $col) ?: 'department';
        return "( ({$c}) = 'ALL' OR ({$c}) = ? OR (LEFT(TRIM({$c}), 1) = '[' AND JSON_VALID({$c}) AND JSON_SEARCH({$c}, 'one', ?) IS NOT NULL) )";
    }
}
