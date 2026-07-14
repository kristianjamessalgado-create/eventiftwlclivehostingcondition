<?php
/**
 * Unified student nav list items (no wrapping <ul>).
 * See student_hub_nav_items.php for full drawer list wrapper.
 *
 * Activities hub = list of all your events (pick / switch).
 * Main hub       = one event's day activities, RSVP, tickets, check-in.
 */
$studentNavActive = (string) ($studentNavActive ?? 'hub');
$studentNavDashboardUrl = (string) ($studentNavDashboardUrl ?? BASE_URL . '/backend/auth/dashboard_student.php');
$studentNavHubUrl = (string) ($studentNavHubUrl ?? BASE_URL . '/activities_hub.php');
$studentNavMainHubUrl = (string) ($studentNavMainHubUrl ?? $studentNavHubUrl);
$studentNavShowMainHub = (bool) ($studentNavShowMainHub ?? false);
$studentNavScheduleUrl = (string) ($studentNavScheduleUrl ?? '');
$studentNavScheduleCount = (int) ($studentNavScheduleCount ?? 0);
$studentNavTicketsUrl = (string) ($studentNavTicketsUrl ?? BASE_URL . '/backend/auth/dashboard_student.php?panel=tickets');
$studentNavTicketsCount = (int) ($studentNavTicketsCount ?? 0);
$studentNavAttendanceUrl = (string) ($studentNavAttendanceUrl ?? BASE_URL . '/attendance_history.php');
$studentNavAttendanceCount = (int) ($studentNavAttendanceCount ?? 0);
$studentNavHubCount = (int) ($studentNavHubCount ?? 0);
$studentNavMainHubHint = (string) ($studentNavMainHubHint ?? 'This event\'s schedule');

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

$studentNavShowEventSection = ($studentNavScheduleUrl !== '' || $studentNavTicketsUrl !== '');
?>
    <li>
        <a class="eah-nav-drawer__link" href="<?= student_nav_h($studentNavDashboardUrl) ?>">
            <i class="fas fa-gauge-high"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">Dashboard</span>
            </span>
        </a>
    </li>
    <li class="eah-nav-drawer__section" aria-hidden="true">Events</li>
    <li>
        <a class="eah-nav-drawer__link<?= student_nav_is_active('hub', $studentNavActive) ?>" href="<?= student_nav_h($studentNavHubUrl) ?>"<?= $studentNavActive === 'hub' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-th-large"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">My Activities and Events<?= student_nav_count_badge($studentNavHubCount) ?></span>
                <span class="eah-nav-drawer__link-hint">Browse &amp; switch events</span>
            </span>
        </a>
    </li>
    <?php if ($studentNavShowMainHub): ?>
    <li>
        <a class="eah-nav-drawer__link<?= student_nav_is_active('main_hub', $studentNavActive) ?>" href="<?= student_nav_h($studentNavMainHubUrl) ?>"<?= $studentNavActive === 'main_hub' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-calendar-day"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">Main hub</span>
                <span class="eah-nav-drawer__link-hint"><?= student_nav_h($studentNavMainHubHint) ?></span>
            </span>
        </a>
    </li>
    <?php endif; ?>
    <?php if ($studentNavShowEventSection): ?>
    <li class="eah-nav-drawer__section" aria-hidden="true">This event</li>
    <?php if ($studentNavScheduleUrl !== ''): ?>
    <li>
        <a class="eah-nav-drawer__link<?= student_nav_is_active('schedule', $studentNavActive) ?>" href="<?= student_nav_h($studentNavScheduleUrl) ?>"<?= $studentNavActive === 'schedule' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-bookmark"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">My schedule<?= student_nav_count_badge($studentNavScheduleCount) ?></span>
                <span class="eah-nav-drawer__link-hint">RSVP'd day activities</span>
            </span>
        </a>
    </li>
    <?php endif; ?>
    <?php if ($studentNavTicketsUrl !== ''): ?>
    <li>
        <a class="eah-nav-drawer__link<?= student_nav_is_active('tickets', $studentNavActive) ?>" href="<?= student_nav_h($studentNavTicketsUrl) ?>"<?= $studentNavActive === 'tickets' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-ticket-alt"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">My tickets<?= student_nav_count_badge($studentNavTicketsCount, 'eah-nav-count--ticket') ?></span>
                <span class="eah-nav-drawer__link-hint">Passes for this event</span>
            </span>
        </a>
    </li>
    <?php endif; ?>
    <?php endif; ?>
    <li class="eah-nav-drawer__section" aria-hidden="true">Account</li>
    <li>
        <a class="eah-nav-drawer__link<?= student_nav_is_active('attendance', $studentNavActive) ?>" href="<?= student_nav_h($studentNavAttendanceUrl) ?>"<?= $studentNavActive === 'attendance' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-clipboard-check"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">My attendance<?= student_nav_count_badge($studentNavAttendanceCount) ?></span>
                <span class="eah-nav-drawer__link-hint">Full check-in list</span>
            </span>
        </a>
    </li>
    <?php if (!$studentNavShowEventSection): ?>
    <li>
        <a class="eah-nav-drawer__link<?= student_nav_is_active('tickets', $studentNavActive) ?>" href="<?= student_nav_h($studentNavTicketsUrl) ?>"<?= $studentNavActive === 'tickets' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-ticket-alt"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">My tickets<?= student_nav_count_badge($studentNavTicketsCount, 'eah-nav-count--ticket') ?></span>
            </span>
        </a>
    </li>
    <?php endif; ?>
    <li>
        <button type="button" class="eah-nav-drawer__link eah-nav-drawer__link--btn" data-bs-toggle="modal" data-bs-target="#scanQRModal" data-eah-close-nav>
            <i class="fas fa-qrcode"></i>
            <span class="eah-nav-drawer__link-text">
                <span class="eah-nav-drawer__link-label">Scan QR</span>
                <span class="eah-nav-drawer__link-hint">Check in at venue</span>
            </span>
        </button>
    </li>
