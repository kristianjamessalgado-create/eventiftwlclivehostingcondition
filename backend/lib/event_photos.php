<?php

/**
 * Event / activity photos (multimedia uploads, activities hub gallery).
 */

function eventify_event_photos_has_status(mysqli $conn): bool
{
    try {
        $c = $conn->query("SHOW COLUMNS FROM event_photos LIKE 'status'");
        return (bool) ($c && $c->num_rows > 0);
    } catch (Throwable $e) {
        return false;
    }
}

function eventify_event_photos_has_session_column(mysqli $conn): bool
{
    try {
        $c = $conn->query("SHOW COLUMNS FROM event_photos LIKE 'session_id'");
        return (bool) ($c && $c->num_rows > 0);
    } catch (Throwable $e) {
        return false;
    }
}

function eventify_event_photos_has_caption_column(mysqli $conn): bool
{
    try {
        $c = $conn->query("SHOW COLUMNS FROM event_photos LIKE 'caption'");
        return (bool) ($c && $c->num_rows > 0);
    } catch (Throwable $e) {
        return false;
    }
}

function eventify_event_photos_has_credit_line_column(mysqli $conn): bool
{
    try {
        $c = $conn->query("SHOW COLUMNS FROM event_photos LIKE 'credit_line'");
        return (bool) ($c && $c->num_rows > 0);
    } catch (Throwable $e) {
        return false;
    }
}

function eventify_event_photos_has_reject_reason_column(mysqli $conn): bool
{
    try {
        $c = $conn->query("SHOW COLUMNS FROM event_photos LIKE 'reject_reason'");
        return (bool) ($c && $c->num_rows > 0);
    } catch (Throwable $e) {
        return false;
    }
}

function eventify_event_photos_metadata_columns_ready(mysqli $conn): bool
{
    return eventify_event_photos_has_caption_column($conn)
        && eventify_event_photos_has_credit_line_column($conn)
        && eventify_event_photos_has_reject_reason_column($conn);
}

/** @return string e.g. ", caption, credit_line" or "" */
function eventify_event_photos_metadata_select_sql(mysqli $conn, bool $includeRejectReason = true, string $prefix = ''): string
{
    $cols = [];
    if (eventify_event_photos_has_caption_column($conn)) {
        $cols[] = $prefix . 'caption';
    }
    if (eventify_event_photos_has_credit_line_column($conn)) {
        $cols[] = $prefix . 'credit_line';
    }
    if ($includeRejectReason && eventify_event_photos_has_reject_reason_column($conn)) {
        $cols[] = $prefix . 'reject_reason';
    }
    return $cols !== [] ? ', ' . implode(', ', $cols) : '';
}

function eventify_event_photos_ensure_metadata_columns(mysqli $conn): bool
{
    try {
        $t = $conn->query("SHOW TABLES LIKE 'event_photos'");
        if (!$t || $t->num_rows < 1) {
            return false;
        }
        if (eventify_event_photos_metadata_columns_ready($conn)) {
            return true;
        }

        if (!eventify_event_photos_has_caption_column($conn)) {
            @$conn->query(
                "ALTER TABLE event_photos ADD COLUMN caption varchar(255) DEFAULT NULL COMMENT 'Short photo description' AFTER file_path"
            );
            if (!eventify_event_photos_has_caption_column($conn)) {
                @$conn->query(
                    "ALTER TABLE event_photos ADD COLUMN caption varchar(255) DEFAULT NULL COMMENT 'Short photo description'"
                );
            }
        }

        if (!eventify_event_photos_has_credit_line_column($conn)) {
            if (eventify_event_photos_has_caption_column($conn)) {
                @$conn->query(
                    "ALTER TABLE event_photos ADD COLUMN credit_line varchar(255) DEFAULT NULL COMMENT 'Photographer credit' AFTER caption"
                );
            }
            if (!eventify_event_photos_has_credit_line_column($conn)) {
                @$conn->query(
                    "ALTER TABLE event_photos ADD COLUMN credit_line varchar(255) DEFAULT NULL COMMENT 'Photographer credit'"
                );
            }
        }

        if (!eventify_event_photos_has_reject_reason_column($conn)) {
            $afterCol = '';
            if (eventify_event_photos_has_status($conn)) {
                $pub = $conn->query("SHOW COLUMNS FROM event_photos LIKE 'published_at'");
                $afterCol = ($pub && $pub->num_rows > 0) ? 'published_at' : 'status';
            } elseif (eventify_event_photos_has_credit_line_column($conn)) {
                $afterCol = 'credit_line';
            } elseif (eventify_event_photos_has_caption_column($conn)) {
                $afterCol = 'caption';
            }
            if ($afterCol !== '') {
                @$conn->query(
                    "ALTER TABLE event_photos ADD COLUMN reject_reason varchar(500) DEFAULT NULL COMMENT 'Moderator reason when rejected' AFTER {$afterCol}"
                );
            }
            if (!eventify_event_photos_has_reject_reason_column($conn)) {
                @$conn->query(
                    "ALTER TABLE event_photos ADD COLUMN reject_reason varchar(500) DEFAULT NULL COMMENT 'Moderator reason when rejected'"
                );
            }
        }

        return eventify_event_photos_metadata_columns_ready($conn);
    } catch (Throwable $e) {
        return false;
    }
}

function eventify_sanitize_photo_caption(string $value, int $maxLen = 255): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLen);
    }
    return substr($value, 0, $maxLen);
}

function eventify_event_photos_ensure_session_column(mysqli $conn): bool
{
    try {
        $t = $conn->query("SHOW TABLES LIKE 'event_photos'");
        if (!$t || $t->num_rows < 1) {
            return false;
        }
        if (eventify_event_photos_has_session_column($conn)) {
            return true;
        }
        if (!$conn->query(
            "ALTER TABLE event_photos ADD COLUMN session_id INT(11) NULL DEFAULT NULL AFTER event_id"
        )) {
            return false;
        }
        @$conn->query(
            "ALTER TABLE event_photos ADD KEY idx_event_photos_session (session_id)"
        );
        return eventify_event_photos_has_session_column($conn);
    } catch (Throwable $e) {
        return false;
    }
}

/** Multimedia may upload during and after events (closed/completed/active). */
function eventify_event_allows_multimedia_photo_upload(?string $status): bool
{
    $st = strtolower(trim((string) $status));
    return !in_array($st, ['cancelled', 'rejected', 'pending'], true);
}

function eventify_validate_activity_for_event(mysqli $conn, int $sessionId, int $eventId): bool
{
    if ($sessionId < 1 || $eventId < 1) {
        return false;
    }
    $stmt = $conn->prepare('SELECT id FROM event_day_sessions WHERE id = ? AND event_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $sessionId, $eventId);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

/** @return array<string, array{published: int, my_draft: int, my_total: int}> */
function eventify_load_event_session_photo_stats(mysqli $conn, int $eventId, int $multimediaUserId = 0): array
{
    if ($eventId < 1 || !eventify_event_photos_has_session_column($conn)) {
        return [];
    }
    $hasStatus = eventify_event_photos_has_status($conn);
    $stats = [];
    if ($hasStatus) {
        $sql = "
            SELECT session_id,
                   SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
                   SUM(CASE WHEN uploaded_by = ? AND status = 'draft' THEN 1 ELSE 0 END) AS my_draft,
                   SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS pending_draft,
                   SUM(CASE WHEN uploaded_by = ? THEN 1 ELSE 0 END) AS my_total
            FROM event_photos
            WHERE event_id = ? AND session_id IS NOT NULL
            GROUP BY session_id
        ";
    } else {
        $sql = "
            SELECT session_id,
                   COUNT(*) AS published,
                   0 AS my_draft,
                   SUM(CASE WHEN uploaded_by = ? THEN 1 ELSE 0 END) AS my_total
            FROM event_photos
            WHERE event_id = ? AND session_id IS NOT NULL
            GROUP BY session_id
        ";
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($hasStatus) {
        $stmt->bind_param('iii', $multimediaUserId, $multimediaUserId, $eventId);
    } else {
        $stmt->bind_param('ii', $multimediaUserId, $eventId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $sid = (int) ($row['session_id'] ?? 0);
        if ($sid < 1) {
            continue;
        }
        $stats[(string) $sid] = [
            'published' => (int) ($row['published'] ?? 0),
            'my_draft' => (int) ($row['my_draft'] ?? 0),
            'pending_draft' => (int) ($row['pending_draft'] ?? 0),
            'my_total' => (int) ($row['my_total'] ?? 0),
        ];
    }
    $stmt->close();
    return $stats;
}

/** @return array{my_pending: int, my_published: int, my_rejected: int, team_pending: int} */
function eventify_load_event_multimedia_photo_summary(mysqli $conn, int $eventId, int $userId): array
{
    $summary = [
        'my_pending' => 0,
        'my_published' => 0,
        'my_rejected' => 0,
        'team_pending' => 0,
    ];
    if ($eventId < 1 || $userId < 1 || !eventify_event_photos_has_status($conn)) {
        return $summary;
    }
    $sql = "
        SELECT
            SUM(CASE WHEN uploaded_by = ? AND status = 'draft' THEN 1 ELSE 0 END) AS my_pending,
            SUM(CASE WHEN uploaded_by = ? AND status = 'published' THEN 1 ELSE 0 END) AS my_published,
            SUM(CASE WHEN uploaded_by = ? AND status = 'rejected' THEN 1 ELSE 0 END) AS my_rejected,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS team_pending
        FROM event_photos
        WHERE event_id = ?
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $summary;
    }
    $stmt->bind_param('iiii', $userId, $userId, $userId, $eventId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return $summary;
    }
    $summary['my_pending'] = (int) ($row['my_pending'] ?? 0);
    $summary['my_published'] = (int) ($row['my_published'] ?? 0);
    $summary['my_rejected'] = (int) ($row['my_rejected'] ?? 0);
    $summary['team_pending'] = (int) ($row['team_pending'] ?? 0);
    return $summary;
}

/**
 * @return list<array<string, mixed>>
 */
function eventify_load_activity_photos(
    mysqli $conn,
    int $eventId,
    int $sessionId,
    string $role,
    int $userId,
    bool $isModerator = false
): array {
    if ($eventId < 1 || $sessionId < 1 || !eventify_event_photos_has_session_column($conn)) {
        return [];
    }
    $hasStatus = eventify_event_photos_has_status($conn);
    if ($role === 'multimedia') {
        if ($hasStatus && $isModerator) {
            $metaCols = eventify_event_photos_metadata_select_sql($conn, true, 'p.');
            $sql = "
                SELECT p.id, p.file_path, p.status, p.uploaded_by, p.created_at, u.name AS uploader_name{$metaCols}
                FROM event_photos p
                LEFT JOIN users u ON u.id = p.uploaded_by
                WHERE p.event_id = ? AND p.session_id = ?
                ORDER BY p.created_at DESC, p.id DESC
            ";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('ii', $eventId, $sessionId);
        } elseif ($hasStatus) {
            $metaCols = eventify_event_photos_metadata_select_sql($conn);
            $sql = "
                SELECT id, file_path, status, uploaded_by, created_at{$metaCols}
                FROM event_photos
                WHERE event_id = ? AND session_id = ?
                  AND (status = 'published' OR uploaded_by = ?)
                ORDER BY created_at DESC, id DESC
            ";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('iii', $eventId, $sessionId, $userId);
        } else {
            $sql = "
                SELECT id, file_path, uploaded_by, created_at
                FROM event_photos
                WHERE event_id = ? AND session_id = ?
                ORDER BY created_at DESC, id DESC
            ";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('ii', $eventId, $sessionId);
        }
    } else {
        if ($hasStatus) {
            $metaCols = eventify_event_photos_metadata_select_sql($conn, false);
            $sql = "
                SELECT id, file_path, status, created_at{$metaCols}
                FROM event_photos
                WHERE event_id = ? AND session_id = ? AND status = 'published'
                ORDER BY created_at DESC, id DESC
            ";
        } else {
            $sql = "
                SELECT id, file_path, created_at
                FROM event_photos
                WHERE event_id = ? AND session_id = ?
                ORDER BY created_at DESC, id DESC
            ";
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $eventId, $sessionId);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return is_array($rows) ? $rows : [];
}

function eventify_multimedia_photo_redirect(int $eventId, int $sessionId, string $msg): void
{
    if ($sessionId > 0) {
        header(
            'Location: '
            . BASE_URL
            . '/event_activities.php?id='
            . $eventId
            . '&activity='
            . $sessionId
            . '&msg='
            . urlencode($msg)
        );
    } else {
        header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode($msg));
    }
    exit();
}
