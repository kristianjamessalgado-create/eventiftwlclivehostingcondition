<?php
/**
 * Renders scrollable notification cards.
 *
 * @var list<array<string, mixed>> $notifications
 * @var string $empty_title
 * @var string $empty_text
 */
require_once __DIR__ . '/../../backend/lib/notifications_ui.php';

$notifications = $notifications ?? [];
$empty_title = $empty_title ?? 'All caught up';
$empty_text = $empty_text ?? 'No notifications yet.';
$notif_interactive = !empty($notif_interactive);
?>
<div class="eventify-notif-list" id="eventifyNotifList">
    <?php if ($notifications === []): ?>
        <div class="eventify-notif-empty">
            <div class="eventify-notif-empty__icon" aria-hidden="true"><i class="fas fa-bell-slash"></i></div>
            <div class="eventify-notif-empty__title"><?= htmlspecialchars($empty_title) ?></div>
            <p class="eventify-notif-empty__text"><?= htmlspecialchars($empty_text) ?></p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <?php
            $nid = (int) ($n['id'] ?? 0);
            $isUnread = empty($n['read_at']);
            $type = (string) ($n['type'] ?? '');
            $vis = eventify_notification_visual($type);
            $accent = $vis['accent'];
            $timeLabel = eventify_format_notification_time($n['created_at'] ?? null);
            $cardClass = 'eventify-notif-card eventify-notif-card--' . htmlspecialchars($accent, ENT_QUOTES, 'UTF-8');
            if ($isUnread) {
                $cardClass .= ' eventify-notif-card--unread';
            }
            if ($notif_interactive) {
                $cardClass .= ' js-eventify-notif-card';
            }
            $href = $nid > 0 ? eventify_notification_mark_url($nid) : '#';
            $evId = (int) ($n['event_id'] ?? 0);
            $notifType = $type;
            ?>
            <?php if ($notif_interactive): ?>
                <div
                    class="<?= $cardClass ?>"
                    role="button"
                    tabindex="0"
                    data-notif-id="<?= $nid ?>"
                    data-event-id="<?= $evId > 0 ? $evId : '' ?>"
                    data-notif-type="<?= htmlspecialchars($notifType, ENT_QUOTES, 'UTF-8') ?>"
                    data-notif-title="<?= htmlspecialchars((string) ($n['title'] ?? 'Notification'), ENT_QUOTES, 'UTF-8') ?>"
                    data-notif-message="<?= htmlspecialchars((string) ($n['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    data-notif-time="<?= htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8') ?>"
                    data-notif-icon="<?= htmlspecialchars($vis['icon'], ENT_QUOTES, 'UTF-8') ?>"
                    data-notif-accent="<?= htmlspecialchars($accent, ENT_QUOTES, 'UTF-8') ?>"
                    data-notif-label="<?= htmlspecialchars($vis['label'], ENT_QUOTES, 'UTF-8') ?>"
                    <?= $isUnread ? ' data-unread="1"' : '' ?>
                >
            <?php else: ?>
                <a href="<?= htmlspecialchars($href) ?>" class="<?= $cardClass ?>">
            <?php endif; ?>
                <div class="eventify-notif-card__icon" aria-hidden="true">
                    <i class="fas <?= htmlspecialchars($vis['icon']) ?>"></i>
                </div>
                <div class="eventify-notif-card__body">
                    <div class="eventify-notif-card__top">
                        <h6 class="eventify-notif-card__title">
                            <?= htmlspecialchars($n['title'] ?? 'Notification') ?>
                            <?php if ($isUnread): ?>
                                <span class="eventify-notif-card__badge">New</span>
                            <?php endif; ?>
                        </h6>
                        <?php if ($timeLabel !== ''): ?>
                            <span class="eventify-notif-card__time"><?= htmlspecialchars($timeLabel) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($n['message'])): ?>
                        <p class="eventify-notif-card__message"><?= htmlspecialchars($n['message']) ?></p>
                    <?php endif; ?>
                    <span class="eventify-notif-card__type-pill"><?= htmlspecialchars($vis['label']) ?></span>
                </div>
                <i class="fas fa-chevron-right eventify-notif-card__chevron" aria-hidden="true"></i>
            <?php if ($notif_interactive): ?>
                </div>
            <?php else: ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
