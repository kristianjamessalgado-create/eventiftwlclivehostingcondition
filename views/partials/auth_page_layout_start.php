<?php
/** @var string $auth_page_close_fn */
/** @var string $auth_hero_title */
$auth_page_close_fn = $auth_page_close_fn ?? 'closeLoginModal';
$auth_hero_title = $auth_hero_title ?? 'Welcome back to your school events.';
?>
<div class="auth-page">
    <div class="auth-page__split">
        <aside class="auth-page__aside" aria-label="About EVENTIFY">
            <div class="auth-page__aside-brand">
                <div class="auth-page__logo" aria-hidden="true">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <span class="auth-page__wordmark">EVENTIFY</span>
            </div>
            <h2 class="auth-page__hero"><?= htmlspecialchars($auth_hero_title) ?></h2>
            <ul class="auth-page__features">
                <li>
                    <span class="auth-page__feature-icon" aria-hidden="true"><i class="fas fa-calendar-days"></i></span>
                    <span>Events, RSVP, and QR check-in in one school hub.</span>
                </li>
                <li>
                    <span class="auth-page__feature-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                    <span>Dashboards for students, organizers, multimedia, and admins.</span>
                </li>
                <li>
                    <span class="auth-page__feature-icon" aria-hidden="true"><i class="fas fa-bell"></i></span>
                    <span>Department calendars, tickets, and live activity schedules.</span>
                </li>
            </ul>
            <p class="auth-page__aside-foot">Web &amp; App · School Events Monitoring System</p>
        </aside>
        <div class="auth-page__main">
            <div class="auth-page__top">
                <button type="button" class="auth-page__back" onclick="<?= htmlspecialchars($auth_page_close_fn) ?>()">
                    <span class="auth-page__back-icon" aria-hidden="true">
                        <i class="fas fa-arrow-left"></i>
                    </span>
                    <span class="auth-page__back-text">Back to home</span>
                </button>
            </div>
            <div class="auth-page__main-inner">
                <div class="auth-page__mobile-brand">
                    <div class="auth-page__logo" aria-hidden="true">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <span class="auth-page__wordmark">EVENTIFY</span>
                </div>
