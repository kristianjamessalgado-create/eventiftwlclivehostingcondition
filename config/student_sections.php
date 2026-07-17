<?php
/**
 * Admin-managed class sections (free-text labels) for optional event audience narrowing.
 */

if (!function_exists('eventify_normalize_section_label')) {
    /** Trim, collapse internal whitespace, keep original casing for display. */
    function eventify_normalize_section_label(string $label): string
    {
        $label = trim(preg_replace('/\s+/u', ' ', $label) ?? '');
        if (function_exists('mb_substr')) {
            return mb_substr($label, 0, 80);
        }
        return substr($label, 0, 80);
    }
}

if (!function_exists('eventify_section_match_key')) {
    /** Case-insensitive key for comparing section labels. */
    function eventify_section_match_key(string $label): string
    {
        $n = eventify_normalize_section_label($label);
        if ($n === '') {
            return '';
        }
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($n, 'UTF-8');
        }
        return strtolower($n);
    }
}

if (!function_exists('eventify_class_sections_ensure_table')) {
    function eventify_class_sections_ensure_table(mysqli $conn): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;
        try {
            $conn->query(
                "CREATE TABLE IF NOT EXISTS class_sections (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    label VARCHAR(80) NOT NULL,
                    label_key VARCHAR(80) NOT NULL,
                    created_by INT NULL DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_class_sections_label_key (label_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Throwable $e) {
            // leave as-is until DBA runs migration
        }
    }
}

if (!function_exists('eventify_events_ensure_target_sections')) {
    function eventify_events_ensure_target_sections(mysqli $conn): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;
        try {
            $r = $conn->query("SHOW COLUMNS FROM events LIKE 'target_sections'");
            if ($r && $r->num_rows === 0) {
                $conn->query("ALTER TABLE events ADD COLUMN target_sections VARCHAR(800) NULL DEFAULT NULL");
            }
        } catch (Throwable $e) {
            // leave as-is
        }
    }
}

if (!function_exists('eventify_users_ensure_student_section')) {
    function eventify_users_ensure_student_section(mysqli $conn): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;
        if (function_exists('eventify_users_ensure_student_profile_fields')) {
            eventify_users_ensure_student_profile_fields($conn);
        }
        try {
            $r = $conn->query("SHOW COLUMNS FROM users LIKE 'student_section'");
            if ($r && $r->num_rows === 0) {
                $conn->query("ALTER TABLE users ADD COLUMN student_section VARCHAR(80) NULL DEFAULT NULL");
            }
        } catch (Throwable $e) {
            // leave as-is
        }
    }
}

if (!function_exists('eventify_sections_schema_ensure')) {
    function eventify_sections_schema_ensure(mysqli $conn): void
    {
        eventify_class_sections_ensure_table($conn);
        eventify_events_ensure_target_sections($conn);
        eventify_users_ensure_student_section($conn);
    }
}

if (!function_exists('eventify_events_has_target_sections')) {
    function eventify_events_has_target_sections(mysqli $conn): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        eventify_events_ensure_target_sections($conn);
        try {
            $r = $conn->query("SHOW COLUMNS FROM events LIKE 'target_sections'");
            $cache = ($r && $r->num_rows > 0);
        } catch (Throwable $e) {
            $cache = false;
        }
        return $cache;
    }
}

/**
 * @return list<array{id:int,label:string}>
 */
if (!function_exists('eventify_list_class_sections')) {
    function eventify_list_class_sections(mysqli $conn): array
    {
        eventify_class_sections_ensure_table($conn);
        $out = [];
        try {
            $res = $conn->query('SELECT id, label FROM class_sections ORDER BY label ASC');
            if (!$res) {
                return [];
            }
            while ($row = $res->fetch_assoc()) {
                $out[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'label' => (string) ($row['label'] ?? ''),
                ];
            }
        } catch (Throwable $e) {
            return [];
        }
        return $out;
    }
}

/**
 * Add or return existing section by free-text label.
 *
 * @return array{ok:bool,id:?int,label:?string,error:?string}
 */
if (!function_exists('eventify_add_class_section')) {
    function eventify_add_class_section(mysqli $conn, string $label, ?int $createdBy = null): array
    {
        eventify_class_sections_ensure_table($conn);
        $label = eventify_normalize_section_label($label);
        $key = eventify_section_match_key($label);
        if ($key === '') {
            return ['ok' => false, 'id' => null, 'label' => null, 'error' => 'Section label is required.'];
        }

        $sel = $conn->prepare('SELECT id, label FROM class_sections WHERE label_key = ? LIMIT 1');
        if ($sel) {
            $sel->bind_param('s', $key);
            $sel->execute();
            $existing = $sel->get_result()->fetch_assoc();
            $sel->close();
            if ($existing) {
                return [
                    'ok' => true,
                    'id' => (int) $existing['id'],
                    'label' => (string) $existing['label'],
                    'error' => null,
                ];
            }
        }

        $ins = $conn->prepare('INSERT INTO class_sections (label, label_key, created_by) VALUES (?, ?, ?)');
        if (!$ins) {
            return ['ok' => false, 'id' => null, 'label' => null, 'error' => 'Could not save section.'];
        }
        if ($createdBy === null || $createdBy < 1) {
            $createdBy = 0;
        }
        $ins->bind_param('ssi', $label, $key, $createdBy);
        if (!$ins->execute()) {
            $ins->close();
            // Race: another insert won unique key
            $sel2 = $conn->prepare('SELECT id, label FROM class_sections WHERE label_key = ? LIMIT 1');
            if ($sel2) {
                $sel2->bind_param('s', $key);
                $sel2->execute();
                $existing = $sel2->get_result()->fetch_assoc();
                $sel2->close();
                if ($existing) {
                    return [
                        'ok' => true,
                        'id' => (int) $existing['id'],
                        'label' => (string) $existing['label'],
                        'error' => null,
                    ];
                }
            }
            return ['ok' => false, 'id' => null, 'label' => null, 'error' => 'Could not save section.'];
        }
        $id = (int) $conn->insert_id;
        $ins->close();
        return ['ok' => true, 'id' => $id, 'label' => $label, 'error' => null];
    }
}

/**
 * @return array{ok:bool,error:?string}
 */
if (!function_exists('eventify_delete_class_section')) {
    function eventify_delete_class_section(mysqli $conn, int $id): array
    {
        if ($id < 1) {
            return ['ok' => false, 'error' => 'Invalid section.'];
        }
        eventify_class_sections_ensure_table($conn);
        $stmt = $conn->prepare('DELETE FROM class_sections WHERE id = ?');
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not delete section.'];
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => 'Could not delete section.'];
    }
}

/**
 * Parse labels from POST section[] and optional new_section free text.
 * Upserts new labels into class_sections.
 *
 * @return array{ok:bool, target_sections:?string, labels:list<string>, error:?string}
 *   target_sections NULL means no section filter (everyone in department audience).
 */
if (!function_exists('eventify_parse_event_sections_from_request')) {
    function eventify_parse_event_sections_from_request(mysqli $conn, array $post, ?int $createdBy = null): array
    {
        eventify_sections_schema_ensure($conn);
        $parts = [];
        if (isset($post['section']) && is_array($post['section'])) {
            foreach ($post['section'] as $x) {
                $t = eventify_normalize_section_label((string) $x);
                if ($t !== '') {
                    $parts[] = $t;
                }
            }
        }
        $newRaw = eventify_normalize_section_label((string) ($post['new_section'] ?? ''));
        if ($newRaw !== '') {
            $parts[] = $newRaw;
        }

        // Dedupe by match key, keep first casing
        $byKey = [];
        foreach ($parts as $p) {
            $k = eventify_section_match_key($p);
            if ($k === '' || isset($byKey[$k])) {
                continue;
            }
            $byKey[$k] = $p;
        }
        $labels = array_values($byKey);
        if ($labels === []) {
            return ['ok' => true, 'target_sections' => null, 'labels' => [], 'error' => null];
        }

        $stored = [];
        foreach ($labels as $lab) {
            $add = eventify_add_class_section($conn, $lab, $createdBy);
            if (!$add['ok'] || empty($add['label'])) {
                return [
                    'ok' => false,
                    'target_sections' => null,
                    'labels' => [],
                    'error' => $add['error'] ?? 'Invalid section.',
                ];
            }
            $stored[] = (string) $add['label'];
        }
        sort($stored, SORT_NATURAL | SORT_FLAG_CASE);
        $json = json_encode(array_values($stored), JSON_UNESCAPED_UNICODE);
        if ($json === false || strlen($json) > 780) {
            return [
                'ok' => false,
                'target_sections' => null,
                'labels' => [],
                'error' => 'Too many sections selected.',
            ];
        }
        return ['ok' => true, 'target_sections' => $json, 'labels' => $stored, 'error' => null];
    }
}

/** @return list<string> */
if (!function_exists('eventify_parse_target_sections_list')) {
    function eventify_parse_target_sections_list(?string $stored): array
    {
        $s = trim((string) $stored);
        if ($s === '') {
            return [];
        }
        if ($s[0] === '[') {
            $arr = json_decode($s, true);
            if (!is_array($arr)) {
                return [];
            }
            $out = [];
            foreach ($arr as $x) {
                $t = eventify_normalize_section_label((string) $x);
                if ($t !== '') {
                    $out[] = $t;
                }
            }
            return $out;
        }
        return [eventify_normalize_section_label($s)];
    }
}

if (!function_exists('eventify_format_target_sections_label')) {
    function eventify_format_target_sections_label(?string $stored): string
    {
        $list = eventify_parse_target_sections_list($stored);
        if ($list === []) {
            return '';
        }
        return implode(' · ', $list);
    }
}

/**
 * When event has no section list → all sections OK.
 * When event is section-locked → student must have a matching section (blank student = deny).
 */
if (!function_exists('eventify_student_sees_event_sections')) {
    function eventify_student_sees_event_sections(?string $eventTargetSections, ?string $studentSection): bool
    {
        $targets = eventify_parse_target_sections_list($eventTargetSections);
        if ($targets === []) {
            return true;
        }
        $stuKey = eventify_section_match_key((string) $studentSection);
        if ($stuKey === '') {
            return false;
        }
        foreach ($targets as $t) {
            if (eventify_section_match_key($t) === $stuKey) {
                return true;
            }
        }
        return false;
    }
}

/**
 * Department + optional section audience gate for students.
 *
 * @param array $event expects department, optional target_sections
 * @param array $student expects department, optional student_section
 */
if (!function_exists('eventify_student_may_access_event')) {
    function eventify_student_may_access_event(array $event, array $student): bool
    {
        if (!function_exists('eventify_student_sees_event_department')) {
            require_once __DIR__ . '/departments.php';
        }
        $deptOk = eventify_student_sees_event_department(
            (string) ($event['department'] ?? 'ALL'),
            $student['department'] ?? null
        );
        if (!$deptOk) {
            return false;
        }
        return eventify_student_sees_event_sections(
            $event['target_sections'] ?? null,
            $student['student_section'] ?? null
        );
    }
}

/** Persist target_sections after create/update (avoids rewriting every INSERT). */
if (!function_exists('eventify_event_save_target_sections')) {
    function eventify_event_save_target_sections(mysqli $conn, int $eventId, ?string $targetSectionsJson): bool
    {
        if ($eventId < 1 || !eventify_events_has_target_sections($conn)) {
            return false;
        }
        $stmt = $conn->prepare('UPDATE events SET target_sections = ? WHERE id = ?');
        if (!$stmt) {
            return false;
        }
        $val = $targetSectionsJson;
        $stmt->bind_param('si', $val, $eventId);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool) $ok;
    }
}

/**
 * Load student audience fields for access checks.
 *
 * @return array{department:?string,student_section:?string}
 */
if (!function_exists('eventify_load_student_audience_profile')) {
    function eventify_load_student_audience_profile(mysqli $conn, int $userId): array
    {
        $out = ['department' => null, 'student_section' => null];
        if ($userId < 1) {
            return $out;
        }
        eventify_users_ensure_student_section($conn);
        $hasSec = false;
        try {
            $r = $conn->query("SHOW COLUMNS FROM users LIKE 'student_section'");
            $hasSec = ($r && $r->num_rows > 0);
        } catch (Throwable $e) {
            $hasSec = false;
        }
        $cols = $hasSec ? 'department, student_section' : 'department';
        $stmt = $conn->prepare("SELECT {$cols} FROM users WHERE id = ? AND role = 'student' LIMIT 1");
        if (!$stmt) {
            return $out;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $out['department'] = $row['department'] ?? null;
            $out['student_section'] = $row['student_section'] ?? null;
        }
        return $out;
    }
}

/**
 * Ensure $event has department + target_sections for audience checks.
 *
 * @param array<string,mixed> $event
 * @return array<string,mixed>
 */
if (!function_exists('eventify_event_attach_audience_fields')) {
    function eventify_event_attach_audience_fields(mysqli $conn, array $event): array
    {
        $id = (int) ($event['id'] ?? $event['event_id'] ?? 0);
        if ($id < 1) {
            return $event;
        }
        $needDept = !array_key_exists('department', $event);
        $needSec = !array_key_exists('target_sections', $event);
        if (!$needDept && !$needSec) {
            return $event;
        }
        eventify_events_ensure_target_sections($conn);
        $cols = 'department';
        try {
            $r = $conn->query("SHOW COLUMNS FROM events LIKE 'target_sections'");
            if ($r && $r->num_rows > 0) {
                $cols .= ', target_sections';
            }
        } catch (Throwable $e) {
            /* ignore */
        }
        $stmt = $conn->prepare("SELECT {$cols} FROM events WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return $event;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            if ($needDept) {
                $event['department'] = $row['department'] ?? 'ALL';
            }
            if ($needSec && array_key_exists('target_sections', $row)) {
                $event['target_sections'] = $row['target_sections'] ?? null;
            }
        }
        return $event;
    }
}

/**
 * @return array{ok:bool,error:?string}
 */
if (!function_exists('eventify_student_event_audience_gate')) {
    function eventify_student_event_audience_gate(mysqli $conn, int $userId, array $event): array
    {
        if ($userId < 1) {
            return ['ok' => false, 'error' => 'Sign in as a student to continue.'];
        }
        if (!function_exists('eventify_student_may_access_event')) {
            return ['ok' => true, 'error' => null];
        }
        $event = eventify_event_attach_audience_fields($conn, $event);
        $student = eventify_load_student_audience_profile($conn, $userId);
        if (eventify_student_may_access_event($event, $student)) {
            return ['ok' => true, 'error' => null];
        }
        $locked = eventify_parse_target_sections_list($event['target_sections'] ?? null);
        if ($locked !== []) {
            return [
                'ok' => false,
                'error' => 'This event is only for section ' . implode(' / ', $locked) . '. Your account is not assigned to that section. Ask an admin to set your class section.',
            ];
        }
        return [
            'ok' => false,
            'error' => 'This event is not available for your department.',
        ];
    }
}
