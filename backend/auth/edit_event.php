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
require_once __DIR__ . '/../lib/event_day_sessions.php';

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
$editOrphanPlan = null;
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
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
    if (function_exists('eventify_push_queue_flush')) {
        eventify_push_queue_flush(true);
    }
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
$eventsHasEndDate = eventify_events_has_end_date($conn);
$eventsHasCheckinTimes = function_exists('eventify_events_ensure_checkin_times')
    && eventify_events_ensure_checkin_times($conn);
$eventsHasAttendanceMode = function_exists('eventify_events_ensure_attendance_mode')
    && eventify_events_ensure_attendance_mode($conn);
eventify_event_schedule_dates_ensure_table($conn);

// Fetch event — must be owned by this user (organizer or admin who assigned it to themselves)
$selectCols = 'id, title, description, date, location, department, status, organizer_id, start_time, end_time';
if ($eventsHasCheckinTimes) {
    $selectCols .= ', checkin_start_time, checkin_end_time';
}
if ($eventsHasAttendanceMode) {
    $selectCols .= ', attendance_mode, checkout_end_time';
}
if ($eventsHasEndDate) {
    $selectCols .= ', end_date';
}
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
if (!array_key_exists('end_date', $event)) {
    $event['end_date'] = null;
}
if (!array_key_exists('checkin_start_time', $event)) {
    $event['checkin_start_time'] = null;
}
if (!array_key_exists('checkin_end_time', $event)) {
    $event['checkin_end_time'] = null;
}

$existingScheduleDays = [];
$daysMap = eventify_load_schedule_days_map($conn, [$event_id]);
if (!empty($daysMap[$event_id])) {
    $existingScheduleDays = $daysMap[$event_id];
}
$editScheduleDates = array_column($existingScheduleDays, 'schedule_date');
$editStartDate = trim((string) ($event['date'] ?? ''));
$editEndDate = trim((string) ($event['end_date'] ?? ''));
$editDayEndTimes = [];
$editDayStartTimes = [];
foreach ($existingScheduleDays as $dayRow) {
    $ymd = (string) ($dayRow['schedule_date'] ?? '');
    $st = trim((string) ($dayRow['start_time'] ?? ''));
    if ($st !== '') {
        $editDayStartTimes[$ymd] = strlen($st) >= 5 ? substr($st, 0, 5) : $st;
    }
    $et = trim((string) ($dayRow['end_time'] ?? ''));
    if (!empty($dayRow['end_time_na'])) {
        $editDayEndTimes[$ymd] = ['mode' => 'na', 'time' => ''];
    } elseif ($et !== '') {
        $editDayEndTimes[$ymd] = ['mode' => 'time', 'time' => strlen($et) >= 5 ? substr($et, 0, 5) : $et];
    } else {
        $editDayEndTimes[$ymd] = ['mode' => 'none', 'time' => ''];
    }
}
$editScheduleModeValue = 'single';
if (count($editScheduleDates) >= 2) {
    $sortedScheduleDates = $editScheduleDates;
    sort($sortedScheduleDates);
    $expectedRange = eventify_dates_between_inclusive($sortedScheduleDates[0], $sortedScheduleDates[count($sortedScheduleDates) - 1]);
    $editScheduleModeValue = ($sortedScheduleDates === $expectedRange) ? 'range' : 'specific';
} elseif ($editEndDate !== '' && $editEndDate > $editStartDate) {
    $editScheduleModeValue = 'range';
    if (count($editScheduleDates) < 2) {
        $editScheduleDates = eventify_dates_between_inclusive($editStartDate, $editEndDate);
    }
}

$eventForOldDates = $event;
$eventForOldDates['schedule_days'] = $existingScheduleDays;
$oldEventDisplayDates = eventify_event_calendar_display_dates($eventForOldDates);

$editSessionsByDate = [];
$editSessionsForSchedule = eventify_load_event_day_sessions($conn, $event_id, null, null);
foreach ($editSessionsForSchedule as $editSessionRow) {
    $editSessionDay = substr(trim((string) ($editSessionRow['schedule_date'] ?? '')), 0, 10);
    if ($editSessionDay === '') {
        continue;
    }
    if (!isset($editSessionsByDate[$editSessionDay])) {
        $editSessionsByDate[$editSessionDay] = [];
    }
    $editSessionsByDate[$editSessionDay][] = [
        'id' => (int) ($editSessionRow['id'] ?? 0),
        'title' => trim((string) ($editSessionRow['title'] ?? '')) !== ''
            ? trim((string) ($editSessionRow['title'] ?? ''))
            : 'Activity',
    ];
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

$eventCheckinStartValue = '';
$rawCheckinStart = trim((string) ($event['checkin_start_time'] ?? ''));
if ($rawCheckinStart !== '') {
    $eventCheckinStartValue = strlen($rawCheckinStart) >= 5 ? substr($rawCheckinStart, 0, 5) : $rawCheckinStart;
}
$eventCheckinEndValue = '';
$rawCheckinEnd = trim((string) ($event['checkin_end_time'] ?? ''));
if ($rawCheckinEnd !== '') {
    $eventCheckinEndValue = strlen($rawCheckinEnd) >= 5 ? substr($rawCheckinEnd, 0, 5) : $rawCheckinEnd;
}
$eventAttendanceMode = strtolower(trim((string) ($event['attendance_mode'] ?? 'single')));
if ($eventAttendanceMode !== 'time_in_out') {
    $eventAttendanceMode = 'single';
}
$eventCheckoutEndValue = '';
$rawCheckoutEnd = trim((string) ($event['checkout_end_time'] ?? ''));
if ($rawCheckoutEnd !== '') {
    $eventCheckoutEndValue = strlen($rawCheckoutEnd) >= 5 ? substr($rawCheckoutEnd, 0, 5) : $rawCheckoutEnd;
}

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
    $end_date    = trim($_POST['end_date'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $start_time  = trim((string) ($_POST['start_time'] ?? ''));
    $endParsed   = eventify_parse_event_end_time_from_request($_POST);
    $end_time    = (string) ($endParsed['end_time'] ?? '');
    $end_time_na = !empty($endParsed['end_time_na']);
    $dayEndTimes = eventify_parse_schedule_day_end_times_from_request($_POST);
    $dayStartTimes = eventify_parse_schedule_day_start_times_from_request($_POST);
    $checkinParsed = eventify_parse_event_checkin_times_from_request($_POST);
    $checkin_start_param = $checkinParsed['checkin_start_time'];
    $checkin_end_param = $checkinParsed['checkin_end_time'];
    $attendance_mode_param = eventify_parse_attendance_mode_from_request($_POST);
    $checkout_end_param = eventify_parse_checkout_end_time_from_request($_POST);
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
        $error = "Click at least one day on the calendar for your event.";
    } elseif (empty($location)) {
        $error = "Location is required.";
    } elseif (strlen($title) > 150) {
        $error = "Title must be 150 characters or less.";
    } elseif (strlen($location) > 255) {
        $error = "Location must be 255 characters or less.";
    } else {
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            $error = "Invalid date format.";
        } else {
            $startTimeObj = null;
            $endTimeObj = null;
            $scheduleMode = eventify_resolve_schedule_mode_from_request($_POST);
            $scheduleDates = eventify_parse_schedule_dates_from_request($_POST);

            if (!$end_time_na && $end_time !== '') {
                $endTimeObj = DateTime::createFromFormat('H:i', $end_time);
                if (!$endTimeObj || $endTimeObj->format('H:i') !== $end_time) {
                    $error = "Invalid end time format.";
                }
            } elseif ($end_time_na) {
                $end_time = '';
            }

            $endDateYmd = '';
            if (!$error && $scheduleMode === 'range' && $eventsHasEndDate && $end_date !== '') {
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
                    $end_time = (string) ($dayEndTimes[$lastYmd]['end_time'] ?? '');
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

            if (!$error && !$end_time_na && $end_time !== '' && !$endTimeObj) {
                $endTimeObj = DateTime::createFromFormat('H:i', $end_time);
                if (!$endTimeObj || $endTimeObj->format('H:i') !== $end_time) {
                    $error = 'Invalid end time format.';
                }
            }

            if (!$error && $scheduleMode === 'single' && !$end_time_na && $endTimeObj && $startTimeObj && $endTimeObj <= $startTimeObj) {
                $error = "End time must be after start time on the same day.";
            }
            if (!$error && $end_time_na === false && $end_time === '' && (($_POST['end_time_option'] ?? '') === 'time')) {
                $error = "Please enter an end time or choose Not applicable.";
            }

            if (!$error) {
                $checkinErr = eventify_validate_event_checkin_times(
                    $checkin_start_param,
                    $checkin_end_param,
                    $start_time !== '' ? $start_time : null,
                    (!$end_time_na && $end_time !== '') ? $end_time : null
                );
                if ($checkinErr) {
                    $error = $checkinErr;
                }
            }

            if (!$error && $attendance_mode_param === 'time_in_out') {
                $checkoutErr = eventify_validate_checkout_end_time(
                    $checkout_end_param,
                    (!$end_time_na && $end_time !== '') ? $end_time : null
                );
                if ($checkoutErr) {
                    $error = $checkoutErr;
                }
            } elseif ($attendance_mode_param !== 'time_in_out') {
                $checkout_end_param = null;
            }

            if (!$error) {
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
                        $previewNewEventDisplayDates = count($scheduleDatesToStore) >= 1
                            ? $scheduleDatesToStore
                            : eventify_event_calendar_display_dates([
                                'date' => $date,
                                'end_date' => $endDateYmd !== '' ? $endDateYmd : null,
                            ]);
                        $orphanPlan = eventify_schedule_change_plan(
                            $conn,
                            $event_id,
                            $oldEventDisplayDates,
                            $previewNewEventDisplayDates
                        );
                        $postedDroppedAction = trim((string) ($_POST['schedule_dropped_sessions_action'] ?? ''));
                        if ($orphanPlan['dropped'] !== [] && !in_array($postedDroppedAction, ['delete', 'keep'], true)) {
                            $dropCount = count($orphanPlan['dropped']);
                            $error = $dropCount . ' activit' . ($dropCount === 1 ? 'y falls' : 'ies fall')
                                . ' on days removed from the new schedule. Choose Delete or Keep to continue.';
                            $editOrphanPlan = $orphanPlan;
                        }
                    }

                    if (!$error) {
                        $oldTitle = (string)($event['title'] ?? 'this event');
                        $eventStatusBeforeEdit = strtolower(trim((string) ($event['status'] ?? '')));
                        // Already-active events stay active so organizers are not sent back through OTP approval.
                        $organizerKeepsActive = !$isAdminEditor && $eventStatusBeforeEdit === 'active';
                        $saveWithoutReapproval = $isAdminEditor || $organizerKeepsActive;
                        $start_time_param = $start_time;
                        $end_time_param = ($end_time !== '' && !$end_time_na) ? $end_time : null;
                        $end_date_param = ($eventsHasEndDate && $endDateYmd !== '') ? $endDateYmd : null;
                        $stmt = null;
                        if ($saveWithoutReapproval) {
                            if ($eventsHasMaxCapacity) {
                                if ($eventsHasEndDate) {
                                    $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, end_date = ?, start_time = ?, end_time = ?, location = ?, department = ?, max_capacity = ? WHERE id = ? AND organizer_id = ?");
                                    if ($stmt) {
                                        $stmt->bind_param("ssssssssiii", $title, $description, $date, $end_date_param, $start_time_param, $end_time_param, $location, $department, $maxCapVal, $event_id, $session_user_id);
                                    }
                                } else {
                                    $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, start_time = ?, end_time = ?, location = ?, department = ?, max_capacity = ? WHERE id = ? AND organizer_id = ?");
                                    if ($stmt) {
                                        $stmt->bind_param("sssssssiii", $title, $description, $date, $start_time_param, $end_time_param, $location, $department, $maxCapVal, $event_id, $session_user_id);
                                    }
                                }
                            } elseif ($eventsHasEndDate) {
                                $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, end_date = ?, start_time = ?, end_time = ?, location = ?, department = ? WHERE id = ? AND organizer_id = ?");
                                if ($stmt) {
                                    $stmt->bind_param("ssssssssii", $title, $description, $date, $end_date_param, $start_time_param, $end_time_param, $location, $department, $event_id, $session_user_id);
                                }
                            } else {
                                $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, start_time = ?, end_time = ?, location = ?, department = ? WHERE id = ? AND organizer_id = ?");
                                if ($stmt) {
                                    $stmt->bind_param("sssssssii", $title, $description, $date, $start_time_param, $end_time_param, $location, $department, $event_id, $session_user_id);
                                }
                            }
                        } elseif ($eventsHasMaxCapacity) {
                            if ($eventsHasEndDate) {
                                $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, end_date = ?, start_time = ?, end_time = ?, location = ?, department = ?, max_capacity = ?, status = 'pending', reject_reason = NULL WHERE id = ? AND organizer_id = ?");
                                if ($stmt) {
                                    $stmt->bind_param("ssssssssiii", $title, $description, $date, $end_date_param, $start_time_param, $end_time_param, $location, $department, $maxCapVal, $event_id, $session_user_id);
                                }
                            } else {
                                $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, start_time = ?, end_time = ?, location = ?, department = ?, max_capacity = ?, status = 'pending', reject_reason = NULL WHERE id = ? AND organizer_id = ?");
                                if ($stmt) {
                                    $stmt->bind_param("sssssssiii", $title, $description, $date, $start_time_param, $end_time_param, $location, $department, $maxCapVal, $event_id, $session_user_id);
                                }
                            }
                        } elseif ($eventsHasEndDate) {
                            $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, end_date = ?, start_time = ?, end_time = ?, location = ?, department = ?, status = 'pending', reject_reason = NULL WHERE id = ? AND organizer_id = ?");
                            if ($stmt) {
                                $stmt->bind_param("ssssssssii", $title, $description, $date, $end_date_param, $start_time_param, $end_time_param, $location, $department, $event_id, $session_user_id);
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
                            eventify_save_event_schedule_dates($conn, $event_id, $scheduleDatesToStore, $dayEndTimes, $dayStartTimes);
                            $newEventDisplayDates = count($scheduleDatesToStore) >= 1
                                ? $scheduleDatesToStore
                                : eventify_event_calendar_display_dates([
                                    'date' => $date,
                                    'end_date' => $end_date_param,
                                ]);
                            $droppedSessionsAction = trim((string) ($_POST['schedule_dropped_sessions_action'] ?? ''));
                            if (!in_array($droppedSessionsAction, ['delete', 'keep'], true)) {
                                $droppedSessionsAction = 'leave';
                            }
                            $scheduleSessionChanges = eventify_apply_schedule_change_to_sessions(
                                $conn,
                                $event_id,
                                $oldEventDisplayDates,
                                $newEventDisplayDates,
                                $droppedSessionsAction
                            );
                            eventify_save_event_checkin_times($conn, $event_id, $checkin_start_param, $checkin_end_param);
                            if ($eventsHasAttendanceMode) {
                                eventify_save_event_attendance_settings($conn, $event_id, $attendance_mode_param, $checkout_end_param);
                            }
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
                                    : ($organizerKeepsActive
                                        ? ('Updated active event: ' . $title)
                                        : ('Updated event and sent back for approval: ' . $title))
                            );

                            try {
                                require_once __DIR__ . '/../lib/notifications_service.php';
                                $beforeSnap = [
                                    'title' => (string) ($event['title'] ?? ''),
                                    'description' => (string) ($event['description'] ?? ''),
                                    'date' => (string) ($event['date'] ?? ''),
                                    'end_date' => (string) ($event['end_date'] ?? ''),
                                    'start_time' => (string) ($event['start_time'] ?? ''),
                                    'end_time' => (string) ($event['end_time'] ?? ''),
                                    'location' => (string) ($event['location'] ?? ''),
                                    'department' => (string) ($event['department'] ?? 'ALL'),
                                    'target_sections' => $event['target_sections'] ?? null,
                                ];
                                $afterSnap = [
                                    'title' => $title,
                                    'description' => $description,
                                    'date' => $date,
                                    'end_date' => $end_date_param,
                                    'start_time' => $start_time_param,
                                    'end_time' => $end_time_param,
                                    'location' => $location,
                                    'department' => $department,
                                    'target_sections' => $targetSectionsJson,
                                    'status' => $saveWithoutReapproval ? 'active' : 'pending',
                                ];

                                if ($saveWithoutReapproval) {
                                    eventify_notify_students_event_details_changed(
                                        $conn,
                                        $event_id,
                                        $beforeSnap,
                                        $afterSnap,
                                        'live',
                                        true,
                                        true
                                    );
                                    if ($organizerKeepsActive) {
                                        $admins = $conn->query("SELECT id FROM users WHERE role IN ('admin','super_admin') AND status = 'active'");
                                        if ($admins) {
                                            $adminTitle = 'Event updated';
                                            $adminMsg = 'Organizer updated active event "' . $title . '" (previously "' . $oldTitle . '").';
                                            $insAdmin = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_updated', ?, ?, ?)");
                                            if ($insAdmin) {
                                                while ($a = $admins->fetch_assoc()) {
                                                    $adminId = (int) ($a['id'] ?? 0);
                                                    if ($adminId > 0) {
                                                        $insAdmin->bind_param('issi', $adminId, $adminTitle, $adminMsg, $event_id);
                                                        $insAdmin->execute();
                                                    }
                                                }
                                                $insAdmin->close();
                                            }
                                        }
                                    }
                                } else {
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

                            $success = $saveWithoutReapproval
                                ? 'Event updated and saved.'
                                : 'Event updated and submitted for admin approval.';
                            $droppedHandled = (int) ($scheduleSessionChanges['dropped_handled'] ?? 0);
                            if ($droppedHandled > 0) {
                                $droppedActionUsed = (string) ($scheduleSessionChanges['dropped_action'] ?? 'leave');
                                if ($droppedActionUsed === 'delete') {
                                    $success .= ' ' . $droppedHandled . ' activit' . ($droppedHandled === 1 ? 'y was' : 'ies were') . ' removed from dropped days.';
                                } elseif ($droppedActionUsed === 'keep' && !empty($newEventDisplayDates)) {
                                    $lastNewDayLabel = date('M j, Y', strtotime($newEventDisplayDates[count($newEventDisplayDates) - 1]));
                                    $success .= ' ' . $droppedHandled . ' activit' . ($droppedHandled === 1 ? 'y was' : 'ies were') . ' moved to ' . $lastNewDayLabel . '.';
                                }
                            }
                            eventify_edit_event_go_home($editHomeUrl, $success, false);
                        } else {
                            $error = "Failed to update event. Please try again.";
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }

    // If there was an error, keep form values from POST
    $event['title']       = $title;
    $event['description'] = $description;
    $event['date']        = $date;
    $event['end_date']    = $end_date;
    $event['location']    = $location;
    $event['max_capacity'] = $maxCapVal;
    $eventStartTimeValue = $start_time;
    $eventEndTimeValue = $end_time;
    $eventEndTimeNa = $end_time_na;
    $eventEndTimeOption = $end_time_na ? 'na' : ($end_time !== '' ? 'time' : 'none');
    $eventCheckinStartValue = (string) ($checkin_start_param ?? '');
    $eventCheckinEndValue = (string) ($checkin_end_param ?? '');
    $editScheduleModeValue = eventify_resolve_schedule_mode_from_request($_POST);
    $editScheduleDates = eventify_parse_schedule_dates_from_request($_POST);
    $editStartDate = trim((string) ($date ?? ''));
    $editEndDate = trim((string) ($end_date ?? ''));
    $editDayEndTimes = [];
    if (!empty($_POST['schedule_day_end']) && is_array($_POST['schedule_day_end'])) {
        foreach ($_POST['schedule_day_end'] as $ymd => $row) {
            if (!is_array($row)) {
                continue;
            }
            $editDayEndTimes[$ymd] = [
                'mode' => $row['mode'] ?? 'none',
                'time' => $row['time'] ?? '',
            ];
        }
    }
    $editDayStartTimes = $dayStartTimes;
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

$scheduleModeValue = $editScheduleModeValue;
$postedScheduleDates = $editScheduleDates;
$postedStartDate = $editStartDate;
$postedEndDate = $editEndDate;
$postedEndTimeOption = $eventEndTimeOption;
$postedEndTime = ($eventEndTimeOption === 'time') ? $eventEndTimeValue : '';
$postedDayEndTimes = $editDayEndTimes;
$postedDayStartTimes = $editDayStartTimes;
$idPrefix = 'edit';
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/eventify_modal.css?v=4">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Google Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .create-event-container {
            max-width: 760px;
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
                <input type="hidden" name="schedule_dropped_sessions_action" id="scheduleDroppedSessionsAction" value="">

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

                <?php include __DIR__ . '/../../views/partials/event_schedule_fields.php'; ?>

                <div class="form-group" id="editStartTimeRow">
                    <label for="editStartTime">Event start time <span class="required">*</span></label>
                    <div class="input-icon">
                        <i class="fas fa-clock"></i>
                        <input type="time" id="editStartTime" name="start_time" class="form-control" required
                               value="<?= htmlspecialchars($eventStartTimeValue) ?>">
                    </div>
                </div>

                <?php
                if ($eventsHasCheckinTimes) {
                    $checkinFieldsPartial = __DIR__ . '/../../views/partials/event_checkin_time_fields.php';
                    if (is_file($checkinFieldsPartial)) {
                        $idPrefix = 'edit';
                        $postedCheckinStart = $eventCheckinStartValue;
                        $postedCheckinEnd = $eventCheckinEndValue;
                        include $checkinFieldsPartial;
                    }
                    if ($eventsHasAttendanceMode) {
                        $idPrefix = 'edit';
                        $postedAttendanceMode = $eventAttendanceMode;
                        $postedCheckoutEnd = $eventCheckoutEndValue;
                        include __DIR__ . '/../../views/partials/event_attendance_mode_fields.php';
                    }
                }
                ?>

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

    <div class="modal fade" id="orphanActivitiesModal" tabindex="-1" aria-labelledby="orphanActivitiesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable efy-orphan-modal__dialog">
            <div class="modal-content efy-modal efy-modal--orphan">
                <div class="modal-header efy-modal__header">
                    <div>
                        <span class="efy-modal__eyebrow">Schedule change</span>
                        <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="orphanActivitiesModalLabel">
                            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                            Activities on removed days
                        </h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body efy-modal__body efy-orphan-modal__body">
                    <p id="orphanActivitiesMessage" class="efy-orphan-modal__lead"></p>
                    <div class="efy-orphan-modal__list-wrap">
                        <ul id="orphanActivitiesList" class="efy-orphan-modal__list"></ul>
                    </div>
                    <p id="orphanActivitiesKeepHint" class="efy-orphan-modal__hint mb-0"></p>
                </div>
                <div class="modal-footer efy-modal__footer efy-orphan-modal__footer">
                    <div class="efy-orphan-modal__actions">
                        <button type="button" class="btn efy-btn-primary" id="orphanActivitiesKeepBtn">
                            <i class="fas fa-calendar-plus" aria-hidden="true"></i>
                            Keep — move to last day
                        </button>
                        <button type="button" class="btn efy-btn-danger-outline" id="orphanActivitiesDeleteBtn">
                            <i class="fas fa-trash-alt" aria-hidden="true"></i>
                            Delete activities
                        </button>
                    </div>
                    <button type="button" class="btn efy-btn-ghost" data-bs-dismiss="modal">
                        Cancel
                    </button>
                </div>
            </div>
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
    <script src="<?= htmlspecialchars(BASE_URL) ?>/assets/js/event_schedule_picker.js?v=26"></script>
    <script>
    window.__editEventScheduleContext = {
        oldDisplayDates: <?= json_encode(array_values($oldEventDisplayDates), JSON_UNESCAPED_UNICODE) ?>,
        sessionsByDate: <?= json_encode($editSessionsByDate, JSON_UNESCAPED_UNICODE) ?>
    };
    window.__editEventPendingOrphanPreview = <?= json_encode(
        $editOrphanPlan !== null
            ? [
                'dropped' => $editOrphanPlan['dropped'],
                'lastNewDay' => $editOrphanPlan['last_new_day'],
            ]
            : null,
        JSON_UNESCAPED_UNICODE
    ) ?>;
    (function () {
        var form = document.getElementById('editEventForm');
        if (!form) return;

        var allCb = document.getElementById('editDeptAll');
        var specifics = form.querySelectorAll('.edit-dept-specific');
        var droppedActionEl = document.getElementById('scheduleDroppedSessionsAction');
        var scheduleContext = window.__editEventScheduleContext || { oldDisplayDates: [], sessionsByDate: {} };
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

        function showMessageModal(msg) {
            var el = document.getElementById('messageModalBody');
            if (el) el.textContent = msg;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('messageModal')).show();
        }

        var confirmModalEl = document.getElementById('confirmUpdateEventModal');
        var confirmYesBtn = document.getElementById('confirmUpdateEventYesBtn');
        var confirmModal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);
        var orphanModalEl = document.getElementById('orphanActivitiesModal');
        var orphanModal = orphanModalEl ? bootstrap.Modal.getOrCreateInstance(orphanModalEl) : null;
        var orphanMessageEl = document.getElementById('orphanActivitiesMessage');
        var orphanListEl = document.getElementById('orphanActivitiesList');
        var orphanKeepHintEl = document.getElementById('orphanActivitiesKeepHint');
        var orphanDeleteBtn = document.getElementById('orphanActivitiesDeleteBtn');
        var orphanKeepBtn = document.getElementById('orphanActivitiesKeepBtn');

        function normalizeScheduleDates(list) {
            return (list || []).slice().filter(Boolean).sort();
        }

        function datesBetweenInclusiveLocal(startYmd, endYmd) {
            if (!startYmd || !endYmd) {
                return startYmd ? [startYmd] : [];
            }
            if (endYmd < startYmd) {
                var swap = startYmd;
                startYmd = endYmd;
                endYmd = swap;
            }
            var parts = startYmd.split('-');
            var cur = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            var endParts = endYmd.split('-');
            var end = new Date(parseInt(endParts[0], 10), parseInt(endParts[1], 10) - 1, parseInt(endParts[2], 10));
            var out = [];
            while (cur <= end) {
                out.push(
                    cur.getFullYear() + '-' +
                    String(cur.getMonth() + 1).padStart(2, '0') + '-' +
                    String(cur.getDate()).padStart(2, '0')
                );
                cur.setDate(cur.getDate() + 1);
            }
            return out;
        }

        function getNewDisplayDatesFromForm() {
            if (typeof eventifySyncScheduleBeforeSubmit === 'function') {
                eventifySyncScheduleBeforeSubmit('edit');
            }
            if (typeof eventifyGetScheduleDisplayDates === 'function') {
                return eventifyGetScheduleDisplayDates('edit');
            }

            var modeEl = form.querySelector('.js-schedule-mode:checked');
            var mode = modeEl ? modeEl.value : 'single';
            var hiddenDates = [];
            form.querySelectorAll('input[name="schedule_dates[]"]').forEach(function (inp) {
                if (inp.value) {
                    hiddenDates.push(inp.value);
                }
            });
            hiddenDates.sort();

            if (mode === 'range' && hiddenDates.length >= 2) {
                return datesBetweenInclusiveLocal(hiddenDates[0], hiddenDates[hiddenDates.length - 1]);
            }
            if (hiddenDates.length > 0) {
                return hiddenDates;
            }

            var startEl = document.getElementById('editDate');
            var endEl = document.getElementById('editEndDate');
            var start = startEl ? startEl.value : '';
            var end = endEl ? endEl.value : '';
            if (start && end && end > start) {
                return datesBetweenInclusiveLocal(start, end);
            }
            return start ? [start] : [];
        }

        function buildScheduleDateMap(oldDisplayDates, newDisplayDates, sessionsByDate) {
            oldDisplayDates = normalizeScheduleDates(oldDisplayDates);
            newDisplayDates = normalizeScheduleDates(newDisplayDates);
            var activityDates = normalizeScheduleDates(Object.keys(sessionsByDate || {}));
            var dateMap = {};
            var useRealign = false;

            if (activityDates.length > 0) {
                var activityMismatch = activityDates.length !== newDisplayDates.length;
                if (!activityMismatch) {
                    for (var i = 0; i < activityDates.length; i++) {
                        if (oldDisplayDates.indexOf(activityDates[i]) === -1) {
                            activityMismatch = true;
                            break;
                        }
                    }
                }
                useRealign = activityMismatch;
            }

            if (useRealign) {
                for (var j = 0; j < activityDates.length; j++) {
                    if (!newDisplayDates[j]) {
                        break;
                    }
                    dateMap[activityDates[j]] = newDisplayDates[j];
                }
            } else {
                for (var k = 0; k < oldDisplayDates.length; k++) {
                    if (!newDisplayDates[k]) {
                        continue;
                    }
                    dateMap[oldDisplayDates[k]] = newDisplayDates[k];
                }
            }

            return dateMap;
        }

        function getDroppedSessionsPreview() {
            var oldDates = scheduleContext.oldDisplayDates || [];
            var newDates = getNewDisplayDatesFromForm();
            var sessionsByDate = scheduleContext.sessionsByDate || {};

            if (typeof eventifyScheduleChangeDroppedSessions === 'function') {
                return eventifyScheduleChangeDroppedSessions(oldDates, newDates, sessionsByDate);
            }

            var dateMap = buildScheduleDateMap(oldDates, newDates, sessionsByDate);
            var dropped = [];
            Object.keys(sessionsByDate).forEach(function (ymd) {
                if (dateMap[ymd]) {
                    return;
                }
                (sessionsByDate[ymd] || []).forEach(function (session) {
                    dropped.push({
                        id: session.id,
                        title: session.title || 'Activity',
                        schedule_date: ymd
                    });
                });
            });

            return {
                dropped: dropped,
                lastNewDay: newDates.length ? newDates[newDates.length - 1] : ''
            };
        }

        function showConfirmModal() {
            confirmModal.show();
        }

        function formatScheduleDayLabel(ymd) {
            if (typeof eventifyFormatScheduleDayLabel === 'function') {
                return eventifyFormatScheduleDayLabel(ymd);
            }
            var parts = String(ymd || '').split('-');
            if (parts.length !== 3) {
                return ymd;
            }
            var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            if (isNaN(d.getTime())) {
                return ymd;
            }
            return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        }

        function showOrphanActivitiesModal(preview) {
            if (!orphanModal || !orphanMessageEl || !orphanListEl) {
                showConfirmModal();
                return;
            }

            var count = preview.dropped.length;
            var dayWord = count === 1 ? 'activity' : 'activities';
            orphanMessageEl.textContent =
                'Your new schedule is shorter. ' + count + ' ' + dayWord +
                ' fall on days that are no longer part of this event.';

            orphanListEl.innerHTML = '';
            preview.dropped.slice(0, 8).forEach(function (session) {
                var li = document.createElement('li');
                li.className = 'efy-orphan-modal__item';
                var dayLabel = formatScheduleDayLabel(session.schedule_date);
                li.innerHTML =
                    '<span class="efy-orphan-modal__item-icon" aria-hidden="true"><i class="fas fa-calendar-day"></i></span>' +
                    '<span class="efy-orphan-modal__item-text">' +
                    '<strong class="efy-orphan-modal__item-title">' + String(session.title || 'Activity').replace(/</g, '&lt;') + '</strong>' +
                    '<span class="efy-orphan-modal__item-date">' + String(dayLabel).replace(/</g, '&lt;') + '</span>' +
                    '</span>';
                orphanListEl.appendChild(li);
            });
            if (preview.dropped.length > 8) {
                var more = document.createElement('li');
                more.className = 'efy-orphan-modal__item efy-orphan-modal__item--more';
                more.textContent = '…and ' + (preview.dropped.length - 8) + ' more.';
                orphanListEl.appendChild(more);
            }

            if (orphanKeepHintEl) {
                var keepDay = preview.lastNewDay || '';
                var keepLabel = keepDay ? formatScheduleDayLabel(keepDay) : keepDay;
                orphanKeepHintEl.textContent = keepLabel
                    ? ('Keep moves them to ' + keepLabel + '. Delete removes them permanently.')
                    : 'Delete removes them permanently.';
            }

            orphanModal.show();
        }

        function proceedAfterOrphanChoice(action) {
            if (droppedActionEl) {
                droppedActionEl.value = action;
            }
            if (orphanModal) {
                orphanModal.hide();
            }
            showConfirmModal();
        }

        if (orphanDeleteBtn) {
            orphanDeleteBtn.addEventListener('click', function () {
                proceedAfterOrphanChoice('delete');
            });
        }
        if (orphanKeepBtn) {
            orphanKeepBtn.addEventListener('click', function () {
                proceedAfterOrphanChoice('keep');
            });
        }
        if (orphanModalEl) {
            orphanModalEl.addEventListener('hidden.bs.modal', function () {
                if (droppedActionEl && droppedActionEl.value !== 'delete' && droppedActionEl.value !== 'keep') {
                    droppedActionEl.value = '';
                }
            });
        }

        confirmYesBtn.addEventListener('click', function () {
            confirmModal.hide();
            form.submit();
        });

        form.addEventListener('submit', function (e) {
            if (typeof eventifySyncScheduleBeforeSubmit === 'function') {
                eventifySyncScheduleBeforeSubmit('edit');
            }

            var title = (document.getElementById('title').value || '').trim();
            var dateEl = document.getElementById('editDate');
            var date = dateEl ? dateEl.value : '';
            var startEl = document.getElementById('editStartTime');
            var start = startEl ? startEl.value : '';
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
                showMessageModal('Please select an event day on the calendar.');
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

            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var eventDate = new Date(date + 'T00:00:00');
            if (eventDate < today) {
                e.preventDefault();
                showMessageModal('Event date cannot be in the past.');
                return false;
            }

            if (typeof eventifyValidateScheduleOnSubmit === 'function' && !eventifyValidateScheduleOnSubmit('edit')) {
                e.preventDefault();
                return false;
            }

            var droppedPreview = getDroppedSessionsPreview();
            if (droppedPreview.dropped.length === 0) {
                if (droppedActionEl) {
                    droppedActionEl.value = '';
                }
                e.preventDefault();
                showConfirmModal();
                return false;
            }

            var chosenAction = droppedActionEl ? droppedActionEl.value : '';
            if (chosenAction !== 'delete' && chosenAction !== 'keep') {
                e.preventDefault();
                showOrphanActivitiesModal(droppedPreview);
                return false;
            }

            e.preventDefault();
            showConfirmModal();
            return false;
        });

        var pendingOrphanPreview = window.__editEventPendingOrphanPreview;
        if (pendingOrphanPreview && pendingOrphanPreview.dropped && pendingOrphanPreview.dropped.length) {
            showOrphanActivitiesModal(pendingOrphanPreview);
        }
    })();
    </script>
</body>
</html>
<?php
$conn->close();
?>
