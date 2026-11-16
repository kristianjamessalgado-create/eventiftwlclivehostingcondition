/**
 * EVENTIFY profile picture helpers (student, multimedia, organizer, admin profiles).
 *
 * 1) "Remove photo" button: <button data-profile-pic-remove data-preview="<img id>" data-input="<file input id>">
 *    Sets the form's hidden remove_profile_picture=1 and shows a placeholder; click again to undo.
 *    Picking a new file cancels the removal.
 * 2) Full-screen viewer for profile pictures that open above every modal
 *    (img.organizer-profile-picture-preview, img[data-profile-pic-view]).
 */
(function () {
    'use strict';

    /* ---------- Remove photo ---------- */

    function placeholderFor(img, cls) {
        var ph = img.parentNode.querySelector('.efy-pic-removed-placeholder');
        if (!ph) {
            ph = document.createElement('div');
            ph.className = (cls || 'profile-picture-placeholder') + ' efy-pic-removed-placeholder';
            ph.innerHTML = '<i class="fas fa-user" aria-hidden="true"></i>';
            ph.hidden = true;
            img.parentNode.insertBefore(ph, img.nextSibling);
        }
        return ph;
    }

    function setRemoved(btn, removed) {
        var form = btn.closest('form');
        var flag = form ? form.querySelector('input[name="remove_profile_picture"]') : null;
        var img = document.getElementById(btn.getAttribute('data-preview') || '');
        var input = document.getElementById(btn.getAttribute('data-input') || '');
        if (flag) {
            flag.value = removed ? '1' : '0';
        }
        if (img && img.tagName === 'IMG') {
            var ph = placeholderFor(img, btn.getAttribute('data-placeholder-class'));
            img.hidden = removed;
            img.style.display = removed ? 'none' : '';
            ph.hidden = !removed;
            ph.style.display = removed ? '' : 'none';
        }
        if (removed && input) {
            input.value = '';
        }
        btn.setAttribute('aria-pressed', removed ? 'true' : 'false');
        btn.classList.toggle('is-removing', removed);
        btn.innerHTML = removed
            ? '<i class="fas fa-rotate-left" aria-hidden="true"></i> Undo remove'
            : '<i class="fas fa-trash-alt" aria-hidden="true"></i> Remove photo';
        var note = btn.parentNode.querySelector('.efy-pic-remove-note');
        if (note) {
            note.hidden = !removed;
        }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-profile-pic-remove]');
        if (!btn) {
            return;
        }
        e.preventDefault();
        setRemoved(btn, btn.getAttribute('aria-pressed') !== 'true');
    });

    // Choosing a new picture cancels "remove".
    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input || input.type !== 'file' || input.name !== 'profile_picture' || !input.files || !input.files.length) {
            return;
        }
        var form = input.closest('form');
        var btn = form ? form.querySelector('[data-profile-pic-remove][aria-pressed="true"]') : null;
        if (btn) {
            setRemoved(btn, false);
        }
    }, true);

    // Reset when a profile modal is closed without saving.
    document.addEventListener('hidden.bs.modal', function (e) {
        e.target.querySelectorAll('[data-profile-pic-remove][aria-pressed="true"]').forEach(function (btn) {
            setRemoved(btn, false);
        });
    });

    /* ---------- Full-screen viewer (above all modals) ---------- */

    var viewer = null;

    function ensureViewer() {
        if (viewer) {
            return viewer;
        }
        viewer = document.createElement('div');
        viewer.className = 'efy-pic-viewer';
        viewer.hidden = true;
        viewer.setAttribute('role', 'dialog');
        viewer.setAttribute('aria-modal', 'true');
        viewer.setAttribute('aria-label', 'Profile picture');
        viewer.innerHTML = '<button type="button" class="efy-pic-viewer__close" aria-label="Close"><i class="fas fa-times" aria-hidden="true"></i></button>'
            + '<img class="efy-pic-viewer__img" src="" alt="Profile picture">';
        document.body.appendChild(viewer);
        viewer.addEventListener('click', function (e) {
            if (e.target === viewer || e.target.closest('.efy-pic-viewer__close')) {
                closeViewer();
            }
        });
        return viewer;
    }

    function openViewer(src) {
        if (!src) {
            return;
        }
        var v = ensureViewer();
        v.querySelector('.efy-pic-viewer__img').src = src;
        v.hidden = false;
        document.documentElement.classList.add('efy-pic-viewer-open');
    }

    function closeViewer() {
        if (!viewer || viewer.hidden) {
            return;
        }
        viewer.hidden = true;
        viewer.querySelector('.efy-pic-viewer__img').src = '';
        document.documentElement.classList.remove('efy-pic-viewer-open');
    }

    document.addEventListener('click', function (e) {
        var img = e.target.closest('img.organizer-profile-picture-preview, img[data-profile-pic-view]');
        if (!img || img.hidden) {
            return;
        }
        e.preventDefault();
        openViewer(img.currentSrc || img.src);
    });

    // Esc closes the picture first, without also closing the profile modal underneath.
    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Escape' || e.key === 'Esc') && viewer && !viewer.hidden) {
            e.stopPropagation();
            e.preventDefault();
            closeViewer();
        }
    }, true);

    window.eventifyOpenProfilePicViewer = openViewer;
})();
