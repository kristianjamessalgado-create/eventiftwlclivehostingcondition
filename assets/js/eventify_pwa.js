/**
 * EVENTIFY student PWA — install prompt, service worker, offline ticket cache.
 */
(function (global) {
    'use strict';

    var STORAGE_TICKETS = 'eventify_my_tickets_v1';
    var STORAGE_PASSES = 'eventify_ticket_passes_v1';
    var STORAGE_INSTALL_DISMISSED = 'eventify_pwa_install_dismissed';
    var STORAGE_PUSH_DISMISSED = 'eventify_push_prompt_dismissed';

    function baseUrl() {
        return (global.BASE_URL || '').replace(/\/$/, '');
    }

    function ticketsApiUrl() {
        return baseUrl() + '/backend/auth/student_tickets_api.php';
    }

    function isOnline() {
        return typeof navigator.onLine === 'boolean' ? navigator.onLine : true;
    }

    function readJson(key, fallback) {
        try {
            var raw = localStorage.getItem(key);
            return raw ? JSON.parse(raw) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function writeJson(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (e) { /* quota */ }
    }

    function normalizeTicketsPayload(data) {
        if (!data || !data.ok || !Array.isArray(data.tickets)) {
            return [];
        }
        return data.tickets;
    }

    function saveTicketsCache(tickets, cachedAt) {
        writeJson(STORAGE_TICKETS, {
            tickets: tickets,
            cached_at: cachedAt || new Date().toISOString()
        });
    }

    function loadTicketsCache() {
        var blob = readJson(STORAGE_TICKETS, null);
        if (!blob || !Array.isArray(blob.tickets)) {
            return { tickets: [], cached_at: null };
        }
        return blob;
    }

    function saveTicketPassCache(ticket) {
        if (!ticket || !ticket.ticket_code) {
            return;
        }
        var map = readJson(STORAGE_PASSES, {});
        map[ticket.ticket_code] = {
            ticket_code: ticket.ticket_code,
            event_title: ticket.event_title || '',
            type_name: ticket.type_name || '',
            event_date: ticket.event_date || '',
            event_location: ticket.event_location || '',
            holder_name: ticket.holder_name || '',
            holder_student_id: ticket.holder_student_id || '',
            checkin_url: ticket.checkin_url || '',
            qr_url: ticket.qr_url || '',
            status: ticket.status || 'valid',
            cached_at: new Date().toISOString()
        };
        writeJson(STORAGE_PASSES, map);
    }

    function loadTicketPassCache(code) {
        var map = readJson(STORAGE_PASSES, {});
        return map[code] || null;
    }

    function fetchAndCacheTickets() {
        if (!isOnline()) {
            return Promise.resolve(loadTicketsCache());
        }
        return fetch(ticketsApiUrl(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var tickets = normalizeTicketsPayload(data);
                saveTicketsCache(tickets, data.cached_at);
                return { tickets: tickets, cached_at: data.cached_at, from_network: true };
            })
            .catch(function () {
                return loadTicketsCache();
            });
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return Promise.resolve();
        }
        return navigator.serviceWorker.register(baseUrl() + '/sw-student.js?v=12', {
            scope: baseUrl() + '/',
            updateViaCache: 'none'
        }).catch(function () { /* dev / http */ });
    }

    function isStandalone() {
        if (global.matchMedia) {
            if (global.matchMedia('(display-mode: standalone)').matches) {
                return true;
            }
            if (global.matchMedia('(display-mode: fullscreen)').matches) {
                return true;
            }
            if (isIosDevice() && global.matchMedia('(display-mode: minimal-ui)').matches) {
                return true;
            }
        }
        return global.navigator.standalone === true;
    }

    function isIosBrowserTab() {
        return isIosDevice() && !isStandalone();
    }

    function isIosDevice() {
        var ua = navigator.userAgent || '';
        if (/iPad|iPhone|iPod/.test(ua)) {
            return true;
        }
        return navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
    }

    function isAndroidDevice() {
        return /Android/i.test(navigator.userAgent || '');
    }

    function isMobileDevice() {
        if (global.matchMedia && global.matchMedia('(max-width: 768px)').matches) {
            return true;
        }
        return isIosDevice() || isAndroidDevice();
    }

    function installDismissed() {
        try {
            return localStorage.getItem(STORAGE_INSTALL_DISMISSED) === '1';
        } catch (e) {
            return false;
        }
    }

    function shouldOfferInstall() {
        return !isStandalone() && !installDismissed();
    }

    function setBannerHint(text) {
        var hint = document.getElementById('pwaInstallBannerHint');
        if (!hint) {
            return;
        }
        if (text) {
            hint.textContent = text;
            hint.hidden = false;
        } else {
            hint.textContent = '';
            hint.hidden = true;
        }
    }

    function revealInstallBanner(mode) {
        var banner = document.getElementById('pwaInstallBanner');
        if (!banner || !shouldOfferInstall()) {
            return;
        }
        banner.hidden = false;
        banner.setAttribute('data-install-mode', mode || 'default');
        if (mode === 'ios') {
            setBannerHint('Tap Install for iPhone steps (Share → Add to Home Screen).');
        } else if (mode === 'android') {
            setBannerHint('Tap Install to add EVENTIFY to your home screen.');
        } else {
            setBannerHint('');
        }
    }

    function installHelpHtml(platform) {
        if (platform === 'ios') {
            return '<p class="pwa-install-modal__lead">Add EVENTIFY to your iPhone home screen — it opens like an app for tickets and QR check-in.</p>' +
                '<ol class="pwa-install-steps">' +
                '<li class="pwa-install-steps__item">' +
                '<span class="pwa-install-steps__icon" aria-hidden="true"><i class="fas fa-share-square"></i></span>' +
                '<span>Tap <strong>Share</strong> at the bottom of Safari (square with an arrow).</span>' +
                '</li>' +
                '<li class="pwa-install-steps__item">' +
                '<span class="pwa-install-steps__icon" aria-hidden="true"><i class="fas fa-plus-square"></i></span>' +
                '<span>Scroll down and tap <strong>Add to Home Screen</strong>.</span>' +
                '</li>' +
                '<li class="pwa-install-steps__item">' +
                '<span class="pwa-install-steps__icon" aria-hidden="true"><i class="fas fa-check-circle"></i></span>' +
                '<span>Tap <strong>Add</strong> in the top right.</span>' +
                '</li>' +
                '</ol>' +
                '<p class="pwa-install-modal__note"><i class="fas fa-info-circle me-1"></i>Use <strong>Safari</strong> — Chrome on iPhone cannot install home screen apps the same way.</p>';
        }
        if (platform === 'android') {
            return '<p class="pwa-install-modal__lead">Add EVENTIFY to your Android home screen for one-tap access to tickets and QR check-in.</p>' +
                '<ol class="pwa-install-steps">' +
                '<li class="pwa-install-steps__item">' +
                '<span class="pwa-install-steps__icon" aria-hidden="true"><i class="fas fa-ellipsis-v"></i></span>' +
                '<span>Tap the <strong>menu</strong> (⋮) in Chrome, top right.</span>' +
                '</li>' +
                '<li class="pwa-install-steps__item">' +
                '<span class="pwa-install-steps__icon" aria-hidden="true"><i class="fas fa-download"></i></span>' +
                '<span>Tap <strong>Install app</strong> or <strong>Add to Home screen</strong>.</span>' +
                '</li>' +
                '<li class="pwa-install-steps__item">' +
                '<span class="pwa-install-steps__icon" aria-hidden="true"><i class="fas fa-check-circle"></i></span>' +
                '<span>Confirm — EVENTIFY appears on your home screen.</span>' +
                '</li>' +
                '</ol>';
        }
        return '<p class="pwa-install-modal__lead">Add EVENTIFY to your home screen for quick access to tickets and QR check-in.</p>' +
            '<ol class="pwa-install-steps">' +
            '<li class="pwa-install-steps__item"><span>Open your browser menu.</span></li>' +
            '<li class="pwa-install-steps__item"><span>Choose <strong>Install app</strong> or <strong>Add to Home screen</strong>.</span></li>' +
            '<li class="pwa-install-steps__item"><span>Confirm to add EVENTIFY.</span></li>' +
            '</ol>';
    }

    function openInstallHelp(forcePlatform) {
        var platform = forcePlatform;
        if (!platform) {
            if (isIosDevice()) {
                platform = 'ios';
            } else if (isAndroidDevice()) {
                platform = 'android';
            } else {
                platform = 'default';
            }
        }
        var modalEl = document.getElementById('pwaInstallHelpModal');
        var bodyEl = document.getElementById('pwaInstallHelpModalBody');
        if (bodyEl) {
            bodyEl.innerHTML = installHelpHtml(platform);
        }
        if (modalEl && global.bootstrap && global.bootstrap.Modal) {
            global.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return;
        }
        alert(installHelpHtml(platform).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim());
    }

    function syncInstallSidebarButton() {
        var btn = document.getElementById('pwaInstallSidebarBtn');
        if (!btn) {
            return;
        }
        btn.hidden = isStandalone() || (!isMobileDevice() && !isIosDevice() && !isAndroidDevice());
    }

    function initInstallPrompt() {
        var deferredPrompt = null;
        var banner = document.getElementById('pwaInstallBanner');
        var mobileBannerScheduled = false;

        function triggerInstallFlow() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.finally(function () {
                    deferredPrompt = null;
                    if (banner) {
                        banner.hidden = true;
                    }
                });
                return;
            }
            openInstallHelp();
        }

        global.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            deferredPrompt = e;
            if (banner && shouldOfferInstall()) {
                banner.hidden = false;
                setBannerHint(isAndroidDevice() ? 'Tap Install to add EVENTIFY to your home screen.' : '');
            }
        });

        if (banner) {
            var installBtn = document.getElementById('pwaInstallBtn');
            var dismissBtn = document.getElementById('pwaInstallDismiss');

            if (installBtn) {
                installBtn.addEventListener('click', triggerInstallFlow);
            }

            if (dismissBtn) {
                dismissBtn.addEventListener('click', function () {
                    banner.hidden = true;
                    try {
                        localStorage.setItem(STORAGE_INSTALL_DISMISSED, '1');
                    } catch (err) { /* ignore */ }
                    syncInstallSidebarButton();
                });
            }

            if (isStandalone()) {
                banner.hidden = true;
            }
        }

        var sidebarBtn = document.getElementById('pwaInstallSidebarBtn');
        if (sidebarBtn) {
            sidebarBtn.addEventListener('click', function () {
                triggerInstallFlow();
            });
        }

        syncInstallSidebarButton();

        if (shouldOfferInstall() && isMobileDevice() && !mobileBannerScheduled) {
            mobileBannerScheduled = true;
            if (isIosDevice()) {
                setTimeout(function () {
                    revealInstallBanner('ios');
                }, 600);
            } else {
                setTimeout(function () {
                    if (!deferredPrompt && shouldOfferInstall()) {
                        revealInstallBanner(isAndroidDevice() ? 'android' : 'default');
                    }
                }, 2200);
            }
        }

        global.addEventListener('appinstalled', function () {
            if (banner) {
                banner.hidden = true;
            }
            syncInstallSidebarButton();
        });
    }

    function renderTicketsList(container, tickets, opts) {
        if (!container) {
            return;
        }
        opts = opts || {};
        if (!tickets.length) {
            container.innerHTML = '<p class="text-muted mb-0">You have no saved tickets. Buy tickets while online, then they appear here for offline use.</p>';
            return;
        }
        var html = '<div class="row g-3">';
        tickets.forEach(function (t) {
            var passUrl = t.pass_url || (baseUrl() + '/ticket_pass.php?code=' + encodeURIComponent(t.ticket_code || ''));
            html += '<div class="col-12"><div class="card ticket-pass-preview shadow-sm border-0">' +
                '<div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">' +
                '<div><div class="fw-semibold">' + escapeHtml(t.event_title || '') + '</div>' +
                '<div class="small text-muted">' + escapeHtml(t.type_name || '') + ' · ' + escapeHtml(t.ticket_code || '') + '</div>' +
                '<div class="small">' + escapeHtml(t.event_date || '') + '</div></div>' +
                '<a href="' + escapeHtml(passUrl) + '" class="btn btn-success btn-sm"><i class="fas fa-qrcode me-1"></i>Digital pass</a>' +
                '</div></div></div>';
        });
        html += '</div>';
        container.innerHTML = html;

        if (opts.offlineBanner && opts.cachedAt) {
            var notice = document.getElementById('pwaOfflineTicketsNotice');
            if (notice) {
                notice.hidden = false;
                notice.textContent = 'Offline — showing tickets saved ' + formatCachedAt(opts.cachedAt);
            }
        }
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function formatCachedAt(iso) {
        try {
            return new Date(iso).toLocaleString();
        } catch (e) {
            return 'earlier';
        }
    }

    function initMyTicketsPage() {
        var listEl = document.getElementById('myTicketsList');
        if (!listEl) {
            return;
        }
        var serverTickets = global.__myTicketsBootstrap || [];

        function apply(tickets, cachedAt, offline) {
            if (tickets.length) {
                renderTicketsList(listEl, tickets, { offlineBanner: offline, cachedAt: cachedAt });
            } else if (!offline && serverTickets.length) {
                renderTicketsList(listEl, serverTickets, {});
            }
        }

        if (isOnline()) {
            fetchAndCacheTickets().then(function (blob) {
                apply(blob.tickets.length ? blob.tickets : serverTickets, blob.cached_at, false);
            });
        } else {
            var cached = loadTicketsCache();
            var tickets = cached.tickets.length ? cached.tickets : serverTickets.map(function (t) {
                return {
                    ticket_code: t.ticket_code,
                    event_title: t.event_title,
                    type_name: t.type_name,
                    event_date: (t.event_date || '').slice(0, 10),
                    pass_url: baseUrl() + '/ticket_pass.php?code=' + encodeURIComponent(t.ticket_code || '')
                };
            });
            apply(tickets, cached.cached_at, true);
        }

        global.addEventListener('online', function () {
            fetchAndCacheTickets().then(function (blob) {
                var notice = document.getElementById('pwaOfflineTicketsNotice');
                if (notice) {
                    notice.hidden = true;
                }
                apply(blob.tickets, blob.cached_at, false);
            });
        });
    }

    function initTicketPassPage() {
        var bootstrap = global.__ticketPassBootstrap;
        if (bootstrap && isOnline()) {
            saveTicketPassCache(bootstrap);
        }
        if (!isOnline() && bootstrap && bootstrap.ticket_code) {
            var cached = loadTicketPassCache(bootstrap.ticket_code);
            if (cached) {
                applyOfflinePass(cached);
            }
        }
    }

    function applyOfflinePass(cached) {
        var title = document.getElementById('ticketPassEventTitle');
        var code = document.getElementById('ticketPassCode');
        var type = document.getElementById('ticketPassType');
        var qr = document.getElementById('ticketPassQr');
        var notice = document.getElementById('pwaOfflinePassNotice');
        if (title) title.textContent = cached.event_title || 'Event';
        if (type) type.textContent = cached.type_name || '';
        if (code) code.textContent = cached.ticket_code || '';
        if (qr && cached.qr_url) qr.src = cached.qr_url;
        if (notice) notice.hidden = false;
    }

    function csrfToken() {
        return global.csrfToken || '';
    }

    function pushApiUrl() {
        return baseUrl() + '/backend/auth/push_subscription_api.php';
    }

    var cachedPushKeys = { publicKey: null, keyBytes: null };
    var swReadyPromise = null;

    function normalizeVapidPublicKey(key) {
        return String(key || '').trim().replace(/\s+/g, '');
    }

    function freshKeyBytes(keyBytes) {
        var copy = new Uint8Array(keyBytes.length);
        copy.set(keyBytes);
        return copy;
    }

    function initPushKeysSync() {
        var keys = pushKeysFromPublicKey(readEmbeddedVapidKey());
        if (keys) {
            cachePushKeys(keys);
        }
    }

    function getCachedPushKeysOrNull() {
        if (cachedPushKeys.publicKey && cachedPushKeys.keyBytes) {
            return cachedPushKeys;
        }
        var keys = pushKeysFromPublicKey(readEmbeddedVapidKey());
        if (keys) {
            return cachePushKeys(keys);
        }
        return null;
    }

    function urlBase64ToUint8Array(base64String) {
        var normalized = normalizeVapidPublicKey(base64String);
        if (!normalized) {
            return new Uint8Array(0);
        }
        if (isIosDevice() && typeof fetch === 'function') {
            var std = toStandardBase64(normalized);
            return fetch('data:application/octet-stream;base64,' + std)
                .then(function (res) { return res.arrayBuffer(); })
                .then(function (buf) { return new Uint8Array(buf); })
                .catch(function () {
                    return urlBase64ToUint8ArraySync(normalized);
                });
        }
        return Promise.resolve(urlBase64ToUint8ArraySync(normalized));
    }

    function urlBase64ToUint8ArraySync(base64String) {
        var normalized = normalizeVapidPublicKey(base64String);
        if (!normalized) {
            return new Uint8Array(0);
        }
        var padding = '='.repeat((4 - normalized.length % 4) % 4);
        var base64 = (normalized + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function pushKeysFromPublicKey(publicKey) {
        var normalized = normalizeVapidPublicKey(publicKey);
        if (!normalized) {
            return null;
        }
        var keyBytes = urlBase64ToUint8ArraySync(normalized);
        if (!isValidVapidPublicKeyBytes(keyBytes)) {
            return null;
        }
        return { publicKey: normalized, keyBytes: keyBytes };
    }

    function isValidVapidPublicKeyBytes(bytes) {
        return bytes && bytes.length === 65 && bytes[0] === 4;
    }

    function pushKeysFromPublicKeyAsync(publicKey) {
        var normalized = normalizeVapidPublicKey(publicKey);
        if (!normalized) {
            return Promise.resolve(null);
        }
        return urlBase64ToUint8Array(normalized).then(function (keyBytes) {
            if (!isValidVapidPublicKeyBytes(keyBytes)) {
                return null;
            }
            return { publicKey: normalized, keyBytes: keyBytes };
        });
    }

    function cachePushKeys(keys) {
        if (keys && keys.publicKey && keys.keyBytes) {
            cachedPushKeys.publicKey = keys.publicKey;
            cachedPushKeys.keyBytes = keys.keyBytes;
        }
        return keys;
    }

    function readEmbeddedVapidKey() {
        var fromWindow = global.__eventifyVapidPublicKey || '';
        if (fromWindow) {
            return fromWindow;
        }
        var meta = document.querySelector('meta[name="eventify-vapid-key"]');
        return meta ? (meta.getAttribute('content') || '') : '';
    }

    function toStandardBase64(base64url) {
        var normalized = normalizeVapidPublicKey(base64url);
        var padding = '='.repeat((4 - normalized.length % 4) % 4);
        return normalized.replace(/-/g, '+').replace(/_/g, '/') + padding;
    }

    function applicationServerKeyCandidates(keys) {
        var fresh = freshKeyBytes(keys.keyBytes);
        var buffer = fresh.buffer.slice(fresh.byteOffset, fresh.byteOffset + fresh.byteLength);
        if (isIosDevice()) {
            return [keys.publicKey, toStandardBase64(keys.publicKey), fresh, buffer];
        }
        return [fresh, buffer, keys.publicKey, toStandardBase64(keys.publicKey)];
    }

    function pushSubscribeWithKey(reg, keys) {
        if (isIosDevice()) {
            return reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: keys.publicKey
            }).catch(function () {
                return reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: toStandardBase64(keys.publicKey)
                });
            }).catch(function () {
                return reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: freshKeyBytes(keys.keyBytes)
                });
            });
        }
        var candidates = applicationServerKeyCandidates(keys);
        var attempt = Promise.reject(new Error('subscribe_start'));
        candidates.forEach(function (candidate) {
            attempt = attempt.catch(function () {
                return reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: candidate
                });
            });
        });
        return attempt.catch(function (err) {
            var msg = (err && err.message) ? err.message : 'subscribe_failed';
            return Promise.reject(new Error(msg));
        });
    }

    function prepareIosPushSubscription(reg, keys) {
        return reg.pushManager.getSubscription().then(function (existing) {
            if (existing) {
                return existing.unsubscribe().catch(function () { return null; });
            }
            return null;
        }).then(function () {
            return pushSubscribeWithKey(reg, keys);
        });
    }

    function resolvePushKeys() {
        var embedded = pushKeysFromPublicKey(readEmbeddedVapidKey());
        if (embedded) {
            return Promise.resolve(cachePushKeys(embedded));
        }
        if (cachedPushKeys.publicKey && cachedPushKeys.keyBytes) {
            return Promise.resolve(cachedPushKeys);
        }
        return fetchPushStatus().then(function (data) {
            if (!data || !data.ok || !data.configured || !data.publicKey) {
                return fetch(baseUrl() + '/backend/auth/vapid_public_key.php', { cache: 'no-store' })
                    .then(function (r) { return r.json(); })
                    .then(function (pub) {
                        if (!pub || !pub.ok || !pub.publicKey) {
                            return null;
                        }
                        return cachePushKeys(pushKeysFromPublicKey(pub.publicKey));
                    });
            }
            return cachePushKeys(pushKeysFromPublicKey(data.publicKey));
        });
    }

    function warmPushEnvironment() {
        swReadyPromise = ensureServiceWorkerReady();
        return resolvePushKeys();
    }

    function studentPushEnabledInSettings() {
        var s = global.__studentSettings || {};
        if (s.notif_channel_push === undefined) {
            return true;
        }
        return s.notif_channel_push === 1 || s.notif_channel_push === true || s.notif_channel_push === '1';
    }

    function pushSupported() {
        return 'Notification' in global && 'serviceWorker' in navigator && 'PushManager' in global;
    }

    function fetchPushStatus() {
        return fetch(pushApiUrl(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Cache-Control': 'no-cache' }
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!data || typeof data !== 'object') {
                    return { ok: false, error: 'invalid_response' };
                }
                if (r.status === 403 || data.error === 'Access denied') {
                    return { ok: false, error: 'login_required' };
                }
                return data;
            });
        }).catch(function () { return null; });
    }

    function fetchVapidPublicKey() {
        return fetchPushStatus().then(function (data) {
            if (!data || !data.ok || !data.configured || !data.publicKey) {
                return null;
            }
            var publicKey = normalizeVapidPublicKey(data.publicKey);
            var keyBytes = urlBase64ToUint8ArraySync(publicKey);
            if (!isValidVapidPublicKeyBytes(keyBytes)) {
                return null;
            }
            return publicKey;
        });
    }

    function pushStatusMessage(result) {
        if (result && result.ok) {
            return 'Push notifications enabled on this device.';
        }
        if (result && result.error === 'login_required') {
            return 'Please log in first, then enable push again.';
        }
        if (result && result.error === 'not_configured') {
            return 'Push is not configured on the server. VAPID keys in .env may be invalid — generate a new pair and upload .env again.';
        }
        if (result && result.error && result.error.indexOf('Invalid VAPID keys') !== -1) {
            return result.error;
        }
        if (result && result.error === 'ios_install_required') {
            return 'Push on iPhone only works from the Home Screen app. In Safari: Share → Add to Home Screen, then open EVENTIFY from the new icon.';
        }
        if (result && result.error && result.error.indexOf('applicationServerKey') !== -1) {
            return 'Push setup failed on this iPhone. Delete the EVENTIFY home screen icon, add it again from Safari, log in, then tap Enable.';
        }
        return 'Could not enable push: ' + ((result && result.error) || 'unknown error');
    }

    function performPushSubscribe(reg, keys) {
        if (!keys || !keys.publicKey || !keys.keyBytes) {
            return Promise.resolve({ ok: false, error: 'not_configured' });
        }
        if (keys.publicKey.length < 80) {
            return Promise.resolve({
                ok: false,
                error: 'Push key missing on page. Upload latest dashboard_student.php to the server.'
            });
        }
        var subscribePromise = isIosDevice()
            ? prepareIosPushSubscription(reg, keys)
            : reg.pushManager.getSubscription().then(function (existing) {
                if (existing) {
                    return savePushSubscriptionToServer(existing);
                }
                return pushSubscribeWithKey(reg, keys);
            });
        return subscribePromise.then(function (result) {
            if (result && result.ok !== undefined) {
                return result;
            }
            return savePushSubscriptionToServer(result);
        }).catch(function (err) {
            return { ok: false, error: (err && err.message) ? err.message : 'subscribe_failed' };
        });
    }

    function savePushSubscriptionToServer(subscription) {
        return fetch(pushApiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'subscribe',
                csrf_token: csrfToken(),
                subscription: subscription.toJSON()
            })
        }).then(function (r) { return r.json(); });
    }

    function syncPushSubscription(showAlerts) {
        if (!pushSupported() || !studentPushEnabledInSettings()) {
            return Promise.resolve({ ok: false, error: 'unsupported' });
        }
        if (isIosBrowserTab()) {
            if (showAlerts) {
                openInstallHelp('ios');
            }
            return Promise.resolve({ ok: false, error: 'ios_install_required' });
        }
        if (Notification.permission !== 'granted') {
            return Promise.resolve({ ok: false, error: 'permission_not_granted' });
        }
        var ready = swReadyPromise || ensureServiceWorkerReady();
        return resolvePushKeys().then(function (keys) {
            if (!keys) {
                return { ok: false, error: 'not_configured' };
            }
            return ready.then(function (reg) {
                return performPushSubscribe(reg, keys);
            });
        }).then(function (result) {
            if (result && result.ok) {
                return result;
            }
            if (showAlerts) {
                alert(pushStatusMessage(result));
            }
            return result || { ok: false, error: 'subscribe_failed' };
        });
    }

    function sendTestPush() {
        return fetch(pushApiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'test',
                csrf_token: csrfToken()
            })
        }).then(function (r) { return r.json(); });
    }

    function ensureServiceWorkerReady() {
        return registerServiceWorker().then(function (registration) {
            if (!('serviceWorker' in navigator)) {
                return Promise.reject(new Error('Service worker not supported'));
            }
            if (registration && registration.waiting && registration.active) {
                registration.waiting.postMessage({ type: 'SKIP_WAITING' });
            }
            return Promise.race([
                navigator.serviceWorker.ready,
                new Promise(function (_, reject) {
                    setTimeout(function () {
                        reject(new Error('Service worker did not start. Close the app and open it again from Home Screen.'));
                    }, 12000);
                })
            ]).then(function (reg) {
                if (navigator.serviceWorker.controller) {
                    return reg;
                }
                return new Promise(function (resolve) {
                    var timer = setTimeout(function () { resolve(reg); }, 3000);
                    navigator.serviceWorker.addEventListener('controllerchange', function () {
                        clearTimeout(timer);
                        resolve(reg);
                    }, { once: true });
                });
            });
        });
    }

    function waitForPushPermission(reg, keys) {
        if (!reg || !reg.pushManager || typeof reg.pushManager.permissionState !== 'function') {
            return Promise.resolve('unknown');
        }
        var fresh = freshKeyBytes(keys.keyBytes);
        return reg.pushManager.permissionState({
            userVisibleOnly: true,
            applicationServerKey: fresh
        }).catch(function () {
            return 'unknown';
        });
    }

    function subscribePush() {
        if (!pushSupported()) {
            return Promise.resolve({ ok: false, error: 'unsupported' });
        }
        if (isIosBrowserTab()) {
            return Promise.resolve({ ok: false, error: 'ios_install_required' });
        }
        var ready = swReadyPromise || ensureServiceWorkerReady();
        return resolvePushKeys().then(function (keys) {
            if (!keys) {
                return { ok: false, error: 'not_configured' };
            }
            return ready.then(function (reg) {
                return performPushSubscribe(reg, keys);
            });
        }).catch(function (err) {
            return { ok: false, error: (err && err.message) ? err.message : 'subscribe_failed' };
        });
    }

    function enablePushFromSettings() {
        if (!pushSupported()) {
            alert('This browser does not support push notifications.');
            return Promise.resolve({ ok: false, error: 'unsupported' });
        }
        if (isIosBrowserTab()) {
            openInstallHelp('ios');
            return Promise.resolve({ ok: false, error: 'ios_install_required' });
        }
        if (Notification.permission === 'granted') {
            return syncPushSubscription(true);
        }
        return Notification.requestPermission().then(function (perm) {
            if (perm !== 'granted') {
                alert('Allow notifications in your browser, then try again.');
                return { ok: false, permission: perm };
            }
            return syncPushSubscription(true);
        });
    }

    function bindPushBannerActions(banner) {
        var enableBtn = document.getElementById('pwaPushEnableBtn');
        var dismissBtn = document.getElementById('pwaPushDismissBtn') || document.getElementById('pwaPushDismiss');
        if (enableBtn && !enableBtn.dataset.bound) {
            enableBtn.dataset.bound = '1';
            var onEnable = function (e) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                if (isIosBrowserTab()) {
                    openInstallHelp('ios');
                    return;
                }
                handlePushEnableClick(enableBtn, banner);
            };
            enableBtn.addEventListener('click', onEnable);
        }
        if (dismissBtn && !dismissBtn.dataset.bound) {
            dismissBtn.dataset.bound = '1';
            var onDismiss = function (e) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                if (banner) {
                    banner.hidden = true;
                }
                try {
                    localStorage.setItem(STORAGE_PUSH_DISMISSED, '1');
                } catch (err) { /* ignore */ }
            };
            dismissBtn.addEventListener('click', onDismiss);
        }
    }

    function handlePushEnableClick(enableBtn, banner) {
        if (!enableBtn || enableBtn.disabled) {
            return;
        }
        if (!('Notification' in global)) {
            alert('This browser does not support notifications.');
            return;
        }
        if (isIosBrowserTab()) {
            openInstallHelp('ios');
            return;
        }
        enableBtn.disabled = true;
        var prevLabel = enableBtn.textContent;
        enableBtn.textContent = 'Enabling…';

        var keys = getCachedPushKeysOrNull();
        if (!keys) {
            alert('Push key not loaded yet. Pull down to refresh the page, wait 3 seconds, then tap Enable again.');
            enableBtn.disabled = false;
            enableBtn.textContent = prevLabel || 'Enable';
            return;
        }

        swReadyPromise = ensureServiceWorkerReady();
        swReadyPromise.then(function (reg) {
            function afterPermission(perm) {
                if (perm !== 'granted') {
                    alert(
                        perm === 'denied'
                            ? 'Notifications are blocked. On iPhone: Settings → Notifications → EVENTIFY → Allow Notifications.'
                            : 'Permission not granted. Tap Enable again and choose Allow.'
                    );
                    return { ok: false, permission: perm };
                }
                return performPushSubscribe(reg, keys);
            }
            if (Notification.permission === 'granted') {
                return afterPermission('granted');
            }
            return Notification.requestPermission().then(afterPermission);
        }).then(function (result) {
            if (!result) {
                return;
            }
            if (result.ok) {
                if (banner) {
                    banner.hidden = true;
                }
                alert('Push notifications enabled on this device.');
                return;
            }
            alert(pushStatusMessage(result));
        }).catch(function (err) {
            alert(pushStatusMessage({ ok: false, error: (err && err.message) ? err.message : 'subscribe_failed' }));
        }).finally(function () {
            enableBtn.disabled = false;
            enableBtn.textContent = prevLabel || 'Enable';
        });
    }

    function initPushNotifications() {
        var banner = document.getElementById('pwaPushBanner');
        initPushKeysSync();
        warmPushEnvironment();
        if (!pushSupported() || !studentPushEnabledInSettings()) {
            if (banner) {
                banner.hidden = true;
            }
            return;
        }

        if (Notification.permission === 'granted') {
            syncPushSubscription(false).then(function (result) {
                if (banner) {
                    var needsFix = result && !result.ok && !result.already_ready;
                    banner.hidden = !needsFix;
                    if (needsFix) {
                        bindPushBannerActions(banner);
                    }
                }
            });
            return;
        }

        if (Notification.permission === 'denied') {
            if (banner) {
                banner.hidden = true;
            }
            return;
        }

        try {
            if (localStorage.getItem(STORAGE_PUSH_DISMISSED) === '1') {
                if (banner) {
                    banner.hidden = true;
                }
                return;
            }
        } catch (e) { /* ignore */ }

        if (isIosBrowserTab()) {
            if (banner) {
                banner.hidden = false;
                banner.setAttribute('data-push-mode', 'ios-install');
                var iosNote = banner.querySelector('[data-ios-push-note]');
                if (iosNote) {
                    iosNote.hidden = false;
                }
                var enableBtn = document.getElementById('pwaPushEnableBtn');
                if (enableBtn) {
                    enableBtn.textContent = 'Install first';
                }
            }
            bindPushBannerActions(banner);
            return;
        }

        bindPushBannerActions(banner);

        fetchVapidPublicKey().then(function (key) {
            if (!key) {
                if (banner) {
                    banner.hidden = true;
                }
                return;
            }
            if (banner) {
                banner.hidden = false;
            }
        });
    }

    function initOfflineBanner() {
        var banner = document.getElementById('pwaOfflineBanner');
        if (!banner) {
            return;
        }
        function sync() {
            banner.hidden = isOnline();
        }
        sync();
        global.addEventListener('online', sync);
        global.addEventListener('offline', sync);
    }

    function initStudentDashboard() {
        initOfflineBanner();
        initPushNotifications();
        if (isOnline()) {
            fetchAndCacheTickets();
        }
    }

    function boot() {
        registerServiceWorker();
        initInstallPrompt();
        initOfflineBanner();
        if (document.getElementById('myTicketsList')) {
            initMyTicketsPage();
        }
        if (global.__ticketPassBootstrap) {
            initTicketPassPage();
        }
        if (document.body && document.body.classList.contains('student-dashboard')) {
            initStudentDashboard();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    initPushKeysSync();

    global.eventifyPwa = {
        fetchAndCacheTickets: fetchAndCacheTickets,
        loadTicketsCache: loadTicketsCache,
        saveTicketPassCache: saveTicketPassCache,
        loadTicketPassCache: loadTicketPassCache,
        openInstallHelp: openInstallHelp,
        isStandalone: isStandalone,
        enablePush: enablePushFromSettings,
        subscribePush: subscribePush,
        syncPushSubscription: syncPushSubscription,
        sendTestPush: sendTestPush,
        fetchPushStatus: fetchPushStatus,
        pushSupported: pushSupported
    };
})(typeof window !== 'undefined' ? window : this);
