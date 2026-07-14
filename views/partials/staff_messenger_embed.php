<?php
/** @var bool $msgr_embedded */
/** @var string $dashboardHref */
/** @var int $uid */
/** @var string $role */
/** @var string $myName */
/** @var array $peersList */
/** @var int $initialWith */
/** @var string|null $messaging_error */

$msgr_embedded = !empty($msgr_embedded);
$dashboardHref = $dashboardHref ?? BASE_URL . '/';
$myName = $myName ?? '';
$peersList = $peersList ?? [];
$initialWith = isset($initialWith) ? (int) $initialWith : 0;
$messaging_error = $messaging_error ?? null;
$uid = isset($uid) ? (int) $uid : (int) ($_SESSION['user_id'] ?? 0);
$role = $role ?? (string) ($_SESSION['role'] ?? '');
$peerLabel = ($role === 'admin') ? 'Organizers' : 'Admins';
?>
<div class="msgr-detail-backdrop" id="msgrDetailBackdrop" hidden aria-hidden="true"></div>
<div class="msgr-app<?= $msgr_embedded ? ' msgr-app--embedded' : '' ?>" id="staffMessengerApp">
    <?php if (!$msgr_embedded): ?>
    <aside class="msgr-rail" aria-label="Shortcuts">
        <div class="msgr-rail-brand">
            <i class="fas fa-calendar-check msgr-rail-logo" aria-hidden="true"></i>
            <span>EVENTIFY</span>
        </div>
        <a href="<?= htmlspecialchars($dashboardHref) ?>" class="msgr-rail-btn" title="Back to dashboard">
            <i class="fas fa-arrow-left"></i>
        </a>
        <span class="msgr-rail-btn msgr-rail-btn-active" title="Staff messages"><i class="fas fa-comments"></i></span>
    </aside>
    <?php endif; ?>

    <aside class="msgr-inbox">
        <div class="msgr-inbox-head">
            <div class="d-flex align-items-start justify-content-between gap-2">
                <div>
                    <span class="msgr-inbox-eyebrow">Messaging</span>
                    <h1 class="msgr-inbox-title mb-0">Staff messages</h1>
                    <p class="msgr-inbox-sub mb-0">Chat with <?= htmlspecialchars(strtolower($peerLabel)) ?></p>
                </div>
                <?php if ($msgr_embedded): ?>
                    <button type="button" class="msgr-icon-btn msgr-icon-btn--on-dark" title="Back to calendar" data-admin-panel="home"><i class="fas fa-times"></i></button>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($dashboardHref) ?>" class="msgr-icon-btn msgr-icon-btn--on-dark" title="Close"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
            <div class="msgr-search-wrap">
                <i class="fas fa-search msgr-search-icon"></i>
                <input type="search" id="msgrSearch" class="msgr-search" placeholder="Search <?= htmlspecialchars(strtolower($peerLabel)) ?>…" autocomplete="off">
            </div>
            <div class="msgr-tabs" role="tablist">
                <button type="button" class="msgr-tab active" data-msgr-filter="all">All</button>
                <button type="button" class="msgr-tab" data-msgr-filter="unread">Unread</button>
            </div>
        </div>
        <div class="msgr-chat-list" id="msgrPeerList">
            <?php if ($messaging_error): ?>
                <div class="p-3 small text-danger"><?= htmlspecialchars($messaging_error) ?></div>
            <?php elseif (empty($peersList)): ?>
                <div class="msgr-list-empty">No <?= htmlspecialchars(strtolower($peerLabel)) ?> to message yet.</div>
            <?php endif; ?>
        </div>
    </aside>

    <main class="msgr-conversation">
        <header class="msgr-conv-head" id="msgrConvHead">
            <div class="msgr-conv-head-main">
                <button type="button" class="msgr-icon-btn msgr-back-mobile" id="msgrBackToList" title="Chats"><i class="fas fa-arrow-left"></i></button>
                <div class="msgr-avatar msgr-avatar-lg" id="msgrHeadAvatar">?</div>
                <div>
                    <div class="msgr-conv-name" id="msgrHeadName">Select a chat</div>
                    <div class="msgr-conv-sub msgr-muted" id="msgrHeadSub"><?= htmlspecialchars($peerLabel) ?></div>
                </div>
            </div>
            <div class="msgr-conv-actions">
                <button type="button" class="msgr-icon-btn" id="msgrToggleDetail" title="Chat info" aria-expanded="false" hidden><i class="fas fa-circle-info"></i></button>
            </div>
        </header>

        <div class="msgr-messages" id="msgrThread"></div>

        <footer class="msgr-composer-wrap">
            <div id="msgrAttachPreview" class="msgr-attach-preview" hidden>
                <img id="msgrAttachPreviewImg" src="" alt="Attachment preview">
                <button type="button" class="msgr-attach-preview__clear" id="msgrAttachClear" title="Remove attachment"><i class="fas fa-times"></i></button>
            </div>
            <form id="msgrSendForm" class="msgr-composer" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" id="msgrCsrf" value="<?= htmlspecialchars(function_exists('csrf_token') ? csrf_token() : '') ?>">
                <input type="hidden" name="recipient_id" id="msgrRecipientId" value="">
                <input type="file" id="msgrAttachmentInput" name="attachment" accept="image/jpeg,image/png,image/gif,image/webp" class="d-none">
                <button type="button" class="msgr-composer-icon" id="msgrAttachBtn" disabled title="Attach image"><i class="fas fa-paperclip"></i></button>
                <textarea class="msgr-input" name="body" id="msgrBody" rows="1" placeholder="Write a message…" maxlength="8000" disabled></textarea>
                <button type="submit" class="msgr-send" id="msgrSendBtn" disabled title="Send"><i class="fas fa-paper-plane"></i></button>
            </form>
        </footer>
    </main>

    <aside class="msgr-detail msgr-detail-collapsed" id="msgrDetailPanel" aria-hidden="true">
        <div class="msgr-detail-inner">
            <div class="msgr-detail-topbar">
                <span class="msgr-detail-topbar-label">Contact info</span>
                <button type="button" class="msgr-icon-btn msgr-detail-close" id="msgrDetailClose" title="Close panel"><i class="fas fa-times"></i></button>
            </div>

            <div class="msgr-detail-idle" id="msgrDetailIdle">
                <div class="msgr-detail-idle-icon" aria-hidden="true"><i class="fas fa-user-circle"></i></div>
                <h2 class="msgr-detail-idle-title">No chat selected</h2>
                <p class="msgr-detail-idle-text">Choose someone from the list to view their profile and message history.</p>
            </div>

            <div class="msgr-detail-contact" id="msgrDetailContact" hidden>
                <div class="msgr-detail-hero">
                    <div class="msgr-avatar msgr-avatar-xl" id="msgrDetailAvatar">?</div>
                    <h2 class="msgr-detail-name" id="msgrDetailName">—</h2>
                    <p class="msgr-detail-email msgr-muted small mb-0" id="msgrDetailEmail"></p>
                    <span class="msgr-detail-role" id="msgrDetailRole"><?= htmlspecialchars($peerLabel) ?></span>
                </div>
                <div class="msgr-detail-actions">
                    <button type="button" class="msgr-pill-btn" disabled title="Coming soon"><i class="fas fa-bell-slash"></i><span>Mute</span></button>
                    <button type="button" class="msgr-pill-btn" disabled title="Coming soon"><i class="fas fa-magnifying-glass"></i><span>Search in chat</span></button>
                </div>
            </div>

            <div class="msgr-detail-section">
                <div class="msgr-detail-section-title"><i class="fas fa-shield-alt"></i> Staff channel</div>
                <p class="small msgr-muted mb-0">Official admin ↔ organizer messaging on EVENTIFY. Use this for event approvals, updates, and coordination.</p>
            </div>
        </div>
    </aside>
</div>
