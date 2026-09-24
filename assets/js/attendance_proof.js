(function () {
    document.documentElement.classList.add('js-att-proof');

    function ensureViewer() {
        var el = document.getElementById('attendanceProofViewer');
        if (el) {
            return el;
        }
        el = document.createElement('div');
        el.id = 'attendanceProofViewer';
        el.className = 'att-proof-viewer';
        el.hidden = true;
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-label', 'Attendance photo');
        el.innerHTML = '<button type="button" class="att-proof-viewer__close" id="attendanceProofViewerClose" aria-label="Close">&times;</button>'
            + '<img class="att-proof-viewer__img" id="attendanceProofViewerImg" alt="Attendance photo">';
        document.body.appendChild(el);

        el.addEventListener('click', function (e) {
            if (e.target === el) {
                hideViewer();
            }
        });
        var closeBtn = document.getElementById('attendanceProofViewerClose');
        if (closeBtn) {
            closeBtn.addEventListener('click', hideViewer);
        }
        return el;
    }

    function showViewer(url) {
        if (!url) {
            return;
        }
        var viewer = ensureViewer();
        var img = document.getElementById('attendanceProofViewerImg');
        if (img) {
            img.src = url;
        }
        viewer.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function hideViewer() {
        var viewer = document.getElementById('attendanceProofViewer');
        if (!viewer) {
            return;
        }
        viewer.hidden = true;
        var img = document.getElementById('attendanceProofViewerImg');
        if (img) {
            img.removeAttribute('src');
        }
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-attendance-proof-view]');
        if (!trigger) {
            return;
        }
        e.preventDefault();
        showViewer(trigger.getAttribute('data-attendance-proof-view') || '');
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            hideViewer();
        }
    });

    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input || !input.classList || !input.classList.contains('att-proof__input')) {
            return;
        }
        if (!input.files || !input.files.length) {
            return;
        }
        var form = input.closest('form');
        var wrap = input.closest('.att-proof');
        if (wrap) {
            wrap.classList.add('is-uploading');
            var label = wrap.querySelector('.att-proof__label');
            if (label) {
                label.innerHTML = '<i class="fas fa-image" aria-hidden="true"></i> Uploading…';
            }
        }
        if (form) {
            form.submit();
        }
    });
})();
