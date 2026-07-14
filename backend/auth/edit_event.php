<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../config/departments.php';
require_once __DIR__ . '/../../config/organizer_departments.php';
require_once __DIR__ . '/../lib/web_push.php';

// Require organizer login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Access denied"));
    exit();
}

$session_user_id = $_SESSION['user_id'];
$error   = '';
$success = '';
$eventsHasMaxCapacity = false;

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
    header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Invalid event ID."));
    exit();
}

// Fetch event and ensure it belongs to this organizer
if ($eventsHasMaxCapacity) {
    $stmt = $conn->prepare("SELECT id, title, description, date, location, department, max_capacity FROM events WHERE id = ? AND organizer_id = ?");
} else {
    $stmt = $conn->prepare("SELECT id, title, description, date, location, department FROM events WHERE id = ? AND organizer_id = ?");
}
if (!$stmt) {
    header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Database error."));
    exit();
}
$stmt->bind_param("ii", $event_id, $session_user_id);
$stmt->execute();
$result = $stmt->get_result();
$event  = $result->fetch_assoc();
$stmt->close();

if (!$event) {
    header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Event not found or you do not have permission to edit it."));
    exit();
}

eventify_events_department_ensure_varchar($conn);

if (!array_key_exists('max_capacity', $event)) {
    $event['max_capacity'] = null;
}

$eventDepartmentStored = (string) ($event['department'] ?? 'ALL');

$organizer_department_choices = eventify_organizer_department_choices();
$deptFormPost = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['department']))
    ? (is_array($_POST['department']) ? $_POST['department'] : [$_POST['department']])
    : null;
$deptCheckboxState = eventify_organizer_department_form_checkbox_state(
    $deptFormPost,
    $eventDepartmentStored
);

// Handle form submission (update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Invalid request. Please try again."));
        exit();
    }
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date        = $_POST['date'] ?? '';
    $location    = trim($_POST['location'] ?? '');
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
    } elseif (empty($location)) {
        $error = "Location is required.";
    } elseif (strlen($title) > 150) {
        $error = "Title must be 150 characters or less.";
    } elseif (strlen($location) > 100) {
        $error = "Location must be 100 characters or less.";
    } else {
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

                if (!$error) {
                    $oldTitle = (string)($event['title'] ?? 'this event');
                    if ($eventsHasMaxCapacity) {
                        $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, location = ?, department = ?, max_capacity = ?, status = 'pending', reject_reason = NULL WHERE id = ? AND organizer_id = ?");
                        if ($stmt) {
                            $stmt->bind_param("sssssiii", $title, $description, $date, $location, $department, $maxCapVal, $event_id, $session_user_id);
                        }
                    } else {
                        $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, location = ?, department = ?, status = 'pending', reject_reason = NULL WHERE id = ? AND organizer_id = ?");
                        if ($stmt) {
                            $stmt->bind_param("sssssii", $title, $description, $date, $location, $department, $event_id, $session_user_id);
                        }
                    }

                    if (!$stmt) {
                        $error = "Database error. Please try again.";
                    } elseif ($stmt->execute()) {
                        $stmt->close();
                        require_once __DIR__ . '/../lib/activity_logger.php';
                        log_activity(
                            $conn,
                            (int) $session_user_id,
                            'organizer',
                            'event_updated',
                            'event',
                            $event_id,
                            'Updated event and sent back for approval: ' . $title
                        );

                        // Notify admins, affected students, and multimedia after event update.
                        try {
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

                            // 2) Registered students notification
                            $students = $conn->prepare("
                                SELECT DISTINCT r.user_id
                                FROM registrations r
                                INNER JOIN users u ON u.id = r.user_id
                                WHERE r.event_id = ? AND u.role = 'student'
                            ");
                            if ($students) {
                                $students->bind_param("i", $event_id);
                                $students->execute();
                                $sres = $students->get_result();
                                $studentTitle = 'Event updated';
                                $studentMsg = 'An event you registered for ("' . $title . '") was updated by the organizer and is pending admin approval.';
                                $insStudent = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_updated_pending', ?, ?, ?)");
                                if ($insStudent && $sres) {
                                    while ($s = $sres->fetch_assoc()) {
                                        $sid = (int)($s['user_id'] ?? 0);
                                        if ($sid > 0) {
                                            $insStudent->bind_param("issi", $sid, $studentTitle, $studentMsg, $event_id);
                                            if ($insStudent->execute()) {
                                                eventify_push_notify_user($conn, $sid, 'event_updated_pending', $studentTitle, $studentMsg, $event_id);
                                            }
                                        }
                                    }
                                    $insStudent->close();
                                }
                                $students->close();
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
                        } catch (Throwable $e) {
                            // Keep update successful even if notifications fail.
                        }

                        $success = "Event updated and submitted for admin approval.";
                        header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode($success));
                        exit();
                    } else {
                        $error = "Failed to update event. Please try again.";
                        $stmt->close();
                    }
                }
            }
        }
    }

    // If there was an error, keep form values from POST
    $event['title']       = $title;
    $event['description'] = $description;
    $event['date']        = $date;
    $event['location']    = $location;
    $event['max_capacity'] = $maxCapVal;
    $deptPostErr = isset($_POST['department'])
        ? (is_array($_POST['department']) ? $_POST['department'] : [$_POST['department']])
        : null;
    $deptCheckboxState = eventify_organizer_department_form_checkbox_state(
        $deptPostErr,
        $eventDepartmentStored
    );
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

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Google Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .edit-event-container {
            max-width: 700px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: slideUp 0.4s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .edit-event-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .edit-event-header h1 {
            font-size: 28px;
            font-weight: 500;
            margin: 0;
        }

        .edit-event-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .edit-event-body {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #3c4043;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group label .required {
            color: #ea4335;
        }

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

        .form-control::placeholder {
            color: #9aa0a6;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
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

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #5f6368;
            border: 1px solid #dadce0;
        }

        .btn-secondary:hover {
            background: #e8eaed;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            margin-bottom: 20px;
            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: #764ba2;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9aa0a6;
        }

        .input-icon .form-control {
            padding-left: 45px;
        }

        @media (max-width: 768px) {
            .edit-event-body {
                padding: 24px;
            }

            .edit-event-header {
                padding: 24px;
            }

            .edit-event-header h1 {
                font-size: 24px;
            }

            .btn-group {
                flex-direction: column;
            }
        }

        /* Theme override: match organizer dashboard palette */
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

        .edit-event-container {
            background: linear-gradient(180deg, #ffffff 0%, var(--school-cream) 100%);
            border: 2px solid var(--school-border);
            box-shadow: 0 14px 36px rgba(0,0,0,0.35);
        }

        .edit-event-header {
            background: linear-gradient(180deg, var(--school-forest-card) 0%, #021a08 100%);
            border-bottom: 3px solid var(--school-gold);
        }

        .form-control:focus {
            border-color: var(--school-gold-dim);
            box-shadow: 0 0 0 3px rgba(230, 197, 74, 0.25);
        }

        .btn-primary {
            background: linear-gradient(180deg, var(--school-forest-card) 0%, var(--school-forest-deep) 100%);
            color: var(--school-gold);
            border: 2px solid var(--school-gold);
        }

        .btn-primary:hover {
            color: #fff7a8;
            border-color: #fff7a8;
            box-shadow: 0 4px 12px rgba(0,0,0,0.28);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.85);
            color: var(--school-forest-card);
            border: 1px solid rgba(1, 50, 32, 0.28);
        }

        .btn-secondary:hover {
            background: rgba(230, 197, 74, 0.2);
            border-color: var(--school-forest-card);
        }

        .back-link {
            color: var(--school-forest-card);
            background: rgba(230, 197, 74, 0.18);
            border: 1px solid rgba(1, 50, 32, 0.25);
            border-radius: 999px;
            padding: 0.4rem 0.8rem;
            font-weight: 700;
        }

        .back-link:hover {
            color: var(--school-forest-card);
            background: rgba(230, 197, 74, 0.34);
            border-color: var(--school-gold-dim);
        }
    </style>
</head>
<body>
    <div class="edit-event-container">
        <div class="edit-event-header">
            <h1><i class="fas fa-edit"></i> Edit Event</h1>
            <p>Update the details of your event</p>
        </div>

        <div class="edit-event-body">
            <a href="<?= BASE_URL ?>/backend/auth/dashboardorganizer.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>

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
                    <label for="title">
                        Event Title <span class="required">*</span>
                    </label>
                    <div class="input-icon">
                        <i class="fas fa-heading"></i>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            class="form-control"
                            placeholder="Enter event title"
                            value="<?= htmlspecialchars($event['title'] ?? '') ?>"
                            required
                            maxlength="150"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">
                        Description
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        class="form-control"
                        placeholder="Enter event description (optional)"
                        maxlength="1000"
                    ><?= htmlspecialchars($event['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="date">
                        Event Date <span class="required">*</span>
                    </label>
                    <div class="input-icon">
                        <i class="fas fa-calendar-alt"></i>
                        <input
                            type="date"
                            id="date"
                            name="date"
                            class="form-control"
                            value="<?= htmlspecialchars($event['date'] ?? '') ?>"
                            required
                            min="<?= date('Y-m-d') ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="location">
                        Location <span class="required">*</span>
                    </label>
                    <div class="input-icon">
                        <i class="fas fa-map-marker-alt"></i>
                        <input
                            type="text"
                            id="location"
                            name="location"
                            class="form-control"
                            placeholder="Enter event location"
                            value="<?= htmlspecialchars($event['location'] ?? '') ?>"
                            required
                            maxlength="100"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="max_capacity">Max attendees (RSVP cap)</label>
                    <input
                        type="number"
                        id="max_capacity"
                        name="max_capacity"
                        class="form-control"
                        min="1"
                        max="50000"
                        placeholder="Leave empty for unlimited"
                        value="<?= htmlspecialchars(isset($event['max_capacity']) && $event['max_capacity'] !== null && $event['max_capacity'] !== '' ? (string)(int)$event['max_capacity'] : '') ?>"
                    >
                </div>

                <div class="form-group">
                    <span class="d-block mb-2">
                        Department / Audience <span class="required">*</span>
                    </span>
                    <p class="text-muted small mb-2" style="font-size: 13px;">Choose <strong>All departments</strong> or one or more specific audiences.</p>
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

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Update Event
                    </button>
                    <a href="<?= BASE_URL ?>/backend/auth/dashboardorganizer.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirm update (yes / no) -->
    <div class="modal fade" id="confirmUpdateEventModal" tabindex="-1" aria-labelledby="confirmUpdateEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmUpdateEventModalLabel"><i class="fas fa-question-circle me-2 text-primary"></i>Update this event?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to save these changes? The event will be sent back to the admin for approval before it goes live again.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn btn-primary" id="confirmUpdateEventYesBtn">Yes, update event</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Modal (replaces alert) -->
    <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="messageModalLabel"><i class="fas fa-exclamation-circle me-2"></i>Please fix the following</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="messageModalBody" class="mb-0"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function showMessageModal(msg) {
            var el = document.getElementById('messageModalBody');
            if (el) el.textContent = msg;
            var modal = new bootstrap.Modal(document.getElementById('messageModal'));
            modal.show();
        }
        (function () {
            var form = document.getElementById('editEventForm');
            if (!form) return;
            var allCb = document.getElementById('editDeptAll');
            var specifics = form.querySelectorAll('.edit-dept-specific');
            if (allCb) {
                allCb.addEventListener('change', function () {
                    if (allCb.checked) {
                        specifics.forEach(function (cb) { cb.checked = false; });
                    }
                });
            }
            specifics.forEach(function (cb) {
                cb.addEventListener('change', function () {
                    if (cb.checked && allCb) allCb.checked = false;
                });
            });
        })();

        (function () {
            var form = document.getElementById('editEventForm');
            var confirmModalEl = document.getElementById('confirmUpdateEventModal');
            var confirmYesBtn = document.getElementById('confirmUpdateEventYesBtn');
            if (!form || !confirmModalEl || !confirmYesBtn || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                return;
            }
            var confirmModal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);

            confirmYesBtn.addEventListener('click', function () {
                confirmModal.hide();
                form.submit();
            });

            form.addEventListener('submit', function(e) {
                const title = document.getElementById('title').value.trim();
                const date  = document.getElementById('date').value;
                const loc   = document.getElementById('location').value.trim();
                const allCb = document.getElementById('editDeptAll');
                const specifics = form.querySelectorAll('.edit-dept-specific');
                const anySpec = Array.from(specifics).some(function (c) { return c.checked; });
                const allOn = allCb && allCb.checked;
                if (!allOn && !anySpec) {
                    e.preventDefault();
                    showMessageModal('Please choose "All departments" or select at least one department.');
                    return false;
                }

                if (!title) {
                    e.preventDefault();
                    showMessageModal('Please enter an event title.');
                    document.getElementById('title').focus();
                    return false;
                }

                if (!date) {
                    e.preventDefault();
                    showMessageModal('Please select an event date.');
                    document.getElementById('date').focus();
                    return false;
                }

                if (!loc) {
                    e.preventDefault();
                    showMessageModal('Please enter an event location.');
                    document.getElementById('location').focus();
                    return false;
                }

                const today    = new Date();
                today.setHours(0, 0, 0, 0);
                const eventDate = new Date(date);

                if (eventDate < today) {
                    e.preventDefault();
                    showMessageModal('Event date cannot be in the past.');
                    document.getElementById('date').focus();
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

