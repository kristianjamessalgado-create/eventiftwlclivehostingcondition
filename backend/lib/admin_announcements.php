<?php
/**
 * Admin announcements: targeted broadcasts to students (bell + push).
 */

require_once __DIR__ . '/notifications_service.php';
require_once __DIR__ . '/../../config/departments.php';
require_once __DIR__ . '/../../config/student_profile_fields.php';
if (is_file(__DIR__ . '/../../config/student_sections.php')) {
    require_once __DIR__ . '/../../config/student_sections.php';
}

/**
 * Section audience matching ignores spacing/case differences, so labels such
 * as "BSIT4101" and "BSIT 4101" reach the same assigned section.
 */
function eventify_announcement_section_match_key(string $label): string
{
    $key = function_exists('eventify_section_match_key')
        ? eventify_section_match_key($label)
        : strtolower(trim($label));

    return preg_replace('/[\s\-_]+/u', '', $key) ?? '';
}

function eventify_announcements_ensure_table(mysqli $conn): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    try {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS admin_announcements (
                id INT(11) NOT NULL AUTO_INCREMENT,
                created_by INT(11) NOT NULL,
                title VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                target_filters JSON NULL,
                recipient_count INT(11) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY created_by (created_by),
                KEY created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    } catch (Throwable $e) {
        // leave as-is until DBA runs migration
    }
}

/**
 * @param array<string, mixed> $post
 * @return array{
 *   ok: bool,
 *   error: ?string,
 *   filters: array{
 *     departments: list<string>,
 *     sections: list<string>,
 *     courses: list<string>,
 *     academic_years: list<string>
 *   }
 * }
 */
function eventify_parse_announcement_filters_from_request(array $post): array
{
    $empty = [
        'departments' => [],
        'sections' => [],
        'courses' => [],
        'academic_years' => [],
    ];

    $departments = [];
    if (isset($post['department']) && is_array($post['department'])) {
        foreach ($post['department'] as $x) {
            $x = trim((string) $x);
            if ($x !== '') {
                $departments[] = $x;
            }
        }
    } else {
        $raw = trim((string) ($post['department'] ?? ''));
        if ($raw !== '') {
            $departments[] = $raw;
        }
    }
    $departments = array_values(array_unique($departments));
    if (in_array('ALL', $departments, true)) {
        $departments = ['ALL'];
    } else {
        $allowed = eventify_allowed_departments();
        foreach ($departments as $d) {
            if (!in_array($d, $allowed, true)) {
                return ['ok' => false, 'error' => 'Invalid department selection.', 'filters' => $empty];
            }
        }
    }

    $sections = [];
    if (isset($post['section']) && is_array($post['section'])) {
        foreach ($post['section'] as $s) {
            $s = trim((string) $s);
            if ($s !== '') {
                $sections[] = $s;
            }
        }
    }
    $newSection = trim((string) ($post['new_section'] ?? ''));
    if ($newSection !== '') {
        $sections[] = $newSection;
    }
    $sections = array_values(array_unique($sections));

    $courses = [];
    if (isset($post['course']) && is_array($post['course'])) {
        foreach ($post['course'] as $c) {
            $c = trim((string) $c);
            if ($c === '') {
                continue;
            }
            if (!eventify_student_course_program_valid($c)) {
                return ['ok' => false, 'error' => 'Invalid course selection.', 'filters' => $empty];
            }
            $courses[] = $c;
        }
    }
    $courses = array_values(array_unique($courses));

    $filters = [
        'departments' => $departments,
        'sections' => $sections,
        'courses' => $courses,
        'academic_years' => [],
    ];

    if (
        $filters['departments'] === []
        && $filters['sections'] === []
        && $filters['courses'] === []
    ) {
        return [
            'ok' => false,
            'error' => 'Choose at least one audience filter (department, section, or course).',
            'filters' => $empty,
        ];
    }

    return ['ok' => true, 'error' => null, 'filters' => $filters];
}

/**
 * @param array{
 *   departments?: list<string>,
 *   sections?: list<string>,
 *   courses?: list<string>,
 *   academic_years?: list<string>
 * } $filters
 * @return list<int>
 */
function eventify_student_ids_for_announcement_audience(mysqli $conn, array $filters): array
{
    eventify_users_ensure_student_profile_fields($conn);
    if (function_exists('eventify_sections_schema_ensure')) {
        eventify_sections_schema_ensure($conn);
    }

    $departments = array_values(array_filter(array_map('strval', $filters['departments'] ?? [])));
    $sections = array_values(array_filter(array_map('strval', $filters['sections'] ?? [])));
    $courses = array_values(array_filter(array_map('strval', $filters['courses'] ?? [])));
    $years = array_values(array_filter(array_map('strval', $filters['academic_years'] ?? [])));

    $hasDept = $departments !== [];
    $hasSection = $sections !== [];
    $hasCourse = $courses !== [];
    $hasYear = $years !== [];
    if (!$hasDept && !$hasSection && !$hasCourse && !$hasYear) {
        return [];
    }

    $deptAll = $hasDept && in_array('ALL', $departments, true);
    $deptSet = [];
    if ($hasDept && !$deptAll) {
        foreach ($departments as $d) {
            $deptSet[$d] = true;
        }
    }

    $sectionKeys = [];
    if ($hasSection) {
        foreach ($sections as $s) {
            $k = eventify_announcement_section_match_key($s);
            if ($k !== '') {
                $sectionKeys[$k] = true;
            }
        }
        if ($sectionKeys === []) {
            return [];
        }
    }

    $courseSet = [];
    if ($hasCourse) {
        foreach ($courses as $c) {
            $courseSet[$c] = true;
        }
    }

    $yearSet = [];
    if ($hasYear) {
        foreach ($years as $y) {
            $yearSet[$y] = true;
        }
    }

    $ids = [];
    $res = @$conn->query(
        "SELECT id, department, student_section, student_course, student_academic_year
         FROM users
         WHERE role = 'student' AND status = 'active'"
    );
    if (!$res) {
        return [];
    }

    while ($row = $res->fetch_assoc()) {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            continue;
        }

        if ($hasDept && !$deptAll) {
            $stuDept = trim((string) ($row['department'] ?? ''));
            if ($stuDept === '' || !isset($deptSet[$stuDept])) {
                continue;
            }
        }

        if ($hasSection) {
            $stuSec = trim((string) ($row['student_section'] ?? ''));
            if ($stuSec === '') {
                continue;
            }
            $k = eventify_announcement_section_match_key($stuSec);
            if ($k === '' || !isset($sectionKeys[$k])) {
                continue;
            }
        }

        if ($hasCourse) {
            $stuCourse = trim((string) ($row['student_course'] ?? ''));
            if ($stuCourse === '' || !isset($courseSet[$stuCourse])) {
                continue;
            }
        }

        if ($hasYear) {
            $stuYear = trim((string) ($row['student_academic_year'] ?? ''));
            if ($stuYear === '' || !isset($yearSet[$stuYear])) {
                continue;
            }
        }

        $ids[] = $id;
    }

    return $ids;
}

/**
 * Human-readable summary of stored filters for admin history.
 *
 * @param array<string, mixed>|string|null $filters
 */
function eventify_announcement_filters_label($filters): string
{
    if (is_string($filters)) {
        $decoded = json_decode($filters, true);
        $filters = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($filters)) {
        return '—';
    }

    $parts = [];
    $depts = $filters['departments'] ?? [];
    if (is_array($depts) && $depts !== []) {
        if (in_array('ALL', $depts, true)) {
            $parts[] = 'All departments';
        } else {
            $parts[] = 'Dept: ' . implode(', ', array_map('strval', $depts));
        }
    }
    $secs = $filters['sections'] ?? [];
    if (is_array($secs) && $secs !== []) {
        $parts[] = 'Section: ' . implode(', ', array_map('strval', $secs));
    }
    $courses = $filters['courses'] ?? [];
    if (is_array($courses) && $courses !== []) {
        $parts[] = 'Course: ' . implode(', ', array_map('strval', $courses));
    }
    $years = $filters['academic_years'] ?? [];
    if (is_array($years) && $years !== []) {
        $parts[] = 'SY: ' . implode(', ', array_map('strval', $years));
    }

    return $parts === [] ? '—' : implode(' · ', $parts);
}

/**
 * @return list<array<string, mixed>>
 */
function eventify_list_admin_announcements(mysqli $conn, int $limit = 40): array
{
    eventify_announcements_ensure_table($conn);
    $limit = max(1, min(100, $limit));
    $rows = [];
    try {
        $sql = "SELECT a.id, a.title, a.body, a.target_filters, a.recipient_count, a.created_at,
                       u.name AS created_by_name
                FROM admin_announcements a
                LEFT JOIN users u ON u.id = a.created_by
                ORDER BY a.created_at DESC
                LIMIT {$limit}";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
    } catch (Throwable $e) {
        return [];
    }
    return $rows;
}

/**
 * Create announcement record, notify matching students (bell + push).
 *
 * @param array{
 *   departments: list<string>,
 *   sections: list<string>,
 *   courses: list<string>,
 *   academic_years: list<string>
 * } $filters
 * @return array{ok: bool, error: ?string, announcement_id: int, recipient_count: int}
 */
function eventify_send_admin_announcement(
    mysqli $conn,
    int $adminId,
    string $title,
    string $body,
    array $filters
): array {
    $title = trim($title);
    $body = trim($body);
    if ($adminId < 1) {
        return ['ok' => false, 'error' => 'Invalid admin.', 'announcement_id' => 0, 'recipient_count' => 0];
    }
    if ($title === '' || mb_strlen($title) > 255) {
        return ['ok' => false, 'error' => 'Title is required (max 255 characters).', 'announcement_id' => 0, 'recipient_count' => 0];
    }
    if ($body === '' || mb_strlen($body) > 4000) {
        return ['ok' => false, 'error' => 'Message is required (max 4000 characters).', 'announcement_id' => 0, 'recipient_count' => 0];
    }

    eventify_announcements_ensure_table($conn);

    $recipientIds = eventify_student_ids_for_announcement_audience($conn, $filters);
    if ($recipientIds === []) {
        return [
            'ok' => false,
            'error' => 'No active students match the selected filters. Check assigned sections and courses.',
            'announcement_id' => 0,
            'recipient_count' => 0,
        ];
    }

    $filtersJson = json_encode($filters, JSON_UNESCAPED_UNICODE);
    if ($filtersJson === false) {
        $filtersJson = '{}';
    }

    $announcementId = 0;
    $stmt = $conn->prepare(
        'INSERT INTO admin_announcements (created_by, title, body, target_filters, recipient_count) VALUES (?, ?, ?, ?, 0)'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not save announcement.', 'announcement_id' => 0, 'recipient_count' => 0];
    }
    $stmt->bind_param('isss', $adminId, $title, $body, $filtersJson);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['ok' => false, 'error' => 'Could not save announcement.', 'announcement_id' => 0, 'recipient_count' => 0];
    }
    $announcementId = (int) $stmt->insert_id;
    $stmt->close();

    $delivered = eventify_notify_student_ids(
        $conn,
        $recipientIds,
        'admin_announcement',
        $title,
        $body,
        null,
        $announcementId
    );

    if ($delivered === 0) {
        $cleanup = $conn->prepare('DELETE FROM admin_announcements WHERE id = ?');
        if ($cleanup) {
            $cleanup->bind_param('i', $announcementId);
            $cleanup->execute();
            $cleanup->close();
        }
        return [
            'ok' => false,
            'error' => 'Students matched the audience, but their bell notifications could not be created. Upload the latest notification files and try again.',
            'announcement_id' => 0,
            'recipient_count' => 0,
        ];
    }

    $upd = $conn->prepare('UPDATE admin_announcements SET recipient_count = ? WHERE id = ?');
    if ($upd) {
        $upd->bind_param('ii', $delivered, $announcementId);
        $upd->execute();
        $upd->close();
    }

    return [
        'ok' => true,
        'error' => null,
        'announcement_id' => $announcementId,
        'recipient_count' => $delivered,
    ];
}

/**
 * @return array<string, mixed>|null
 */
function eventify_get_admin_announcement(mysqli $conn, int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    eventify_announcements_ensure_table($conn);
    $stmt = $conn->prepare(
        'SELECT id, created_by, title, body, target_filters, recipient_count, created_at
         FROM admin_announcements WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Update announcement title/body, replace student bell rows, and re-send push.
 *
 * @return array{ok: bool, error: ?string, recipient_count: int}
 */
function eventify_update_admin_announcement(mysqli $conn, int $id, string $title, string $body): array
{
    $title = trim($title);
    $body = trim($body);
    if ($id < 1) {
        return ['ok' => false, 'error' => 'Invalid announcement.', 'recipient_count' => 0];
    }
    if ($title === '' || mb_strlen($title) > 255) {
        return ['ok' => false, 'error' => 'Title is required (max 255 characters).', 'recipient_count' => 0];
    }
    if ($body === '' || mb_strlen($body) > 4000) {
        return ['ok' => false, 'error' => 'Message is required (max 4000 characters).', 'recipient_count' => 0];
    }

    $existing = eventify_get_admin_announcement($conn, $id);
    if (!$existing) {
        return ['ok' => false, 'error' => 'Announcement not found.', 'recipient_count' => 0];
    }

    $oldTitle = (string) ($existing['title'] ?? '');
    $oldBody = (string) ($existing['body'] ?? '');

    $hasAnnouncementId = eventify_notifications_ensure_announcement_id($conn);

    // Recipients who already got this announcement (preferred), else re-resolve filters.
    $recipientIds = [];
    $seen = [];
    $collect = static function (mysqli_result $res) use (&$recipientIds, &$seen): void {
        while ($row = $res->fetch_assoc()) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0 && !isset($seen[$uid])) {
                $seen[$uid] = true;
                $recipientIds[] = $uid;
            }
        }
    };

    if ($hasAnnouncementId) {
        $qLinked = $conn->prepare(
            "SELECT DISTINCT user_id FROM notifications WHERE type = 'admin_announcement' AND announcement_id = ?"
        );
        if ($qLinked) {
            $qLinked->bind_param('i', $id);
            $qLinked->execute();
            $r = $qLinked->get_result();
            if ($r) {
                $collect($r);
            }
            $qLinked->close();
        }
    }

    if ($recipientIds === []) {
        $fallbackSql = "SELECT DISTINCT user_id FROM notifications
                        WHERE type = 'admin_announcement'
                          AND title = ? AND message = ?";
        if ($hasAnnouncementId) {
            $fallbackSql .= ' AND (announcement_id IS NULL OR announcement_id = 0)';
        }
        $qFallback = $conn->prepare($fallbackSql);
        if ($qFallback) {
            $qFallback->bind_param('ss', $oldTitle, $oldBody);
            $qFallback->execute();
            $r = $qFallback->get_result();
            if ($r) {
                $collect($r);
            }
            $qFallback->close();
        }
    }

    if ($recipientIds === []) {
        $filters = $existing['target_filters'] ?? null;
        if (is_string($filters)) {
            $decoded = json_decode($filters, true);
            $filters = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($filters)) {
            $filters = [];
        }
        $recipientIds = eventify_student_ids_for_announcement_audience($conn, $filters);
    }

    if ($recipientIds === []) {
        return [
            'ok' => false,
            'error' => 'No students found to notify for this announcement.',
            'recipient_count' => 0,
        ];
    }

    $stmt = $conn->prepare('UPDATE admin_announcements SET title = ?, body = ? WHERE id = ?');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not update announcement.', 'recipient_count' => 0];
    }
    $stmt->bind_param('ssi', $title, $body, $id);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['ok' => false, 'error' => 'Could not update announcement.', 'recipient_count' => 0];
    }
    $stmt->close();

    // Remove previous bell rows so students get a fresh unread notification.
    if ($hasAnnouncementId) {
        $delLinked = $conn->prepare(
            "DELETE FROM notifications WHERE type = 'admin_announcement' AND announcement_id = ?"
        );
        if ($delLinked) {
            $delLinked->bind_param('i', $id);
            $delLinked->execute();
            $delLinked->close();
        }
    }
    $deleteFallbackSql = "DELETE FROM notifications
                          WHERE type = 'admin_announcement'
                            AND title = ? AND message = ?";
    if ($hasAnnouncementId) {
        $deleteFallbackSql .= ' AND (announcement_id IS NULL OR announcement_id = 0)';
    }
    $delFallback = $conn->prepare($deleteFallbackSql);
    if ($delFallback) {
        $delFallback->bind_param('ss', $oldTitle, $oldBody);
        $delFallback->execute();
        $delFallback->close();
    }

    $delivered = eventify_notify_student_ids(
        $conn,
        $recipientIds,
        'admin_announcement',
        $title,
        $body,
        null,
        $id
    );

    $updCount = $conn->prepare('UPDATE admin_announcements SET recipient_count = ? WHERE id = ?');
    if ($updCount) {
        $updCount->bind_param('ii', $delivered, $id);
        $updCount->execute();
        $updCount->close();
    }

    return ['ok' => true, 'error' => null, 'recipient_count' => $delivered];
}

/**
 * Delete announcement history and related student bell notifications.
 *
 * @return array{ok: bool, error: ?string}
 */
function eventify_delete_admin_announcement(mysqli $conn, int $id): array
{
    if ($id < 1) {
        return ['ok' => false, 'error' => 'Invalid announcement.'];
    }

    $existing = eventify_get_admin_announcement($conn, $id);
    if (!$existing) {
        return ['ok' => false, 'error' => 'Announcement not found.'];
    }

    $oldTitle = (string) ($existing['title'] ?? '');
    $oldBody = (string) ($existing['body'] ?? '');

    $hasAnnouncementId = eventify_notifications_ensure_announcement_id($conn);

    if ($hasAnnouncementId) {
        $delLinked = $conn->prepare(
            "DELETE FROM notifications WHERE type = 'admin_announcement' AND announcement_id = ?"
        );
        if ($delLinked) {
            $delLinked->bind_param('i', $id);
            $delLinked->execute();
            $delLinked->close();
        }
    }

    $deleteFallbackSql = "DELETE FROM notifications
                          WHERE type = 'admin_announcement'
                            AND title = ? AND message = ?";
    if ($hasAnnouncementId) {
        $deleteFallbackSql .= ' AND (announcement_id IS NULL OR announcement_id = 0)';
    }
    $delFallback = $conn->prepare($deleteFallbackSql);
    if ($delFallback) {
        $delFallback->bind_param('ss', $oldTitle, $oldBody);
        $delFallback->execute();
        $delFallback->close();
    }

    $stmt = $conn->prepare('DELETE FROM admin_announcements WHERE id = ?');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not delete announcement.'];
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['ok' => false, 'error' => 'Could not delete announcement.'];
    }
    $stmt->close();

    return ['ok' => true, 'error' => null];
}
