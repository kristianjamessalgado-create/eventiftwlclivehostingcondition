(function () {
    function fileKey(file) {
        return [file.name, file.size, file.lastModified].join('|');
    }

    function isHeic(file) {
        var name = String(file.name || '').toLowerCase();
        var type = String(file.type || '').toLowerCase();
        return type === 'image/heic' || type === 'image/heif' || name.endsWith('.heic') || name.endsWith('.heif');
    }

    function jpegName(name) {
        var base = String(name || 'photo').replace(/\.(heic|heif)$/i, '');
        return base + '.jpg';
    }

    function convertHeic(file) {
        if (typeof heic2any !== 'function') {
            return Promise.reject(new Error('converter missing'));
        }
        return heic2any({
            blob: file,
            toType: 'image/jpeg',
            quality: 0.9
        }).then(function (result) {
            var blob = Array.isArray(result) ? result[0] : result;
            return new File([blob], jpegName(file.name), {
                type: 'image/jpeg',
                lastModified: Date.now()
            });
        });
    }

    function bindQueue(input) {
        var listId = input.getAttribute('data-photo-queue');
        var list = listId ? document.getElementById(listId) : null;
        var form = input.closest('form');
        if (!list || !form || input.dataset.photoQueueBound === '1') {
            return;
        }
        input.dataset.photoQueueBound = '1';

        var queue = [];
        var syncing = false;
        var converting = false;
        var convertNote = '';
        var convertErrors = [];
        var submitBtn = form.querySelector('button[type="submit"]');
        var submitLabel = submitBtn ? submitBtn.innerHTML : '';
        var host = form.closest('.modal-content') || form;
        var overlay = document.createElement('div');
        var overlayText = document.createElement('p');
        host.classList.add('mm-upload-status-host');
        overlay.className = 'mm-upload-status';
        overlay.hidden = true;
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        var overlaySpinner = document.createElement('div');
        overlaySpinner.className = 'mm-upload-status__spinner';
        overlaySpinner.setAttribute('aria-hidden', 'true');
        overlayText.className = 'mm-upload-status__text';
        overlay.appendChild(overlaySpinner);
        overlay.appendChild(overlayText);
        host.appendChild(overlay);

        function showStatus(text) {
            overlayText.textContent = text;
            overlay.hidden = false;
        }

        function hideStatus() {
            overlay.hidden = true;
            overlayText.textContent = '';
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitLabel;
            }
        }

        function syncInput() {
            if (typeof DataTransfer === 'undefined') {
                return;
            }
            syncing = true;
            var transfer = new DataTransfer();
            queue.forEach(function (file) {
                transfer.items.add(file);
            });
            input.files = transfer.files;
            syncing = false;
        }

        function render() {
            list.innerHTML = '';
            if (!queue.length && !convertNote && !convertErrors.length) {
                list.hidden = true;
                return;
            }
            list.hidden = false;
            if (convertNote) {
                var note = document.createElement('li');
                note.className = 'mm-upload-queue__count';
                note.textContent = convertNote;
                list.appendChild(note);
            }
            if (queue.length) {
                var count = document.createElement('li');
                count.className = 'mm-upload-queue__count';
                count.textContent = queue.length === 1
                    ? '1 photo ready to upload'
                    : queue.length + ' photos ready to upload';
                list.appendChild(count);
            }
            convertErrors.forEach(function (message) {
                var err = document.createElement('li');
                err.className = 'mm-upload-queue__item';
                err.textContent = message;
                list.appendChild(err);
            });
            queue.forEach(function (file, index) {
                var item = document.createElement('li');
                item.className = 'mm-upload-queue__item';
                var name = document.createElement('span');
                name.textContent = file.name;
                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'mm-upload-queue__remove';
                remove.textContent = 'Remove';
                remove.setAttribute('aria-label', 'Remove ' + file.name);
                remove.addEventListener('click', function () {
                    queue.splice(index, 1);
                    syncInput();
                    render();
                });
                item.appendChild(name);
                item.appendChild(remove);
                list.appendChild(item);
            });
        }

        function setConverting(on, note) {
            converting = on;
            convertNote = note || '';
            if (submitBtn) {
                submitBtn.disabled = on;
            }
            if (on) {
                showStatus(note || 'Converting photos…');
            } else if (!overlayText.textContent || overlayText.textContent.indexOf('Upload') !== 0) {
                hideStatus();
            }
        }

        function reset() {
            queue = [];
            convertErrors = [];
            converting = false;
            convertNote = '';
            input.value = '';
            hideStatus();
            render();
        }

        input.mmResetPhotoQueue = reset;

        input.addEventListener('change', function () {
            if (syncing) {
                return;
            }
            var seen = {};
            queue.forEach(function (file) {
                seen[fileKey(file)] = true;
            });
            var incoming = [];
            Array.prototype.forEach.call(input.files || [], function (file) {
                var key = fileKey(file);
                if (!seen[key]) {
                    seen[key] = true;
                    incoming.push(file);
                }
            });
            var heics = [];
            incoming.forEach(function (file) {
                if (isHeic(file)) {
                    heics.push(file);
                } else {
                    queue.push(file);
                }
            });
            syncInput();
            render();
            if (!heics.length) {
                return;
            }
            setConverting(true, 'Converting ' + heics.length + ' iPhone photo' + (heics.length === 1 ? '' : 's') + ' to JPG…');
            render();
            var chain = Promise.resolve();
            heics.forEach(function (file) {
                chain = chain.then(function () {
                    return convertHeic(file).then(function (jpeg) {
                        queue.push(jpeg);
                    }).catch(function () {
                        convertErrors.push(file.name + ' could not be converted. Save it as JPG and try again.');
                    });
                });
            });
            chain.then(function () {
                setConverting(false, '');
                syncInput();
                render();
            });
        });

        form.addEventListener('submit', function (e) {
            if (converting) {
                e.preventDefault();
                return;
            }
            syncInput();
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="mm-upload-status__spinner mm-upload-status__spinner--btn" aria-hidden="true"></span> Uploading…';
            }
            showStatus('Uploading photos…');
        });

        var modal = input.closest('.modal');
        if (modal) {
            modal.addEventListener('show.bs.modal', reset);
        }
    }

    document.querySelectorAll('input[data-photo-queue]').forEach(bindQueue);
})();
