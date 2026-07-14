<?php
/**
 * Colored registration-mode badge (Free RSVP / Open entry / Paid tickets).
 *
 * @var array<string, mixed>|null $event
 * @var array{mode: string, label: string, badge_class: string}|null $regUi
 * @var string $regBadgeExtraClass optional extra classes on the span
 */
if (!function_exists('eventify_registration_mode_ui')) {
    require_once __DIR__ . '/../../backend/lib/event_ticketing.php';
}
$regUi = $regUi ?? eventify_registration_mode_ui($event ?? []);
$regBadgeExtraClass = trim((string) ($regBadgeExtraClass ?? ''));
?>
<span class="badge <?= htmlspecialchars($regUi['badge_class']) ?><?= $regBadgeExtraClass !== '' ? ' ' . htmlspecialchars($regBadgeExtraClass) : '' ?>"><?= htmlspecialchars($regUi['label']) ?></span>
