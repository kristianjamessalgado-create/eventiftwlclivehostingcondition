/**
 * Multimedia dashboard calendar.
 * Read-only month/week/day view of events. Clicking an event scrolls to its
 * photo card in the list below so the user can upload / view photos.
 */
document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('mmCalendar');
    if (!calendarEl) {
        return;
    }
    if (typeof FullCalendar === 'undefined') {
        calendarEl.innerHTML = '<div class="mm-cal-fallback" role="status">Calendar could not load. Check your internet connection, or allow scripts from the CDN on this page.</div>';
        return;
    }

    var titleEl = document.getElementById('mmCalTitle');
    var prevBtn = document.getElementById('mmCalPrev');
    var nextBtn = document.getElementById('mmCalNext');
    var viewBtns = Array.prototype.slice.call(document.querySelectorAll('#mmCalendarSection .view-btn'));
    var events = Array.isArray(window.eventsData) ? window.eventsData : [];
    var mainContent = document.querySelector('body.multimedia-dashboard .main-content');
    var isMobileLayout = function () {
        return window.matchMedia('(max-width: 768px)').matches;
    };

    function isMonthView(viewType) {
        return viewType === 'dayGridMonth';
    }

    function dayHeaderFormat() {
        if (window.matchMedia('(max-width: 480px)').matches) {
            return { weekday: 'narrow' };
        }
        if (window.matchMedia('(max-width: 768px)').matches) {
            return { weekday: 'short' };
        }
        return { weekday: 'long' };
    }

    function refreshCalendarLayout() {
        try {
            calendar.setOption('dayHeaderFormat', dayHeaderFormat());
            calendar.updateSize();
        } catch (e) { /* ignore */ }
    }

    function scrollToEventCard(eventId) {
        if (!eventId) return;
        if (typeof window.mmOpenEventById === 'function') {
            window.mmOpenEventById(eventId);
            return;
        }
        if (typeof window.showMmDashboardPanel === 'function') {
            window.showMmDashboardPanel('events', { highlightEventId: eventId });
            return;
        }
        var card = document.getElementById('mm-event-' + eventId);
        if (!card) return;
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.classList.add('mm-event-card-flash');
        setTimeout(function () {
            card.classList.remove('mm-event-card-flash');
        }, 1600);
    }

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: false,
        height: isMobileLayout() ? 520 : 650,
        expandRows: true,
        fixedWeekCount: false,
        dayMaxEvents: false,
        eventDisplay: 'block',
        eventOrder: 'start',
        nowIndicator: true,
        slotMinTime: '06:00:00',
        slotMaxTime: '22:00:00',
        scrollTime: '07:00:00',
        dayHeaderFormat: dayHeaderFormat(),
        events: events,
        views: {
            dayGridMonth: {
                dayMaxEvents: false,
                expandRows: true
            }
        },
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            omitZeroMinute: false,
            meridiem: 'short'
        },
        eventContent: function (arg) {
            if (typeof eventifyCalendarEventContent === 'function') {
                var custom = eventifyCalendarEventContent(arg);
                if (custom !== true) {
                    return custom;
                }
            }
            return true;
        },
        eventDidMount: function (info) {
            if (typeof eventifyApplyCalendarEventMount === 'function') {
                eventifyApplyCalendarEventMount(info);
            }
        },
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            var props = info.event.extendedProps || {};
            var eventId = props.event_id || info.event.id;
            scrollToEventCard(eventId);
        },
        datesSet: function (info) {
            if (titleEl) {
                titleEl.textContent = info.view.title;
            }
        }
    });

    calendar.render();
    window.eventifyMmCalendar = calendar;

    [50, 180, 400, 800].forEach(function (ms) {
        window.setTimeout(refreshCalendarLayout, ms);
    });

    if (typeof document !== 'undefined' && document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () {
            refreshCalendarLayout();
        }).catch(function () { /* ignore */ });
    }

    var headerResizeTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(headerResizeTimer);
        headerResizeTimer = setTimeout(refreshCalendarLayout, 180);
    });

    if (mainContent) {
        var scrollRefreshTimer = null;
        mainContent.addEventListener('scroll', function () {
            clearTimeout(scrollRefreshTimer);
            scrollRefreshTimer = setTimeout(refreshCalendarLayout, 150);
        }, { passive: true });
    }

    if (typeof ResizeObserver !== 'undefined' && calendarEl) {
        var resizeObserver = new ResizeObserver(function () {
            clearTimeout(headerResizeTimer);
            headerResizeTimer = setTimeout(refreshCalendarLayout, 120);
        });
        resizeObserver.observe(calendarEl);
    }

    var homePanel = document.getElementById('mmDashboardHome');
    if (typeof ResizeObserver !== 'undefined' && homePanel) {
        var homeObserver = new ResizeObserver(function () {
            if (!homePanel.classList.contains('d-none')) {
                refreshCalendarLayout();
            }
        });
        homeObserver.observe(homePanel);
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () { calendar.prev(); });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () { calendar.next(); });
    }

    viewBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var view = btn.getAttribute('data-view');
            if (view === 'today') {
                calendar.today();
                viewBtns.forEach(function (b) {
                    b.classList.toggle('active', b.getAttribute('data-view') === 'dayGridMonth');
                });
                return;
            }
            calendar.changeView(view);
            calendar.setOption('height', view === 'today' || isMonthView(view)
                ? (isMobileLayout() ? 520 : 650)
                : (isMobileLayout() ? 520 : 700));
            viewBtns.forEach(function (b) {
                b.classList.toggle('active', b === btn);
            });
        });
    });

    var topCalBtn = document.getElementById('mmTopCalendarShortcutBtn');
    var calControls = document.getElementById('mmCalendarSection');
    if (topCalBtn) {
        topCalBtn.addEventListener('click', function () {
            calendar.today();
            viewBtns.forEach(function (b) {
                b.classList.toggle('active', b.getAttribute('data-view') === 'dayGridMonth');
            });
            if (typeof window.showMmDashboardPanel === 'function') {
                window.showMmDashboardPanel('home');
            }
            if (calControls && typeof calControls.scrollIntoView === 'function') {
                calControls.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }
});
