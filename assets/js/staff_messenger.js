/**
 * Full-page staff messenger (admin ↔ organizer), EVENTIFY UI.
 */
(function () {
    function esc(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function initials(name) {
        var p = String(name || '?').trim().split(/\s+/);
        if (p.length >= 2) {
            return (p[0][0] + p[1][0]).toUpperCase();
        }
        return (name || '?').slice(0, 2).toUpperCase();
    }

    function formatShortTime(iso) {
        if (!iso) return '';
        try {
            var d = new Date(String(iso).replace(' ', 'T'));
            if (isNaN(d.getTime())) return '';
            var now = new Date();
            var sameDay = d.toDateString() === now.toDateString();
            if (sameDay) {
                return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
            }
            return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        } catch (e) {
            return '';
        }
    }

    function formatMsgTime(iso) {
        if (!iso) return '';
        try {
            var d = new Date(String(iso).replace(' ', 'T'));
            if (isNaN(d.getTime())) return '';
            return d.toLocaleString(undefined, {
                weekday: 'short',
                hour: 'numeric',
                minute: '2-digit'
            });
        } catch (e) {
            return '';
        }
    }

    function truncate(str, n) {
        var s = String(str || '');
        if (s.length <= n) return s;
        return s.slice(0, n - 1) + '…';
    }

    function bootPage() {
        var app = document.getElementById('staffMessengerApp');
        if (!app) return;
        if (app.getAttribute('data-msgr-ready') === '1') return;
        app.setAttribute('data-msgr-ready', '1');

        var embedded = app.classList.contains('msgr-app--embedded');

        function updateMessengerUrl(withPeerId) {
            try {
                var u = new URL(window.location.href);
                if (embedded) {
                    u.searchParams.set('panel', 'messages');
                    if (withPeerId) {
                        u.searchParams.set('with', String(withPeerId));
                    } else {
                        u.searchParams.delete('with');
                    }
                } else if (withPeerId) {
                    u.searchParams.set('with', String(withPeerId));
                } else {
                    u.searchParams.delete('with');
                }
                var next = u.pathname + (u.search || '');
                window.history.replaceState({ adminPanel: embedded ? 'messages' : undefined }, '', next);
            } catch (e) { /* ignore */ }
        }

        if (window.__staffMessengerError) {
            var le = document.getElementById('msgrPeerList');
            if (le) {
                le.innerHTML = '<div class="p-3 small text-danger">' + esc(window.__staffMessengerError) + '</div>';
            }
            return;
        }

        var base = (window.BASE_URL || '').replace(/\/$/, '');
        var selfId = parseInt(window.__staffMessengerSelfId, 10) || 0;
        var peers = Array.isArray(window.__staffMessengerPeers) ? window.__staffMessengerPeers.slice() : [];
        var peerLabel = window.__staffMessengerPeerLabel || 'Contacts';
        var initialWith = parseInt(window.__staffMessengerInitialWith, 10) || 0;

        var listEl = document.getElementById('msgrPeerList');
        var threadEl = document.getElementById('msgrThread');
        var formEl = document.getElementById('msgrSendForm');
        var recipientInput = document.getElementById('msgrRecipientId');
        var textareaEl = document.getElementById('msgrBody');
        var sendBtn = document.getElementById('msgrSendBtn');
        var attachBtn = document.getElementById('msgrAttachBtn');
        var attachInput = document.getElementById('msgrAttachmentInput');
        var attachPreview = document.getElementById('msgrAttachPreview');
        var attachPreviewImg = document.getElementById('msgrAttachPreviewImg');
        var attachClear = document.getElementById('msgrAttachClear');
        var csrfInput = document.getElementById('msgrCsrf');

        function toastError(msg) {
            if (window.eventifyToast && window.eventifyToast.error) {
                window.eventifyToast.error(msg);
            } else {
                window.alert(msg);
            }
        }

        function toastSuccess(msg) {
            if (window.eventifyToast && window.eventifyToast.success) {
                window.eventifyToast.success(msg);
            }
        }

        function clearAttachmentPreview() {
            if (attachInput) attachInput.value = '';
            if (attachPreview) attachPreview.hidden = true;
            if (attachPreviewImg) attachPreviewImg.src = '';
        }

        function syncComposerControls() {
            var enabled = !!selectedPeerId;
            if (textareaEl) textareaEl.disabled = !enabled;
            if (sendBtn) sendBtn.disabled = !enabled;
            if (attachBtn) attachBtn.disabled = !enabled;
        }

        if (attachBtn && attachInput) {
            attachBtn.addEventListener('click', function () {
                if (!selectedPeerId) return;
                attachInput.click();
            });
            attachInput.addEventListener('change', function () {
                var file = attachInput.files && attachInput.files[0];
                if (!file) {
                    clearAttachmentPreview();
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    toastError('Image must be 5MB or smaller.');
                    clearAttachmentPreview();
                    return;
                }
                var reader = new FileReader();
                reader.onload = function (ev) {
                    if (attachPreviewImg) attachPreviewImg.src = ev.target.result;
                    if (attachPreview) attachPreview.hidden = false;
                };
                reader.readAsDataURL(file);
            });
        }
        if (attachClear) {
            attachClear.addEventListener('click', clearAttachmentPreview);
        }
        var searchEl = document.getElementById('msgrSearch');
        var headAvatar = document.getElementById('msgrHeadAvatar');
        var headName = document.getElementById('msgrHeadName');
        var headSub = document.getElementById('msgrHeadSub');
        var detailAvatar = document.getElementById('msgrDetailAvatar');
        var detailName = document.getElementById('msgrDetailName');
        var detailEmail = document.getElementById('msgrDetailEmail');
        var detailRole = document.getElementById('msgrDetailRole');
        var detailToggle = document.getElementById('msgrToggleDetail');
        var detailPanel = document.getElementById('msgrDetailPanel');
        var detailBackdrop = document.getElementById('msgrDetailBackdrop');
        var detailIdle = document.getElementById('msgrDetailIdle');
        var detailContact = document.getElementById('msgrDetailContact');
        var mqTablet = window.matchMedia ? window.matchMedia('(max-width: 1199.98px)') : null;

        function updateDetailBackdrop() {
            if (!detailBackdrop || !detailPanel) return;
            var overlayOpen = !!(mqTablet && mqTablet.matches) && !detailPanel.classList.contains('msgr-detail-collapsed');
            detailBackdrop.classList.toggle('is-visible', overlayOpen);
            detailBackdrop.hidden = !overlayOpen;
            detailBackdrop.setAttribute('aria-hidden', overlayOpen ? 'false' : 'true');
        }

        function syncDetailPanelState() {
            if (!detailPanel || !detailToggle) return;
            var isTablet = !!(mqTablet && mqTablet.matches);
            var hasPeer = !!selectedPeerId;

            if (detailToggle) {
                detailToggle.hidden = !hasPeer;
            }

            if (!hasPeer) {
                detailPanel.classList.add('msgr-detail-collapsed');
                detailPanel.setAttribute('aria-hidden', 'true');
                if (detailToggle) detailToggle.setAttribute('aria-expanded', 'false');
            } else if (isTablet) {
                detailPanel.classList.add('msgr-detail-collapsed');
                detailPanel.setAttribute('aria-hidden', 'true');
                if (detailToggle) detailToggle.setAttribute('aria-expanded', 'false');
            } else {
                detailPanel.classList.remove('msgr-detail-collapsed');
                detailPanel.setAttribute('aria-hidden', 'false');
                if (detailToggle) detailToggle.setAttribute('aria-expanded', 'true');
            }

            updateDetailBackdrop();
        }

        if (mqTablet) {
            if (typeof mqTablet.addEventListener === 'function') {
                mqTablet.addEventListener('change', syncDetailPanelState);
            } else if (typeof mqTablet.addListener === 'function') {
                mqTablet.addListener(syncDetailPanelState);
            }
        }

        var filter = 'all';
        var selectedPeerId = null;
        var selectedPeer = null;
        var pollTimer = null;
        var lastDayLabel = null;

        function getCsrf() {
            if (csrfInput && csrfInput.value) return csrfInput.value;
            return window.csrfToken || '';
        }

        function peerById(id) {
            for (var i = 0; i < peers.length; i++) {
                if (parseInt(peers[i].id, 10) === id) return peers[i];
            }
            return null;
        }

        function previewLine(p) {
            if (!p.last_body) return 'Start a conversation';
            var you = parseInt(p.last_sender_id, 10) === selfId;
            return (you ? 'You: ' : '') + truncate(p.last_body, 52);
        }

        function matchesFilter(p) {
            if (filter === 'unread' && !(parseInt(p.unread_count, 10) > 0)) return false;
            return true;
        }

        function matchesSearch(p, q) {
            if (!q) return true;
            q = q.toLowerCase();
            return (String(p.name || '').toLowerCase().indexOf(q) >= 0) ||
                (String(p.email || '').toLowerCase().indexOf(q) >= 0);
        }

        function renderPeerList() {
            if (!listEl) return;
            if (!peers.length) {
                listEl.innerHTML = '<div class="msgr-list-empty">No ' + esc(peerLabel.toLowerCase()) + ' available yet.</div>';
                return;
            }
            var q = (searchEl && searchEl.value) ? searchEl.value.trim() : '';
            listEl.innerHTML = '';
            var any = false;
            peers.forEach(function (p) {
                if (!matchesFilter(p) || !matchesSearch(p, q)) return;
                any = true;
                var id = parseInt(p.id, 10);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'msgr-chat-row' + (selectedPeerId === id ? ' active' : '') +
                    (parseInt(p.unread_count, 10) > 0 ? ' unread' : '');
                btn.setAttribute('data-peer-id', String(id));
                var unreadDot = parseInt(p.unread_count, 10) > 0 ? '<span class="msgr-unread-dot" aria-hidden="true"></span>' : '';
                btn.innerHTML =
                    '<div class="msgr-avatar msgr-avatar-sm">' + esc(initials(p.name)) + '</div>' +
                    '<div class="msgr-chat-body">' +
                    '<div class="msgr-chat-top">' +
                    '<span class="msgr-chat-name">' + esc(p.name || 'User') + '</span>' +
                    '<span class="msgr-chat-time">' + esc(formatShortTime(p.last_at)) + '</span>' +
                    '</div>' +
                    '<div class="msgr-chat-preview">' + esc(previewLine(p)) + '</div>' +
                    '</div>' + unreadDot;
                btn.addEventListener('click', function () {
                    selectPeer(id);
                });
                listEl.appendChild(btn);
            });
            if (!any && peers.length) {
                listEl.innerHTML = '<div class="msgr-list-empty">No chats match this filter.</div>';
            }
        }

        function setSelectionClass() {
            app.classList.toggle('msgr-has-selection', !!selectedPeerId);
        }

        function updateHeaderDetail(p) {
            if (!p) {
                if (headName) headName.textContent = 'Select a chat';
                if (headSub) headSub.textContent = peerLabel;
                if (headAvatar) {
                    headAvatar.textContent = '?';
                    headAvatar.classList.add('msgr-avatar--placeholder');
                }
                if (detailName) detailName.textContent = '—';
                if (detailEmail) detailEmail.textContent = '';
                if (detailAvatar) detailAvatar.textContent = '?';
                if (detailRole) detailRole.style.display = 'none';
                if (detailIdle) detailIdle.hidden = false;
                if (detailContact) detailContact.hidden = true;
                syncDetailPanelState();
                return;
            }
            var ini = initials(p.name);
            if (headName) headName.textContent = p.name || 'User';
            if (headSub) headSub.textContent = p.email || peerLabel;
            if (headAvatar) {
                headAvatar.textContent = ini;
                headAvatar.classList.remove('msgr-avatar--placeholder');
            }
            if (detailName) detailName.textContent = p.name || 'User';
            if (detailEmail) detailEmail.textContent = p.email || '';
            if (detailAvatar) detailAvatar.textContent = ini;
            if (detailRole) {
                detailRole.textContent = peerLabel;
                detailRole.style.display = '';
            }
            if (detailIdle) detailIdle.hidden = true;
            if (detailContact) detailContact.hidden = false;
            syncDetailPanelState();
        }

        function selectPeer(id) {
            var p = peerById(id);
            if (!p) return;
            selectedPeerId = id;
            selectedPeer = p;
            if (recipientInput) recipientInput.value = String(id);
            syncComposerControls();
            updateHeaderDetail(p);
            renderPeerList();
            setSelectionClass();
            loadThread();
            markRead();
            updateMessengerUrl(id);
        }

        function renderMessages(rows) {
            if (!threadEl) return;
            threadEl.innerHTML = '';
            lastDayLabel = null;
            if (!selectedPeerId) {
                threadEl.innerHTML =
                    '<div class="msgr-empty-thread">' +
                    '<div class="msgr-empty-icon-wrap"><i class="fas fa-comments"></i></div>' +
                    '<div class="msgr-empty-title">Select a conversation</div>' +
                    '<p class="mb-0 msgr-muted small">Pick someone from the list to read and send staff messages.</p></div>';
                return;
            }
            if (!rows || !rows.length) {
                var empty = document.createElement('div');
                empty.className = 'msgr-empty-thread';
                empty.innerHTML =
                    '<div class="msgr-empty-icon-wrap"><i class="fas fa-paper-plane"></i></div>' +
                    '<div class="msgr-empty-title">Start the conversation</div>' +
                    '<p class="mb-0 msgr-muted small">No messages yet — say hello below.</p>';
                threadEl.appendChild(empty);
                return;
            }
            rows.forEach(function (m) {
                var dayKey = (m.created_at || '').slice(0, 10);
                if (dayKey && dayKey !== lastDayLabel) {
                    lastDayLabel = dayKey;
                    var sep = document.createElement('div');
                    sep.className = 'msgr-day-sep';
                    sep.textContent = formatMsgTime(m.created_at);
                    threadEl.appendChild(sep);
                }
                var row = document.createElement('div');
                row.className = 'msgr-row ' + (m.mine ? 'mine' : 'theirs');
                var bubble = document.createElement('div');
                bubble.className = 'msgr-bubble ' + (m.mine ? 'mine' : 'theirs');
                var meta = document.createElement('div');
                meta.className = 'msgr-bubble-meta';
                meta.textContent = (m.mine ? 'You' : (m.sender_name || 'Other'));
                var body = document.createElement('div');
                body.textContent = m.body || '';
                bubble.appendChild(meta);
                bubble.appendChild(body);
                if (m.attachment_path) {
                    var img = document.createElement('img');
                    img.className = 'msgr-bubble-attach';
                    img.src = base + '/' + String(m.attachment_path).replace(/^\/+/, '');
                    img.alt = 'Attachment';
                    img.loading = 'lazy';
                    bubble.appendChild(img);
                }
                row.appendChild(bubble);
                threadEl.appendChild(row);
            });
            threadEl.scrollTop = threadEl.scrollHeight;
        }

        function loadThread() {
            if (!selectedPeerId || !threadEl) return;
            var url = base + '/backend/messaging/staff_fetch.php?with=' + encodeURIComponent(String(selectedPeerId));
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok && data.messages) {
                        renderMessages(data.messages);
                    } else {
                        threadEl.innerHTML = '<div class="p-3 text-danger small">' +
                            esc(data && data.error ? data.error : 'Could not load messages.') + '</div>';
                    }
                })
                .catch(function () {
                    threadEl.innerHTML = '<div class="p-3 text-danger small">Network error.</div>';
                });
        }

        function markRead() {
            if (!selectedPeerId) return;
            var fd = new FormData();
            fd.append('csrf_token', getCsrf());
            fd.append('other_user_id', String(selectedPeerId));
            fetch(base + '/backend/messaging/staff_mark_read.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
                .then(function () {
                    var p = peerById(selectedPeerId);
                    if (p) {
                        p.unread_count = 0;
                        renderPeerList();
                    }
                })
                .catch(function () { /* ignore */ });
        }

        function bumpLocalPreview(bodyText) {
            var p = peerById(selectedPeerId);
            if (!p) return;
            p.last_body = bodyText;
            p.last_sender_id = selfId;
            p.last_at = new Date().toISOString();
            peers.sort(function (a, b) {
                var ta = a.last_at ? new Date(String(a.last_at).replace(' ', 'T')).getTime() : 0;
                var tb = b.last_at ? new Date(String(b.last_at).replace(' ', 'T')).getTime() : 0;
                return tb - ta;
            });
            renderPeerList();
        }

        if (formEl) {
            formEl.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!selectedPeerId || !textareaEl) return;
                var body = (textareaEl.value || '').trim();
                var hasFile = attachInput && attachInput.files && attachInput.files.length > 0;
                if (!body && !hasFile) return;
                var fd = new FormData(formEl);
                fd.set('csrf_token', getCsrf());
                fd.set('recipient_id', String(selectedPeerId));
                fd.set('body', body);
                if (sendBtn) sendBtn.disabled = true;
                fetch(base + '/backend/messaging/staff_send.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        syncComposerControls();
                        if (data && data.ok) {
                            textareaEl.value = '';
                            clearAttachmentPreview();
                            bumpLocalPreview(body || '[Image]');
                            loadThread();
                        } else {
                            toastError(data && data.error ? data.error : 'Send failed.');
                        }
                    })
                    .catch(function () {
                        syncComposerControls();
                        toastError('Send failed.');
                    });
            });
        }

        document.querySelectorAll('[data-msgr-filter]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                filter = tab.getAttribute('data-msgr-filter') || 'all';
                document.querySelectorAll('[data-msgr-filter]').forEach(function (t) {
                    t.classList.toggle('active', t === tab);
                });
                renderPeerList();
            });
        });

        if (searchEl) {
            searchEl.addEventListener('input', function () {
                renderPeerList();
            });
        }

        if (detailToggle && detailPanel) {
            detailToggle.addEventListener('click', function () {
                if (!selectedPeerId) return;
                var collapsed = detailPanel.classList.toggle('msgr-detail-collapsed');
                detailPanel.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
                detailToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                updateDetailBackdrop();
            });
        }

        function closeDetailPanel() {
            if (!detailPanel || !detailToggle) return;
            detailPanel.classList.add('msgr-detail-collapsed');
            detailPanel.setAttribute('aria-hidden', 'true');
            detailToggle.setAttribute('aria-expanded', 'false');
            updateDetailBackdrop();
        }

        var detailClose = document.getElementById('msgrDetailClose');
        if (detailClose) {
            detailClose.addEventListener('click', closeDetailPanel);
        }

        if (detailBackdrop) {
            detailBackdrop.addEventListener('click', closeDetailPanel);
        }

        document.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Escape' || !detailBackdrop || !detailBackdrop.classList.contains('is-visible')) return;
            closeDetailPanel();
        });

        syncDetailPanelState();

        var backBtn = document.getElementById('msgrBackToList');
        if (backBtn) {
            backBtn.addEventListener('click', function () {
                selectedPeerId = null;
                selectedPeer = null;
                if (recipientInput) recipientInput.value = '';
                if (textareaEl) {
                    textareaEl.value = '';
                }
                clearAttachmentPreview();
                syncComposerControls();
                updateHeaderDetail(null);
                renderPeerList();
                renderMessages([]);
                setSelectionClass();
                updateMessengerUrl(null);
            });
        }

        function startPoll() {
            if (pollTimer) clearInterval(pollTimer);
            pollTimer = setInterval(function () {
                if (document.hidden || !selectedPeerId) return;
                loadThread();
            }, 8000);
        }

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && selectedPeerId) {
                loadThread();
            }
        });

        renderPeerList();
        if (initialWith > 0 && peerById(initialWith)) {
            selectPeer(initialWith);
        } else if (peers.length === 1) {
            selectPeer(parseInt(peers[0].id, 10));
        } else {
            updateHeaderDetail(null);
            renderMessages([]);
            setSelectionClass();
        }

        startPoll();
    }

    document.addEventListener('DOMContentLoaded', bootPage);
})();
