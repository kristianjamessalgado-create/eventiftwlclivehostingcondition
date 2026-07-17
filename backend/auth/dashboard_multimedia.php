<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../config/departments.php';
require_once __DIR__ . '/../lib/event_status_auto.php';
require_once __DIR__ . '/../lib/event_day_sessions.php';
require_once __DIR__ . '/../lib/event_calendar.php';
require_once __DIR__ . '/../lib/event_photos.php';
require_once __DIR__ . '/../lib/multimedia_moderator.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'multimedia') {
    header("Location: " . BASE_URL . "/index.php?auth_modal=login");
    exit();
}

eventify_run_dashboard_maintenance($conn);
eventify_events_department_ensure_varchar($conn);
eventify_event_photos_ensure_metadata_columns($conn);

$hasMustChangePasswordColumn = false;
try {
    $cpCol = $conn->query("SHOW COLUMNS FROM users LIKE 'must_change_password'");
    $hasMustChangePasswordColumn = (bool)($cpCol && $cpCol->num_rows > 0);
} catch (Throwable $e) {
    $hasMustChangePasswordColumn = false;
}
if ($hasMustChangePasswordColumn) {
    $forceCp = $conn->prepare("SELECT must_change_password FROM users WHERE id = ? LIMIT 1");
    if ($forceCp) {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $forceCp->bind_param("i", $uid);
        $forceCp->execute();
        $cpRow = $forceCp->get_result()->fetch_assoc();
        $forceCp->close();
        if ((int)($cpRow['must_change_password'] ?? 0) === 1) {
            header("Location: " . BASE_URL . "/views/change_password.php?from=required&next=" . urlencode(BASE_URL . "/backend/auth/dashboard_multimedia.php"));
            exit();
        }
    }
}

$session_user_id = (int) $_SESSION['user_id'];

// Fetch user info (including department and profile picture)
$stmt = $conn->prepare("SELECT id, user_id, name, department, profile_picture, is_multimedia_moderator FROM users WHERE id = ?");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;
$stmt->close();

$user_name  = $user['name'] ?? 'Multimedia';
$user_department = $user['department'] ?? null;
$is_multimedia_moderator = eventify_user_is_multimedia_moderator($conn, $session_user_id);

// Feature flag: photo publishing workflow (status column on event_photos)
$photoStatusEnabled = false;
$chkCol = $conn->query("SHOW COLUMNS FROM event_photos LIKE 'status'");
if ($chkCol && $chkCol->num_rows > 0) {
    $photoStatusEnabled = true;
}

// Fetch all events (newest date first)
$events = [];
$uid = (int) $session_user_id;
$deptWhere = empty($user_department) ? '1=1' : eventify_department_match_sql('e.department');
$stEv = null;

if ($photoStatusEnabled) {
    $sqlEv = "
        SELECT e.id, e.title, e.date, e.end_date, e.location, e.department, e.status, e.registration_mode, e.start_time, e.end_time, e.description, e.created_at, e.checkin_token,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id AND p.status = 'published') AS photo_count,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id) AS total_photo_count,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id AND p.uploaded_by = {$uid}) AS my_photo_count,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id AND p.uploaded_by = {$uid} AND p.status = 'draft') AS my_draft_count,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id AND p.status = 'draft') AS pending_draft_count
        FROM events e
        WHERE e.title NOT LIKE 'sample%'
          AND LOWER(e.status) IN ('active', 'closed', 'completed')
          AND ({$deptWhere})
        ORDER BY e.date DESC, e.id DESC
    ";
} else {
    $sqlEv = "
        SELECT e.id, e.title, e.date, e.end_date, e.location, e.department, e.status, e.registration_mode, e.start_time, e.end_time, e.description, e.created_at, e.checkin_token,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id) AS photo_count,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id) AS total_photo_count,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id AND p.uploaded_by = {$uid}) AS my_photo_count,
               0 AS my_draft_count,
               0 AS pending_draft_count
        FROM events e
        WHERE e.title NOT LIKE 'sample%'
          AND LOWER(e.status) IN ('active', 'closed', 'completed')
          AND ({$deptWhere})
        ORDER BY e.date DESC, e.id DESC
    ";
}

if (empty($user_department)) {
    $res = $conn->query($sqlEv);
} else {
    $stEv = $conn->prepare($sqlEv);
    if ($stEv) {
        $stEv->bind_param('ss', $user_department, $user_department);
        $stEv->execute();
        $res = $stEv->get_result();
    } else {
        $res = false;
    }
}
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $events[] = $row;
    }
}
if ($stEv) {
    $stEv->close();
}

// Attach multi-day schedule info so calendar bars span the full event duration
if (function_exists('eventify_events_attach_schedule_dates')) {
    eventify_events_attach_schedule_dates($conn, $events);
}

// Fetch upcoming events (department-aware) for modal
$upcomingEvents = [];
$today = date('Y-m-d');
$deptUpSql = eventify_department_match_sql('department');
if (!empty($user_department)) {
    $stmtUp = $conn->prepare("SELECT id, title, description, date, location, department FROM events WHERE status = 'active' AND date >= ? AND {$deptUpSql} AND title NOT LIKE 'sample%' ORDER BY date ASC, id ASC LIMIT 12");
    if ($stmtUp) {
        $stmtUp->bind_param('sss', $today, $user_department, $user_department);
        if ($stmtUp->execute()) {
            $resUp = $stmtUp->get_result();
            if ($resUp) {
                $upcomingEvents = $resUp->fetch_all(MYSQLI_ASSOC);
            }
        }
        $stmtUp->close();
    }
} else {
    $stmtUp = $conn->prepare("SELECT id, title, description, date, location, department FROM events WHERE status = 'active' AND date >= ? AND title NOT LIKE 'sample%' ORDER BY date ASC, id ASC LIMIT 12");
    if ($stmtUp) {
        $stmtUp->bind_param("s", $today);
        if ($stmtUp->execute()) {
            $resUp = $stmtUp->get_result();
            if ($resUp) $upcomingEvents = $resUp->fetch_all(MYSQLI_ASSOC);
        }
        $stmtUp->close();
    }
}

// Fetch photos per event so we can show thumbnails / gallery
$photosByEvent = [];
if (!empty($events)) {
    $ids = array_column($events, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $photoMetaCols = eventify_event_photos_metadata_select_sql($conn);
    $photoStatusCol = eventify_event_photos_has_status($conn) ? ', status' : '';
    $sql = "SELECT id, event_id, file_path, uploaded_by{$photoMetaCols}{$photoStatusCol} FROM event_photos WHERE event_id IN ($placeholders) ORDER BY created_at DESC, id DESC";
    $stmtPhotos = $conn->prepare($sql);
    if ($stmtPhotos) {
        $stmtPhotos->bind_param($types, ...$ids);
        if ($stmtPhotos->execute()) {
            $resultPhotos = $stmtPhotos->get_result();
            if ($resultPhotos) {
                while ($row = $resultPhotos->fetch_assoc()) {
                    $eid = (int)$row['event_id'];
                    if (!isset($photosByEvent[$eid])) {
                        $photosByEvent[$eid] = [];
                    }
                    $photosByEvent[$eid][] = [
                        'id' => (int)$row['id'],
                        'file_path' => $row['file_path'],
                        'uploaded_by' => (int)($row['uploaded_by'] ?? 0),
                        'caption' => (string)($row['caption'] ?? ''),
                        'credit_line' => (string)($row['credit_line'] ?? ''),
                        'reject_reason' => (string)($row['reject_reason'] ?? ''),
                        'status' => (string)($row['status'] ?? ''),
                    ];
                }
            }
        }
        $stmtPhotos->close();
    }
}

$msg = $_GET['msg'] ?? '';

if ($is_multimedia_moderator) {
    eventify_sync_moderator_pending_photo_notifications($conn, $session_user_id);
}

$multimedia_notifications = [];
$multimedia_unread_count = 0;
try {
    $stmtN = $conn->prepare("SELECT id, type, title, message, event_id, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 40");
    if ($stmtN) {
        $stmtN->bind_param('i', $session_user_id);
        if ($stmtN->execute()) {
            $rn = $stmtN->get_result();
            if ($rn) {
                $multimedia_notifications = $rn->fetch_all(MYSQLI_ASSOC);
            }
        }
        $stmtN->close();
    }
    foreach ($multimedia_notifications as $n) {
        if (empty($n['read_at'])) {
            $multimedia_unread_count++;
        }
    }
} catch (Throwable $e) {
    $multimedia_notifications = [];
    $multimedia_unread_count = 0;
}

$multimedia_notif_dropdown = array_values(array_filter($multimedia_notifications, static function ($n) {
    return empty($n['read_at']);
}));

$activities_hub_events = [];
try {
    $activities_hub_events = eventify_load_activities_hub_picker_events(
        $conn,
        $session_user_id,
        'multimedia',
        $user_department
    );
} catch (Throwable $e) {
    $activities_hub_events = [];
}

$pending_photo_count = $is_multimedia_moderator ? eventify_count_pending_photos($conn) : 0;
$pending_photos_queue = $is_multimedia_moderator ? eventify_load_pending_photos_for_moderator($conn, 60) : [];
$multimedia_photo_activity_logs = $is_multimedia_moderator ? eventify_load_multimedia_photo_activity_logs($conn, 50) : [];

// Per-image upload limit (admin "Max Upload Size (MB)" setting, fallback 10MB),
// clamped to whatever PHP's upload_max_filesize permits.
$max_upload_mb = 10;
if ($resUp = @$conn->query("SELECT max_upload_size_mb FROM admin_settings WHERE max_upload_size_mb IS NOT NULL ORDER BY updated_at DESC LIMIT 1")) {
    if ($rowUp = $resUp->fetch_assoc()) {
        $max_upload_mb = max(1, (int) $rowUp['max_upload_size_mb']);
    }
    $resUp->free();
}
$iniUp = trim((string) ini_get('upload_max_filesize'));
if ($iniUp !== '') {
    $iniUpBytes = (int) $iniUp;
    switch (strtolower(substr($iniUp, -1))) {
        case 'g': $iniUpBytes *= 1024 * 1024 * 1024; break;
        case 'm': $iniUpBytes *= 1024 * 1024; break;
        case 'k': $iniUpBytes *= 1024; break;
    }
    $iniUpMb = (int) floor($iniUpBytes / (1024 * 1024));
    if ($iniUpMb > 0) {
        $max_upload_mb = min($max_upload_mb, $iniUpMb);
    }
}

$eventActivitiesByEvent = [];
$eventSessionPhotoStats = [];
eventify_event_day_sessions_ensure_enhanced($conn);
foreach ($events as $ev) {
    $eid = (int) ($ev['id'] ?? 0);
    if ($eid < 1) {
        continue;
    }
    $sessions = eventify_load_event_day_sessions($conn, $eid, null, null);
    usort($sessions, static function ($a, $b) {
        $da = substr((string) ($a['schedule_date'] ?? ''), 0, 10);
        $db = substr((string) ($b['schedule_date'] ?? ''), 0, 10);
        if ($da !== $db) {
            return strcmp($da, $db);
        }
        return strcmp((string) ($a['start_time'] ?? ''), (string) ($b['start_time'] ?? ''));
    });
    $eventActivitiesByEvent[$eid] = array_values(array_map(static function ($s) {
        return [
            'id' => (int) ($s['id'] ?? 0),
            'title' => (string) ($s['title'] ?? ''),
            'schedule_date' => substr((string) ($s['schedule_date'] ?? ''), 0, 10),
            'start_time' => (string) ($s['start_time'] ?? ''),
            'end_time' => (string) ($s['end_time'] ?? ''),
            'category' => (string) ($s['category'] ?? ''),
        ];
    }, $sessions));
    $eventSessionPhotoStats[$eid] = eventify_load_event_session_photo_stats($conn, $eid, $uid);
}

$mm_panel = (string) ($_GET['panel'] ?? '');
$mm_events_panel_open = ($mm_panel === 'events');
$mm_upcoming_panel_open = ($mm_panel === 'upcoming');
$mm_photo_approvals_panel_open = $is_multimedia_moderator && ($mm_panel === 'photo_approvals');
$mm_photo_activity_panel_open = $is_multimedia_moderator && ($mm_panel === 'photo_activity');
$mm_dashboard_panel_open = $mm_events_panel_open
    || $mm_upcoming_panel_open
    || $mm_photo_approvals_panel_open
    || $mm_photo_activity_panel_open;
$mm_events_count = count($events);
$mm_upcoming_count = count($upcomingEvents);

$conn->close();

if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

include __DIR__ . '/../../views/dashboard_multimedia.php';
