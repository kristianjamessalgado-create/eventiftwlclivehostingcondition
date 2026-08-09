/**

 * Mobile pull-to-refresh for EVENTIFY dashboards (iPhone / touch).

 */

(function (global) {

    'use strict';



    var THRESHOLD = 68;

    var MAX_PULL = 120;

    var ACTIVATE_PX = 12;



    function isTouchMobile() {

        var touch = 'ontouchstart' in global || (global.navigator && global.navigator.maxTouchPoints > 0);

        var narrow = global.matchMedia && global.matchMedia('(max-width: 768px)').matches;

        return touch && narrow;

    }



    function sidebarOpen() {

        var body = document.body;

        return body.classList.contains('organizer-sidebar-open')

            || body.classList.contains('admin-sidebar-open')

            || body.classList.contains('student-sidebar-open')

            || body.classList.contains('mm-sidebar-open');

    }



    function isBlocked() {

        return sidebarOpen() || document.body.classList.contains('modal-open');

    }



    function resolveScrollEl(config) {

        if (config && config.scrollEl) {

            return config.scrollEl;

        }

        var selector = (config && config.scrollSelector)

            || document.body.getAttribute('data-eventify-main')

            || '.main-content';

        return document.querySelector(selector);

    }



    function initPullToRefresh(config) {

        config = config || {};

        var scrollEl = resolveScrollEl(config);

        if (!scrollEl || !isTouchMobile()) {

            return null;

        }



        var threshold = config.threshold || THRESHOLD;

        var maxPull = config.maxPull || MAX_PULL;

        var onRefresh = typeof config.onRefresh === 'function'

            ? config.onRefresh

            : function () {

                global.location.reload();

            };



        var indicator = document.createElement('div');

        indicator.className = 'eventify-ptr';

        indicator.setAttribute('aria-hidden', 'true');

        indicator.innerHTML = ''

            + '<div class="eventify-ptr__icon" aria-hidden="true"><i class="fas fa-sync-alt"></i></div>'

            + '<span class="eventify-ptr__text">Pull to refresh</span>';

        document.body.appendChild(indicator);



        var textEl = indicator.querySelector('.eventify-ptr__text');

        var startY = 0;

        var tracking = false;

        var ptrActive = false;

        var pullDistance = 0;

        var refreshing = false;



        function atScrollTop() {

            return scrollEl.scrollTop <= 1;

        }



        function setPull(distance) {

            pullDistance = Math.min(Math.max(0, distance), maxPull);

            var progress = Math.min(1, pullDistance / threshold);

            indicator.style.setProperty('--efy-ptr-progress', String(progress));

            indicator.style.setProperty('--efy-ptr-offset', pullDistance + 'px');

            indicator.classList.toggle('eventify-ptr--visible', pullDistance > 6);

            indicator.classList.toggle('eventify-ptr--ready', pullDistance >= threshold);

            if (!refreshing && ptrActive) {

                scrollEl.style.transform = pullDistance > 0

                    ? 'translate3d(0,' + (pullDistance * 0.35) + 'px,0)'

                    : '';

            }

        }



        function resetPull() {

            if (refreshing) {

                return;

            }

            tracking = false;

            ptrActive = false;

            pullDistance = 0;

            scrollEl.style.transition = 'transform 0.22s ease';

            scrollEl.style.transform = '';

            indicator.classList.remove('eventify-ptr--visible', 'eventify-ptr--ready');

            indicator.style.setProperty('--efy-ptr-progress', '0');

            indicator.style.setProperty('--efy-ptr-offset', '0px');

            global.setTimeout(function () {

                scrollEl.style.transition = '';

            }, 240);

        }



        function startRefresh() {

            refreshing = true;

            tracking = false;

            ptrActive = false;

            scrollEl.style.transition = 'transform 0.22s ease';

            scrollEl.style.transform = '';

            indicator.classList.add('eventify-ptr--refreshing', 'eventify-ptr--visible');

            if (textEl) {

                textEl.textContent = 'Refreshing…';

            }

            onRefresh();

        }



        scrollEl.addEventListener('touchstart', function (e) {

            if (refreshing || isBlocked() || !atScrollTop()) {

                tracking = false;

                ptrActive = false;

                return;

            }

            startY = e.touches[0].clientY;

            tracking = true;

            ptrActive = false;

            pullDistance = 0;

        }, { passive: true });



        scrollEl.addEventListener('touchmove', function (e) {

            if (!tracking || refreshing || isBlocked()) {

                return;

            }



            var dy = e.touches[0].clientY - startY;



            // Finger moving up — normal scroll; never intercept.

            if (dy < 0) {

                if (ptrActive) {

                    resetPull();

                }

                tracking = false;

                return;

            }



            if (!atScrollTop()) {

                resetPull();

                return;

            }



            if (!ptrActive && dy < ACTIVATE_PX) {

                return;

            }



            ptrActive = true;

            e.preventDefault();

            setPull(dy);

        }, { passive: false });



        scrollEl.addEventListener('touchend', function () {

            if (!tracking || refreshing || isBlocked()) {

                resetPull();

                return;

            }

            if (ptrActive && pullDistance >= threshold) {

                startRefresh();

            } else {

                resetPull();

            }

        });



        scrollEl.addEventListener('touchcancel', resetPull);



        return {

            destroy: function () {

                indicator.remove();

                scrollEl.style.transform = '';

                scrollEl.style.transition = '';

            }

        };

    }



    global.eventifyInitPullToRefresh = initPullToRefresh;



    document.addEventListener('DOMContentLoaded', function () {

        if (!document.body.hasAttribute('data-eventify-pull-refresh')) {

            return;

        }

        initPullToRefresh();

    });

}(window));


