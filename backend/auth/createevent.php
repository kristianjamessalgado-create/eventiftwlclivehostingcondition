<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../config/departments.php';
require_once __DIR__ . '/../../config/organizer_departments.php';
require_once __DIR__ . '/../lib/event_calendar.php';
require_once __DIR__ . '/../lib/event_ticketing.php';

// Check if user is logged in as organizer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: " . BASE_URL . "/views/login.php?error=Access denied");
    exit();
}

$session_user_id = $_SESSION['user_id'];
$error = '';
$success = '';

eventify_events_department_ensure_varchar($conn);

$eventsHasGeo = false;
try {
    $geoColCheck = $conn->query("SHOW COLUMNS FROM events WHERE Field IN ('latitude','longitude')");
    if ($geoColCheck && $geoColCheck->num_rows >= 2) {
        $eventsHasGeo = true;
    }
} catch (Throwable $e) {
    $eventsHasGeo = false;
}

$eventsHasMaxCapacity = false;
try {
    $mcCol = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'max_capacity'");
    if ($mcCol && $mcCol->num_rows >= 1) {
        $eventsHasMaxCapacity = true;
    }
} catch (Throwable $e) {
    $eventsHasMaxCapacity = false;
}

$eventsHasEndDate = eventify_events_has_end_date($conn);
eventify_event_schedule_dates_ensure_table($conn);
eventify_ticketing_ensure_registration_mode_column($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Invalid request. Please try again."));
        exit();
    }
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date = $_POST['date'] ?? '';
    $end_date = trim($_POST['end_date'] ?? '');
    $start_time = $_POST['start_time'] ?? '';
    $endParsed = eventify_parse_event_end_time_from_request($_POST);
    $end_time = $endParsed['end_time'] ?? '';
    $end_time_na = !empty($endParsed['end_time_na']);
    $dayEndTimes = eventify_parse_schedule_day_end_times_from_request($_POST);
    $dayStartTimes = eventify_parse_schedule_day_start_times_from_request($_POST);
    $location = trim($_POST['location'] ?? '');
    $registration_mode = eventify_parse_registration_mode_from_request($_POST);
    $max_capacity_raw = trim($_POST['max_capacity'] ?? '');
    $maxCapVal = null;
    if ($max_capacity_raw !== '' && ctype_digit($max_capacity_raw)) {
        $v = (int) $max_capacity_raw;
        if ($v > 0) {
            $maxCapVal = $v;
        }
    }
    
    // Validation
    if (empty($title)) {
        $error = "Title is required.";
    } elseif (empty($date)) {
        $error = "Click at least one day on the calendar for your event.";
    } elseif (empty($location)) {
        $error = "Location is required.";
    } elseif (strlen($title) > 150) {
        $error = "Title must be 150 characters or less.";
    } elseif (strlen($location) > 255) {
        $error = "Location must be 255 characters or less.";
    } else {
        // Validate date format
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            $error = "Invalid date format.";
        } else {
            $startTimeObj = null;
            $scheduleMode = eventify_resolve_schedule_mode_from_request($_POST);
            $scheduleDates = eventify_parse_schedule_dates_from_request($_POST);

            $endTimeObj = null;
            if (!$end_time_na && $end_time !== '') {
                $endTimeObj = DateTime::createFromFormat('H:i', $end_time);
                if (!$endTimeObj || $endTimeObj->format('H:i') !== $end_time) {
                    $error = "Invalid end time format.";
                }
            } elseif ($end_time_na) {
                $end_time = '';
            }

            $endDateObj = null;
            $endDateYmd = '';
            if ($scheduleMode === 'range' && $eventsHasEndDate && $end_date !== '') {
                $endDateObj = DateTime::createFromFormat('Y-m-d', $end_date);
                if (!$endDateObj || $endDateObj->format('Y-m-d') !== $end_date) {
                    $error = "Invalid end date format.";
                } else {
                    $endDateYmd = $end_date;
                }
            }

            if (!$error && $scheduleMode === 'range' && $endDateYmd === '') {
                $error = "Please enter an end date for a date-range event, or choose specific days.";
            }

            if (!$error && $scheduleMode === 'range' && $endDateYmd !== '' && $endDateYmd < $date) {
                $error = "End date cannot be before the start date.";
            }

            if (!$error && $scheduleMode === 'specific' && count($scheduleDates) < 1) {
                $error = "Add at least one event day for a specific-days schedule.";
            }

            if (!$error && $scheduleMode === 'specific') {
                $todayYmd = (new DateTime())->format('Y-m-d');
                foreach ($scheduleDates as $sd) {
                    if ($sd < $todayYmd) {
                        $error = "Event days cannot be in the past.";
                        break;
                    }
                }
                if (!$error) {
                    $date = $scheduleDates[0];
                    $endDateYmd = '';
                }
            }

            $scheduleDatesToStore = [];
            if (!$error && $scheduleMode === 'range' && $endDateYmd !== '' && $endDateYmd >= $date) {
                $scheduleDatesToStore = eventify_dates_between_inclusive($date, $endDateYmd);
            } elseif (!$error && $scheduleMode === 'specific' && count($scheduleDates) > 0) {
                $scheduleDatesToStore = $scheduleDates;
            }

            if (!$error && count($scheduleDatesToStore) > 0) {
                $lastYmd = $scheduleDatesToStore[count($scheduleDatesToStore) - 1];
                if (isset($dayEndTimes[$lastYmd])) {
                    $end_time = $dayEndTimes[$lastYmd]['end_time'];
                    $end_time_na = !empty($dayEndTimes[$lastYmd]['end_time_na']);
                }
            }

            if (!$error && count($scheduleDatesToStore) >= 2) {
                foreach ($scheduleDatesToStore as $ymd) {
                    $st = trim((string) ($dayStartTimes[$ymd] ?? $start_time));
                    if ($st === '') {
                        $error = 'Please set a start time for each event day.';
                        break;
                    }
                    $stObj = DateTime::createFromFormat('H:i', $st);
                    if (!$stObj || $stObj->format('H:i') !== $st) {
                        $error = 'Invalid start time for one or more event days.';
                        break;
                    }
                    $dayStartTimes[$ymd] = $st;
                    $dayEnd = $dayEndTimes[$ymd] ?? null;
                    if ($dayEnd && empty($dayEnd['end_time_na']) && !empty($dayEnd['end_time']) && $dayEnd['end_time'] <= $st) {
                        $error = 'End time must be after start time on each event day.';
                        break;
                    }
                }
                if (!$error) {
                    $start_time = $dayStartTimes[$scheduleDatesToStore[0]];
                    $startTimeObj = DateTime::createFromFormat('H:i', $start_time);
                }
            } elseif (!$error) {
                if ($start_time === '') {
                    $error = 'Start time is required.';
                } else {
                    $startTimeObj = DateTime::createFromFormat('H:i', $start_time);
                    if (!$startTimeObj || $startTimeObj->format('H:i') !== $start_time) {
                        $error = 'Invalid start time format.';
                    }
                }
            }

            if (!$error && !$end_time_na && $end_time !== '') {
                $endTimeObj = DateTime::createFromFormat('H:i', $end_time);
                if (!$endTimeObj || $endTimeObj->format('H:i') !== $end_time) {
                    $error = 'Invalid end time format.';
                }
            }

            $isMultiDay = $scheduleMode === 'range' && $endDateYmd !== '' && $endDateYmd > $date;
            if (!$error && $scheduleMode === 'single' && !$end_time_na && $endTimeObj && $startTimeObj && $endTimeObj <= $startTimeObj) {
                $error = "End time must be after start time on the same day.";
            }
            if (!$error && $end_time_na === false && $end_time === '' && ($_POST['end_time_option'] ?? '') === 'time') {
                $error = "Please enter an end time or choose Not applicable.";
            }

            // Check if start date is in the past
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            $eventDate = new DateTime($date);
            $eventDate->setTime(0, 0, 0);
            
            if ($eventDate < $today) {
                $error = "Event start date cannot be in the past.";
            }

            if (!$error) {
                $parsedDept = eventify_parse_event_departments_from_request($_POST);
                if (!$parsedDept['ok']) {
                    $error = $parsedDept['error'] ?? 'Invalid department selection.';
                } else {
                    $department = $parsedDept['department'];
                }
            }

            if (!$error) {

                $latVal = null;
                $lngVal = null;
                if ($eventsHasGeo) {
                    $latRaw = trim($_POST['event_latitude'] ?? '');
                    $lngRaw = trim($_POST['event_longitude'] ?? '');
                    if ($latRaw === '' || $lngRaw === '' || !is_numeric($latRaw) || !is_numeric($lngRaw)) {
                        $error = 'Please set the venue on the map, use “Use my location”, or search and pick a result.';
                    } else {
                        $latVal = (float) $latRaw;
                        $lngVal = (float) $lngRaw;
                        if ($latVal < -90 || $latVal > 90 || $lngVal < -180 || $lngVal > 180) {
                            $error = 'Invalid map coordinates.';
                        }
                    }
                }

                if (!$error) {
                    $checkin_token = bin2hex(random_bytes(16));
                    $start_time_param = $start_time ?: null;
                    $end_time_param = ($end_time !== '' && !$end_time_na) ? $end_time : null;
                    $end_date_param = ($eventsHasEndDate && $endDateYmd !== '') ? $endDateYmd : null;
                    $executed = false;

                    if ($eventsHasGeo && $latVal !== null && $lngVal !== null) {
                        if ($eventsHasEndDate) {
                            if ($eventsHasMaxCapacity) {
                                $stmt = $conn->prepare("INSERT INTO events (title, description, date, end_date, start_time, end_time, location, latitude, longitude, max_capacity, organizer_id, department, status, checkin_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                                if ($stmt) {
                                    $stmt->bind_param("sssssssddiiss", $title, $description, $date, $end_date_param, $start_time_param, $end_time_param, $location, $latVal, $lngVal, $maxCapVal, $session_user_id, $department, $checkin_token);
                                    if ($stmt->execute()) {
                                        $executed = true;
                                    }
                                    $stmt->close();
                                }
                            }
                            if (!$executed) {
                                $stmt = $conn->prepare("INSERT INTO events (title, description, date, end_date, start_time, end_time, location, latitude, longitude, organizer_id, department, status, checkin_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                                if ($stmt) {
                                    $stmt->bind_param("sssssssddiss", $title, $description, $date, $end_date_param, $start_time_param, $end_time_param, $location, $latVal, $lngVal, $session_user_id, $department, $checkin_token);
                                    if ($stmt->execute()) {
                                        $executed = true;
                                    }
                                    $stmt->close();
                                }
                            }
                        } elseif ($eventsHasMaxCapacity) {
                            $stmt = $conn->prepare("INSERT INTO events (title, description, date, start_time, end_time, location, latitude, longitude, max_capacity, organizer_id, department, status, checkin_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                            if ($stmt) {
                                $stmt->bind_param("ssssssddiiss", $title, $description, $date, $start_time_param, $end_time_param, $location, $latVal, $lngVal, $maxCapVal, $session_user_id, $department, $checkin_token);
                                if ($stmt->execute()) {
                                    $executed = true;
                                }
                                $stmt->close();
                            }
                        }
                        if (!$executed && !$eventsHasEndDate) {
                            $stmt = $conn->prepare("INSERT INTO events (title, description, date, start_time, end_time, location, latitude, longitude, organizer_id, department, status, checkin_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                            if ($stmt) {
                                $stmt->bind_param("ssssssddiss", $title, $description, $date, $start_time_param, $end_time_param, $location, $latVal, $lngVal, $session_user_id, $department, $checkin_token);
                                if ($stmt->execute()) {
                                    $executed = true;
                                }
                                $stmt->close();
                            }
                        }
                    }

                    if (!$executed && !$eventsHasGeo) {
                        if ($eventsHasEndDate) {
                            if ($eventsHasMaxCapacity) {
                                $stmt = $conn->prepare("INSERT INTO events (title, description, date, end_date, start_time, end_time, location, max_capacity, organizer_id, department, status, checkin_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                                if ($stmt) {
                                    $stmt->bind_param("sssssssiiss", $title, $description, $date, $end_date_param, $start_time_param, $end_time_param, $location, $maxCapVal, $session_user_id, $department, $checkin_token);
                                    if ($stmt->execute()) {
                                        $executed = true;
                                    }
                                    $stmt->close();
                                }
                            }
                            if (!$executed) {
                                $stmt = $conn->prepare("INSERT INTO events (title, description, date, end_date, start_time, end_time, location, organizer_id, department, status, checkin_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                                if ($stmt) {
                                    $stmt->bind_param("sssssssiss", $title, $description, $date, $end_date_param, $start_time_param, $end_time_param, $location, $session_user_id, $department, $checkin_token);
                                    if ($stmt->execute()) {
                                        $executed = true;
                                    }
                                    $stmt->close();
                                }
                            }
                        } elseif ($eventsHasMaxCapacity) {
                            $stmt = $conn->prepare("INSERT INTO events (title, description, date, start_time, end_time, location, max_capacity, organizer_id, department, status, checkin_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                            if ($stmt) {
                                $stmt->bind_param("ssssssiiss", $title, $description, $date, $start_time_param, $end_time_param, $location, $maxCapVal, $session_user_id, $department, $checkin_token);
                                if ($stmt->execute()) {
                                    $executed = true;
                                }
                                $stmt->close();
                            }
                        }
                        if (!$executed && !$eventsHasEndDate) {
                            $stmt = $conn->prepare("INSERT INTO events (title, description, date, start_time, end_time, location, organizer_id, department, status, checkin_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                            if ($stmt) {
                                $stmt->bind_param("ssssssiss", $title, $description, $date, $start_time_param, $end_time_param, $location, $session_user_id, $department, $checkin_token);
                                if ($stmt->execute()) {
                                    $executed = true;
                                }
                                $stmt->close();
                            }
                        }
                    }

                    if ($executed) {
                        $newEventId = (int) $conn->insert_id;
                        if (count($scheduleDatesToStore) >= 2) {
                            eventify_save_event_schedule_dates($conn, $newEventId, $scheduleDatesToStore, $dayEndTimes, $dayStartTimes);
                        } else {
                            eventify_save_event_schedule_dates($conn, $newEventId, []);
                        }
                        if (eventify_events_has_end_time_na($conn)) {
                            $naFlag = $end_time_na ? 1 : 0;
                            $upNa = $conn->prepare('UPDATE events SET end_time_na = ? WHERE id = ?');
                            if ($upNa) {
                                $upNa->bind_param('ii', $naFlag, $newEventId);
                                $upNa->execute();
                                $upNa->close();
                            }
                        }
                        $upMode = $conn->prepare('UPDATE events SET registration_mode = ? WHERE id = ?');
                        if ($upMode) {
                            $upMode->bind_param('si', $registration_mode, $newEventId);
                            $upMode->execute();
                            $upMode->close();
                        }
                        require_once __DIR__ . '/../lib/activity_logger.php';
                        log_activity(
                            $conn,
                            (int) $session_user_id,
                            'organizer',
                            'event_submitted_pending',
                            'event',
                            $newEventId,
                            'Submitted for admin approval: ' . $title
                        );
                        try {
                            $who = $conn->query("SELECT id FROM users WHERE role IN ('admin','super_admin') AND status = 'active'");
                            if ($who) {
                                $notifTitle = 'New event pending approval';
                                $notifMsg = 'Organizer submitted "' . $title . '" for approval.';
                                $insNotif = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_pending_review', ?, ?, ?)");
                                if ($insNotif) {
                                    while ($adm = $who->fetch_assoc()) {
                                        $adminId = (int) ($adm['id'] ?? 0);
                                        if ($adminId > 0) {
                                            $insNotif->bind_param("issi", $adminId, $notifTitle, $notifMsg, $newEventId);
                                            $insNotif->execute();
                                        }
                                    }
                                    $insNotif->close();
                                }
                            }
                        } catch (Throwable $e) {
                            // ignore
                        }
                        $success = 'Event submitted successfully and is now pending approval. An admin will review it and send you an OTP — enter that code under My Events to publish the event on the calendar.';
                        $redirectTo = trim((string) ($_POST['redirect_to'] ?? ''));
                        if ($redirectTo === 'activities_hub') {
                            $redirect = BASE_URL . '/activities_hub.php?msg=' . urlencode($success);
                        } elseif ($redirectTo === 'event_activities' && $newEventId > 0) {
                            $redirect = BASE_URL . '/event_activities.php?id=' . $newEventId . '&msg=' . urlencode($success);
                        } else {
                            $redirect = BASE_URL . '/backend/auth/dashboardorganizer.php?msg=' . urlencode($success);
                        }
                        if (count($scheduleDatesToStore) >= 2) {
                            if ($redirectTo === 'activities_hub') {
                                $redirect .= '&prompt_activities=' . $newEventId;
                            } elseif ($redirectTo === 'event_activities' && $newEventId > 0) {
                                $redirect .= '&prompt_activities=1';
                            } else {
                                $redirect .= '&prompt_activities=' . $newEventId;
                            }
                        }
                        header("Location: " . $redirect);
                        exit();
                    }

                    if ($eventsHasGeo) {
                        $error = 'Could not save event with map location. Check database migration (latitude/longitude columns).';
                    } else {
                        $error = "Failed to create event. Please try again.";
                    }
                }
            }
        }
    }
}

// Get pre-filled date from URL parameter (from calendar click)
$prefilled_date = $_GET['date'] ?? '';

// Fetch user info for display
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$stmt->bind_result($db_name);
$stmt->fetch();
$user_name = $db_name ?? 'Organizer';
$stmt->close();

$organizer_department_choices = eventify_organizer_department_choices();
$pageDeptCheckboxState = eventify_organizer_department_form_checkbox_state(
    isset($_POST['department'])
        ? (is_array($_POST['department']) ? $_POST['department'] : [$_POST['department']])
        : null,
    'ALL'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - EVENTIFY</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    
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
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .event-location-map {
            height: 280px;
            border-radius: 8px;
            border: 1px solid #dadce0;
            overflow: hidden;
            z-index: 0;
        }

        .event-loc-results {
            max-height: 160px;
            overflow-y: auto;
            border-radius: 8px;
            z-index: 2;
        }
        
        @media (max-width: 768px) {
            .create-event-body {
                padding: 24px;
            }
            
            .create-event-header {
                padding: 24px;
            }
            
            .create-event-header h1 {
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

        .create-event-container {
            background: linear-gradient(180deg, #ffffff 0%, var(--school-cream) 100%);
            border: 2px solid var(--school-border);
            box-shadow: 0 14px 36px rgba(0,0,0,0.35);
        }

        .create-event-header {
            background: linear-gradient(180deg, var(--school-forest-card) 0%, #021a08 100%);
            border-bottom: 3px solid var(--school-gold);
        }

        .create-event-header .header-actions {
            margin-top: 0.9rem;
            display: flex;
            justify-content: center;
        }

        .create-event-header .header-back-link {
            color: var(--school-forest-card);
            background: var(--school-gold);
            border: 1px solid rgba(1, 50, 32, 0.32);
            border-radius: 999px;
            padding: 0.4rem 0.85rem;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .create-event-header .header-back-link:hover {
            background: #f3da78;
            color: var(--school-forest-card);
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

        .page-back-wrap {
            max-width: 700px;
            margin: 0 auto 14px;
            display: flex;
            justify-content: flex-start;
        }
    </style>
</head>
<body>
    <div class="page-back-wrap">
        <a href="<?= BASE_URL ?>/backend/auth/dashboardorganizer.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    <div class="create-event-container">
        <div class="create-event-header">
            <h1><i class="fas fa-calendar-plus"></i> Create New Event</h1>
            <p>Fill in the details below to create your event</p>
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
            
            <form method="POST" action="" id="createEventForm" data-require-geo="<?= $eventsHasGeo ? '1' : '0' ?>">
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
                            value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
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
                    ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                
                <?php
                $scheduleModeValue = $_SERVER['REQUEST_METHOD'] === 'POST'
                    ? eventify_resolve_schedule_mode_from_request($_POST)
                    : 'single';
                $postedScheduleDates = $_SERVER['REQUEST_METHOD'] === 'POST'
                    ? eventify_parse_schedule_dates_from_request($_POST)
                    : [];
                $postedStartDate = $_SERVER['REQUEST_METHOD'] === 'POST'
                    ? trim($_POST['date'] ?? '')
                    : trim($prefilled_date ?: '');
                $postedEndDate = $_SERVER['REQUEST_METHOD'] === 'POST'
                    ? trim($_POST['end_date'] ?? '')
                    : '';
                $postedEndTimeOption = $_SERVER['REQUEST_METHOD'] === 'POST'
                    ? trim($_POST['end_time_option'] ?? 'none')
                    : 'none';
                $postedEndTime = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['end_time_option'] ?? '') === 'time')
                    ? trim($_POST['end_time'] ?? '')
                    : '';
                $postedDayEndTimes = [];
                $postedDayStartTimes = [];
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['schedule_day_end']) && is_array($_POST['schedule_day_end'])) {
                    foreach ($_POST['schedule_day_end'] as $ymd => $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $postedDayEndTimes[$ymd] = [
                            'mode' => $row['mode'] ?? 'none',
                            'time' => $row['time'] ?? '',
                        ];
                    }
                }
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['schedule_day_start']) && is_array($_POST['schedule_day_start'])) {
                    foreach ($_POST['schedule_day_start'] as $ymd => $time) {
                        $postedDayStartTimes[$ymd] = trim((string) $time);
                    }
                }
                $idPrefix = 'standalone';
                include __DIR__ . '/../../views/partials/event_schedule_fields.php';
                ?>

                <div class="form-group" id="standaloneStartTimeRow">
                    <label for="start_time">
                        Event start time <span class="required">*</span>
                    </label>
                    <div class="input-icon">
                        <i class="fas fa-clock"></i>
                        <input
                            type="time"
                            id="start_time"
                            name="start_time"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="location">
                        Location <span class="required">*</span>
                    </label>
                    <p class="text-muted" style="font-size: 13px; margin-bottom: 10px;">
                        Search OpenStreetMap, click the map, or use your current location. The pin sets GPS coordinates for the venue.
                    </p>
                    <input type="hidden" name="event_latitude" id="event_latitude" value="<?= htmlspecialchars($_POST['event_latitude'] ?? '') ?>">
                    <input type="hidden" name="event_longitude" id="event_longitude" value="<?= htmlspecialchars($_POST['event_longitude'] ?? '') ?>">
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 10px;">
                        <input type="search" id="eventLocSearch" class="form-control" style="flex: 1; min-width: 200px;" placeholder="Search place or address" autocomplete="off">
                        <button type="button" class="btn btn-secondary" id="eventLocSearchBtn" style="white-space: nowrap;">Search</button>
                        <button type="button" class="btn btn-primary" id="eventLocUseGps" style="white-space: nowrap;"><i class="fas fa-location-crosshairs"></i> Use my location</button>
                    </div>
                    <div id="eventLocResults" class="list-group event-loc-results mb-2" style="display: none;"></div>
                    <div id="eventLocationMap" class="event-location-map mb-2"></div>
                    <label for="location" style="font-size: 14px; font-weight: 500; margin-bottom: 6px; display: block;">Venue name / address (shown to attendees)</label>
                    <div class="input-icon">
                        <i class="fas fa-map-marker-alt"></i>
                        <input 
                            type="text"
                            id="location"
                            name="location"
                            class="form-control"
                            placeholder="e.g. Main campus gym"
                            value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                            required
                            maxlength="255"
                            autocomplete="off"
                        >
                    </div>
                    <?php if ($eventsHasGeo): ?>
                    <small class="text-muted d-block mt-1">After migrating the database, a map position is required to submit.</small>
                    <?php endif; ?>
                </div>

                <?php
                $regModeFieldIdPrefix = 'standalone';
                $postedRegistrationMode = $_POST['registration_mode'] ?? 'rsvp';
                include __DIR__ . '/../../views/partials/event_registration_mode_fields.php';
                ?>

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
                        value="<?= htmlspecialchars($_POST['max_capacity'] ?? '') ?>"
                    >
                    <small class="text-muted d-block mt-1">Students cannot register once this limit is reached. Requires database migration <code>school_events_high_value_features.sql</code> for the column.</small>
                </div>
                
                <div class="form-group">
                    <span class="d-block mb-2" style="color: #3c4043; font-weight: 500; font-size: 14px;">
                        Department / Audience <span class="required">*</span>
                    </span>
                    <p class="text-muted small mb-2" style="font-size: 13px;">Choose <strong>All departments</strong> or one or more specific audiences.</p>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="department[]" value="ALL" id="standaloneDeptAll" <?= !empty($pageDeptCheckboxState['all']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="standaloneDeptAll">All departments</label>
                    </div>
                    <div class="border rounded p-2" style="max-height: 220px; overflow-y: auto; background: #fafafa;">
                        <?php foreach ($organizer_department_choices as $deptVal => $deptLabel): ?>
                            <?php if ($deptVal === 'ALL') {
                                continue;
                            } ?>
                            <?php $sdCbId = 'standalone_dept_' . substr(md5($deptVal), 0, 14); ?>
                            <?php $sdChecked = !$pageDeptCheckboxState['all'] && in_array($deptVal, $pageDeptCheckboxState['specific'], true); ?>
                            <div class="form-check">
                                <input class="form-check-input standalone-dept-specific" type="checkbox" name="department[]" value="<?= htmlspecialchars($deptVal) ?>" id="<?= htmlspecialchars($sdCbId) ?>" <?= $sdChecked ? 'checked' : '' ?>>
                                <label class="form-check-label" for="<?= htmlspecialchars($sdCbId) ?>"><?= htmlspecialchars($deptLabel) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" id="createEventSubmitBtn">
                        <i class="fas fa-check"></i> Create Event
                    </button>
                    <a href="<?= BASE_URL ?>/backend/auth/dashboardorganizer.php" class="btn btn-secondary" id="createEventCancelLink">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
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

    <!-- Confirm Create/Cancel Modal -->
    <div class="modal fade" id="confirmActionModal" tabindex="-1" aria-labelledby="confirmActionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmActionModalLabel"><i class="fas fa-circle-question me-2"></i>Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmActionModalBody" class="mb-0">Are you sure you want to continue?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn btn-primary" id="confirmActionYesBtn">Yes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="<?= htmlspecialchars(BASE_URL) ?>/assets/js/event_location_picker.js"></script>
    <script src="<?= htmlspecialchars(BASE_URL) ?>/assets/js/event_schedule_picker.js"></script>

    <script>
        function showMessageModal(msg) {
            var el = document.getElementById('messageModalBody');
            if (el) el.textContent = msg;
            var modal = new bootstrap.Modal(document.getElementById('messageModal'));
            modal.show();
        }

        var EVENTIFY_GEOCODE_URL = <?= json_encode(BASE_URL . '/backend/auth/geocode_proxy.php') ?>;

        document.addEventListener('DOMContentLoaded', function () {
            var cform = document.getElementById('createEventForm');
            var submitBtn = document.getElementById('createEventSubmitBtn');
            var cancelLink = document.getElementById('createEventCancelLink');
            var confirmModalEl = document.getElementById('confirmActionModal');
            var confirmBodyEl = document.getElementById('confirmActionModalBody');
            var confirmYesBtn = document.getElementById('confirmActionYesBtn');
            var confirmModal = null;
            var pendingAction = null; // { type: 'submit' } or { type: 'cancel', href: '...' }

            if (confirmModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                confirmModal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);
            }

            if (submitBtn && cform && confirmModal) {
                submitBtn.addEventListener('click', function () {
                    pendingAction = { type: 'submit' };
                    if (confirmBodyEl) confirmBodyEl.textContent = 'Are you sure you want to create this event?';
                    confirmModal.show();
                });
            }

            if (cancelLink && confirmModal) {
                cancelLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    pendingAction = { type: 'cancel', href: cancelLink.getAttribute('href') || '' };
                    if (confirmBodyEl) confirmBodyEl.textContent = 'Are you sure you want to cancel? Unsaved changes will be lost.';
                    confirmModal.show();
                });
            }

            if (confirmYesBtn) {
                confirmYesBtn.addEventListener('click', function () {
                    if (!pendingAction) return;
                    if (confirmModal) confirmModal.hide();
                    if (pendingAction.type === 'submit' && cform) {
                        cform.requestSubmit();
                    } else if (pendingAction.type === 'cancel' && pendingAction.href) {
                        window.location.href = pendingAction.href;
                    }
                    pendingAction = null;
                });
            }

            if (confirmModalEl) {
                confirmModalEl.addEventListener('hidden.bs.modal', function () {
                    pendingAction = null;
                });
            }

            if (cform) {
                var allD = document.getElementById('standaloneDeptAll');
                var specsD = cform.querySelectorAll('.standalone-dept-specific');
                if (allD) {
                    allD.addEventListener('change', function () {
                        if (allD.checked) {
                            specsD.forEach(function (cb) { cb.checked = false; });
                        }
                    });
                }
                specsD.forEach(function (cb) {
                    cb.addEventListener('change', function () {
                        if (cb.checked && allD) allD.checked = false;
                    });
                });
            }
            if (typeof window.initEventLocationPicker === 'function' && window.L) {
                window.initEventLocationPicker({
                    mapElId: 'eventLocationMap',
                    latInputId: 'event_latitude',
                    lngInputId: 'event_longitude',
                    addressInputId: 'location',
                    searchInputId: 'eventLocSearch',
                    searchBtnId: 'eventLocSearchBtn',
                    useLocationBtnId: 'eventLocUseGps',
                    resultsElId: 'eventLocResults',
                    geocodeBase: EVENTIFY_GEOCODE_URL
                });
            }
        });

        document.getElementById('createEventForm').addEventListener('submit', function(e) {
            const form = e.target;
            const title = document.getElementById('title').value.trim();
            const startTime = (document.getElementById('start_time') || {}).value;
            if (typeof eventifySyncScheduleBeforeSubmit === 'function') {
                eventifySyncScheduleBeforeSubmit('standalone');
            }
            const location = document.getElementById('location').value.trim();
            const allD = document.getElementById('standaloneDeptAll');
            const specsD = form.querySelectorAll('.standalone-dept-specific');
            const anyD = Array.from(specsD).some(function (c) { return c.checked; });
            const allOn = allD && allD.checked;
            if (!allOn && !anyD) {
                e.preventDefault();
                showMessageModal('Please choose "All departments" or select at least one department.');
                return false;
            }
            const requireGeo = form.getAttribute('data-require-geo') === '1';
            const latEl = document.getElementById('event_latitude');
            const lngEl = document.getElementById('event_longitude');
            
            if (!title) {
                e.preventDefault();
                showMessageModal('Please enter an event title.');
                document.getElementById('title').focus();
                return false;
            }
            
            if (!startTime) {
                e.preventDefault();
                showMessageModal('Please select a start time.');
                var st = document.getElementById('start_time');
                if (st) st.focus();
                return false;
            }
            
            if (!location) {
                e.preventDefault();
                showMessageModal('Please enter a venue name or address.');
                document.getElementById('location').focus();
                return false;
            }

            if (requireGeo && latEl && lngEl) {
                var lat = latEl.value.trim();
                var lng = lngEl.value.trim();
                if (!lat || !lng || isNaN(parseFloat(lat)) || isNaN(parseFloat(lng))) {
                    e.preventDefault();
                    showMessageModal('Please set the venue on the map, search and pick a result, or use your location.');
                    return false;
                }
            }
            
            if (typeof eventifyValidateScheduleOnSubmit === 'function' && !eventifyValidateScheduleOnSubmit('standalone')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>
