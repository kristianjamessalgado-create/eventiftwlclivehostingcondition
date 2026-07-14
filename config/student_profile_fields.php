<?php
/**
 * Student profile fields: course/program (required, fixed choices), year level, academic school year.
 * Columns are added on first use (idempotent).
 */

if (!function_exists('eventify_users_ensure_student_profile_fields')) {
    function eventify_users_ensure_student_profile_fields(mysqli $conn): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;
        $cols = [
            'student_course' => 'VARCHAR(120) NULL DEFAULT NULL',
            'student_year_level' => 'VARCHAR(40) NULL DEFAULT NULL',
            'student_academic_year' => 'VARCHAR(20) NULL DEFAULT NULL',
        ];
        foreach ($cols as $field => $definition) {
            if (!preg_match('/^[a-z_]+$/', $field)) {
                continue;
            }
            try {
                $esc = $conn->real_escape_string($field);
                $r = $conn->query("SHOW COLUMNS FROM users LIKE '{$esc}'");
                if ($r && $r->num_rows === 0) {
                    $conn->query("ALTER TABLE users ADD COLUMN `{$field}` {$definition}");
                }
            } catch (Throwable $e) {
                // leave as-is until DBA runs migration
            }
        }
    }
}

/**
 * Canonical course->department mapping used by registration/profile validation.
 *
 * @return array<string, string> course => department
 */
if (!function_exists('eventify_student_course_program_department_map')) {
    function eventify_student_course_program_department_map(): array
    {
        return [
            'BS Information Technology' => 'College of Communication, Information and Technology',
            'BS Computer Science' => 'College of Communication, Information and Technology',
            'BS Information Systems' => 'College of Communication, Information and Technology',
            'BS Hospitality Management' => 'College of Hospitality Management',
            'BS Tourism Management' => 'College of Hospitality Management',
            'BS Nursing' => 'College of Nursing and Allied health sciences',
            'BS Medical Technology' => 'College of Nursing and Allied health sciences',
            'BS Accountancy' => 'College of Accountancy and Business',
            'BS Accounting Information System' => 'College of Accountancy and Business',
            'BS Business Administration' => 'College of Accountancy and Business',
            'BS Psychology' => 'College of Education',
            'BS Secondary Education' => 'College of Education',
            'BS Elementary Education' => 'College of Education',
            'Bachelor of Laws (JD)' => 'School of Law and Political Science',
            'Senior High School — STEM' => 'High school department',
            'Senior High School — ABM' => 'High school department',
            'Senior High School — HUMSS' => 'High school department',
            'Senior High School — GAS' => 'High school department',
            'Senior High School — TVL' => 'High school department',
            'High school (Junior High)' => 'High school department',
        ];
    }
}

/**
 * Course / program choices (stored value => label shown in UI and attendance).
 *
 * @return array<string, string>
 */
if (!function_exists('eventify_student_course_program_options')) {
    function eventify_student_course_program_options(): array
    {
        $out = ['' => '— Select course / program —'];
        foreach (eventify_student_course_program_department_map() as $course => $dept) {
            $out[$course] = $course;
        }
        return $out;
    }
}

if (!function_exists('eventify_student_course_program_valid')) {
    function eventify_student_course_program_valid(string $v): bool
    {
        $v = trim($v);
        if ($v === '') {
            return false;
        }
        $opts = eventify_student_course_program_options();
        return array_key_exists($v, $opts) && $v !== '';
    }
}

if (!function_exists('eventify_student_course_program_department')) {
    function eventify_student_course_program_department(string $course): string
    {
        $course = trim($course);
        if ($course === '') {
            return '';
        }
        $map = eventify_student_course_program_department_map();
        return (string)($map[$course] ?? '');
    }
}

if (!function_exists('eventify_student_course_matches_department')) {
    function eventify_student_course_matches_department(string $course, string $department): bool
    {
        $course = trim($course);
        $department = trim($department);
        if ($course === '' || $department === '') {
            return false;
        }
        return eventify_student_course_program_department($course) === $department;
    }
}

/** @return array<string, string> value => label */
if (!function_exists('eventify_student_year_level_options')) {
    function eventify_student_year_level_options(): array
    {
        return [
            '' => '— Select —',
            '1st Year' => '1st Year',
            '2nd Year' => '2nd Year',
            '3rd Year' => '3rd Year',
            '4th Year' => '4th Year',
            '5th Year' => '5th Year',
            'Grade 7' => 'Grade 7',
            'Grade 8' => 'Grade 8',
            'Grade 9' => 'Grade 9',
            'Grade 10' => 'Grade 10',
            'Grade 11' => 'Grade 11',
            'Grade 12' => 'Grade 12',
        ];
    }
}

/** @return array<string, string> e.g. 2025-2026 => 2025-2026 */
if (!function_exists('eventify_student_academic_year_options')) {
    function eventify_student_academic_year_options(): array
    {
        $y = (int) date('Y');
        $out = ['' => '— Select —'];
        for ($i = -1; $i <= 4; $i++) {
            $start = $y + $i;
            $key = $start . '-' . ($start + 1);
            $out[$key] = $key;
        }
        return $out;
    }
}

if (!function_exists('eventify_student_academic_year_valid')) {
    function eventify_student_academic_year_valid(string $v): bool
    {
        $v = trim($v);
        if ($v === '') {
            return true;
        }
        return (bool) preg_match('/^\d{4}-\d{4}$/', $v);
    }
}
