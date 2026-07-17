<?php
/**
 * Unified student nav list items (no wrapping <ul>).
 * See student_hub_nav_items.php for full drawer list wrapper.
 *
 * My registrations = list of all your events (pick / switch).
 * This event       = one event's day activities, RSVP, tickets, check-in.
 */
$studentNavActive = (string) ($studentNavActive ?? 'hub');
$studentNavDashboardUrl = (string) ($studentNavDashboardUrl ?? BASE_URL . '/backend/auth/dashboard_student.php');
$studentNavHubUrl = (string) ($studentNavHubUrl ?? BASE_URL . '/activities_hub.php');
$studentNavMainHubUrl = (string) ($studentNavMainHubUrl ?? $studentNavHubUrl);
$studentNavShowMainHub = (bool) ($studentNavShowMainHub ?? false);
$studentNavInEventContext = (bool) ($studentNavInEventContext ?? false);
$studentNavScheduleUrl = (string) ($studentNavScheduleUrl ?? '');
$studentNavScheduleCount = (int) ($studentNavScheduleCount ?? 0);
$studentNavTicketsUrl = (string) ($studentNavTicketsUrl ?? '');
$studentNavTicketsCount = (int) ($studentNavTicketsCount ?? 0);
$studentNavAttendanceUrl = (string) ($studentNavAttendanceUrl ?? BASE_URL . '/attendance_history.php');
$studentNavAttendanceCount = (int) ($studentNavAttendanceCount ?? 0);
$studentNavHubCount = (int) ($studentNavHubCount ?? 0);
$studentNavMainHubLabel = (string) ($studentNavMainHubLabel ?? 'This event');
$studentNavMainHubHint = (string) ($studentNavMainHubHint ?? 'Day activities & check-in');
$studentNavTicketsHint = (string) ($studentNavTicketsHint ?? '');

if (!function_exists('student_nav_h')) {
    function student_nav_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('student_nav_is_active')) {
    function student_nav_is_active(string $key, string $active): string
    {
        return $key === $active ? ' is-active' : '';
    }
}

if (!function_exists('student_nav_count_badge')) {
    function student_nav_count_badge(int $count, string $extraClass = ''): string
    {
        if ($count < 1) {
            return '';
        }
        $label = $count > 99 ? '99+' : (string) $count;
        $class = trim('eah-nav-count ' . $extraClass);

        return ' <span class="' . student_nav_h($class) . '">' . student_nav_h($label) . '</span>';
    }
}

$studentNavInCurrentEvent = $studentNavInEventContext || $studentNavScheduleUrl !== '';
$studentNavShowTickets = $studentNavTicketsUrl !== '';
$studentNavTicketsInCurrentEvent = $studentNavShowTickets && $studentNavInCurrentEvent;
$studentNavTicketsGlobal = $studentNavShowTickets && !$studentNavInCurrentEvent;
$studentNavMainHubInCurrentEvent = $studentNavShowMainHub && $studentNavInCurrentEvent;
$studentNavMainHubResume = $studentNavShowMainHub && !$studentNavInCurrentEvent;
$studentNavTicketsHintText = $studentNavTicketsHint !== ''
    ? $studentNavTicketsHint
    : ($studentNavTicketsInCurrentEvent ? 'Passes for this event' : 'Your digital passes');
?>
    <li>
        <a class="eah-nav-drawer__link<?= student_nav_is_active('dashboard', $studentNavActive) ?>" href="<?= student_nav_h($studentNavDashboardUrl) ?>"<?= $studentNavActive === 'dashboard' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-gauge-high"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">Dashboard</span>
                <span class="eah-nav-drawer__link-hint">Calendar &amp; home</span>
            </span>
        </a>
    </li>
    <li class="eah-nav-drawer__section" aria-hidden="true">Events</li>
    <li>
        <a class="eah-nav-drawer__link<?= student_nav_is_active('hub', $studentNavActive) ?>" href="<?= student_nav_h($studentNavHubUrl) ?>"<?= $studentNavActive === 'hub' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-th-large"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">My registrations<?= student_nav_count_badge($studentNavHubCount) ?></span>
                <span class="eah-nav-drawer__link-hint">Events you joined</span>
            </span>
        </a>
    </li>
    <?php if ($studentNavMainHubResume): ?>
    <li>
        <a class="eah-nav-drawer__link<?= student_nav_is_active('main_hub', $studentNavActive) ?>" href="<?= student_nav_h($studentNavMainHubUrl) ?>"<?= $studentNavActive === 'main_hub' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-calendar-day"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label"><?= student_nav_h($studentNavMainHubLabel) ?></span>
                <span class="eah-nav-drawer__link-hint"><?= student_nav_h($studentNavMainHubHint) ?></span>
            </span>
        </a>
    </li>
    <?php endif; ?>
    <?php if ($studentNavTicketsGlobal): ?>
    <li>
        <a class="eah-nav-drawer__link<?= student_nav_is_active('tickets', $studentNavActive) ?>" href="<?= student_nav_h($studentNavTicketsUrl) ?>"<?= $studentNavActive === 'tickets' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-ticket-alt"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">My tickets<?= student_nav_count_badge($studentNavTicketsCount, 'eah-nav-count--ticket') ?></span>
                <span class="eah-nav-drawer__link-hint"><?= student_nav_h($studentNavTicketsHintText) ?></span>
            </span>
        </a>
    </li>
    <?php endif; ?>
    <?php if ($studentNavInCurrentEvent): ?>
    <li class="eah-nav-drawer__section" aria-hidden="true">Current event</li>
    <?php if ($studentNavMainHubInCurrentEvent): ?>
    <li>
        <a class="eah-nav-drawer__link<?= student_nav_is_active('main_hub', $studentNavActive) ?>" href="<?= student_nav_h($studentNavMainHubUrl) ?>"<?= $studentNavActive === 'main_hub' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-calendar-day"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label"><?= student_nav_h($studentNavMainHubLabel) ?></span>
                <span class="eah-nav-drawer__link-hint"><?= student_nav_h($studentNavMainHubHint) ?></span>
            </span>
        </a>
    </li>
    <?php endif; ?>
    <?php if ($studentNavScheduleUrl !== ''): ?>
    <li>
        <a class="eah-nav-drawer__link<?= student_nav_is_active('schedule', $studentNavActive) ?>" href="<?= student_nav_h($studentNavScheduleUrl) ?>"<?= $studentNavActive === 'schedule' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-bookmark"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">My schedule<?= student_nav_count_badge($studentNavScheduleCount) ?></span>
                <span class="eah-nav-drawer__link-hint">Only what you RSVP’d</span>
            </span>
        </a>
    </li>
    <?php endif; ?>
    <?php if ($studentNavTicketsInCurrentEvent): ?>
    <li>
        <a class="eah-nav-drawer__link<?= student_nav_is_active('tickets', $studentNavActive) ?>" href="<?= student_nav_h($studentNavTicketsUrl) ?>"<?= $studentNavActive === 'tickets' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-ticket-alt"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">My tickets<?= student_nav_count_badge($studentNavTicketsCount, 'eah-nav-count--ticket') ?></span>
                <span class="eah-nav-drawer__link-hint"><?= student_nav_h($studentNavTicketsHintText) ?></span>
            </span>
        </a>
    </li>
    <?php endif; ?>
    <?php endif; ?>
    <li class="eah-nav-drawer__section" aria-hidden="true">Check-in</li>
    <li>
        <button type="button" class="eah-nav-drawer__link eah-nav-drawer__link--btn" data-bs-toggle="modal" data-bs-target="#scanQRModal" data-eah-close-nav>
            <i class="fas fa-qrcode"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">Scan QR</span>
                <span class="eah-nav-drawer__link-hint">Check in at venue</span>
            </span>
        </button>
    </li>
    <li>
        <a class="eah-nav-drawer__link<?= student_nav_is_active('attendance', $studentNavActive) ?>" href="<?= student_nav_h($studentNavAttendanceUrl) ?>"<?= $studentNavActive === 'attendance' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-clipboard-check"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">Check-in history<?= student_nav_count_badge($studentNavAttendanceCount) ?></span>
                <span class="eah-nav-drawer__link-hint">Past scans</span>
            </span>
        </a>
    </li>
