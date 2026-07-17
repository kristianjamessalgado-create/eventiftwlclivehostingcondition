<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../config/departments.php';
if (is_file(__DIR__ . '/../../config/student_sections.php')) {
    require_once __DIR__ . '/../../config/student_sections.php';
}
require_once __DIR__ . '/../../config/organizer_departments.php';
require_once __DIR__ . '/../lib/web_push.php';
require_once __DIR__ . '/../lib/event_calendar.php';

$sessionRole = (string) ($_SESSION['role'] ?? '');
$isOrganizerEditor = $sessionRole === 'organizer';
$isAdminEditor = in_array($sessionRole, ['admin', 'super_admin'], true);

if (!isset($_SESSION['user_id']) || (!$isOrganizerEditor && !$isAdminEditor)) {
    header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Access denied"));
    exit();
}

$session_user_id = (int) $_SESSION['user_id'];
$error   = '';
$success = '';
$eventsHasMaxCapacity = false;

$editHomeUrl = $isAdminEditor
    ? (
        $sessionRole === 'super_admin'
            ? BASE_URL . '/backend/super_admin/dashboardsuperadmin.php'
            : BASE_URL . '/backend/admin/dashboard.php?panel=events'
    )
    : BASE_URL . '/backend/auth/dashboardorganizer.php';

function eventify_edit_event_go_home(string $homeUrl, string $message, bool $isError = false): void
{
    $param = $isError ? 'error' : ($homeUrl !== '' && strpos($homeUrl, 'dashboardorganizer') !== false ? 'msg' : 'success');
    $sep = strpos($homeUrl, '?') !== false ? '&' : '?';
    header('Location: ' . $homeUrl . $sep . $param . '=' . urlencode($message));
    exit();
}

try {
    $mcCol = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'max_capacity'");
    if ($mcCol && $mcCol->num_rows >= 1) {
        $eventsHasMaxCapacity = true;
    }
} catch (Throwable $e) {
    $eventsHasMaxCapacity = false;
}

// Get event ID
$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($event_id <= 0) {
    eventify_edit_event_go_home($editHomeUrl, 'Invalid event ID.', true);
}

$eventsHasEndTimeNa = eventify_events_has_end_time_na($conn);

// Fetch event — must be owned by this user (organizer or admin who assigned it to themselves)
$selectCols = 'id, title, description, date, location, department, status, organizer_id, start_time, end_time';
if ($eventsHasMaxCapacity) {
    $selectCols .= ', max_capacity';
}
if ($eventsHasEndTimeNa) {
    $selectCols .= ', end_time_na';
}
if (function_exists('eventify_events_ensure_target_sections')) {
    eventify_events_ensure_target_sections($conn);
}
if (function_exists('eventify_events_has_target_sections') && eventify_events_has_target_sections($conn)) {
    $selectCols .= ', target_sections';
}
$stmt = $conn->prepare("SELECT {$selectCols} FROM events WHERE id = ? AND organizer_id = ?");
if (!$stmt) {
    eventify_edit_event_go_home($editHomeUrl, 'Database error.', true);
}
$stmt->bind_param("ii", $event_id, $session_user_id);
$stmt->execute();
$result = $stmt->get_result();
$event  = $result->fetch_assoc();
$stmt->close();

if (!$event) {
    eventify_edit_event_go_home($editHomeUrl, 'Event not found or you do not have permission to edit it.', true);
}

eventify_events_department_ensure_varchar($conn);
eventify_sections_schema_ensure($conn);

if (!array_key_exists('max_capacity', $event)) {
    $event['max_capacity'] = null;
}
if (!array_key_exists('end_time_na', $event)) {
    $event['end_time_na'] = 0;
}

$eventStartTimeValue = '';
$rawStart = trim((string) ($event['start_time'] ?? ''));
if ($rawStart !== '') {
    $eventStartTimeValue = strlen($rawStart) >= 5 ? substr($rawStart, 0, 5) : $rawStart;
}
$eventEndTimeValue = '';
$rawEnd = trim((string) ($event['end_time'] ?? ''));
if ($rawEnd !== '') {
    $eventEndTimeValue = strlen($rawEnd) >= 5 ? substr($rawEnd, 0, 5) : $rawEnd;
}
$eventEndTimeNa = !empty($event['end_time_na']) || ($rawEnd === '' && $rawStart !== '');
$eventEndTimeOption = $eventEndTimeNa ? 'na' : ($eventEndTimeValue !== '' ? 'time' : 'none');

$eventDepartmentStored = (string) ($event['department'] ?? 'ALL');

$organizer_department_choices = eventify_organizer_department_choices();
$deptFormPost = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptFormPost = isset($_POST['department'])
        ? (is_array($_POST['department']) ? $_POST['department'] : [$_POST['department']])
        : [];
}
$deptCheckboxState = eventify_organizer_department_form_checkbox_state(
    $deptFormPost,
    $eventDepartmentStored
);

// Section-only: do not show "All departments" checked (DB may still store ALL).
$editUiSections = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['section']) && is_array($_POST['section'])) {
        $editUiSections = $_POST['section'];
    }
    $ns = trim((string) ($_POST['new_section'] ?? ''));
    if ($ns !== '') {
        $editUiSections[] = $ns;
    }
} elseif (function_exists('eventify_parse_target_sections_list')) {
    $editUiSections = eventify_parse_target_sections_list($event['target_sections'] ?? null);
}
$editHasSectionAudience = false;
foreach ($editUiSections as $sLab) {
    if (trim((string) $sLab) !== '') {
        $editHasSectionAudience = true;
        break;
    }
}
if ($editHasSectionAudience && !empty($deptCheckboxState['all']) && empty($deptCheckboxState['specific'])) {
    $deptCheckboxState['all'] = false;
}

// Handle form submission (update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        eventify_edit_event_go_home($editHomeUrl, 'Invalid request. Please try again.', true);
    }
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date        = $_POST['date'] ?? '';
    $location    = trim($_POST['location'] ?? '');
    $start_time  = trim((string) ($_POST['start_time'] ?? ''));
    $endParsed   = eventify_parse_event_end_time_from_request($_POST);
    $end_time    = (string) ($endParsed['end_time'] ?? '');
    $end_time_na = !empty($endParsed['end_time_na']);
    $max_capacity_raw = trim($_POST['max_capacity'] ?? '');
    $maxCapVal = null;
    if ($max_capacity_raw !== '' && ctype_digit($max_capacity_raw)) {
        $v = (int) $max_capacity_raw;
        if ($v > 0) {
            $maxCapVal = $v;
        }
    }

    if (empty($title)) {
        $error = "Title is required.";
    } elseif (empty($date)) {
        $error = "Date is required.";
    } elseif ($start_time === '') {
        $error = "Start time is required.";
    } elseif (empty($location)) {
        $error = "Location is required.";
    } elseif (strlen($title) > 150) {
        $error = "Title must be 150 characters or less.";
    } elseif (strlen($location) > 255) {
        $error = "Location must be 255 characters or less.";
    } else {
        $startObj = DateTime::createFromFormat('H:i', $start_time);
        if (!$startObj || $startObj->format('H:i') !== $start_time) {
            $error = "Invalid start time.";
        } elseif (!$end_time_na && $end_time !== '') {
            $endObj = DateTime::createFromFormat('H:i', $end_time);
            if (!$endObj || $endObj->format('H:i') !== $end_time) {
                $error = "Invalid end time.";
            } elseif ($endObj <= $startObj) {
                $error = "End time must be after start time.";
            }
        } elseif (!$end_time_na && $end_time === '' && (($_POST['end_time_option'] ?? '') === 'time')) {
            $error = "Please enter an end time or choose Not applicable.";
        }

        if (!$error) {
        // Validate date format
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            $error = "Invalid date format.";
        } else {
            // Check if date is in the past
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            $eventDate = new DateTime($date);
            $eventDate->setTime(0, 0, 0);

            if ($eventDate < $today) {
                $error = "Event date cannot be in the past.";
            } else {
                $parsedDept = eventify_parse_event_departments_from_request($_POST);
                if (!$parsedDept['ok']) {
                    $error = $parsedDept['error'] ?? 'Invalid department selection.';
                } else {
                    $department = $parsedDept['department'];
                }

                $targetSectionsJson = null;
                if (!$error) {
                    $parsedSecs = eventify_parse_event_sections_from_request($conn, $_POST, $session_user_id);
                    if (!$parsedSecs['ok']) {
                        $error = $parsedSecs['error'] ?? 'Invalid section selection.';
                    } else {
                        $targetSectionsJson = $parsedSecs['target_sections'];
                    }
                }

                if (!$error) {
                    $oldTitle = (string)($event['title'] ?? 'this event');
                    $start_time_param = $start_time;
                    $end_time_param = ($end_time !== '' && !$end_time_na) ? $end_time : null;
                    $stmt = null;
                    if ($isAdminEditor) {
                        // Admin owning the event: publish changes immediately (no re-approval).
                        if ($eventsHasMaxCapacity) {
                            $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, start_time = ?, end_time = ?, location = ?, department = ?, max_capacity = ? WHERE id = ? AND organizer_id = ?");
                            if ($stmt) {
                                $stmt->bind_param("sssssssiii", $title, $description, $date, $start_time_param, $end_time_param, $location, $department, $maxCapVal, $event_id, $session_user_id);
                            }
                        } else {
                            $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, start_time = ?, end_time = ?, location = ?, department = ? WHERE id = ? AND organizer_id = ?");
                            if ($stmt) {
                                $stmt->bind_param("sssssssii", $title, $description, $date, $start_time_param, $end_time_param, $location, $department, $event_id, $session_user_id);
                            }
                        }
                    } elseif ($eventsHasMaxCapacity) {
                        $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, start_time = ?, end_time = ?, location = ?, department = ?, max_capacity = ?, status = 'pending', reject_reason = NULL WHERE id = ? AND organizer_id = ?");
                        if ($stmt) {
                            $stmt->bind_param("sssssssiii", $title, $description, $date, $start_time_param, $end_time_param, $location, $department, $maxCapVal, $event_id, $session_user_id);
                        }
                    } else {
                        $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, start_time = ?, end_time = ?, location = ?, department = ?, status = 'pending', reject_reason = NULL WHERE id = ? AND organizer_id = ?");
                        if ($stmt) {
                            $stmt->bind_param("sssssssii", $title, $description, $date, $start_time_param, $end_time_param, $location, $department, $event_id, $session_user_id);
                        }
                    }

                    if (!$stmt) {
                        $error = "Database error. Please try again.";
                    } elseif ($stmt->execute()) {
                        $stmt->close();
                        eventify_event_save_target_sections($conn, $event_id, $targetSectionsJson);
                        if ($eventsHasEndTimeNa) {
                            $naFlag = $end_time_na ? 1 : 0;
                            $upNa = $conn->prepare('UPDATE events SET end_time_na = ? WHERE id = ? AND organizer_id = ?');
                            if ($upNa) {
                                $upNa->bind_param('iii', $naFlag, $event_id, $session_user_id);
                                $upNa->execute();
                                $upNa->close();
                            }
                        }
                        require_once __DIR__ . '/../lib/activity_logger.php';
                        log_activity(
                            $conn,
                            (int) $session_user_id,
                            $sessionRole,
                            $isAdminEditor ? 'event_updated_by_admin' : 'event_updated',
                            'event',
                            $event_id,
                            $isAdminEditor
                                ? ('Updated owned event: ' . $title)
                                : ('Updated event and sent back for approval: ' . $title)
                        );

                        try {
                            require_once __DIR__ . '/../lib/notifications_service.php';
                            $beforeSnap = [
                                'title' => (string) ($event['title'] ?? ''),
                                'date' => (string) ($event['date'] ?? ''),
                                'start_time' => (string) ($event['start_time'] ?? ''),
                                'end_time' => (string) ($event['end_time'] ?? ''),
                                'location' => (string) ($event['location'] ?? ''),
                                'department' => (string) ($event['department'] ?? 'ALL'),
                                'target_sections' => $event['target_sections'] ?? null,
                            ];
                            $afterSnap = [
                                'title' => $title,
                                'date' => $date,
                                'start_time' => $start_time_param,
                                'end_time' => $end_time_param,
                                'location' => $location,
                                'department' => $department,
                                'target_sections' => $targetSectionsJson,
                                'status' => $isAdminEditor ? 'active' : 'pending',
                            ];

                            if ($isAdminEditor) {
                                // Always alert calendar audience on admin live save with current schedule/venue.
                                eventify_notify_students_event_details_changed(
                                    $conn,
                                    $event_id,
                                    $beforeSnap,
                                    $afterSnap,
                                    'live',
                                    true
                                );
                            } else {
                                // 1) Admin + Super Admin approval notification
                                $admins = $conn->query("SELECT id FROM users WHERE role IN ('admin','super_admin') AND status = 'active'");
                                if ($admins) {
                                    $adminTitle = 'Event update pending approval';
                                    $adminMsg = 'Organizer updated "' . $title . '" (previously "' . $oldTitle . '"). Please review and approve.';
                                    $insAdmin = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_update_pending_review', ?, ?, ?)");
                                    if ($insAdmin) {
                                        while ($a = $admins->fetch_assoc()) {
                                            $adminId = (int)($a['id'] ?? 0);
                                            if ($adminId > 0) {
                                                $insAdmin->bind_param("issi", $adminId, $adminTitle, $adminMsg, $event_id);
                                                $insAdmin->execute();
                                            }
                                        }
                                        $insAdmin->close();
                                    }
                                }

                                // 2) Students: include what changed (date / time / venue) while pending.
                                $studentsNotified = eventify_notify_students_event_details_changed(
                                    $conn,
                                    $event_id,
                                    $beforeSnap,
                                    $afterSnap,
                                    'pending'
                                );
                                if ($studentsNotified < 1) {
                                    $fallbackIds = eventify_student_ids_registered_for_event($conn, $event_id);
                                    if ($fallbackIds === []) {
                                        $fallbackIds = eventify_student_ids_for_event_audience($conn, $afterSnap);
                                    }
                                    eventify_notify_student_ids(
                                        $conn,
                                        $fallbackIds,
                                        'event_updated_pending',
                                        'Event updated',
                                        'An event you follow ("' . $title . '") was updated by the organizer and is pending admin approval.',
                                        $event_id
                                    );
                                }

                                // 3) Multimedia users notification
                                $media = $conn->query("SELECT id FROM users WHERE role = 'multimedia' AND status = 'active'");
                                if ($media) {
                                    $mmTitle = 'Event updated';
                                    $mmMsg = 'Event "' . $title . '" was updated by organizer and is pending admin approval.';
                                    $insMm = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_updated_pending', ?, ?, ?)");
                                    if ($insMm) {
                                        while ($m = $media->fetch_assoc()) {
                                            $mid = (int)($m['id'] ?? 0);
                                            if ($mid > 0) {
                                                $insMm->bind_param("issi", $mid, $mmTitle, $mmMsg, $event_id);
                                                $insMm->execute();
                                            }
                                        }
                                        $insMm->close();
                                    }
                                }
                            }
                        } catch (Throwable $e) {
                            // Keep update successful even if notifications fail.
                        }

                        $success = $isAdminEditor
                            ? 'Event updated and saved.'
                            : 'Event updated and submitted for admin approval.';
                        eventify_edit_event_go_home($editHomeUrl, $success, false);
                    } else {
                        $error = "Failed to update event. Please try again.";
                        $stmt->close();
                    }
                }
            }
        }
        } // end !$error after time validation
    }

    // If there was an error, keep form values from POST
    $event['title']       = $title;
    $event['description'] = $description;
    $event['date']        = $date;
    $event['location']    = $location;
    $event['max_capacity'] = $maxCapVal;
    $eventStartTimeValue = $start_time;
    $eventEndTimeValue = $end_time;
    $eventEndTimeNa = $end_time_na;
    $eventEndTimeOption = $end_time_na ? 'na' : ($end_time !== '' ? 'time' : 'none');
    $deptPostErr = isset($_POST['department'])
        ? (is_array($_POST['department']) ? $_POST['department'] : [$_POST['department']])
        : [];
    $deptCheckboxState = eventify_organizer_department_form_checkbox_state(
        $deptPostErr,
        $eventDepartmentStored
    );
    $editUiSections = [];
    if (isset($_POST['section']) && is_array($_POST['section'])) {
        $editUiSections = $_POST['section'];
    }
    $ns = trim((string) ($_POST['new_section'] ?? ''));
    if ($ns !== '') {
        $editUiSections[] = $ns;
    }
    $editHasSectionAudience = false;
    foreach ($editUiSections as $sLab) {
        if (trim((string) $sLab) !== '') {
            $editHasSectionAudience = true;
            break;
        }
    }
    if ($editHasSectionAudience && !empty($deptCheckboxState['all']) && empty($deptCheckboxState['specific'])) {
        $deptCheckboxState['all'] = false;
    }
}

// Fetch organizer name for display
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$stmt->bind_result($db_name);
$stmt->fetch();
$user_name = $db_name ?? 'Organizer';
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/eventify_modal.css?v=3">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Google Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .create-event-container {
            max-width: 700px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: slideUp 0.4s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .create-event-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .create-event-header h1 {
            font-size: 28px;
            font-weight: 500;
            margin: 0;
        }

        .create-event-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .create-event-body {
            padding: 40px;
            max-height: min(88vh, 900px);
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .form-group { margin-bottom: 25px; }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #3c4043;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group label .required { color: #ea4335; }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #dadce0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Google Sans', sans-serif;
            transition: all 0.2s ease;
            background: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control::placeholder { color: #9aa0a6; }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        /* Scope form actions only — do not override student efy-modal buttons */
        .btn-group .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-group .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
        }

        .btn-group .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-group .btn-secondary {
            background: #f8f9fa;
            color: #5f6368;
            border: 1px solid #dadce0;
        }

        .btn-group .btn-secondary:hover { background: #e8eaed; }

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-danger {
            background: #fce8e6;
            color: #c5221f;
            border: 1px solid #f28b82;
        }

        .alert-success {
            background: #e6f4ea;
            color: #137333;
            border: 1px solid #81c995;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .input-icon { position: relative; }
        .input-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9aa0a6;
            pointer-events: none;
        }
        .input-icon .form-control { padding-left: 45px; }

        .end-time-options {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1.25rem;
            margin: 0.35rem 0 0.65rem;
        }
        .end-time-options label {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin: 0;
            font-size: 13px;
            font-weight: 500;
            color: #5f6368;
            cursor: pointer;
        }

        .form-text-help {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #80868b;
        }

        .page-back-wrap {
            max-width: 700px;
            margin: 0 auto 14px;
            display: flex;
            justify-content: flex-start;
        }

        @media (max-width: 768px) {
            .create-event-body { padding: 24px; }
            .create-event-header { padding: 24px; }
            .create-event-header h1 { font-size: 24px; }
            .btn-group { flex-direction: column; }
        }

        /* Theme override: match organizer dashboard / create event palette */
        :root {
            --school-cream: #f7f4e7;
            --school-olive-top: #b7be77;
            --school-forest-mid: #3f6a2a;
            --school-forest-deep: #153313;
            --school-forest-card: #1b4a1b;
            --school-gold: #e6c54a;
            --school-gold-dim: #b88f2a;
            --school-border: rgba(230, 197, 74, 0.42);
        }

        body {
            background: linear-gradient(180deg, var(--school-olive-top) 0%, var(--school-forest-mid) 42%, var(--school-forest-deep) 100%);
            background-attachment: fixed;
        }

        .create-event-container {
            background: linear-gradient(180deg, #ffffff 0%, var(--school-cream) 100%);
            border: 2px solid var(--school-border);
            box-shadow: 0 14px 36px rgba(0,0,0,0.35);
        }

        .create-event-header {
            background: linear-gradient(180deg, var(--school-forest-card) 0%, #021a08 100%);
            border-bottom: 3px solid var(--school-gold);
        }

        .form-control:focus {
            border-color: var(--school-gold-dim);
            box-shadow: 0 0 0 3px rgba(230, 197, 74, 0.25);
        }

        .btn-group .btn-primary {
            background: linear-gradient(180deg, var(--school-forest-card) 0%, var(--school-forest-deep) 100%);
            color: var(--school-gold);
            border: 2px solid var(--school-gold);
        }

        .btn-group .btn-primary:hover {
            color: #fff7a8;
            border-color: #fff7a8;
            box-shadow: 0 4px 12px rgba(0,0,0,0.28);
            transform: none;
        }

        .btn-group .btn-secondary {
            background: rgba(255,255,255,0.85);
            color: var(--school-forest-card);
            border: 1px solid rgba(1, 50, 32, 0.28);
        }

        .btn-group .btn-secondary:hover {
            background: rgba(230, 197, 74, 0.2);
            border-color: var(--school-forest-card);
        }

        .back-link {
            color: var(--school-forest-card);
            background: var(--school-gold);
            border: 2px solid rgba(1, 50, 32, 0.35);
            border-radius: 999px;
            padding: 0.5rem 1rem;
            font-weight: 700;
            font-size: 0.95rem;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.2);
        }

        .back-link:hover {
            color: var(--school-forest-card);
            background: #f3da78;
            border-color: var(--school-gold-dim);
            transform: translateY(-1px);
        }

        .form-check-input:checked {
            background-color: #3d8a35;
            border-color: var(--school-forest-card);
        }

        .form-check-input:focus {
            border-color: rgba(61, 138, 53, 0.55);
            box-shadow: 0 0 0 3px rgba(61, 138, 53, 0.15);
        }
    </style>
</head>
<body>
    <div class="page-back-wrap">
        <a href="<?= htmlspecialchars($editHomeUrl, ENT_QUOTES, 'UTF-8') ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="create-event-container">
        <div class="create-event-header">
            <h1><i class="fas fa-edit"></i> Edit Event</h1>
            <p>
                <?= $isAdminEditor
                    ? 'Update your event details (saved immediately — no re-approval).'
                    : 'Update the details of your event' ?>
            </p>
        </div>

        <div class="create-event-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="editEventForm">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="title">Event Title <span class="required">*</span></label>
                    <div class="input-icon">
                        <i class="fas fa-heading"></i>
                        <input type="text" id="title" name="title" class="form-control" maxlength="150" required
                               placeholder="Enter event title"
                               value="<?= htmlspecialchars($event['title'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" maxlength="1000"
                              placeholder="Enter event description (optional)"><?= htmlspecialchars($event['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="date">Event Date <span class="required">*</span></label>
                    <div class="input-icon">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" id="date" name="date" class="form-control" required
                               value="<?= htmlspecialchars($event['date'] ?? '') ?>"
                               min="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="start_time">Event start time <span class="required">*</span></label>
                    <div class="input-icon">
                        <i class="fas fa-clock"></i>
                        <input type="time" id="start_time" name="start_time" class="form-control" required
                               value="<?= htmlspecialchars($eventStartTimeValue) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="end_time">Event end time</label>
                    <div class="end-time-options" role="group" aria-label="End time options">
                        <label>
                            <input type="radio" name="end_time_option" value="time" id="endOptTime"
                                <?= $eventEndTimeOption === 'time' ? 'checked' : '' ?>> Set time
                        </label>
                        <label>
                            <input type="radio" name="end_time_option" value="na" id="endOptNa"
                                <?= $eventEndTimeOption === 'na' ? 'checked' : '' ?>> Not applicable
                        </label>
                    </div>
                    <div class="input-icon">
                        <i class="fas fa-clock"></i>
                        <input type="time" id="end_time" name="end_time" class="form-control"
                               value="<?= htmlspecialchars($eventEndTimeOption === 'time' ? $eventEndTimeValue : '') ?>"
                               <?= $eventEndTimeOption === 'na' ? 'disabled' : '' ?>>
                    </div>
                    <span class="form-text-help">Shown on the student calendar. Day-by-day activity times are managed separately.</span>
                </div>

                <div class="form-group">
                    <label for="location">Location <span class="required">*</span></label>
                    <div class="input-icon">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" id="location" name="location" class="form-control" maxlength="255" required
                               placeholder="Enter event location"
                               value="<?= htmlspecialchars($event['location'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="max_capacity">Max attendees (RSVP cap)</label>
                    <input type="number" id="max_capacity" name="max_capacity" class="form-control"
                           min="1" max="50000" placeholder="Leave empty for unlimited"
                           value="<?= htmlspecialchars(isset($event['max_capacity']) && $event['max_capacity'] !== null && $event['max_capacity'] !== '' ? (string)(int)$event['max_capacity'] : '') ?>">
                </div>

                <div class="form-group">
                    <span class="d-block mb-2">
                        Department / Audience <span class="required">*</span>
                    </span>
                    <p class="text-muted small mb-2" style="font-size: 13px;">Choose <strong>All departments</strong>, specific colleges, or leave departments empty when limiting to a class section below.</p>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="department[]" value="ALL" id="editDeptAll" <?= !empty($deptCheckboxState['all']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="editDeptAll">All departments</label>
                    </div>
                    <div class="border rounded p-2" style="max-height: 220px; overflow-y: auto; background: #fafafa;">
                        <?php foreach ($organizer_department_choices as $deptVal => $deptLabel): ?>
                            <?php if ($deptVal === 'ALL') {
                                continue;
                            } ?>
                            <?php $edCbId = 'edit_dept_' . substr(md5($deptVal), 0, 14); ?>
                            <?php $isChecked = !$deptCheckboxState['all'] && in_array($deptVal, $deptCheckboxState['specific'], true); ?>
                            <div class="form-check">
                                <input class="form-check-input edit-dept-specific" type="checkbox" name="department[]" value="<?= htmlspecialchars($deptVal) ?>" id="<?= htmlspecialchars($edCbId) ?>" <?= $isChecked ? 'checked' : '' ?>>
                                <label class="form-check-label" for="<?= htmlspecialchars($edCbId) ?>"><?= htmlspecialchars($deptLabel) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php
                  $sectionFieldIdPrefix = 'edit';
                  $postedSections = isset($_POST['section']) && is_array($_POST['section'])
                      ? $_POST['section']
                      : (function_exists('eventify_parse_target_sections_list')
                          ? eventify_parse_target_sections_list($event['target_sections'] ?? null)
                          : []);
                  $newSectionValue = (string) ($_POST['new_section'] ?? '');
                  include __DIR__ . '/../../views/partials/event_section_audience_fields.php';
                ?>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Update Event
                    </button>
                    <a href="<?= htmlspecialchars($editHomeUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="confirmUpdateEventModal" tabindex="-1" aria-labelledby="confirmUpdateEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content efy-modal efy-modal--compact">
                <div class="modal-header efy-modal__header">
                    <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="confirmUpdateEventModalLabel">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        Confirm changes
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body efy-modal__body efy-modal__body--compact">
                    <p class="efy-confirm-message mb-0">
                        <?= $isAdminEditor
                            ? 'Are you sure you want to save your changes?'
                            : 'Are you sure you want to save your changes? The event will be sent back to the admin for approval before it goes live again.' ?>
                    </p>
                </div>
                <div class="modal-footer efy-modal__footer">
                    <button type="button" class="btn efy-btn-primary" id="confirmUpdateEventYesBtn">
                        <i class="fas fa-check me-1"></i> Yes, proceed
                    </button>
                    <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content efy-modal efy-modal--compact">
                <div class="modal-header efy-modal__header">
                    <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="messageModalLabel">
                        <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                        Please fix
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body efy-modal__body efy-modal__body--compact">
                    <p id="messageModalBody" class="efy-confirm-message mb-0"></p>
                </div>
                <div class="modal-footer efy-modal__footer">
                    <button type="button" class="btn efy-btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function () {
        var form = document.getElementById('editEventForm');
        if (!form) return;

        var allCb = document.getElementById('editDeptAll');
        var specifics = form.querySelectorAll('.edit-dept-specific');
        function formHasSelectedSection() {
            var sectionCbs = form.querySelectorAll('input[name="section[]"]:checked');
            if (sectionCbs.length > 0) return true;
            var newSec = form.querySelector('input[name="new_section"]');
            return !!(newSec && String(newSec.value || '').trim());
        }
        function syncDeptForSections() {
            if (formHasSelectedSection() && allCb && allCb.checked) {
                allCb.checked = false;
            }
        }
        if (allCb) {
            allCb.addEventListener('change', function () {
                if (allCb.checked) specifics.forEach(function (cb) { cb.checked = false; });
            });
        }
        specifics.forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (cb.checked && allCb) allCb.checked = false;
            });
        });
        form.querySelectorAll('input[name="section[]"]').forEach(function (cb) {
            cb.addEventListener('change', syncDeptForSections);
        });
        var newSecInput = form.querySelector('input[name="new_section"]');
        if (newSecInput) {
            newSecInput.addEventListener('input', syncDeptForSections);
        }
        syncDeptForSections();

        var endTime = document.getElementById('end_time');
        var endOptTime = document.getElementById('endOptTime');
        var endOptNa = document.getElementById('endOptNa');
        function syncEndTimeUi() {
            if (!endTime) return;
            var useNa = !!(endOptNa && endOptNa.checked);
            endTime.disabled = useNa;
            if (useNa) endTime.value = '';
        }
        if (endOptTime) endOptTime.addEventListener('change', syncEndTimeUi);
        if (endOptNa) endOptNa.addEventListener('change', syncEndTimeUi);
        syncEndTimeUi();

        function showMessageModal(msg) {
            var el = document.getElementById('messageModalBody');
            if (el) el.textContent = msg;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('messageModal')).show();
        }

        var confirmModalEl = document.getElementById('confirmUpdateEventModal');
        var confirmYesBtn = document.getElementById('confirmUpdateEventYesBtn');
        var confirmModal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);
        confirmYesBtn.addEventListener('click', function () {
            confirmModal.hide();
            form.submit();
        });

        form.addEventListener('submit', function (e) {
            var title = (document.getElementById('title').value || '').trim();
            var date = document.getElementById('date').value;
            var start = document.getElementById('start_time').value;
            var loc = (document.getElementById('location').value || '').trim();
            var anySpec = Array.prototype.some.call(specifics, function (c) { return c.checked; });
            var allOn = allCb && allCb.checked;
            var hasSection = formHasSelectedSection();

            if (!allOn && !anySpec && !hasSection) {
                e.preventDefault();
                showMessageModal('Choose All departments, pick at least one college, or select a class section.');
                return false;
            }
            if (!title) {
                e.preventDefault();
                showMessageModal('Please enter an event title.');
                return false;
            }
            if (!date) {
                e.preventDefault();
                showMessageModal('Please select an event date.');
                return false;
            }
            if (!start) {
                e.preventDefault();
                showMessageModal('Please set a start time.');
                return false;
            }
            if (!loc) {
                e.preventDefault();
                showMessageModal('Please enter an event location.');
                return false;
            }
            if (endOptTime && endOptTime.checked && endTime && !endTime.value) {
                e.preventDefault();
                showMessageModal('Please enter an end time or choose Not applicable.');
                return false;
            }
            if (endOptTime && endOptTime.checked && endTime && endTime.value && endTime.value <= start) {
                e.preventDefault();
                showMessageModal('End time must be after start time.');
                return false;
            }

            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var eventDate = new Date(date + 'T00:00:00');
            if (eventDate < today) {
                e.preventDefault();
                showMessageModal('Event date cannot be in the past.');
                return false;
            }

            e.preventDefault();
            confirmModal.show();
            return false;
        });
    })();
    </script>
</body>
</html>
<?php
$conn->close();
?>
