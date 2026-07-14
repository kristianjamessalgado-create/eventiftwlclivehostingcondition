<?php

require_once __DIR__ . '/event_photos.php';
require_once __DIR__ . '/activity_logger.php';
require_once __DIR__ . '/notifications_service.php';

function eventify_users_ensure_multimedia_moderator_column(mysqli $conn): bool
{
    try {
        $c = $conn->query("SHOW COLUMNS FROM users LIKE 'is_multimedia_moderator'");
        if ($c && $c->num_rows > 0) {
            return true;
        }
        return (bool) $conn->query(
            "ALTER TABLE users ADD COLUMN is_multimedia_moderator TINYINT(1) NOT NULL DEFAULT 0 AFTER role"
        );
    } catch (Throwable $e) {
        return false;
    }
}

function eventify_user_is_multimedia_moderator(mysqli $conn, int $userId): bool
{
    if ($userId < 1) {
        return false;
    }
    eventify_users_ensure_multimedia_moderator_column($conn);
    $stmt = $conn->prepare(
        "SELECT is_multimedia_moderator FROM users WHERE id = ? AND role = 'multimedia' AND status = 'active' LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool) ((int) ($row['is_multimedia_moderator'] ?? 0) === 1);
}

/** @return list<array<string, mixed>> */
function eventify_load_pending_photos_for_moderator(mysqli $conn, int $limit = 80): array
{
    if (!eventify_event_photos_has_status($conn)) {
        return [];
    }
    eventify_event_photos_ensure_session_column($conn);
    eventify_event_photos_ensure_metadata_columns($conn);
    $limit = max(1, min(200, $limit));
    $metaCols = eventify_event_photos_metadata_select_sql($conn, false, 'p.');
    $sql = "
        SELECT p.id, p.event_id, p.session_id, p.file_path, p.uploaded_by, p.created_at{$metaCols},
               e.title AS event_title,
               u.name AS uploader_name,
               s.title AS session_title
        FROM event_photos p
        INNER JOIN events e ON e.id = p.event_id
        INNER JOIN users u ON u.id = p.uploaded_by
        LEFT JOIN event_day_sessions s ON s.id = p.session_id
        WHERE p.status = 'draft'
        ORDER BY p.created_at ASC, p.id ASC
        LIMIT ?
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return is_array($rows) ? $rows : [];
}

function eventify_count_pending_photos(mysqli $conn): int
{
    if (!eventify_event_photos_has_status($conn)) {
        return 0;
    }
    $res = $conn->query("SELECT COUNT(*) AS c FROM event_photos WHERE status = 'draft'");
    if (!$res || !($row = $res->fetch_assoc())) {
        return 0;
    }
    return (int) ($row['c'] ?? 0);
}

/** @return list<string> */
function eventify_photo_activity_actions(): array
{
    return ['photo_uploaded', 'photo_approved', 'photo_rejected', 'photo_bulk_approved', 'photo_bulk_rejected', 'photo_deleted'];
}

function eventify_photo_activity_label(string $action): string
{
    $map = [
        'photo_uploaded' => 'Photo uploaded',
        'photo_approved' => 'Photo approved',
        'photo_rejected' => 'Photo rejected',
        'photo_bulk_approved' => 'Bulk approved',
        'photo_bulk_rejected' => 'Bulk rejected',
        'photo_deleted' => 'Photo deleted',
    ];
    return $map[$action] ?? ucwords(str_replace('_', ' ', $action));
}

function eventify_photo_activity_context_suffix(string $eventTitle, string $sessionTitle = ''): string
{
    $eventLabel = $eventTitle !== '' ? '"' . $eventTitle . '"' : 'an event';
    if ($sessionTitle !== '') {
        return ' for ' . $eventLabel . ' · activity "' . $sessionTitle . '"';
    }
    return ' for ' . $eventLabel;
}

function eventify_moderator_display_name(mysqli $conn, int $userId): string
{
    if ($userId < 1) {
        return 'Moderator';
    }
    $stmt = $conn->prepare('SELECT name, user_id FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 'Moderator';
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return 'Moderator';
    }
    $name = trim((string) ($row['name'] ?? ''));
    $uid = trim((string) ($row['user_id'] ?? ''));
    if ($name !== '' && $uid !== '') {
        return $name . ' (' . $uid . ')';
    }
    return $name !== '' ? $name : 'Moderator';
}

function eventify_log_photo_activity(
    mysqli $conn,
    ?int $actorId,
    ?string $actorRole,
    string $action,
    string $targetType,
    ?int $targetId,
    string $details
): void {
    if (!in_array($action, eventify_photo_activity_actions(), true)) {
        return;
    }
    log_activity($conn, $actorId, $actorRole, $action, $targetType, $targetId, $details);
}

/** @return list<array<string, mixed>> */
function eventify_load_multimedia_photo_activity_logs(mysqli $conn, int $limit = 50): array
{
    $actions = eventify_photo_activity_actions();
    $limit = max(1, min(100, $limit));
    $escaped = array_map(static function ($action) use ($conn) {
        return "'" . $conn->real_escape_string($action) . "'";
    }, $actions);
    $in = implode(',', $escaped);
    $sql = "
        SELECT l.id, l.actor_id, l.actor_role, l.action, l.target_type, l.target_id, l.details, l.created_at,
               u.name AS actor_name, u.user_id AS actor_user_id
        FROM activity_logs l
        LEFT JOIN users u ON l.actor_id = u.id
        WHERE l.action IN ($in)
        ORDER BY l.created_at DESC, l.id DESC
        LIMIT $limit
    ";
    $res = $conn->query($sql);
    if (!$res) {
        return [];
    }
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    return is_array($rows) ? $rows : [];
}

/** @return array{uploader_id: int, event_id: int, event_title: string, session_title: string, uploader_name: string}|null */
function eventify_photo_moderation_context(mysqli $conn, int $photoId): ?array
{
    if ($photoId < 1) {
        return null;
    }
    eventify_event_photos_ensure_session_column($conn);
    $sql = "
        SELECT p.uploaded_by, p.event_id, e.title AS event_title, s.title AS session_title,
               u.name AS uploader_name
        FROM event_photos p
        INNER JOIN events e ON e.id = p.event_id
        INNER JOIN users u ON u.id = p.uploaded_by
        LEFT JOIN event_day_sessions s ON s.id = p.session_id
        WHERE p.id = ? AND p.status = 'draft'
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $photoId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    return [
        'uploader_id' => (int) ($row['uploaded_by'] ?? 0),
        'event_id' => (int) ($row['event_id'] ?? 0),
        'event_title' => trim((string) ($row['event_title'] ?? '')),
        'session_title' => trim((string) ($row['session_title'] ?? '')),
        'uploader_name' => trim((string) ($row['uploader_name'] ?? '')),
    ];
}

/** @return array{title: string, message: string, type: string} */
function eventify_photo_moderation_notification_copy(
    string $action,
    string $eventTitle,
    string $sessionTitle,
    int $count = 1,
    string $rejectReason = ''
): array {
    $eventLabel = $eventTitle !== '' ? $eventTitle : 'an event';
    $activitySuffix = $sessionTitle !== '' ? ' for activity "' . $sessionTitle . '"' : '';
    $approved = $action === 'approved';
    $reasonSuffix = '';
    if (!$approved && trim($rejectReason) !== '') {
        $reasonSuffix = ' Reason: ' . trim($rejectReason);
    }

    if ($approved) {
        $title = $count > 1 ? 'Photos approved' : 'Photo approved';
        if ($count > 1) {
            $message = 'Your moderator approved ' . $count . ' of your photos for "' . $eventLabel . '"' . $activitySuffix . '. They are now visible to students.';
        } else {
            $message = 'Your photo for "' . $eventLabel . '"' . $activitySuffix . ' was approved and is now visible to students.';
        }
        return ['title' => $title, 'message' => $message, 'type' => 'photo_approved'];
    }

    $title = $count > 1 ? 'Photos rejected' : 'Photo rejected';
    if ($count > 1) {
        $message = 'Your moderator rejected ' . $count . ' of your photos for "' . $eventLabel . '"' . $activitySuffix . '. They will not appear for students.' . $reasonSuffix;
    } else {
        $message = 'Your photo for "' . $eventLabel . '"' . $activitySuffix . ' was rejected and will not appear for students.' . $reasonSuffix;
    }
    return ['title' => $title, 'message' => $message, 'type' => 'photo_rejected'];
}

function eventify_notify_multimedia_photo_moderation(
    mysqli $conn,
    int $uploaderId,
    int $eventId,
    string $action,
    string $eventTitle = '',
    string $sessionTitle = '',
    int $count = 1,
    string $rejectReason = ''
): void {
    if ($uploaderId < 1 || $eventId < 1 || !in_array($action, ['approved', 'rejected'], true)) {
        return;
    }
    $count = max(1, $count);
    $copy = eventify_photo_moderation_notification_copy($action, $eventTitle, $sessionTitle, $count, $rejectReason);
    eventify_insert_user_notification(
        $conn,
        $uploaderId,
        $copy['type'],
        $copy['title'],
        $copy['message'],
        $eventId
    );
}

/** @return list<int> */
function eventify_load_multimedia_moderator_ids(mysqli $conn): array
{
    eventify_users_ensure_multimedia_moderator_column($conn);
    $ids = [];
    try {
        $res = $conn->query(
            "SELECT id FROM users WHERE role = 'multimedia' AND is_multimedia_moderator = 1 AND status = 'active'"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
    } catch (Throwable $e) {
        return [];
    }
    return $ids;
}

/**
 * Notify photo moderators that a team member submitted draft photo(s) for review.
 */
function eventify_notify_moderators_photo_pending_upload(
    mysqli $conn,
    int $uploaderId,
    int $eventId,
    string $eventTitle = '',
    string $sessionTitle = '',
    int $count = 1
): void {
    if ($eventId < 1 || $count < 1) {
        return;
    }
    $moderatorIds = eventify_load_multimedia_moderator_ids($conn);
    if ($moderatorIds === []) {
        return;
    }

    $count = max(1, $count);
    $context = eventify_photo_activity_context_suffix($eventTitle, $sessionTitle);
    $uploaderName = eventify_moderator_display_name($conn, $uploaderId);
    $title = $count > 1 ? 'Photos awaiting approval' : 'Photo awaiting approval';
    if ($count > 1) {
        $message = $uploaderName . ' uploaded ' . $count . ' photos' . $context . '. Open Photo approvals to review them.';
    } else {
        $message = $uploaderName . ' uploaded a photo' . $context . '. Open Photo approvals to review it.';
    }
    $type = 'photo_pending_approval';

    foreach ($moderatorIds as $moderatorId) {
        if ($moderatorId < 1 || $moderatorId === $uploaderId) {
            continue;
        }
        eventify_insert_user_notification(
            $conn,
            $moderatorId,
            $type,
            $title,
            $message,
            $eventId
        );
    }
}

/**
 * Keep moderator bell notifications in sync with the pending photo queue.
 */
function eventify_sync_moderator_pending_photo_notifications(mysqli $conn, int $moderatorId): void
{
    if ($moderatorId < 1 || !eventify_user_is_multimedia_moderator($conn, $moderatorId)) {
        return;
    }
    if (!eventify_event_photos_has_status($conn)) {
        return;
    }

    $pending = eventify_count_pending_photos($conn);
    $type = 'photo_pending_approval';

    if ($pending < 1) {
        try {
            $clear = $conn->prepare(
                "UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND type = ? AND read_at IS NULL"
            );
            if ($clear) {
                $clear->bind_param('is', $moderatorId, $type);
                $clear->execute();
                $clear->close();
            }
        } catch (Throwable $e) {
            // ignore
        }
        return;
    }

    $title = $pending > 1 ? 'Photos awaiting approval' : 'Photo awaiting approval';
    $message = $pending > 1
        ? 'You have ' . $pending . ' photos waiting for review. Open Photo approvals to approve or reject them.'
        : 'You have 1 photo waiting for review. Open Photo approvals to approve or reject it.';

    try {
        $find = $conn->prepare(
            "SELECT id FROM notifications WHERE user_id = ? AND type = ? AND read_at IS NULL ORDER BY id DESC LIMIT 1"
        );
        if (!$find) {
            eventify_insert_user_notification($conn, $moderatorId, $type, $title, $message, null);
            return;
        }
        $find->bind_param('is', $moderatorId, $type);
        $find->execute();
        $row = $find->get_result()->fetch_assoc();
        $find->close();

        if ($row && !empty($row['id'])) {
            $nid = (int) $row['id'];
            $upd = $conn->prepare(
                'UPDATE notifications SET title = ?, message = ?, created_at = NOW() WHERE id = ? AND user_id = ?'
            );
            if ($upd) {
                $upd->bind_param('ssii', $title, $message, $nid, $moderatorId);
                $upd->execute();
                $upd->close();
            }
            return;
        }

        eventify_insert_user_notification($conn, $moderatorId, $type, $title, $message, null);
    } catch (Throwable $e) {
        eventify_insert_user_notification($conn, $moderatorId, $type, $title, $message, null);
    }
}

/**
 * Students who RSVP'd or checked in to an event — eligible for photo notifications.
 *
 * @return list<int>
 */
function eventify_student_event_photo_recipient_ids(mysqli $conn, int $eventId): array
{
    if ($eventId < 1) {
        return [];
    }
    $recipientIds = [];
    $sql = "
        SELECT DISTINCT u.id
        FROM users u
        WHERE u.role = 'student' AND u.status = 'active'
          AND (
                u.id IN (SELECT r.user_id FROM registrations r WHERE r.event_id = ?)
             OR u.id IN (SELECT a.user_id FROM attendance a WHERE a.event_id = ?)
          )
    ";
    $rStmt = $conn->prepare($sql);
    if ($rStmt) {
        $rStmt->bind_param('ii', $eventId, $eventId);
        $rStmt->execute();
        $res = $rStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $recipientIds[] = (int) ($row['id'] ?? 0);
        }
        $rStmt->close();
    }
    return array_values(array_unique(array_filter($recipientIds)));
}

function eventify_event_photos_were_announced_to_students(mysqli $conn, int $eventId): bool
{
    if ($eventId < 1) {
        return false;
    }
    try {
        $check = $conn->prepare(
            "SELECT 1 FROM notifications WHERE event_id = ? AND type IN ('event_photos_available', 'event_photos_updated') LIMIT 1"
        );
        if (!$check) {
            return false;
        }
        $check->bind_param('i', $eventId);
        $check->execute();
        $already = $check->get_result()->fetch_row();
        $check->close();
        return (bool) $already;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Notify students (registered or attended) when photos are published.
 * First publish uses "photos available"; later batches use "new photo(s) added".
 *
 * @return int Number of notifications created.
 */
function eventify_notify_students_event_photos_published(mysqli $conn, int $eventId, int $photoCount = 1): int
{
    if ($eventId < 1) {
        return 0;
    }
    $photoCount = max(1, $photoCount);
    $recipientIds = eventify_student_event_photo_recipient_ids($conn, $eventId);
    if ($recipientIds === []) {
        return 0;
    }

    $eventTitle = '';
    $evStmt = $conn->prepare('SELECT title FROM events WHERE id = ? LIMIT 1');
    if ($evStmt) {
        $evStmt->bind_param('i', $eventId);
        $evStmt->execute();
        $evRow = $evStmt->get_result()->fetch_assoc();
        $evStmt->close();
        $eventTitle = trim((string) ($evRow['title'] ?? ''));
    }
    $eventLabel = $eventTitle !== '' ? $eventTitle : 'an event';

    $firstAnnouncement = !eventify_event_photos_were_announced_to_students($conn, $eventId);
    if ($firstAnnouncement) {
        $type = 'event_photos_available';
        $notifTitle = 'Event photos available';
        $message = 'Photos from "' . $eventLabel . '" are now available to view.';
    } else {
        $type = 'event_photos_updated';
        $notifTitle = $photoCount > 1 ? 'New photos added' : 'New photo added';
        if ($photoCount > 1) {
            $message = $photoCount . ' new photos were added to "' . $eventLabel . '". View the gallery.';
        } else {
            $message = 'A new photo was added to "' . $eventLabel . '". View the gallery.';
        }
    }

    try {
        $ins = $conn->prepare('INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, ?, ?, ?, ?)');
        if (!$ins) {
            return 0;
        }
        $count = 0;
        foreach ($recipientIds as $rid) {
            if (eventify_insert_user_notification($conn, $rid, $type, $notifTitle, $message, $eventId)) {
                $count++;
            }
        }
        return $count;
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * @deprecated Use eventify_notify_students_event_photos_published().
 */
function eventify_notify_students_event_photos_available(mysqli $conn, int $eventId): int
{
    return eventify_notify_students_event_photos_published($conn, $eventId, 1);
}

function eventify_moderator_approve_photo(
    mysqli $conn,
    int $photoId,
    int $moderatorId,
    bool $notifyUploader = true,
    bool $notifyStudents = true,
    bool $logActivity = true
): bool {
    if ($photoId < 1 || !eventify_event_photos_has_status($conn)) {
        return false;
    }
    $context = eventify_photo_moderation_context($conn, $photoId);
    $stmt = $conn->prepare(
        "UPDATE event_photos SET status = 'published', published_at = NOW(), reject_reason = NULL WHERE id = ? AND status = 'draft'"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $photoId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    if ($ok && $context) {
        if ($notifyUploader) {
            eventify_notify_multimedia_photo_moderation(
                $conn,
                (int) $context['uploader_id'],
                (int) $context['event_id'],
                'approved',
                (string) $context['event_title'],
                (string) $context['session_title']
            );
        }
        if ($logActivity && $moderatorId > 0) {
            $moderatorName = eventify_moderator_display_name($conn, $moderatorId);
            $uploaderName = (string) ($context['uploader_name'] ?? '') ?: 'Multimedia user';
            $details = $moderatorName . ' approved a photo uploaded by ' . $uploaderName
                . eventify_photo_activity_context_suffix((string) $context['event_title'], (string) $context['session_title']);
            eventify_log_photo_activity($conn, $moderatorId, 'multimedia', 'photo_approved', 'event_photo', $photoId, $details);
        }
        if ($notifyStudents) {
            eventify_notify_students_event_photos_published($conn, (int) $context['event_id'], 1);
        }
    }
    return $ok;
}

function eventify_moderator_reject_photo(
    mysqli $conn,
    int $photoId,
    int $moderatorId = 0,
    string $reason = '',
    bool $notifyUploader = true,
    bool $logActivity = true
): bool {
    if ($photoId < 1 || !eventify_event_photos_has_status($conn)) {
        return false;
    }
    $reason = eventify_sanitize_photo_caption($reason, 500);
    $context = eventify_photo_moderation_context($conn, $photoId);
    if (eventify_event_photos_has_reject_reason_column($conn)) {
        $stmt = $conn->prepare(
            "UPDATE event_photos SET status = 'rejected', published_at = NULL, reject_reason = ? WHERE id = ? AND status = 'draft'"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $reason, $photoId);
    } else {
        $stmt = $conn->prepare(
            "UPDATE event_photos SET status = 'rejected', published_at = NULL WHERE id = ? AND status = 'draft'"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $photoId);
    }
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    if ($ok && $context) {
        if ($notifyUploader) {
            eventify_notify_multimedia_photo_moderation(
                $conn,
                (int) $context['uploader_id'],
                (int) $context['event_id'],
                'rejected',
                (string) $context['event_title'],
                (string) $context['session_title'],
                1,
                $reason
            );
        }
        if ($logActivity && $moderatorId > 0) {
            $moderatorName = eventify_moderator_display_name($conn, $moderatorId);
            $uploaderName = (string) ($context['uploader_name'] ?? '') ?: 'Multimedia user';
            $details = $moderatorName . ' rejected a photo uploaded by ' . $uploaderName
                . eventify_photo_activity_context_suffix((string) $context['event_title'], (string) $context['session_title']);
            if ($reason !== '') {
                $details .= ' — reason: ' . $reason;
            }
            eventify_log_photo_activity($conn, $moderatorId, 'multimedia', 'photo_rejected', 'event_photo', $photoId, $details);
        }
    }
    return $ok;
}

/**
 * Build the context for any photo regardless of status (used for moderator deletion).
 *
 * @return array{photo_id:int, uploader_id:int, event_id:int, session_id:int, file_path:string, event_title:string, session_title:string, uploader_name:string}|null
 */
function eventify_photo_delete_context(mysqli $conn, int $photoId): ?array
{
    if ($photoId < 1) {
        return null;
    }
    eventify_event_photos_ensure_session_column($conn);
    $sql = "
        SELECT p.id, p.uploaded_by, p.event_id, p.session_id, p.file_path,
               e.title AS event_title, s.title AS session_title, u.name AS uploader_name
        FROM event_photos p
        INNER JOIN events e ON e.id = p.event_id
        LEFT JOIN users u ON u.id = p.uploaded_by
        LEFT JOIN event_day_sessions s ON s.id = p.session_id
        WHERE p.id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $photoId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    return [
        'photo_id' => (int) ($row['id'] ?? 0),
        'uploader_id' => (int) ($row['uploaded_by'] ?? 0),
        'event_id' => (int) ($row['event_id'] ?? 0),
        'session_id' => (int) ($row['session_id'] ?? 0),
        'file_path' => (string) ($row['file_path'] ?? ''),
        'event_title' => trim((string) ($row['event_title'] ?? '')),
        'session_title' => trim((string) ($row['session_title'] ?? '')),
        'uploader_name' => trim((string) ($row['uploader_name'] ?? '')),
    ];
}

/**
 * Notify a photo's uploader that a moderator deleted their photo, including the reason.
 */
function eventify_notify_multimedia_photo_deletion(
    mysqli $conn,
    int $uploaderId,
    int $eventId,
    string $eventTitle,
    string $sessionTitle,
    string $reason
): void {
    if ($uploaderId < 1 || $eventId < 1) {
        return;
    }
    $eventLabel = $eventTitle !== '' ? $eventTitle : 'an event';
    $activitySuffix = $sessionTitle !== '' ? ' for activity "' . $sessionTitle . '"' : '';
    $reason = trim($reason);
    $reasonText = $reason !== '' ? ' Reason: ' . $reason : '';
    $title = 'Photo deleted';
    $message = 'Your photo for "' . $eventLabel . '"' . $activitySuffix
        . ' was deleted by a moderator.' . $reasonText;
    try {
        $ins = $conn->prepare(
            'INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, ?, ?, ?, ?)'
        );
        if (!$ins) {
            return;
        }
        $type = 'photo_deleted';
        $ins->bind_param('isssi', $uploaderId, $type, $title, $message, $eventId);
        $ins->execute();
        $ins->close();
    } catch (Throwable $e) {
        // ignore if notifications table is unavailable
    }
}

/**
 * Delete a photo as a moderator, removing the file + row, notifying the uploader
 * (with the reason) and logging the action.
 *
 * @return array{ok:bool, error:string}
 */
function eventify_moderator_delete_photo(mysqli $conn, int $photoId, int $moderatorId, string $reason, string $baseDir): array
{
    $reason = trim($reason);
    if ($photoId < 1) {
        return ['ok' => false, 'error' => 'Invalid photo.'];
    }
    if (!eventify_user_is_multimedia_moderator($conn, $moderatorId)) {
        return ['ok' => false, 'error' => 'Only moderators can delete other members\' photos.'];
    }
    $context = eventify_photo_delete_context($conn, $photoId);
    if (!$context) {
        return ['ok' => false, 'error' => 'Photo not found.'];
    }
    $isOwnPhoto = ((int) $context['uploader_id'] === $moderatorId);
    if (!$isOwnPhoto && $reason === '') {
        return ['ok' => false, 'error' => 'Please provide a reason for deleting this photo.'];
    }

    // Remove the file from disk first (best effort).
    $relPath = (string) $context['file_path'];
    if ($relPath !== '') {
        $fullPath = rtrim($baseDir, '/\\') . '/' . ltrim($relPath, '/\\');
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    $del = $conn->prepare('DELETE FROM event_photos WHERE id = ?');
    if (!$del) {
        return ['ok' => false, 'error' => 'Database error: could not delete photo.'];
    }
    $del->bind_param('i', $photoId);
    $del->execute();
    $ok = $del->affected_rows > 0;
    $del->close();
    if (!$ok) {
        return ['ok' => false, 'error' => 'Photo could not be deleted.'];
    }

    // Notify the uploader (only if someone else's photo).
    if (!$isOwnPhoto && (int) $context['uploader_id'] > 0) {
        eventify_notify_multimedia_photo_deletion(
            $conn,
            (int) $context['uploader_id'],
            (int) $context['event_id'],
            (string) $context['event_title'],
            (string) $context['session_title'],
            $reason
        );
    }

    // Log the moderator action.
    $moderatorName = eventify_moderator_display_name($conn, $moderatorId);
    $uploaderName = (string) ($context['uploader_name'] ?? '') ?: 'Multimedia user';
    $details = $moderatorName . ' deleted a photo uploaded by ' . ($isOwnPhoto ? 'themselves' : $uploaderName)
        . eventify_photo_activity_context_suffix((string) $context['event_title'], (string) $context['session_title']);
    if (!$isOwnPhoto && $reason !== '') {
        $details .= ' — reason: ' . $reason;
    }
    eventify_log_photo_activity($conn, $moderatorId, 'multimedia', 'photo_deleted', 'event_photo', $photoId, $details);

    return ['ok' => true, 'error' => ''];
}

/** @return list<array{uploader_id: int, event_id: int, event_title: string, session_title: string}> */
function eventify_load_draft_photos_for_moderation_batch(mysqli $conn, int $eventId, int $sessionId): array
{
    if ($eventId < 1 || !eventify_event_photos_has_status($conn)) {
        return [];
    }
    eventify_event_photos_ensure_session_column($conn);
    if ($sessionId > 0) {
        $sql = "
            SELECT p.uploaded_by, p.event_id, e.title AS event_title, s.title AS session_title
            FROM event_photos p
            INNER JOIN events e ON e.id = p.event_id
            LEFT JOIN event_day_sessions s ON s.id = p.session_id
            WHERE p.event_id = ? AND p.session_id = ? AND p.status = 'draft'
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $eventId, $sessionId);
    } else {
        $sql = "
            SELECT p.uploaded_by, p.event_id, e.title AS event_title, s.title AS session_title
            FROM event_photos p
            INNER JOIN events e ON e.id = p.event_id
            LEFT JOIN event_day_sessions s ON s.id = p.session_id
            WHERE p.event_id = ? AND p.session_id IS NULL AND p.status = 'draft'
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $eventId);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'uploader_id' => (int) ($row['uploaded_by'] ?? 0),
            'event_id' => (int) ($row['event_id'] ?? 0),
            'event_title' => trim((string) ($row['event_title'] ?? '')),
            'session_title' => trim((string) ($row['session_title'] ?? '')),
        ];
    }
    return $out;
}

function eventify_moderator_notify_bulk_photo_approvals(mysqli $conn, array $approvedRows): void
{
    if ($approvedRows === []) {
        return;
    }
    /** @var array<string, array{uploader_id: int, event_id: int, event_title: string, session_title: string, count: int}> $groups */
    $groups = [];
    foreach ($approvedRows as $row) {
        $uploaderId = (int) ($row['uploader_id'] ?? 0);
        $eventId = (int) ($row['event_id'] ?? 0);
        if ($uploaderId < 1 || $eventId < 1) {
            continue;
        }
        $sessionTitle = (string) ($row['session_title'] ?? '');
        $key = $uploaderId . ':' . $eventId . ':' . $sessionTitle;
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'uploader_id' => $uploaderId,
                'event_id' => $eventId,
                'event_title' => (string) ($row['event_title'] ?? ''),
                'session_title' => $sessionTitle,
                'count' => 0,
            ];
        }
        $groups[$key]['count']++;
    }
    foreach ($groups as $group) {
        eventify_notify_multimedia_photo_moderation(
            $conn,
            (int) $group['uploader_id'],
            (int) $group['event_id'],
            'approved',
            (string) $group['event_title'],
            (string) $group['session_title'],
            (int) $group['count']
        );
    }
}

function eventify_moderator_approve_event_drafts(mysqli $conn, int $eventId, int $sessionId, int $moderatorId = 0): int
{
    if ($eventId < 1 || !eventify_event_photos_has_status($conn)) {
        return 0;
    }
    $pendingRows = eventify_load_draft_photos_for_moderation_batch($conn, $eventId, $sessionId);
    if ($pendingRows === []) {
        return 0;
    }
    eventify_event_photos_ensure_session_column($conn);
    if ($sessionId > 0) {
        $stmt = $conn->prepare(
            "UPDATE event_photos SET status = 'published', published_at = NOW() WHERE event_id = ? AND session_id = ? AND status = 'draft'"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('ii', $eventId, $sessionId);
    } else {
        $stmt = $conn->prepare(
            "UPDATE event_photos SET status = 'published', published_at = NOW() WHERE event_id = ? AND session_id IS NULL AND status = 'draft'"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $eventId);
    }
    $stmt->execute();
    $n = (int) $stmt->affected_rows;
    $stmt->close();
    if ($n > 0) {
        eventify_moderator_notify_bulk_photo_approvals($conn, $pendingRows);
        if ($moderatorId > 0) {
            $eventTitle = (string) ($pendingRows[0]['event_title'] ?? '');
            $sessionTitle = $sessionId > 0 ? (string) ($pendingRows[0]['session_title'] ?? '') : '';
            $moderatorName = eventify_moderator_display_name($conn, $moderatorId);
            $details = $moderatorName . ' approved ' . $n . ' pending photo(s)'
                . eventify_photo_activity_context_suffix($eventTitle, $sessionTitle);
            eventify_log_photo_activity($conn, $moderatorId, 'multimedia', 'photo_bulk_approved', 'event', $eventId, $details);
        }
        eventify_notify_students_event_photos_published($conn, $eventId, $n);
    }
    return $n;
}

function eventify_moderator_notify_bulk_photo_rejections(mysqli $conn, array $rejectedRows, string $reason = ''): void
{
    if ($rejectedRows === []) {
        return;
    }
    /** @var array<string, array{uploader_id: int, event_id: int, event_title: string, session_title: string, count: int}> $groups */
    $groups = [];
    foreach ($rejectedRows as $row) {
        $uploaderId = (int) ($row['uploader_id'] ?? 0);
        $eventId = (int) ($row['event_id'] ?? 0);
        if ($uploaderId < 1 || $eventId < 1) {
            continue;
        }
        $sessionTitle = (string) ($row['session_title'] ?? '');
        $key = $uploaderId . ':' . $eventId . ':' . $sessionTitle;
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'uploader_id' => $uploaderId,
                'event_id' => $eventId,
                'event_title' => (string) ($row['event_title'] ?? ''),
                'session_title' => $sessionTitle,
                'count' => 0,
            ];
        }
        $groups[$key]['count']++;
    }
    foreach ($groups as $group) {
        eventify_notify_multimedia_photo_moderation(
            $conn,
            (int) $group['uploader_id'],
            (int) $group['event_id'],
            'rejected',
            (string) $group['event_title'],
            (string) $group['session_title'],
            (int) $group['count'],
            $reason
        );
    }
}

/**
 * @param list<int> $photoIds
 * @return array{approved: int, rejected: int, skipped: int}
 */
function eventify_moderator_bulk_moderate_photos(
    mysqli $conn,
    array $photoIds,
    string $action,
    int $moderatorId,
    string $rejectReason = ''
): array {
    $result = ['approved' => 0, 'rejected' => 0, 'skipped' => 0];
    if ($moderatorId < 1 || !in_array($action, ['approve', 'reject'], true)) {
        return $result;
    }
    $photoIds = array_values(array_unique(array_filter(array_map('intval', $photoIds), static function ($id) {
        return $id > 0;
    })));
    if ($photoIds === []) {
        return $result;
    }

    $rejectReason = eventify_sanitize_photo_caption($rejectReason, 500);
    if ($action === 'reject' && $rejectReason === '') {
        return $result;
    }

    $approvedRows = [];
    $rejectedRows = [];

    foreach ($photoIds as $photoId) {
        if ($action === 'approve') {
            $context = eventify_photo_moderation_context($conn, $photoId);
            if (!$context) {
                $result['skipped']++;
                continue;
            }
            if (eventify_moderator_approve_photo($conn, $photoId, $moderatorId, false, false, false)) {
                $result['approved']++;
                $approvedRows[] = $context;
            } else {
                $result['skipped']++;
            }
            continue;
        }

        $context = eventify_photo_moderation_context($conn, $photoId);
        if (!$context) {
            $result['skipped']++;
            continue;
        }
        if (eventify_moderator_reject_photo($conn, $photoId, 0, $rejectReason, false, false)) {
            $result['rejected']++;
            $rejectedRows[] = $context;
        } else {
            $result['skipped']++;
        }
    }

    if ($action === 'approve' && $approvedRows !== []) {
        eventify_moderator_notify_bulk_photo_approvals($conn, $approvedRows);
        /** @var array<int, int> $eventPublishCounts */
        $eventPublishCounts = [];
        foreach ($approvedRows as $row) {
            $eid = (int) ($row['event_id'] ?? 0);
            if ($eid > 0) {
                $eventPublishCounts[$eid] = ($eventPublishCounts[$eid] ?? 0) + 1;
            }
        }
        foreach ($eventPublishCounts as $eid => $count) {
            eventify_notify_students_event_photos_published($conn, $eid, $count);
        }
        $moderatorName = eventify_moderator_display_name($conn, $moderatorId);
        $details = $moderatorName . ' approved ' . count($approvedRows) . ' pending photo(s) in bulk';
        eventify_log_photo_activity($conn, $moderatorId, 'multimedia', 'photo_bulk_approved', 'event_photo', null, $details);
        eventify_sync_moderator_pending_photo_notifications($conn, $moderatorId);
    }

    if ($action === 'reject' && $rejectedRows !== []) {
        eventify_moderator_notify_bulk_photo_rejections($conn, $rejectedRows, $rejectReason);
        $moderatorName = eventify_moderator_display_name($conn, $moderatorId);
        $details = $moderatorName . ' rejected ' . count($rejectedRows) . ' pending photo(s) in bulk';
        if ($rejectReason !== '') {
            $details .= ' — reason: ' . $rejectReason;
        }
        eventify_log_photo_activity($conn, $moderatorId, 'multimedia', 'photo_bulk_rejected', 'event_photo', null, $details);
        eventify_sync_moderator_pending_photo_notifications($conn, $moderatorId);
    }

    return $result;
}

function eventify_moderator_require(mysqli $conn, int $userId): void
{
    if (($_SESSION['role'] ?? '') !== 'multimedia' || !eventify_user_is_multimedia_moderator($conn, $userId)) {
        header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode('Only a multimedia moderator can approve or reject photos.'));
        exit();
    }
}
