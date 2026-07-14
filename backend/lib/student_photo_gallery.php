<?php
/**
 * Student photo gallery data for dashboard panel and legacy page.
 */
declare(strict_types=1);

require_once __DIR__ . '/event_photos.php';

function eventify_student_photo_dept_viewable(?string $eventDept, ?string $studentDept): bool
{
    $eventDept = trim((string) $eventDept);
    if ($eventDept === '' || strtoupper($eventDept) === 'ALL') {
        return true;
    }

    return $studentDept !== null && $eventDept === $studentDept;
}

/**
 * @return list<array<string, mixed>>
 */
function eventify_load_student_photo_galleries(mysqli $conn, ?string $studentDept): array
{
    $statusEnabled = eventify_event_photos_has_status($conn);
    $publishedCond = $statusEnabled ? "p.status = 'published'" : '1';
    $deptParam = $studentDept !== null ? $studentDept : '';

    $sql = "
        SELECT e.id, e.title, e.date, e.location, e.department,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id AND {$publishedCond}) AS photo_count,
               (SELECT p2.file_path FROM event_photos p2 WHERE p2.event_id = e.id AND " . str_replace('p.', 'p2.', $publishedCond) . "
                  ORDER BY p2.created_at DESC, p2.id DESC LIMIT 1) AS cover,
               (SELECT MAX(p3.created_at) FROM event_photos p3 WHERE p3.event_id = e.id AND " . str_replace('p.', 'p3.', $publishedCond) . ") AS latest_photo
        FROM events e
        WHERE LOWER(e.status) IN ('active', 'closed')
          AND (e.department IS NULL OR e.department = '' OR e.department = 'ALL' OR e.department = ?)
        HAVING photo_count > 0
        ORDER BY latest_photo DESC, e.date DESC, e.id DESC
    ";

    $galleries = [];
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $deptParam);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $galleries[] = $row;
        }
    }
    $stmt->close();

    return $galleries;
}

/**
 * @return array{event: ?array<string, mixed>, photos: list<array<string, mixed>>, error: string}
 */
function eventify_load_student_event_gallery(mysqli $conn, int $eventId, ?string $studentDept): array
{
    $result = ['event' => null, 'photos' => [], 'error' => ''];
    if ($eventId <= 0) {
        $result['error'] = 'Event not found.';

        return $result;
    }

    $statusEnabled = eventify_event_photos_has_status($conn);
    $publishedCond = $statusEnabled ? "p.status = 'published'" : '1';

    $stmt = $conn->prepare('SELECT id, title, date, location, status, department FROM events WHERE id = ? LIMIT 1');
    if (!$stmt) {
        $result['error'] = 'Event not found.';

        return $result;
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$event) {
        $result['error'] = 'Event not found.';

        return $result;
    }
    if (!in_array(strtolower((string) ($event['status'] ?? '')), ['active', 'closed'], true)) {
        $result['error'] = 'Photos are only available for approved events.';

        return $result;
    }
    if (!eventify_student_photo_dept_viewable($event['department'] ?? null, $studentDept)) {
        $result['error'] = 'This gallery is restricted to students from the ' . (string) ($event['department'] ?? '') . '.';

        return $result;
    }

    $photos = [];
    $sql = "SELECT id, file_path FROM event_photos p WHERE event_id = ? AND {$publishedCond} ORDER BY created_at DESC, id DESC";
    $pStmt = $conn->prepare($sql);
    if ($pStmt) {
        $pStmt->bind_param('i', $eventId);
        $pStmt->execute();
        $pRes = $pStmt->get_result();
        while ($row = $pRes->fetch_assoc()) {
            $photos[] = $row;
        }
        $pStmt->close();
    }

    $result['event'] = $event;
    $result['photos'] = $photos;

    return $result;
}
