/**
 * Arrow-key scrolling for EVENTIFY dashboards (sidebar vs main/calendar panels).
 */
(function (global) {
    'use strict';

    const instances = new WeakMap();

    function isEditableTarget(el) {
        if (!el || el === document.body || el === document.documentElement) {
            return false;
        }
        const tag = (el.tagName || '').toUpperCase();
        if (tag === 'TEXTAREA' || tag === 'SELECT') {
            return true;
        }
        if (tag === 'INPUT') {
            const type = (el.type || 'text').toLowerCase();
            return type !== 'button' && type !== 'submit' && type !== 'reset' && type !== 'checkbox' && type !== 'radio' && type !== 'file';
        }
        if (el.isContentEditable) {
            return true;
        }
        return !!el.closest('[contenteditable="true"]');
    }

    function elementScrollable(el, axis) {
        if (!el) {
            return false;
        }
        const isFcScroller = el.classList.contains('fc-scroller')
            || el.classList.contains('fc-scroller-liquid-absolute')
            || el.classList.contains('fc-timegrid-body')
            || el.classList.contains('fc-daygrid-body');
        if (axis === 'y') {
            if (el.scrollHeight <= el.clientHeight + 1) {
                return false;
            }
            if (isFcScroller) {
                return true;
            }
            const oy = window.getComputedStyle(el).overflowY;
            return oy === 'auto' || oy === 'scroll' || oy === 'overlay';
        }
        if (el.scrollWidth <= el.clientWidth + 1) {
            return false;
        }
        if (isFcScroller) {
            return true;
        }
        const ox = window.getComputedStyle(el).overflowX;
        return ox === 'auto' || ox === 'scroll' || ox === 'overlay';
    }

    function findScrollableAncestor(start, axis) {
        let el = start;
        while (el && el !== document.body) {
            if (elementScrollable(el, axis)) {
                return el;
            }
            el = el.parentElement;
        }
        return null;
    }

    function canScroll(target, axis, direction) {
        if (!target) {
            return false;
        }
        const max = axis === 'y'
            ? target.scrollHeight - target.clientHeight
            : target.scrollWidth - target.clientWidth;
        const pos = axis === 'y' ? target.scrollTop : target.scrollLeft;
        if (max <= 0) {
            return false;
        }
        if (direction < 0) {
            return pos > 0;
        }
        return pos < max - 1;
    }

    function pickScrollTarget(candidates, axis, direction) {
        let fallback = null;
        for (let i = 0; i < candidates.length; i++) {
            const el = candidates[i];
            if (!el || !elementScrollable(el, axis)) {
                continue;
            }
            if (!fallback) {
                fallback = el;
            }
            if (canScroll(el, axis, direction)) {
                return el;
            }
        }
        return fallback;
    }

    function getCalendarScrollers(calendarId) {
        const seen = new Set();
        const list = [];
        if (!calendarId) {
            return list;
        }
        const root = document.getElementById(calendarId);
        if (!root) {
            return list;
        }
        root.querySelectorAll(
            '.fc-scroller-liquid-absolute, .fc-scroller, .fc-timegrid-body, .fc-daygrid-body'
        ).forEach(function (el) {
            if (!seen.has(el)) {
                seen.add(el);
                list.push(el);
            }
        });
        return list;
    }

    function createInstance(config) {
        const state = {
            scrollPanel: 'main',
            sidebar: config.sidebarId ? document.getElementById(config.sidebarId) : null,
            main: config.mainSelector ? document.querySelector(config.mainSelector) : null,
            layout: config.layoutSelector ? document.querySelector(config.layoutSelector) : null,
            calendarId: config.calendarId || '',
            root: config.root || document.body,
            onKeydown: null,
        };

        function setScrollPanel(panel) {
            if (panel === 'sidebar' || panel === 'main') {
                state.scrollPanel = panel;
            }
        }

        function getFocusRegion() {
            const active = document.activeElement;

            if (document.querySelector('.modal.show')) {
                return 'modal';
            }
            if (document.querySelector('.dropdown-menu.show')) {
                return 'dropdown';
            }
            if (state.sidebar && (active === state.sidebar || state.sidebar.contains(active))) {
                return 'sidebar';
            }
            if (state.main && (active === state.main || state.main.contains(active))) {
                return 'main';
            }
            return state.scrollPanel || 'main';
        }

        function resolveScrollTarget(axis, direction) {
            const active = document.activeElement;
            const region = getFocusRegion();
            const fromActive = findScrollableAncestor(active, axis);

            if (region === 'modal') {
                const createModal = document.getElementById('createEventModal');
                if (createModal && createModal.classList.contains('show')) {
                    const ceBody = createModal.querySelector('.modal-body');
                    if (ceBody && elementScrollable(ceBody, axis)) {
                        return ceBody;
                    }
                }
                const modalBody = document.querySelector('.modal.show .modal-body');
                if (modalBody && elementScrollable(modalBody, axis)) {
                    return modalBody;
                }
                return fromActive;
            }

            if (region === 'dropdown') {
                const notifScroll = document.querySelector('.dropdown-menu.show .eventify-notif-scroll');
                if (notifScroll && elementScrollable(notifScroll, axis)) {
                    return notifScroll;
                }
                const dropdownMenu = document.querySelector('.dropdown-menu.show');
                if (dropdownMenu && elementScrollable(dropdownMenu, axis)) {
                    return dropdownMenu;
                }
                return fromActive;
            }

            if (region === 'sidebar') {
                return pickScrollTarget([state.sidebar, fromActive], axis, direction);
            }

            const calendarCandidates = getCalendarScrollers(state.calendarId).concat([state.main, fromActive]);
            return pickScrollTarget(calendarCandidates, axis, direction);
        }

        const focusRoot = state.layout || state.root;
        if (focusRoot) {
            focusRoot.addEventListener('focusin', function (e) {
                if (state.sidebar && state.sidebar.contains(e.target)) {
                    setScrollPanel('sidebar');
                } else if (state.main && state.main.contains(e.target)) {
                    setScrollPanel('main');
                }
            });
        }

        if (state.sidebar) {
            state.sidebar.addEventListener('mousedown', function () {
                setScrollPanel('sidebar');
                state.sidebar.focus({ preventScroll: true });
            });
        }

        if (state.main) {
            state.main.addEventListener('mousedown', function (e) {
                setScrollPanel('main');
                if (!e.target.closest('input, textarea, select, button, a, .fc-event')) {
                    state.main.focus({ preventScroll: true });
                }
            });
        }

        state.onKeydown = function (e) {
            let axis = null;
            let direction = 0;
            if (e.key === 'ArrowUp') {
                axis = 'y';
                direction = -1;
            } else if (e.key === 'ArrowDown') {
                axis = 'y';
                direction = 1;
            } else if (e.key === 'ArrowLeft') {
                axis = 'x';
                direction = -1;
            } else if (e.key === 'ArrowRight') {
                axis = 'x';
                direction = 1;
            } else {
                return;
            }

            if (e.altKey || e.ctrlKey || e.metaKey) {
                return;
            }

            if (!state.root.contains(document.activeElement) && document.activeElement !== document.body) {
                return;
            }

            if (isEditableTarget(document.activeElement)) {
                return;
            }

            const target = resolveScrollTarget(axis, direction);
            if (!target || !elementScrollable(target, axis)) {
                return;
            }
            if (!canScroll(target, axis, direction)) {
                return;
            }

            const step = axis === 'y' ? 56 : 48;
            const delta = direction * step;
            if (axis === 'y') {
                target.scrollBy({ top: delta, behavior: 'smooth' });
            } else {
                target.scrollBy({ left: delta, behavior: 'smooth' });
            }
            e.preventDefault();
        };

        document.addEventListener('keydown', state.onKeydown);
        instances.set(state.root, state);
        return state;
    }

    function initFromElement(el) {
        if (!el || instances.has(el)) {
            return;
        }
        const sidebarId = el.getAttribute('data-eventify-sidebar');
        const calendarId = el.getAttribute('data-eventify-calendar') || '';
        const mainSelector = el.getAttribute('data-eventify-main') || '.main-content';
        const layoutAttr = el.getAttribute('data-eventify-layout');
        let layoutSelector = '.dashboard-layout';
        if (layoutAttr === 'root' || layoutAttr === 'body') {
            layoutSelector = null;
        } else if (layoutAttr) {
            layoutSelector = layoutAttr;
        }

        createInstance({
            root: el,
            sidebarId: sidebarId,
            mainSelector: mainSelector,
            layoutSelector: layoutSelector,
            calendarId: calendarId,
        });
    }

    global.eventifyInitDashboardKeyboardScroll = function (config) {
        const root = (config && config.root) || document.body;
        if (instances.has(root)) {
            return instances.get(root);
        }
        return createInstance(config || {});
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-eventify-keyboard-scroll]').forEach(initFromElement);
    });
}(window));
