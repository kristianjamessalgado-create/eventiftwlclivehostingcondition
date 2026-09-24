/**
 * Confirm approve/reject for multimedia photo moderation (single + bulk).
 */
(function (global) {
    'use strict';

    function boot() {
        var modalEl = document.getElementById('photoModerationConfirmModal');
        var titleEl = document.getElementById('photoModerationConfirmModalLabel');
        var msgEl = document.getElementById('photoModerationConfirmMessage');
        var reasonWrap = document.getElementById('photoModerationReasonWrap');
        var reasonInput = document.getElementById('photoModerationReasonInput');
        var confirmBtn = document.getElementById('photoModerationConfirmBtn');
        var bulkForm = document.getElementById('mmPhotoBulkModerateForm');
        if (!modalEl || !titleEl || !msgEl || !confirmBtn || !global.bootstrap) {
            return;
        }

        var pendingForm = null;
        var pendingBulkAction = '';
        var pendingBulkIds = [];
        var submitting = false;
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        function stackModalZIndex() {
            modalEl.style.zIndex = '1080';
            setTimeout(function () {
                var backdrops = document.querySelectorAll('.modal-backdrop');
                if (backdrops.length) {
                    backdrops[backdrops.length - 1].style.zIndex = '1075';
                }
            }, 0);
        }

        function selectedPhotoIds() {
            var ids = [];
            document.querySelectorAll('.js-mm-photo-select:checked').forEach(function (cb) {
                var id = parseInt(cb.value, 10);
                if (id > 0) {
                    ids.push(id);
                }
            });
            return ids;
        }

        function openModal(action, label, count) {
            var isReject = action === 'reject';
            var isBulk = count > 1;

            if (isReject) {
                titleEl.innerHTML = isBulk
                    ? '<i class="fas fa-times" aria-hidden="true"></i> Reject ' + count + ' photos'
                    : '<i class="fas fa-times" aria-hidden="true"></i> Reject photo';
                msgEl.textContent = isBulk
                    ? 'Reject ' + count + ' selected photos? They will stay hidden from students.'
                    : 'Are you sure you want to reject "' + label + '"? It will stay hidden from students.';
                confirmBtn.className = 'btn efy-btn-danger btn-sm';
                confirmBtn.innerHTML = '<i class="fas fa-times me-1"></i> Yes, reject';
            } else {
                titleEl.innerHTML = isBulk
                    ? '<i class="fas fa-check" aria-hidden="true"></i> Approve ' + count + ' photos'
                    : '<i class="fas fa-check" aria-hidden="true"></i> Approve photo';
                msgEl.textContent = isBulk
                    ? 'Approve ' + count + ' selected photos? Students will be able to see them.'
                    : 'Are you sure you want to approve "' + label + '"? Students will be able to see it.';
                confirmBtn.className = 'btn efy-btn-primary btn-sm';
                confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i> Yes, approve';
            }

            if (reasonWrap) {
                reasonWrap.hidden = !isReject;
            }
            if (reasonInput) {
                reasonInput.value = '';
                reasonInput.classList.remove('is-invalid');
            }

            stackModalZIndex();
            modal.show();
        }

        function csrfTokenFromPage() {
            var input = document.querySelector('input[name="csrf_token"]');
            return input ? String(input.value || '') : '';
        }

        function bulkEndpoint() {
            if (bulkForm && bulkForm.action) {
                return bulkForm.action;
            }
            var single = document.querySelector('form.js-photo-moderate-form');
            if (single && single.action) {
                return String(single.action).replace(/moderate_event_photo\.php.*/i, 'moderate_event_photos_bulk.php');
            }
            return '';
        }

        function submitBulk(action, ids, reason) {
            if (!ids.length || submitting) {
                return false;
            }
            submitting = true;

            var form = bulkForm;
            if (!form) {
                form = document.createElement('form');
                form.method = 'POST';
                form.action = bulkEndpoint();
                form.setAttribute('hidden', 'hidden');
                document.body.appendChild(form);
                var csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = 'csrf_token';
                csrf.value = csrfTokenFromPage();
                form.appendChild(csrf);
                var actionInputNew = document.createElement('input');
                actionInputNew.type = 'hidden';
                actionInputNew.name = 'action';
                form.appendChild(actionInputNew);
                var reasonHiddenNew = document.createElement('input');
                reasonHiddenNew.type = 'hidden';
                reasonHiddenNew.name = 'reject_reason';
                form.appendChild(reasonHiddenNew);
                var idsHostNew = document.createElement('div');
                idsHostNew.id = 'mmPhotoBulkIdsHost';
                form.appendChild(idsHostNew);
            }

            var actionInput = form.querySelector('[name="action"]');
            var reasonHidden = form.querySelector('[name="reject_reason"]');
            var idsHost = form.querySelector('#mmPhotoBulkIdsHost');
            if (!actionInput || !idsHost || !form.action) {
                submitting = false;
                return false;
            }

            actionInput.value = action;
            if (reasonHidden) {
                reasonHidden.value = reason;
            }
            idsHost.innerHTML = '';
            ids.forEach(function (id) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'photo_ids[]';
                input.value = String(id);
                idsHost.appendChild(input);
            });

            if (form.parentElement !== document.body) {
                document.body.appendChild(form);
            }
            form.classList.remove('d-none', 'visually-hidden');
            form.removeAttribute('hidden');
            form.style.position = 'absolute';
            form.style.left = '-9999px';

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
            return true;
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.js-photo-moderate-trigger');
            if (btn) {
                e.preventDefault();
                var form = btn.closest('form.js-photo-moderate-form');
                if (!form) {
                    return;
                }
                pendingForm = form;
                pendingBulkAction = '';
                pendingBulkIds = [];
                openModal(
                    (btn.dataset.action || 'approve').toLowerCase(),
                    btn.dataset.photoLabel || 'this photo',
                    1
                );
                return;
            }

            var bulkApprove = e.target.closest('#mmPhotoBulkApprove, #mmPhotoBulkApproveModal');
            if (bulkApprove) {
                e.preventDefault();
                var ids = selectedPhotoIds();
                if (!ids.length) {
                    return;
                }
                pendingForm = null;
                pendingBulkAction = 'approve';
                pendingBulkIds = ids.slice();
                openModal('approve', '', ids.length);
                return;
            }

            var bulkReject = e.target.closest('#mmPhotoBulkReject, #mmPhotoBulkRejectModal');
            if (bulkReject) {
                e.preventDefault();
                var rejectIds = selectedPhotoIds();
                if (!rejectIds.length) {
                    return;
                }
                pendingForm = null;
                pendingBulkAction = 'reject';
                pendingBulkIds = rejectIds.slice();
                openModal('reject', '', rejectIds.length);
            }
        });

        confirmBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (submitting) {
                return;
            }

            var reason = reasonInput ? reasonInput.value.trim() : '';
            var action = pendingBulkAction;
            if (pendingForm) {
                action = (pendingForm.querySelector('[name="action"]') || {}).value || 'approve';
            }

            if (action === 'reject' && !reason) {
                if (reasonInput) {
                    reasonInput.focus();
                    reasonInput.classList.add('is-invalid');
                }
                return;
            }
            if (reasonInput) {
                reasonInput.classList.remove('is-invalid');
            }

            if (pendingForm) {
                var existing = pendingForm.querySelector('input[name="reject_reason"]');
                if (!existing) {
                    existing = document.createElement('input');
                    existing.type = 'hidden';
                    existing.name = 'reject_reason';
                    pendingForm.appendChild(existing);
                }
                existing.value = reason;
                submitting = true;
                pendingForm.submit();
                return;
            }

            var ids = pendingBulkIds.length ? pendingBulkIds.slice() : selectedPhotoIds();
            if (pendingBulkAction && ids.length) {
                submitBulk(pendingBulkAction, ids, reason);
            }
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            if (submitting) {
                return;
            }
            pendingForm = null;
            pendingBulkAction = '';
            pendingBulkIds = [];
            if (reasonInput) {
                reasonInput.classList.remove('is-invalid');
            }
        });

        document.addEventListener('change', function (e) {
            if (e.target && e.target.id === 'mmPhotoSelectAll') {
                var checked = e.target.checked;
                document.querySelectorAll('.js-mm-photo-select').forEach(function (cb) {
                    cb.checked = checked;
                });
                updateBulkToolbar();
                return;
            }
            if (e.target && e.target.classList.contains('js-mm-photo-select')) {
                updateBulkToolbar();
            }
        });

        function updateBulkToolbar() {
            var ids = selectedPhotoIds();
            var count = ids.length;
            document.querySelectorAll('.js-mm-photo-bulk-count').forEach(function (el) {
                el.textContent = String(count);
            });
            document.querySelectorAll('.js-mm-photo-bulk-btn').forEach(function (btn) {
                btn.disabled = count === 0;
            });
            var selectAll = document.getElementById('mmPhotoSelectAll');
            var boxes = document.querySelectorAll('.js-mm-photo-select');
            if (selectAll && boxes.length) {
                selectAll.checked = count > 0 && count === boxes.length;
                selectAll.indeterminate = count > 0 && count < boxes.length;
            }
        }

        updateBulkToolbar();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}(typeof window !== 'undefined' ? window : this));
