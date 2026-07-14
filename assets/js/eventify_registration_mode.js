/**
 * Shared registration mode badge HTML (matches organizer My Events colors).
 */
(function (global) {
    'use strict';

    function registrationModeMeta(mode) {
        var m = String(mode || 'rsvp').toLowerCase();
        if (m === 'paid_ticket') {
            return { label: 'Paid tickets', badgeClass: 'bg-warning text-dark' };
        }
        if (m === 'open') {
            return { label: 'Open entry', badgeClass: 'bg-info text-dark' };
        }
        return { label: 'Free RSVP', badgeClass: 'bg-primary' };
    }

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function eventifyRegistrationModeBadgeHtml(mode, extraClass) {
        var meta = registrationModeMeta(mode);
        var extra = extraClass ? ' ' + String(extraClass) : '';
        return '<span class="badge ' + meta.badgeClass + extra + '">' + escapeHtml(meta.label) + '</span>';
    }

    global.eventifyRegistrationModeMeta = registrationModeMeta;
    global.eventifyRegistrationModeBadgeHtml = eventifyRegistrationModeBadgeHtml;
})(typeof window !== 'undefined' ? window : globalThis);
