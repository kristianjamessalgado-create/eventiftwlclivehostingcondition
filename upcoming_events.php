<?php
session_start();

// Include DB and config
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
include __DIR__ . '/config/departments.php';
require_once __DIR__ . '/backend/lib/event_status_auto.php';
require_once __DIR__ . '/backend/lib/event_calendar.php';

eventify_run_dashboard_maintenance($conn);
eventify_events_department_ensure_varchar($conn);

// Allow logged-in users (student / multimedia / organizer) to view upcoming events
$role = $_SESSION['role'] ?? '';
$allowedRoles = ['student', 'multimedia', 'organizer', 'admin', 'super_admin'];
if (!isset($_SESSION['user_id']) || !in_array($role, $allowedRoles, true)) {
    header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Access denied"));
    exit();
}

// Logged-in user's ID
$session_user_id = $_SESSION['user_id'];

// Fetch user info (including department)
$stmt = $conn->prepare("SELECT id, user_id, name, department FROM users WHERE id = ?");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Safe defaults
$user_name  = $user['name'] ?? 'Student';
$department = $user['department'] ?? null;
$events     = [];
$msg        = $_GET['msg'] ?? '';

// Back link based on role
$backUrl = BASE_URL . "/index.php";
$backLabel = "Back";
switch ($role) {
    case 'student':
        $backUrl = BASE_URL . "/backend/auth/dashboard_student.php";
        $backLabel = "Back to Dashboard";
        break;
    case 'multimedia':
        $backUrl = BASE_URL . "/backend/auth/dashboard_multimedia.php";
        $backLabel = "Back to Dashboard";
        break;
    case 'organizer':
        $backUrl = BASE_URL . "/backend/auth/dashboardorganizer.php";
        $backLabel = "Back to Dashboard";
        break;
    case 'admin':
        $backUrl = BASE_URL . "/backend/admin/dashboard.php";
        $backLabel = "Back to Dashboard";
        break;
    case 'super_admin':
        $backUrl = BASE_URL . "/backend/super_admin/dashboardsuperadmin.php";
        $backLabel = "Back to Dashboard";
        break;
}


// Fetch active events for the user's department; filter to truly upcoming after schedule is loaded
$deptSql = eventify_department_match_sql('department');
if ($department) {
    $stmt2 = $conn->prepare("SELECT * FROM events WHERE status = 'active' AND {$deptSql} ORDER BY date ASC");
    $stmt2->bind_param('ss', $department, $department);
} else {
    $stmt2 = $conn->prepare("SELECT * FROM events WHERE status = 'active' ORDER BY date ASC");
}

if ($stmt2 && $stmt2->execute()) {
    $result2 = $stmt2->get_result();
    if ($result2) {
        $events = $result2->fetch_all(MYSQLI_ASSOC);
    }
    $stmt2->close();
}

eventify_events_attach_schedule_dates($conn, $events);
$events = array_values(array_filter($events, static function ($row) {
    return eventify_event_is_upcoming($row);
}));

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upcoming Events - EVENTIFY</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100vh;
            color: #1f2937;
        }
        
        .top-navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 2rem;
            background: rgba(10, 10, 10, 0.85);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 800;
            font-size: 1.4rem;
            color: #e7e7e7;
            text-decoration: none;
            letter-spacing: -0.03em;
        }
        
        .brand-logo i { color: #00bfff; font-size: 1.5rem; }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.1rem;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.15);
            transition: all 0.2s;
        }
        
        .back-link:hover {
            color: #00bfff;
            border-color: #00bfff;
            background: rgba(0, 191, 255, 0.1);
        }
        
        .container-main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2.5rem 2rem 4rem;
        }
        
        .page-header {
            margin-bottom: 2.5rem;
            padding: 2.5rem 2.5rem;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #00bfff, #00ffff);
        }
        
        .page-header h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            letter-spacing: -0.03em;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #1e293b;
        }
        
        .page-header h1 i { color: #00bfff; }
        .page-header p { color: #64748b; font-size: 1rem; margin: 0; }
        
        .alert-wrapper { margin-bottom: 2rem; }
        .alert { border-radius: 12px; padding: 1rem 1.25rem; }
        
        .section-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #64748b;
            margin-bottom: 1.5rem;
        }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        
        .event-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.75rem 1.75rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }
        
        .event-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, #00bfff, #00ffff);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .event-card:hover {
            border-color: #00bfff;
            box-shadow: 0 8px 28px rgba(0, 191, 255, 0.15);
            transform: translateY(-2px);
        }
        
        .event-card:hover::before { opacity: 1; }
        
        .event-card-top {
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }
        
        .event-date-badge {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 68px;
            height: 68px;
            background: rgba(0, 191, 255, 0.12);
            color: #0099cc;
            border: 1px solid rgba(0, 191, 255, 0.3);
            border-radius: 14px;
            flex-shrink: 0;
        }
        
        .event-month {
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            line-height: 1;
        }
        
        .event-day { font-size: 1.75rem; font-weight: 800; line-height: 1; margin-top: 0.15rem; }
        
        .event-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 0.6rem 0;
            line-height: 1.35;
            padding-top: 0.2rem;
        }
        
        .event-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .event-meta i { color: #00bfff; width: 1rem; text-align: center; }
        
        .event-desc {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.6;
            margin: 0 0 1rem 0;
        }
        
        .event-dept {
            display: inline-block;
            padding: 0.4rem 0.9rem;
            background: rgba(0, 191, 255, 0.12);
            color: #0099cc;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .event-status-ended {
            display: inline-block;
            margin-right: 0.5rem;
            padding: 0.35rem 0.75rem;
            background: rgba(100, 116, 139, 0.2);
            color: #475569;
            border: 1px solid rgba(100, 116, 139, 0.45);
            border-radius: 8px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .event-card.is-ended {
            opacity: 0.92;
            border-color: rgba(100, 116, 139, 0.35);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2.5rem;
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            border-radius: 20px;
            color: #64748b;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }
        
        .empty-state i { font-size: 4rem; margin-bottom: 1.25rem; color: #00bfff; opacity: 0.6; }
        .empty-state h3 { font-size: 1.35rem; margin-bottom: 0.5rem; color: #1e293b; font-weight: 700; }
        .empty-state p { font-size: 1rem; max-width: 360px; margin: 0 auto; line-height: 1.6; }
        
        @media (max-width: 768px) {
            .top-navbar { padding: 0.875rem 1.25rem; }
            .container-main { padding: 1.5rem 1.25rem 3rem; }
            .page-header { padding: 1.75rem 1.5rem; margin-bottom: 2rem; }
            .page-header h1 { font-size: 1.5rem; }
            .events-grid { grid-template-columns: 1fr; gap: 1.25rem; }
            .event-card { padding: 1.5rem 1.25rem; }
            .event-card-top { gap: 1rem; margin-bottom: 1rem; }
            .event-date-badge { min-width: 58px; height: 58px; }
            .event-day { font-size: 1.5rem; }
            .empty-state { padding: 3rem 1.5rem; }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-navbar">
        <div class="navbar-left">
            <a href="<?= htmlspecialchars($backUrl) ?>" class="brand-logo">
                <i class="fas fa-calendar-alt"></i>
                <span>EVENTIFY</span>
            </a>
        </div>
        <div class="navbar-right">
            <a href="<?= htmlspecialchars($backUrl) ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> <?= htmlspecialchars($backLabel) ?>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-main">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> Upcoming Events</h1>
            <p>
                <?php if ($department): ?>
                    View all upcoming events for your department.
                <?php else: ?>
                    View all upcoming events.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($msg): ?>
            <div class="alert-wrapper">
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($events)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Upcoming Events</h3>
                <p>There are no upcoming events scheduled for your department at this time.</p>
            </div>
        <?php else: ?>
            <p class="section-label">Events (<?= count($events) ?>)</p>
            <div class="events-grid">
                <?php foreach ($events as $event): ?>
                    <?php
                        $evStatus = strtolower((string)($event['status'] ?? ''));
                        $isEndedListing = ($evStatus === 'closed' || $evStatus === 'completed');
                    ?>
                    <div class="event-card<?= $isEndedListing ? ' is-ended' : '' ?>">
                        <div class="event-card-top">
                            <div class="event-date-badge">
                                <span class="event-month"><?= date('M', strtotime($event['date'])) ?></span>
                                <span class="event-day"><?= date('d', strtotime($event['date'])) ?></span>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <h3 class="event-title"><?= htmlspecialchars($event['title'] ?? 'Untitled') ?></h3>
                                <div class="event-meta">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?= htmlspecialchars($event['location'] ?? 'TBA') ?></span>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($event['description'])): ?>
                            <p class="event-desc"><?= htmlspecialchars($event['description']) ?></p>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <?php if ($isEndedListing): ?>
                                <span class="event-status-ended">Ended</span>
                            <?php endif; ?>
                            <?php if ($event['department'] !== 'ALL'): ?>
                                <span class="event-dept"><?= htmlspecialchars($event['department']) ?></span>
                            <?php else: ?>
                                <span class="event-dept">All Departments</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
