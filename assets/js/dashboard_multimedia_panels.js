function mmDashboardBaseUrl() {
    var base = (window.BASE_URL || '').replace(/\/$/, '');
    return base + '/backend/auth/dashboard_multimedia.php';
}

var MM_DASH_PANEL_MAP = {
    events: 'mmAllEventsPanel',
    upcoming: 'mmUpcomingEventsPanel',
    photo_approvals: 'mmPhotoApprovalsPanel',
    photo_activity: 'mmPhotoActivityPanel'
};

var MM_DASH_PANEL_NAMES = Object.keys(MM_DASH_PANEL_MAP);
var MM_PANEL_TRANSITION_MS = 300;
var mmPanelTransitionLock = false;

function mmDashboardPanelUrl(panel) {
    return mmDashboardBaseUrl() + '?panel=' + encodeURIComponent(panel);
}

function mmResolvePanelFromUrl() {
    try {
        var params = new URLSearchParams(window.location.search);
        var openModal = params.get('open_modal');
        if (openModal === 'photo_approvals') {
            return 'photo_approvals';
        }
        if (openModal === 'photo_activity') {
            return 'photo_activity';
        }
        return params.get('panel') || 'home';
    } catch (e) {
        return 'home';
    }
}

function mmGetPanelFromUrl() {
    return mmResolvePanelFromUrl();
}

function mmUpdatePanelUrl(panel, replace) {
    try {
        var url = new URL(window.location.href);
        if (!panel || panel === 'home') {
            url.searchParams.delete('panel');
        } else {
            url.searchParams.set('panel', panel);
        }
        url.searchParams.delete('open_modal');
        var next = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash;
        var state = { mmPanel: panel || 'home' };
        if (replace) {
            history.replaceState(state, '', next);
        } else {
            history.pushState(state, '', next);
        }
    } catch (e) { /* ignore */ }
}

function mmPrefersReducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
}

function mmGetViewForPanel(panelName) {
    if (panelName === 'home' || !panelName) {
        return document.getElementById('mmDashboardHome');
    }
    var id = MM_DASH_PANEL_MAP[panelName];
    return id ? document.getElementById(id) : null;
}

function mmIsViewVisible(el) {
    return !!(el && !el.classList.contains('d-none') && !el.hasAttribute('hidden'));
}

function mmGetVisibleView() {
    var home = document.getElementById('mmDashboardHome');
    if (mmIsViewVisible(home)) {
        return home;
    }
    for (var i = 0; i < MM_DASH_PANEL_NAMES.length; i += 1) {
        var el = document.getElementById(MM_DASH_PANEL_MAP[MM_DASH_PANEL_NAMES[i]]);
        if (mmIsViewVisible(el)) {
            return el;
        }
    }
    return null;
}

function mmClearViewMotionClasses(el) {
    if (!el) {
        return;
    }
    el.classList.remove(
        'mm-main-view--switching',
        'mm-main-view--exit',
        'mm-main-view--exit-active',
        'mm-main-view--enter',
        'mm-main-view--enter-active',
        'mm-dash-panel--enter'
    );
}

function mmHideView(el) {
    if (!el) {
        return;
    }
    mmClearViewMotionClasses(el);
    el.classList.add('d-none');
    el.setAttribute('hidden', '');
}

function mmRevealView(el) {
    if (!el) {
        return;
    }
    el.classList.remove('d-none');
    el.removeAttribute('hidden');
}

function mmForceReflow(el) {
    if (el) {
        void el.offsetHeight;
    }
}

function mmGetAllMainViews() {
    var views = [];
    var home = document.getElementById('mmDashboardHome');
    if (home) {
        views.push(home);
    }
    MM_DASH_PANEL_NAMES.forEach(function (name) {
        var el = document.getElementById(MM_DASH_PANEL_MAP[name]);
        if (el) {
            views.push(el);
        }
    });
    return views;
}

function mmApplyPanelVisibility(panelName) {
    var target = mmGetViewForPanel(panelName);
    mmGetAllMainViews().forEach(function (el) {
        if (el === target) {
            mmRevealView(el);
        } else {
            mmHideView(el);
        }
    });
    return target;
}

function mmUpdateNavState(panelName) {
    document.querySelectorAll('[data-mm-panel]').forEach(function (btn) {
        var target = btn.getAttribute('data-mm-panel');
        if (!target || target === 'home') {
            return;
        }
        var active = target === panelName;
        btn.classList.toggle('is-active', active);
        if (active && !mmPrefersReducedMotion()) {
            btn.classList.add('mm-nav-just-activated');
            window.setTimeout(function () {
                btn.classList.remove('mm-nav-just-activated');
            }, 420);
        }
    });
}

function mmAfterPanelSwitch(panelName, panelOpen, options) {
    if (!panelOpen && window.eventifyMmCalendar && typeof window.eventifyMmCalendar.updateSize === 'function') {
        [50, 180, 320, 600, 1000].forEach(function (ms) {
            window.setTimeout(function () {
                window.eventifyMmCalendar.updateSize();
            }, ms);
        });
    }

    if (!options.skipUrl) {
        mmUpdatePanelUrl(panelName === 'home' ? '' : panelName, !!options.replaceUrl);
    }

    var mainContent = document.querySelector('body.multimedia-dashboard .main-content');
    if (mainContent) {
        mainContent.scrollTop = 0;
    }

    if (options.highlightEventId) {
        window.setTimeout(function () {
            mmHighlightEventCard(options.highlightEventId);
        }, 300);
    }
}

function mmRunViewTransition(fromEl, toEl, done) {
    var mainContent = document.querySelector('body.multimedia-dashboard .main-content');

    if (!toEl) {
        done();
        return;
    }

    if (mmPrefersReducedMotion() || !fromEl || fromEl === toEl) {
        mmApplyPanelVisibility(mmPanelNameFromView(toEl));
        done();
        return;
    }

    mmGetAllMainViews().forEach(function (el) {
        if (el !== fromEl && el !== toEl) {
            mmHideView(el);
        }
    });

    mmRevealView(toEl);
    mmClearViewMotionClasses(fromEl);
    mmClearViewMotionClasses(toEl);

    fromEl.classList.add('mm-main-view--switching', 'mm-main-view--exit');
    toEl.classList.add('mm-main-view--switching', 'mm-main-view--enter');

    if (mainContent) {
        mainContent.classList.add('mm-main-view-switching');
        if (fromEl.offsetHeight) {
            mainContent.style.minHeight = fromEl.offsetHeight + 'px';
        }
    }

    mmForceReflow(toEl);

    window.requestAnimationFrame(function () {
        window.requestAnimationFrame(function () {
            fromEl.classList.add('mm-main-view--exit-active');
            toEl.classList.add('mm-main-view--enter-active');
            if (toEl.classList.contains('mm-dash-panel')) {
                toEl.classList.add('mm-dash-panel--enter');
            }
        });
    });

    window.setTimeout(function () {
        mmHideView(fromEl);
        mmClearViewMotionClasses(toEl);
        if (mainContent) {
            mainContent.classList.remove('mm-main-view-switching');
            mainContent.style.minHeight = '';
        }
        done();
    }, MM_PANEL_TRANSITION_MS);
}

function mmPanelNameFromView(viewEl) {
    if (!viewEl || viewEl.id === 'mmDashboardHome') {
        return 'home';
    }
    for (var i = 0; i < MM_DASH_PANEL_NAMES.length; i += 1) {
        var name = MM_DASH_PANEL_NAMES[i];
        if (viewEl.id === MM_DASH_PANEL_MAP[name]) {
            return name;
        }
    }
    return 'home';
}

function mmHighlightEventCard(eventId) {
    if (!eventId) {
        return;
    }
    if (typeof window.mmResetEventListFilters === 'function') {
        window.mmResetEventListFilters();
    }
    var card = document.getElementById('mm-event-' + eventId);
    if (!card) {
        return;
    }
    card.style.display = '';
    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    card.classList.add('mm-event-card-flash');
    window.setTimeout(function () {
        card.classList.remove('mm-event-card-flash');
    }, 1600);
}

function mmOpenEventById(eventId, options) {
    options = options || {};
    if (!eventId) {
        return;
    }
    showMmDashboardPanel('events', {
        highlightEventId: String(eventId),
        skipAnimation: !!options.skipAnimation,
        replaceUrl: !!options.replaceUrl,
        skipUrl: !!options.skipUrl
    });
}

function showMmDashboardPanel(panel, options) {
    options = options || {};
    if (mmPanelTransitionLock) {
        return;
    }

    var panelName = panel === 'home' || !panel ? 'home' : panel;
    var panelOpen = panelName !== 'home' && !!MM_DASH_PANEL_MAP[panelName];
    var toEl = mmGetViewForPanel(panelName);
    var fromEl = mmGetVisibleView();

    if (!toEl) {
        return;
    }

    mmUpdateNavState(panelName);

    if (options.skipAnimation) {
        mmApplyPanelVisibility(panelName);
        mmAfterPanelSwitch(panelName, panelOpen, options);
        return;
    }

    if (fromEl === toEl) {
        mmAfterPanelSwitch(panelName, panelOpen, options);
        return;
    }

    mmPanelTransitionLock = true;

    mmRunViewTransition(fromEl, toEl, function () {
        mmPanelTransitionLock = false;
        mmAfterPanelSwitch(panelName, panelOpen, options);
    });
}

window.showMmDashboardPanel = showMmDashboardPanel;
window.mmOpenEventById = mmOpenEventById;

function initMmDashboardPanels() {
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-mm-panel]');
        if (!el) {
            return;
        }
        var panel = el.getAttribute('data-mm-panel');
        if (!panel) {
            return;
        }
        if (el.tagName === 'A') {
            var href = el.getAttribute('href') || '';
            if (href && href !== '#') {
                try {
                    if (new URL(href, window.location.origin).pathname !== window.location.pathname) {
                        return;
                    }
                } catch (err) {
                    return;
                }
            }
        }
        e.preventDefault();
        showMmDashboardPanel(panel);
    });

    document.querySelectorAll('.mm-upcoming-event-link').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-event-id');
            var dateStr = this.getAttribute('data-event-date') || '';
            if (!id) {
                return;
            }
            mmOpenEventById(id);
            if (!document.getElementById('mm-event-' + id) && window.eventifyMmCalendar && dateStr) {
                window.setTimeout(function () {
                    try {
                        window.eventifyMmCalendar.gotoDate(dateStr);
                    } catch (err) { /* ignore */ }
                }, 320);
            }
        });
    });

    window.addEventListener('popstate', function (e) {
        var panel = (e.state && e.state.mmPanel) || mmGetPanelFromUrl();
        showMmDashboardPanel(panel, { skipUrl: true });
    });

    document.addEventListener('eventify:notif-view-event', function (e) {
        var detail = (e && e.detail) || {};
        if (!detail.eventId) {
            return;
        }
        window.setTimeout(function () {
            mmOpenEventById(detail.eventId);
        }, 200);
    });
}

function mmGetHighlightEventIdFromUrl() {
    try {
        var params = new URLSearchParams(window.location.search);
        var raw = params.get('event_id') || params.get('highlight_event');
        if (!raw) {
            return '';
        }
        var id = parseInt(raw, 10);
        return id > 0 ? String(id) : '';
    } catch (err) {
        return '';
    }
}

function initMmDashboardPanelFromUrl() {
    var panel = mmResolvePanelFromUrl();
    var highlightEventId = mmGetHighlightEventIdFromUrl();
    if (MM_DASH_PANEL_MAP[panel] && document.getElementById(MM_DASH_PANEL_MAP[panel])) {
        showMmDashboardPanel(panel, {
            replaceUrl: true,
            skipAnimation: true,
            highlightEventId: highlightEventId || undefined
        });
    } else if (panel !== 'home') {
        showMmDashboardPanel('home', { replaceUrl: true, skipAnimation: true });
    } else {
        try {
            history.replaceState({ mmPanel: 'home' }, '', window.location.href);
        } catch (e) { /* ignore */ }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    initMmDashboardPanels();
    initMmDashboardPanelFromUrl();
});
