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
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        function stackModalZIndex() {
            var openCount = document.querySelectorAll('.modal.show').length;
            var z = 1055 + openCount * 10;
            modalEl.style.zIndex = String(z);
            setTimeout(function () {
                var backdrops = document.querySelectorAll('.modal-backdrop');
                if (backdrops.length) {
                    backdrops[backdrops.length - 1].style.zIndex = String(z - 1);
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
            pendingBulkAction = '';
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
            }

            stackModalZIndex();
            modal.show();
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
                openModal('reject', '', rejectIds.length);
            }
        });

        confirmBtn.addEventListener('click', function () {
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
                pendingForm.submit();
            } else if (bulkForm && pendingBulkAction) {
                var actionInput = bulkForm.querySelector('[name="action"]');
                var reasonHidden = bulkForm.querySelector('[name="reject_reason"]');
                var idsHost = bulkForm.querySelector('#mmPhotoBulkIdsHost');
                if (!actionInput || !idsHost) {
                    return;
                }
                actionInput.value = pendingBulkAction;
                if (reasonHidden) {
                    reasonHidden.value = reason;
                }
                idsHost.innerHTML = '';
                selectedPhotoIds().forEach(function (id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'photo_ids[]';
                    input.value = String(id);
                    idsHost.appendChild(input);
                });
                bulkForm.submit();
            }

            pendingForm = null;
            pendingBulkAction = '';
            modal.hide();
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            pendingForm = null;
            pendingBulkAction = '';
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
