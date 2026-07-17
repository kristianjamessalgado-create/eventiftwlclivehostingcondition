<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/config.php';

$checkin_token = trim($_GET['t'] ?? '');
$session_checkin_token = trim($_GET['st'] ?? '');
$ticket_checkin_token = trim($_GET['tk'] ?? '');
$auth_modal = trim((string)($_GET['auth_modal'] ?? ''));
$auth_error = trim((string)($_GET['auth_error'] ?? ''));
$auth_success = trim((string)($_GET['auth_success'] ?? ''));
$auth_redirect = trim((string)($_GET['redirect'] ?? ''));
// Session expired / not logged in should open sign-in quietly (no scary "Access denied").
if ($auth_error !== '' && stripos($auth_error, 'access denied') !== false) {
    $auth_error = '';
    if ($auth_modal === '') {
        $auth_modal = 'login';
    }
}
$verify_purpose = (($_GET['verify_purpose'] ?? $_GET['purpose'] ?? 'register') === 'reactivate') ? 'reactivate' : 'register';
$verify_email = strtolower(trim((string)($_GET['verify_email'] ?? $_GET['email'] ?? '')));
if ($auth_modal === 'verify' && $verify_email !== '') {
    require_once __DIR__ . '/backend/lib/account_email_otp.php';
    $verifyFlash = eventify_consume_verify_otp_flash($verify_purpose, $verify_email);
    if ($verifyFlash['success'] !== '') {
        $auth_success = $verifyFlash['success'];
        $auth_error = '';
    } elseif ($verifyFlash['error'] !== '') {
        $auth_error = $verifyFlash['error'];
        $auth_success = '';
    } else {
        // Do not keep stale success text from an old bookmarked/refreshed URL.
        $auth_success = '';
    }
}
$skip_landing_splash = ($auth_modal !== '' || $auth_error !== '' || $auth_success !== '');
$studentCourseOptions = [];
$studentYearLevelOptions = [];
try {
    require_once __DIR__ . '/config/student_profile_fields.php';
    if (function_exists('eventify_student_course_program_options')) {
        $studentCourseOptions = eventify_student_course_program_options();
    }
    if (function_exists('eventify_student_year_level_options')) {
        $studentYearLevelOptions = eventify_student_year_level_options();
    }
} catch (Throwable $e) {
    $studentCourseOptions = [];
    $studentYearLevelOptions = [];
}

// Public landing: show upcoming active events on a calendar (login required to view details/RSVP)
$publicCalendarEvents = [];
$publicUpcomingList = [];
$publicPastList = [];
$publicAllList = [];
$publicPhotoPreviewList = [];
try {
    include __DIR__ . '/config/db.php';
    include __DIR__ . '/config/config.php';
    include __DIR__ . '/config/csrf.php';
    require_once __DIR__ . '/backend/lib/event_calendar.php';
    $today = date('Y-m-d');
    $stmtPub = $conn->prepare("
        SELECT id, title, description, date, end_date, start_time, end_time, location, department, status
        FROM events
        WHERE status IN ('active','completed','closed')
        ORDER BY date DESC, start_time DESC, id DESC
        LIMIT 400
    ");
    $pubRows = [];
    if ($stmtPub) {
        if ($stmtPub->execute()) {
            $res = $stmtPub->get_result();
            while ($row = $res->fetch_assoc()) {
                if (trim($row['date'] ?? '') === '') {
                    continue;
                }
                $pubRows[] = $row;
            }
        }
        $stmtPub->close();
    }
    eventify_events_attach_schedule_dates($conn, $pubRows);
    foreach ($pubRows as $row) {
        $date = trim($row['date'] ?? '');
        $publicAllList[] = [
            'id' => (int)($row['id'] ?? 0),
            'title' => (string)($row['title'] ?? 'Untitled'),
            'description' => (string)($row['description'] ?? ''),
            'date' => $date,
            'end_date' => trim((string)($row['end_date'] ?? '')),
            'schedule_dates' => $row['schedule_dates'] ?? [],
            'start_time' => trim((string)($row['start_time'] ?? '')),
            'end_time' => trim((string)($row['end_time'] ?? '')),
            'location' => (string)($row['location'] ?? ''),
            'department' => (string)($row['department'] ?? 'ALL'),
            'status' => (string)($row['status'] ?? 'active'),
        ];
    }
    $publicCalendarEvents = eventify_events_to_fullcalendar_list($pubRows, static function ($row) {
        return [
            'location' => $row['location'] ?? '',
            'department' => $row['department'] ?? 'ALL',
            'status' => $row['status'] ?? '',
        ];
    });
    // Landing photo preview rail (prefer published-only if migration exists)
    $photoStatusEnabled = false;
    try {
        $colRes = $conn->query("SHOW COLUMNS FROM event_photos LIKE 'status'");
        $photoStatusEnabled = (bool)($colRes && $colRes->num_rows > 0);
    } catch (Throwable $e) {
        $photoStatusEnabled = false;
    }

    $photoSql = "
        SELECT p.file_path, p.created_at, e.id AS event_id, e.title, e.date
        FROM event_photos p
        INNER JOIN events e ON e.id = p.event_id
        WHERE e.title NOT LIKE 'sample%'
    ";
    if ($photoStatusEnabled) {
        $photoSql .= " AND p.status = 'published' ";
    }
    // Pull a wider pool first, then group in PHP (1 card per event, rotating photos per event).
    $photoSql .= " ORDER BY p.created_at DESC, p.id DESC LIMIT 220 ";

    $stmtPhotos = $conn->prepare($photoSql);
    if ($stmtPhotos && $stmtPhotos->execute()) {
        $resPhotos = $stmtPhotos->get_result();
        $photoGroupsByEvent = [];
        $eventOrder = [];
        while ($photo = $resPhotos->fetch_assoc()) {
            $path = trim((string)($photo['file_path'] ?? ''));
            if ($path === '') continue;
            $eventId = (int)($photo['event_id'] ?? 0);
            if ($eventId < 1) continue;

            if (!isset($photoGroupsByEvent[$eventId])) {
                $photoGroupsByEvent[$eventId] = [
                    'event_title' => (string)($photo['title'] ?? 'School event'),
                    'event_date' => (string)($photo['date'] ?? ''),
                    'photo_paths' => [],
                ];
                $eventOrder[] = $eventId;
            }
            // Keep a small set per event for fade rotation.
            if (count($photoGroupsByEvent[$eventId]['photo_paths']) < 6) {
                $photoGroupsByEvent[$eventId]['photo_paths'][] = $path;
            }
        }
        foreach ($eventOrder as $eid) {
            if (count($publicPhotoPreviewList) >= 24) break;
            $group = $photoGroupsByEvent[$eid] ?? null;
            if (!$group || empty($group['photo_paths'])) continue;
            $publicPhotoPreviewList[] = [
                'file_path' => (string)$group['photo_paths'][0],
                'event_title' => (string)($group['event_title'] ?? 'School event'),
                'event_date' => (string)($group['event_date'] ?? ''),
                'photo_paths' => array_values($group['photo_paths']),
            ];
        }
        $stmtPhotos->close();
    }

    if (isset($conn) && $conn) {
        $conn->close();
    }
} catch (Throwable $e) {
    $publicCalendarEvents = [];
    $publicUpcomingList = [];
    $publicPastList = [];
    $publicAllList = [];
    $publicPhotoPreviewList = [];
}

// Split for display
try {
    $publicUpcomingList = array_values(array_filter($publicAllList, function ($e) use ($today) {
        $d = (string)($e['date'] ?? '');
        return $d !== '' && $d >= $today;
    }));
    $publicPastList = array_values(array_filter($publicAllList, function ($e) use ($today) {
        $d = (string)($e['date'] ?? '');
        return $d !== '' && $d < $today;
    }));
} catch (Throwable $e) {
    $publicPastList = [];
}

if (!function_exists('eventify_qr_checkin_route')) {
    function eventify_qr_checkin_route(string $relativePath): void
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $targetPath = BASE_URL . $relativePath;
        $absolute = $scheme . '://' . $host . $targetPath;
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        $role = strtolower((string) ($_SESSION['role'] ?? ''));

        if ($uid > 0 && $role === 'student') {
            header('Location: ' . $targetPath);
            exit();
        }
        if ($uid > 0 && $role !== 'student') {
            header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode($absolute) . '&error=' . urlencode('Please sign in as a student to check in.'));
            exit();
        }
        header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode($absolute));
        exit();
    }
}

if ($session_checkin_token !== '') {
    eventify_qr_checkin_route('/activity_checkin.php?st=' . urlencode($session_checkin_token));
}

if ($ticket_checkin_token !== '') {
    eventify_qr_checkin_route('/ticket_checkin.php?tk=' . urlencode($ticket_checkin_token));
}

if ($checkin_token !== '') {
    eventify_qr_checkin_route('/checkin.php?t=' . urlencode($checkin_token));
}

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // Enforce password change before dashboard access if flagged.
    try {
        include __DIR__ . '/config/db.php';
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid > 0) {
            $cp = $conn->prepare("SELECT must_change_password FROM users WHERE id = ? LIMIT 1");
            if ($cp) {
                $cp->bind_param("i", $uid);
                $cp->execute();
                $cpRow = $cp->get_result()->fetch_assoc();
                $cp->close();
                if ((int)($cpRow['must_change_password'] ?? 0) === 1) {
                    $next = BASE_URL . "/index.php";
                    if ($_SESSION['role'] === 'super_admin') $next = BASE_URL . "/backend/super_admin/dashboardsuperadmin.php";
                    elseif ($_SESSION['role'] === 'admin') $next = BASE_URL . "/backend/admin/dashboard.php";
                    elseif ($_SESSION['role'] === 'organizer') $next = BASE_URL . "/backend/auth/dashboardorganizer.php";
                    elseif ($_SESSION['role'] === 'student') $next = BASE_URL . "/backend/auth/dashboard_student.php";
                    elseif ($_SESSION['role'] === 'multimedia') $next = BASE_URL . "/backend/auth/dashboard_multimedia.php";
                    header("Location: " . BASE_URL . "/views/change_password.php?from=required&next=" . urlencode($next));
                    exit();
                }
            }
        }
    } catch (Throwable $e) {
        // ignore and continue default routing
    }
    switch ($_SESSION['role']) {
        case 'super_admin':
            header("Location: " . BASE_URL . "/backend/super_admin/dashboardsuperadmin.php");
            exit();
        case 'admin':
            header("Location: " . BASE_URL . "/backend/admin/dashboard.php");
            exit();
        case 'organizer':
            header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php");
            exit();
        case 'student':
            header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php");
            exit();
        case 'multimedia':
            header("Location: " . BASE_URL . "/backend/auth/dashboard_multimedia.php");
            exit();
    }
}

// Build login iframe URL: if we have a check-in token, pass redirect so after login they go to check-in
$login_src = BASE_URL . '/views/login.php';
if ($checkin_token !== '') {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $checkin_redirect = $scheme . '://' . $host . BASE_URL . '/checkin.php?t=' . urlencode($checkin_token);
    $login_src .= '?redirect=' . urlencode($checkin_redirect);
}

$landing_upcoming_n = count($publicUpcomingList);
$landing_past_n = count($publicPastList);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>EVENTIFY</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/index.css?v=31">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/calendar_legend.css">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<style>
html.eventify-splash-pending,
html.eventify-splash-pending body { overflow: hidden; }

#eventifySplash {
    position: fixed;
    inset: 0;
    z-index: 2147483000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 1;
    visibility: visible;
    background:
        radial-gradient(ellipse 70% 55% at 50% 38%, rgba(6, 78, 59, 0.55) 0%, transparent 62%),
        linear-gradient(168deg, #010f0c 0%, #022c22 42%, #011612 100%);
    transition: opacity 0.55s ease, visibility 0.55s ease;
}

#eventifySplash.eventify-splash--hide {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}
</style>
<?php if (!$skip_landing_splash): ?>
<script>document.documentElement.classList.add('eventify-splash-pending', 'eventify-landing-enter');</script>
<?php else: ?>
<script>document.documentElement.classList.add('eventify-landing-enter--done');</script>
<?php endif; ?>
</head>
<body>

<?php if (!$skip_landing_splash): ?>
<!-- Loading splash -->
<div id="eventifySplash" class="eventify-splash" role="status" aria-live="polite" aria-label="Loading EVENTIFY" data-splash-managed="inline">
    <div class="eventify-splash__glow" aria-hidden="true"></div>
    <div class="eventify-splash__inner">
        <div class="eventify-splash__ring" aria-hidden="true">
            <i class="fas fa-calendar-check"></i>
        </div>
        <h1 class="eventify-splash__brand"><span class="eventify-splash__brand-a">EVENT</span><span class="eventify-splash__brand-b">IFY</span></h1>
        <div class="eventify-splash__bar" aria-hidden="true">
            <span class="eventify-splash__bar-fill" id="eventifySplashFill"></span>
        </div>
        <p class="eventify-splash__status" id="eventifySplashStatus">
            <span class="eventify-splash__status-dot" aria-hidden="true"></span>
            <span id="eventifySplashStatusText">Initializing Eventify</span>
        </p>
    </div>
</div>
<script>
(function () {
    var splash = document.getElementById('eventifySplash');
    if (!splash || splash.dataset.splashDone === '1') return;
    splash.dataset.splashDone = '1';

    var fill = document.getElementById('eventifySplashFill');
    var statusText = document.getElementById('eventifySplashStatusText');
    var minVisibleMs = 1900;
    var finished = false;
    var visibleSince = null;
    var progress = 0;

    var tick = window.setInterval(function () {
        if (finished) return;
        progress = Math.min(100, progress + (Math.random() * 7 + 3));
        if (fill) fill.style.width = progress + '%';
    }, 140);

    function revealLanding() {
        document.documentElement.classList.add('eventify-landing-enter--active');
        window.setTimeout(function () {
            document.documentElement.classList.add('eventify-landing-enter--done');
            document.documentElement.classList.remove('eventify-landing-enter', 'eventify-landing-enter--active');
        }, 720);
    }

    function finishSplash() {
        if (finished) return;
        finished = true;
        window.clearInterval(tick);
        if (fill) fill.style.width = '100%';
        if (statusText) statusText.textContent = 'Ready';
        splash.classList.add('eventify-splash--hide');
        revealLanding();
        window.setTimeout(function () {
            if (splash.parentNode) splash.parentNode.removeChild(splash);
            document.documentElement.classList.remove('eventify-splash-pending');
            try {
                window.dispatchEvent(new CustomEvent('eventify:splash-done'));
            } catch (e) {
                window.dispatchEvent(new Event('eventify:splash-done'));
            }
        }, 580);
    }

    function scheduleFinish() {
        if (finished) return;
        var base = visibleSince !== null ? visibleSince : Date.now();
        var wait = Math.max(0, minVisibleMs - (Date.now() - base));
        window.setTimeout(finishSplash, wait);
    }

    function markVisible() {
        if (visibleSince !== null) return;
        visibleSince = Date.now();
    }

    requestAnimationFrame(function () {
        requestAnimationFrame(markVisible);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', markVisible, { once: true });
    } else {
        markVisible();
    }

    if (document.readyState === 'complete') {
        scheduleFinish();
    } else {
        window.addEventListener('load', scheduleFinish, { once: true });
    }

    window.setTimeout(finishSplash, 7000);

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) scheduleFinish();
    });
})();
</script>
<?php endif; ?>

<!-- Background layers -->
<img src="<?= BASE_URL ?>/assets/img/gradient.png" alt="Background" class="bg-image">
<div class="layer-blur"></div>
<div class="noise-overlay" aria-hidden="true"></div>

<!-- Header -->
<header class="site-header">
    <div class="header-main">
        <h2 class="brand-wordmark">EVENTIFY</h2>
        <button type="button" class="hamburger" id="hamburgerBtn" aria-label="Open menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav class="desktop-nav">
            <a href="javascript:void(0)" data-nav-section="public-calendar" onclick="goToSection('public-calendar')">Home</a>
            <a href="javascript:void(0)" data-nav-section="how-it-works" onclick="goToSection('how-it-works')">How it works</a>
            <a href="javascript:void(0)" data-nav-section="features" onclick="goToSection('features')">Features</a>
            <a href="javascript:void(0)" data-nav-section="roles" onclick="goToSection('roles')">Roles</a>
            <a href="javascript:void(0)" data-nav-section="faq" onclick="goToSection('faq')">FAQ</a>
            <span class="header-nav-actions">
                <a href="javascript:void(0)" class="btn btn-outline btn-nav-login login-trigger" data-login-url="<?= htmlspecialchars($login_src) ?>">Log in</a>
                <span class="magnetic-wrap">
                    <a href="javascript:void(0)" class="btn btn-shimmer get-started-trigger" id="navGetStarted">Take a quick tour</a>
                </span>
            </span>
        </nav>
        <div class="mobile-nav-overlay" id="mobileNavOverlay" onclick="closeMobileNav()" aria-hidden="true"></div>
        <nav class="mobile-nav" id="mobileNav">
            <a href="javascript:void(0)" data-nav-section="public-calendar" onclick="goToSection('public-calendar'); closeMobileNav();">Home</a>
            <a href="javascript:void(0)" data-nav-section="how-it-works" onclick="goToSection('how-it-works'); closeMobileNav();">How it works</a>
            <a href="javascript:void(0)" data-nav-section="features" onclick="goToSection('features'); closeMobileNav();">Features</a>
            <a href="javascript:void(0)" data-nav-section="roles" onclick="goToSection('roles'); closeMobileNav();">Roles</a>
            <a href="javascript:void(0)" data-nav-section="faq" onclick="goToSection('faq'); closeMobileNav();">FAQ</a>
            <a href="javascript:void(0)" onclick="closeMobileNav()" class="btn btn-outline login-trigger" data-login-url="<?= htmlspecialchars($login_src) ?>">Log in</a>
            <a href="javascript:void(0)" onclick="closeMobileNav(); openGetStartedGuide();" class="btn btn-shimmer get-started-trigger">Take a quick tour</a>
        </nav>
    </div>
    <div class="header-progress-track" aria-hidden="true">
        <span class="header-progress-fill" id="landingScrollProgress"></span>
    </div>
</header>

<!-- Sections -->
<section id="public-calendar" class="active reveal-scope in-view">
    <div class="landing-calendar-video-wrap reveal-item" style="--reveal-d: 0ms">
        <video class="landing-calendar-video" autoplay muted loop playsinline aria-label="EVENTIFY promotional video">
            <source src="<?= BASE_URL ?>/assets/video/adminvid.mov" type="video/quicktime">
            <source src="<?= BASE_URL ?>/assets/video/adminvid.mov" type="video/mp4">
        </video>
        <div class="landing-calendar-video-overlay reveal-item" style="--reveal-d: 40ms">
            <p class="landing-hero-eyebrow">School Events Monitoring System</p>
            <h1>Plan and track every school event in one place.</h1>
            <p class="landing-hero-lead">One calendar for admins, organizers, and students. Browse what&rsquo;s coming up, then log in for RSVP and full details.</p>
            <div class="landing-hero-actions">
                <span class="magnetic-wrap">
                    <a href="javascript:void(0)" class="btn btn-shimmer get-started-trigger">Take a quick tour</a>
                </span>
                <a href="javascript:void(0)" class="btn btn-outline login-trigger" data-login-url="<?= htmlspecialchars($login_src) ?>">Log in</a>
            </div>
            <div class="landing-stat-strip">
                <span class="stat-pill" title="Posted events (active or ended) on or after today."><strong><?= (int) $landing_upcoming_n ?></strong> <span class="stat-pill-label">from today</span></span>
                <span class="stat-pill stat-pill-muted" title="Posted events (active or ended) before today."><strong><?= (int) $landing_past_n ?></strong> <span class="stat-pill-label">earlier</span></span>
                <span class="stat-pill stat-pill-hint"><i class="fas fa-lock" aria-hidden="true"></i> Log in for details &amp; RSVP</span>
            </div>
        </div>
    </div>

    <div class="public-upcoming-wrap reveal-item" style="--reveal-d: 140ms">
        <div class="landing-photo-header">
            <h3 class="public-upcoming-title">Trending now</h3>
            <span class="landing-photo-subtitle">Published moments from recent events</span>
        </div>
        <?php if (!empty($publicPhotoPreviewList)): ?>
            <div class="landing-photo-rail-wrap">
                <button type="button" class="landing-rail-nav landing-rail-prev" aria-label="Scroll previews left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="landing-photo-rail" id="landingPhotoRail">
                    <?php foreach ($publicPhotoPreviewList as $idx => $photo): ?>
                        <?php
                            $photoSrc = BASE_URL . '/' . ltrim((string)($photo['file_path'] ?? ''), '/');
                            $photoTitle = (string)($photo['event_title'] ?? 'School event');
                            $photoDate = '';
                            try { $photoDate = date('M d, Y', strtotime((string)($photo['event_date'] ?? ''))); } catch (Throwable $e) { $photoDate = ''; }
                            $rank = (int)$idx + 1;
                            $photoPaths = array_values(array_filter((array)($photo['photo_paths'] ?? []), function ($p) {
                                return is_string($p) && trim($p) !== '';
                            }));
                            $photoUrls = [];
                            foreach ($photoPaths as $pp) {
                                $photoUrls[] = BASE_URL . '/' . ltrim($pp, '/');
                            }
                        ?>
                        <a class="landing-photo-card login-trigger" href="javascript:void(0)" data-login-url="<?= htmlspecialchars($login_src) ?>" data-photo-urls="<?= htmlspecialchars(json_encode($photoUrls, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)) ?>" aria-label="Log in to view photos for <?= htmlspecialchars($photoTitle) ?>">
                            <span class="landing-photo-rank"><?= $rank ?></span>
                            <img class="landing-photo-img" src="<?= htmlspecialchars($photoSrc) ?>" alt="<?= htmlspecialchars($photoTitle) ?>" loading="lazy" decoding="async">
                            <span class="landing-photo-overlay"></span>
                            <span class="landing-photo-meta">
                                <strong><?= htmlspecialchars($photoTitle) ?></strong>
                                <?php if ($photoDate !== ''): ?><small><?= htmlspecialchars($photoDate) ?></small><?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="landing-rail-nav landing-rail-next" aria-label="Scroll previews right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        <?php else: ?>
            <div class="public-empty">No published photos yet.</div>
        <?php endif; ?>
    </div>

    <div class="landing-events-panel reveal-item" style="--reveal-d: 160ms">
        <div class="landing-events-tabs" role="tablist" aria-label="Browse events">
            <button type="button" class="landing-events-tab is-active" role="tab" id="landingTabBtnUpcoming" aria-selected="true" aria-controls="landingTabUpcoming" data-landing-tab="upcoming">
                <i class="fas fa-calendar-day" aria-hidden="true"></i> Upcoming
            </button>
            <button type="button" class="landing-events-tab" role="tab" id="landingTabBtnPast" aria-selected="false" aria-controls="landingTabPast" data-landing-tab="past">
                <i class="fas fa-clock-rotate-left" aria-hidden="true"></i> Past
            </button>
            <button type="button" class="landing-events-tab" role="tab" id="landingTabBtnCalendar" aria-selected="false" aria-controls="landingTabCalendar" data-landing-tab="calendar">
                <i class="fas fa-calendar" aria-hidden="true"></i> Calendar
            </button>
        </div>

        <div class="landing-tab-panel is-active" id="landingTabUpcoming" role="tabpanel" aria-labelledby="landingTabBtnUpcoming">
            <h3 class="public-upcoming-title">Upcoming events</h3>
            <?php if (!empty($publicUpcomingList)): ?>
                <div class="public-upcoming-grid">
                    <?php foreach (array_slice($publicUpcomingList, 0, 6) as $ev): ?>
                        <?php
                          $dateLabel = '';
                          try { $dateLabel = date('M d, Y', strtotime((string)($ev['date'] ?? ''))); } catch (Throwable $e) { $dateLabel = (string)($ev['date'] ?? ''); }
                          $timeLabel = '';
                          $st = trim((string)($ev['start_time'] ?? ''));
                          $et = trim((string)($ev['end_time'] ?? ''));
                          if ($st !== '' && $et !== '') $timeLabel = $st . ' - ' . $et;
                          elseif ($st !== '') $timeLabel = $st;
                          $deptLabel = trim((string)($ev['department'] ?? ''));
                          $locLabel = trim((string)($ev['location'] ?? ''));
                          $evSt = strtolower((string)($ev['status'] ?? ''));
                          $isEndedCard = ($evSt === 'closed' || $evSt === 'completed');
                        ?>
                        <a class="public-upcoming-card login-trigger<?= $isEndedCard ? ' public-upcoming-card-ended' : '' ?>" href="javascript:void(0)" data-login-url="<?= htmlspecialchars($login_src) ?>" aria-label="Log in to view <?= htmlspecialchars((string)($ev['title'] ?? 'event')) ?>">
                            <div class="public-upcoming-card-top">
                                <div class="public-upcoming-card-title"><?= htmlspecialchars((string)($ev['title'] ?? 'Untitled')) ?></div>
                                <div class="public-upcoming-card-meta">
                                    <span><?= htmlspecialchars($dateLabel) ?></span>
                                    <?php if ($timeLabel !== ''): ?><span class="dot">•</span><span><?= htmlspecialchars($timeLabel) ?></span><?php endif; ?>
                                </div>
                            </div>
                            <div class="public-upcoming-card-bottom">
                                <?php if ($isEndedCard): ?><span class="chip chip-ended">Ended</span><?php endif; ?>
                                <?php if ($deptLabel !== ''): ?><span class="chip"><?= htmlspecialchars($deptLabel) ?></span><?php endif; ?>
                                <?php if ($locLabel !== ''): ?><span class="chip chip-muted"><?= htmlspecialchars($locLabel) ?></span><?php endif; ?>
                                <span class="chip chip-cta">Log in to view</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (count($publicUpcomingList) > 6): ?>
                    <div class="public-upcoming-more">
                        <button type="button" class="btn btn-outline login-trigger" data-login-url="<?= htmlspecialchars($login_src) ?>">View all events (login)</button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="public-empty">No upcoming events posted yet.</div>
            <?php endif; ?>
        </div>

        <div class="landing-tab-panel" id="landingTabPast" role="tabpanel" aria-labelledby="landingTabBtnPast" hidden>
            <h3 class="public-upcoming-title">Past events</h3>
            <?php if (!empty($publicPastList)): ?>
                <div class="public-upcoming-grid">
                    <?php foreach (array_slice($publicPastList, 0, 6) as $ev): ?>
                        <?php
                          $dateLabel = '';
                          try { $dateLabel = date('M d, Y', strtotime((string)($ev['date'] ?? ''))); } catch (Throwable $e) { $dateLabel = (string)($ev['date'] ?? ''); }
                          $timeLabel = '';
                          $st = trim((string)($ev['start_time'] ?? ''));
                          $et = trim((string)($ev['end_time'] ?? ''));
                          if ($st !== '' && $et !== '') $timeLabel = $st . ' - ' . $et;
                          elseif ($st !== '') $timeLabel = $st;
                          $deptLabel = trim((string)($ev['department'] ?? ''));
                          $locLabel = trim((string)($ev['location'] ?? ''));
                          $evStPast = strtolower((string)($ev['status'] ?? ''));
                          $isEndedPastCard = ($evStPast === 'closed' || $evStPast === 'completed');
                        ?>
                        <a class="public-upcoming-card login-trigger<?= $isEndedPastCard ? ' public-upcoming-card-ended' : '' ?>" href="javascript:void(0)" data-login-url="<?= htmlspecialchars($login_src) ?>" aria-label="Log in to view <?= htmlspecialchars((string)($ev['title'] ?? 'event')) ?>">
                            <div class="public-upcoming-card-top">
                                <div class="public-upcoming-card-title"><?= htmlspecialchars((string)($ev['title'] ?? 'Untitled')) ?></div>
                                <div class="public-upcoming-card-meta">
                                    <span><?= htmlspecialchars($dateLabel) ?></span>
                                    <?php if ($timeLabel !== ''): ?><span class="dot">•</span><span><?= htmlspecialchars($timeLabel) ?></span><?php endif; ?>
                                </div>
                            </div>
                            <div class="public-upcoming-card-bottom">
                                <?php if ($isEndedPastCard): ?><span class="chip chip-ended">Ended</span><?php endif; ?>
                                <?php if ($deptLabel !== ''): ?><span class="chip"><?= htmlspecialchars($deptLabel) ?></span><?php endif; ?>
                                <?php if ($locLabel !== ''): ?><span class="chip chip-muted"><?= htmlspecialchars($locLabel) ?></span><?php endif; ?>
                                <span class="chip chip-cta">Log in to view</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (count($publicPastList) > 6): ?>
                    <div class="public-upcoming-more">
                        <button type="button" class="btn btn-outline login-trigger" data-login-url="<?= htmlspecialchars($login_src) ?>">View all past events (login)</button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="public-empty">No past events yet.</div>
            <?php endif; ?>
        </div>

        <div class="landing-tab-panel" id="landingTabCalendar" role="tabpanel" aria-labelledby="landingTabBtnCalendar" hidden>
            <div class="public-calendar-card">
                <div class="public-calendar-toolbar">
                    <div class="public-calendar-note">
                        <span class="pill">Public view</span>
                        <span class="muted">Tap an event to log in</span>
                        <span class="calendar-month-display" id="publicCalendarMonth"><?= date('F Y') ?></span>
                    </div>
                    <a class="btn btn-sm login-trigger" href="javascript:void(0)" data-login-url="<?= htmlspecialchars($login_src) ?>">Log in</a>
                </div>
                <div id="publicCalendar"></div>
            </div>
        </div>
    </div>
    <script>
      window.EVENTIFY_BASE_URL = <?= json_encode(BASE_URL) ?>;
      window.EVENTIFY_SKIP_SPLASH = <?= $skip_landing_splash ? 'true' : 'false' ?>;
      window.PUBLIC_LOGIN_URL = <?= json_encode($login_src) ?>;
      window.PUBLIC_CALENDAR_EVENTS = <?= json_encode($publicCalendarEvents, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
      window.AUTH_MODAL = <?= json_encode($auth_modal) ?>;
      window.AUTH_ERROR = <?= json_encode($auth_error) ?>;
      window.AUTH_SUCCESS = <?= json_encode($auth_success) ?>;
      window.VERIFY_PURPOSE = <?= json_encode($verify_purpose) ?>;
      window.VERIFY_EMAIL = <?= json_encode($verify_email) ?>;
    </script>
</section>

<section id="hero" class="reveal-scope">
    <?php if ($checkin_token !== ''): ?>
    <p class="hero-checkin-notice reveal-item" style="--reveal-d: 0ms; background: rgba(5, 150, 105, 0.22); color: #ecfdf5; border: 1px solid rgba(253, 224, 71, 0.35); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.95rem;">
        <strong>Event check-in.</strong> Log in with your student account to confirm your attendance.
    </p>
    <?php endif; ?>
    <h1 class="reveal-item" style="--reveal-d: 0ms">Plan and track every school event in one place.</h1>
    <p class="hero-lead reveal-item" style="--reveal-d: 60ms">One calendar for admins, organizers, and students—stay aligned without the spreadsheet chaos.</p>
    <p class="reveal-item" style="--reveal-d: 110ms">
        EVENTIFY is a web & app-based school events monitoring system that helps
        administrators, organizers, and students create, announce, and follow every
        activity without missing a thing.
    </p>
    <div class="hero-buttons reveal-item" style="--reveal-d: 170ms">
        <span class="magnetic-wrap">
            <a class="btn btn-shimmer<?= $checkin_token !== '' ? ' login-trigger' : ' get-started-trigger' ?>" href="javascript:void(0)" id="heroGetStarted"<?= $checkin_token !== '' ? ' data-login-url="' . htmlspecialchars($login_src) . '"' : '' ?>><?= $checkin_token !== '' ? 'Log in to check in' : 'Take a quick tour' ?></a>
        </span>
        <a class="btn btn-outline" onclick="goToSection('how-it-works')">See how it works</a>
    </div>
</section>

<!-- Auth Modals on landing page (login, register, verify OTP) -->
<div id="loginModal" class="modal auth-modal auth-modal--page">
    <?php
    $auth_hero_title = 'Welcome back to your school events.';
    $auth_page_close_fn = 'closeLoginModal';
    include __DIR__ . '/views/partials/auth_page_layout_start.php';
    ?>
    <div class="auth-card">
        <div class="auth-card__head">
            <span class="auth-card__accent" aria-hidden="true"></span>
            <div class="auth-card__head-text">
                <h3 class="auth-card__title">Sign in</h3>
                <p class="auth-card__subtitle">Access your dashboard with email and password.</p>
            </div>
        </div>
        <div class="auth-inline-message" id="loginModalMessage" style="display:none;"></div>
        <?php if ($auth_modal === 'login' && $auth_error !== ''): ?>
            <div class="auth-inline-message error" id="loginModalMessageServer"><?= htmlspecialchars($auth_error) ?></div>
        <?php elseif ($auth_modal === 'login' && $auth_success !== ''): ?>
            <div class="auth-inline-message success" id="loginModalMessageServer"><?= htmlspecialchars($auth_success) ?></div>
        <?php endif; ?>
        <form id="loginModalForm" action="<?= BASE_URL ?>/backend/auth/auth.php" method="POST" class="auth-form-wrap">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <?php if ($auth_redirect !== ''): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($auth_redirect, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <div class="auth-input-wrap">
                <label class="auth-label sr-only" for="loginModalEmail">Email</label>
                <div class="auth-field-icon">
                    <i class="fas fa-user auth-field-icon__lead" aria-hidden="true"></i>
                    <input type="email" name="email" id="loginModalEmail" class="auth-input" placeholder="Email address" required autocomplete="username email">
                </div>
            </div>
            <div class="auth-input-wrap auth-password-wrap">
                <label class="auth-label sr-only" for="loginModalPassword">Password</label>
                <div class="auth-field-icon auth-field-icon--password">
                    <i class="fas fa-lock auth-field-icon__lead" aria-hidden="true"></i>
                    <input type="password" name="password" id="loginModalPassword" class="auth-input" placeholder="Password" required autocomplete="current-password">
                    <button type="button" class="auth-eye-btn" id="toggleLoginModalPassword" aria-label="Show password" aria-pressed="false">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="auth-form-meta">
                <label class="auth-remember">
                    <span class="auth-remember__copy">
                        <span class="auth-remember__label">Remember me</span>
                        <span class="auth-remember__hint">Stay signed in on this browser</span>
                    </span>
                    <input type="checkbox" id="loginRememberEmail" class="auth-remember__input" checked>
                    <span class="auth-remember__switch" aria-hidden="true"></span>
                </label>
            </div>
            <button type="submit" class="auth-btn-primary auth-submit-btn btn-shimmer">Sign in</button>
        </form>
        <div class="auth-card__footer">
            <p>Don&rsquo;t have an account? <button type="button" class="auth-link-btn" onclick="openRegisterModal()">Register</button></p>
            <button
                type="button"
                class="auth-btn-secondary auth-reactivate-otp-btn"
                onclick="openVerifyModal({ purpose: 'reactivate' })"
            >
                <i class="fas fa-key" aria-hidden="true"></i>
                Verify reactivation OTP
            </button>
        </div>
    </div>
    <?php include __DIR__ . '/views/partials/auth_page_layout_end.php'; ?>
</div>

<div id="registerModal" class="modal auth-modal auth-modal--page auth-modal--wide">
    <?php
    $auth_hero_title = 'Create your account and join every campus event.';
    $auth_page_close_fn = 'closeRegisterModal';
    include __DIR__ . '/views/partials/auth_page_layout_start.php';
    ?>
    <div class="auth-card">
        <div class="auth-card__head">
            <span class="auth-card__accent" aria-hidden="true"></span>
            <div class="auth-card__head-text">
                <h3 class="auth-card__title">Create account</h3>
                <p class="auth-card__subtitle">Fill in your details to get started with EVENTIFY.</p>
            </div>
        </div>
        <div class="auth-inline-message" id="registerModalMessage" style="display:none;"></div>
        <?php if ($auth_modal === 'register' && $auth_error !== ''): ?>
            <div class="auth-inline-message error" id="registerModalMessageServer"><?= htmlspecialchars($auth_error) ?></div>
        <?php endif; ?>
        <form id="registerModalForm" action="<?= BASE_URL ?>/backend/auth/auth.php" method="POST" class="auth-form-wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="register">
            <div class="auth-name-row">
                <div class="auth-input-wrap">
                    <label class="auth-label" for="registerModalFirstName">First name</label>
                    <div class="auth-field-icon">
                        <i class="fas fa-user auth-field-icon__lead" aria-hidden="true"></i>
                        <input type="text" name="first_name" id="registerModalFirstName" class="auth-input" placeholder="First name" required maxlength="50" autocomplete="given-name">
                    </div>
                </div>
                <div class="auth-input-wrap">
                    <label class="auth-label" for="registerModalLastName">Last name</label>
                    <div class="auth-field-icon">
                        <i class="fas fa-user auth-field-icon__lead" aria-hidden="true"></i>
                        <input type="text" name="last_name" id="registerModalLastName" class="auth-input" placeholder="Last name" required maxlength="50" autocomplete="family-name">
                    </div>
                </div>
            </div>
            <div class="auth-input-wrap">
                <label class="auth-label" for="registerModalEmail">Email</label>
                <div class="auth-field-icon">
                    <i class="fas fa-envelope auth-field-icon__lead" aria-hidden="true"></i>
                    <input type="email" name="email" id="registerModalEmail" class="auth-input" placeholder="Email address" required autocomplete="email">
                </div>
                <p class="auth-field-hint">Use your school account email to sign in — it is your username.</p>
            </div>
            <div class="auth-input-wrap auth-password-wrap">
                <label class="auth-label" for="registerModalPassword">Password</label>
                <div class="auth-field-icon auth-field-icon--password">
                    <i class="fas fa-lock auth-field-icon__lead" aria-hidden="true"></i>
                    <input type="password" name="password" id="registerModalPassword" class="auth-input" placeholder="Password" required autocomplete="new-password">
                    <button type="button" class="auth-eye-btn" id="toggleRegisterModalPassword" aria-label="Show password" aria-pressed="false">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="auth-password-guide" id="registerPasswordGuide">
                    Password guide: at least 8 characters, with 1 uppercase letter and 1 special character.
                </div>
            </div>
            <div class="auth-input-wrap auth-password-wrap">
                <label class="auth-label" for="registerModalConfirmPassword">Confirm Password</label>
                <div class="auth-field-icon auth-field-icon--password">
                    <i class="fas fa-lock auth-field-icon__lead" aria-hidden="true"></i>
                    <input type="password" name="confirm_password" id="registerModalConfirmPassword" class="auth-input" placeholder="Confirm password" required autocomplete="new-password">
                    <button type="button" class="auth-eye-btn" id="toggleRegisterModalConfirmPassword" aria-label="Show password" aria-pressed="false">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="auth-password-match" id="registerPasswordMatchStatus" style="display:none;"></div>
            </div>
                <div class="auth-input-wrap">
                    <label class="auth-label" for="registerRoleSelectModal">Role</label>
                    <select name="role" id="registerRoleSelectModal" class="auth-input" required>
                        <option value="">Select Role</option>
                        <option value="student">Student</option>
                        <option value="organizer">Organizer</option>
                        <option value="multimedia">Multimedia</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="auth-input-wrap" id="registerDepartmentWrapModal" style="display:none;">
                    <label class="auth-label" for="registerDepartmentSelectModal">Department</label>
                    <select name="department" id="registerDepartmentSelectModal" class="auth-input">
                        <option value="">Select Department</option>
                        <option value="High school department">High School Department</option>
                        <option value="College of Communication, Information and Technology">College of Communication, Information and Technology</option>
                        <option value="College of Accountancy and Business">College of Accountancy and Business</option>
                        <option value="School of Law and Political Science">School of Law and Political Science</option>
                        <option value="College of Education">College of Education</option>
                        <option value="College of Nursing and Allied health sciences">College of Nursing and Allied health sciences</option>
                        <option value="College of Hospitality Management">College of Hospitality Management</option>
                    </select>
                </div>
                <div class="auth-input-wrap" id="registerCourseWrapModal" style="display:none;">
                    <label class="auth-label" for="registerCourseSelectModal">Course / Program</label>
                    <select name="student_course" id="registerCourseSelectModal" class="auth-input">
                        <?php if (!empty($studentCourseOptions)): ?>
                            <?php foreach ($studentCourseOptions as $cv => $clab): ?>
                                <?php $courseDept = function_exists('eventify_student_course_program_department') ? eventify_student_course_program_department((string)$cv) : ''; ?>
                                <option value="<?= htmlspecialchars((string)$cv) ?>"<?= $courseDept !== '' ? ' data-department="' . htmlspecialchars($courseDept) . '"' : '' ?>><?= htmlspecialchars((string)$clab) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">Select course / program</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="auth-input-wrap" id="registerYearLevelWrapModal" style="display:none;">
                    <label class="auth-label" for="registerYearLevelSelectModal">Year Level</label>
                    <select name="student_year_level" id="registerYearLevelSelectModal" class="auth-input">
                        <?php if (!empty($studentYearLevelOptions)): ?>
                            <?php foreach ($studentYearLevelOptions as $yv => $ylab): ?>
                                <option value="<?= htmlspecialchars((string)$yv) ?>"><?= htmlspecialchars((string)$ylab) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">— Select —</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="auth-input-wrap auth-consent-wrap">
                    <label class="auth-consent-check">
                        <input type="checkbox" name="accept_legal" value="1" required>
                        <span>
                            I have read and agree to the
                            <button type="button" class="legal-doc-link" data-legal-open="privacy" onclick="openLegalPrivacyModal(); return false;">Data Privacy Notice</button>
                            and
                            <button type="button" class="legal-doc-link" data-legal-open="terms" onclick="openLegalTermsModal(); return false;">Terms and Conditions</button>.
                        </span>
                    </label>
                </div>
            <button type="submit" class="auth-btn-primary auth-submit-btn btn-shimmer">Create account</button>
        </form>
        <div class="auth-card__footer">
            <p>Already have an account? <button type="button" class="auth-link-btn" onclick="openLoginModal()">Sign in</button></p>
        </div>
    </div>
    <?php include __DIR__ . '/views/partials/auth_page_layout_end.php'; ?>
</div>

<!-- Legal documents (stacked above auth modals; no redirect from registration) -->
<div id="legalPrivacyModal" class="modal auth-modal legal-doc-modal" style="display:none;" aria-hidden="true">
    <div class="modal-content auth-modal-content legal-doc-panel">
        <span class="close" onclick="closeLegalPrivacyModal()" aria-label="Close">&times;</span>
        <div class="legal-doc-panel-head">
            <h3 class="auth-title">EVENTIFY Data Privacy Notice</h3>
            <p class="auth-subtitle legal-doc-sub">Republic Act No. 10173 (Data Privacy Act of 2012)</p>
        </div>
        <div class="legal-doc-scroll legal-doc-body">
            <?php include __DIR__ . '/views/partials/legal_privacy_inner.php'; ?>
        </div>
        <div class="legal-doc-actions">
            <button type="button" class="login-modal-action-btn primary" onclick="closeLegalPrivacyModal()">Close</button>
        </div>
    </div>
</div>
<div id="legalTermsModal" class="modal auth-modal legal-doc-modal" style="display:none;" aria-hidden="true">
    <div class="modal-content auth-modal-content legal-doc-panel">
        <span class="close" onclick="closeLegalTermsModal()" aria-label="Close">&times;</span>
        <div class="legal-doc-panel-head">
            <h3 class="auth-title">Terms and Conditions</h3>
            <p class="auth-subtitle legal-doc-sub">EVENTIFY - School Events Monitoring System</p>
        </div>
        <div class="legal-doc-scroll legal-doc-body">
            <?php $legal_terms_context = 'modal'; include __DIR__ . '/views/partials/legal_terms_inner.php'; ?>
        </div>
        <div class="legal-doc-actions">
            <button type="button" class="login-modal-action-btn primary" onclick="closeLegalTermsModal()">Close</button>
        </div>
    </div>
</div>

<div id="verifyModal" class="modal auth-modal auth-modal--page">
    <?php
    $auth_hero_title = 'Verify your email to activate your account.';
    $auth_page_close_fn = 'closeVerifyModal';
    include __DIR__ . '/views/partials/auth_page_layout_start.php';
    ?>
    <div class="auth-card">
        <div class="auth-card__head">
            <span class="auth-card__accent" aria-hidden="true"></span>
            <div class="auth-card__head-text">
                <h3 class="auth-card__title" id="verifyModalTitle"><?= $verify_purpose === 'reactivate' ? 'Verify reactivation OTP' : 'Verify your email' ?></h3>
                <p class="auth-card__subtitle" id="verifyModalSubtitle"><?= $verify_purpose === 'reactivate'
                    ? 'Enter the code sent to your registered email.'
                    : 'Enter the 6-digit code we sent to your email.' ?></p>
            </div>
        </div>
            <?php
            $verifyTopAlertType = '';
            $verifyTopAlertText = '';
            if ($auth_modal === 'verify' && $auth_success !== '') {
                $verifyTopAlertType = 'success';
                $verifyTopAlertText = $auth_success;
            } elseif ($auth_modal === 'verify' && $auth_error !== '') {
                $verifyTopAlertType = 'error';
                $verifyTopAlertText = $auth_error;
            }
            ?>
            <div id="verifyModalTopAlert" class="auth-inline-message auth-verify-top-alert<?= $verifyTopAlertType !== '' ? ' ' . $verifyTopAlertType : '' ?>" role="alert"<?= $verifyTopAlertText === '' ? ' style="display:none;"' : '' ?>><?= $verifyTopAlertText !== '' ? htmlspecialchars($verifyTopAlertText) : '' ?></div>
            <p class="auth-field-hint auth-verify-hint">Did not get the email? Check <strong>Spam/Junk</strong>. School inboxes may take 1–2 minutes.</p>
            <form id="verifyModalForm" action="<?= BASE_URL ?>/backend/auth/verify_account_otp.php" method="POST" class="auth-form-wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="purpose" id="verifyModalPurpose" value="<?= htmlspecialchars($verify_purpose) ?>">
                <div class="auth-input-wrap">
                    <label class="auth-label" for="verifyModalEmail">Email</label>
                    <div class="auth-field-icon">
                        <i class="fas fa-envelope auth-field-icon__lead" aria-hidden="true"></i>
                        <input type="email" name="email" id="verifyModalEmail" class="auth-input" placeholder="Email address" required value="<?= htmlspecialchars($verify_email) ?>" autocomplete="email"<?= ($verify_purpose === 'register' && $verify_email !== '') ? ' readonly' : '' ?>>
                    </div>
                </div>
                <div class="auth-input-wrap auth-otp-wrap">
                    <label class="auth-label" id="verifyOtpLabel" for="verifyOtpDigit0">6-digit OTP</label>
                    <input type="hidden" name="otp_code" id="verifyOtpCode" value="" required pattern="\d{6}">
                    <div class="auth-otp-boxes" role="group" aria-labelledby="verifyOtpLabel">
                        <?php for ($otpDigit = 0; $otpDigit < 6; $otpDigit++): ?>
                        <input
                            type="text"
                            id="verifyOtpDigit<?= $otpDigit ?>"
                            class="auth-otp-box"
                            inputmode="numeric"
                            pattern="\d*"
                            maxlength="1"
                            autocomplete="<?= $otpDigit === 0 ? 'one-time-code' : 'off' ?>"
                            aria-label="Digit <?= $otpDigit + 1 ?> of 6"
                            data-otp-index="<?= $otpDigit ?>"
                        >
                        <?php endfor; ?>
                    </div>
                    <p class="auth-field-hint auth-otp-hint">Type each digit in its box, or paste the full code.</p>
                </div>
                <button type="submit" class="auth-btn-primary auth-submit-btn btn-shimmer">Verify</button>
            </form>
            <form id="verifyResendOtpForm" action="<?= BASE_URL ?>/backend/auth/resend_account_otp.php" method="POST" class="auth-verify-resend-form mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="purpose" id="verifyResendPurpose" value="<?= htmlspecialchars($verify_purpose) ?>">
                <input type="hidden" name="email" id="verifyResendEmail" value="<?= htmlspecialchars($verify_email) ?>">
                <button type="submit" class="auth-btn-secondary auth-verify-resend-btn">Resend OTP</button>
            </form>
        <div class="auth-card__footer">
            <p><button type="button" class="auth-link-btn" onclick="openLoginModal()">Back to sign in</button></p>
        </div>
    </div>
    <?php include __DIR__ . '/views/partials/auth_page_layout_end.php'; ?>
</div>

<?php include __DIR__ . '/views/partials/get_started_guide_modal.php'; ?>

<section id="how-it-works" class="reveal-scope">
    <h1 class="reveal-item" style="--reveal-d: 0ms">How EVENTIFY works</h1>
    <div class="grid reveal-item" style="--reveal-d: 80ms">
        <div class="card landing-card">
            <span class="landing-card__accent" aria-hidden="true"></span>
            <span class="landing-card__icon" aria-hidden="true"><i class="fas fa-calendar-plus"></i></span>
            <h3 class="landing-card__title">1. Plan</h3>
            <p class="landing-card__text">Organizers create events, choose which departments can see them, and set dates and locations in a few clicks.</p>
        </div>
        <div class="card landing-card">
            <span class="landing-card__accent" aria-hidden="true"></span>
            <span class="landing-card__icon" aria-hidden="true"><i class="fas fa-bullhorn"></i></span>
            <h3 class="landing-card__title">2. Announce &amp; track</h3>
            <p class="landing-card__text">Students see upcoming events on a Google-style calendar filtered to their department, while admins and organizers review and update events in one centralized place.</p>
        </div>
    </div>
</section>

<section id="features" class="reveal-scope">
    <h1 class="reveal-item" style="--reveal-d: 0ms">Powerful features for your campus</h1>
    <div class="grid reveal-item" style="--reveal-d: 80ms">
        <div class="card landing-card">
            <span class="landing-card__accent" aria-hidden="true"></span>
            <span class="landing-card__icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></span>
            <h3 class="landing-card__title">Smart event creation</h3>
            <p class="landing-card__text">Create events in a few clicks, set dates and locations, and target the right departments or the whole school.</p>
        </div>
        <div class="card landing-card">
            <span class="landing-card__accent" aria-hidden="true"></span>
            <span class="landing-card__icon" aria-hidden="true"><i class="fas fa-calendar-days"></i></span>
            <h3 class="landing-card__title">Google-style calendars</h3>
            <p class="landing-card__text">Organizer and student dashboards use a clean month/week/day calendar view so everyone can see what is happening at a glance.</p>
        </div>
        <div class="card landing-card">
            <span class="landing-card__accent" aria-hidden="true"></span>
            <span class="landing-card__icon" aria-hidden="true"><i class="fas fa-filter"></i></span>
            <h3 class="landing-card__title">Department-based visibility</h3>
            <p class="landing-card__text">Events can be exclusive to BSIT, BSHM, CONAHS, Senior High, or visible to all — students only see what is relevant to them.</p>
        </div>
        <div class="card landing-card">
            <span class="landing-card__accent" aria-hidden="true"></span>
            <span class="landing-card__icon" aria-hidden="true"><i class="fas fa-id-card"></i></span>
            <h3 class="landing-card__title">Personalized dashboards</h3>
            <p class="landing-card__text">Students can update their profile and view events in one place, while organizers manage and edit their events easily.</p>
        </div>
    </div>
</section>

<section id="roles" class="reveal-scope">
    <h1 class="reveal-item" style="--reveal-d: 0ms">Built for your whole school</h1>
    <div class="grid reveal-item" style="--reveal-d: 80ms">
        <div class="card landing-card">
            <span class="landing-card__accent" aria-hidden="true"></span>
            <span class="landing-card__icon" aria-hidden="true"><i class="fas fa-user-shield"></i></span>
            <h3 class="landing-card__title">Administrators</h3>
            <p class="landing-card__text">Get a clear view of all upcoming events, departments involved, and organizers responsible for each activity.</p>
        </div>
        <div class="card landing-card">
            <span class="landing-card__accent" aria-hidden="true"></span>
            <span class="landing-card__icon" aria-hidden="true"><i class="fas fa-user-pen"></i></span>
            <h3 class="landing-card__title">Organizers</h3>
            <p class="landing-card__text">Create, edit, and manage events from a modern Google-like calendar, and see exactly which students you are targeting.</p>
        </div>
        <div class="card landing-card">
            <span class="landing-card__accent" aria-hidden="true"></span>
            <span class="landing-card__icon" aria-hidden="true"><i class="fas fa-camera"></i></span>
            <h3 class="landing-card__title">Multimedia</h3>
            <p class="landing-card__text">Capture and manage photos and visuals for school events so announcements and galleries stay complete and easy to find.</p>
        </div>
        <div class="card landing-card">
            <span class="landing-card__accent" aria-hidden="true"></span>
            <span class="landing-card__icon" aria-hidden="true"><i class="fas fa-user-graduate"></i></span>
            <h3 class="landing-card__title">Students</h3>
            <p class="landing-card__text">View upcoming events for your department, avoid schedule conflicts, and keep track of your participation throughout the school year.</p>
        </div>
    </div>
</section>

<section id="faq" class="reveal-scope">
    <h1 class="reveal-item" style="--reveal-d: 0ms">Frequently asked questions</h1>
    <div class="faq-accordion reveal-item" style="--reveal-d: 80ms">
        <div class="faq-item is-open">
            <button type="button" class="faq-trigger" id="faqTrigger1" aria-expanded="true" aria-controls="faqPanel1">
                <span>Who can create events?</span>
                <i class="fas fa-chevron-down faq-trigger-icon" aria-hidden="true"></i>
            </button>
            <div class="faq-panel" id="faqPanel1" role="region" aria-labelledby="faqTrigger1">
                <p>Only users with an organizer or admin account can create and manage events from their dashboard.</p>
            </div>
        </div>
        <div class="faq-item">
            <button type="button" class="faq-trigger" id="faqTrigger2" aria-expanded="false" aria-controls="faqPanel2">
                <span>What do students see?</span>
                <i class="fas fa-chevron-down faq-trigger-icon" aria-hidden="true"></i>
            </button>
            <div class="faq-panel" id="faqPanel2" role="region" aria-labelledby="faqTrigger2">
                <p>Students see events that are tagged for their department or for all departments, displayed in a clean calendar view.</p>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer>
    <div class="footer-inner">
        <div class="footer-left">
            <span class="footer-brand">EVENTIFY</span>
            <span class="footer-text">Web & App-Based School Events Monitoring System</span>
        </div>
        <div class="footer-links">
            <a href="javascript:void(0)" onclick="goToSection('features')">Features</a>
            <a href="javascript:void(0)" onclick="goToSection('roles')">Roles</a>
            <a class="legal-doc-link legal-footer-link" href="<?= BASE_URL ?>/privacy-notice.php" target="_blank" rel="noopener noreferrer">Privacy Notice</a>
            <a class="legal-doc-link legal-footer-link" href="<?= BASE_URL ?>/terms-and-conditions.php" target="_blank" rel="noopener noreferrer">Terms &amp; Conditions</a>
            <a href="mailto:youremail@example.com">Contact</a>
        </div>
    </div>
</footer>


<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_calendar_colors.js"></script>
<script src="<?= BASE_URL ?>/assets/js/index.js?v=24"></script>
</body>
</html>
