(function () {
    function fileKey(file) {
        return [file.name, file.size, file.lastModified].join('|');
    }

    function isHeic(file) {
        var name = String(file.name || '').toLowerCase();
        var type = String(file.type || '').toLowerCase();
        return type === 'image/heic' || type === 'image/heif' || name.endsWith('.heic') || name.endsWith('.heif');
    }

    function isVideo(file) {
        var type = String(file.type || '').toLowerCase();
        return type.indexOf('video/') === 0 || /\.(mp4|m4v|mov|webm)$/i.test(String(file.name || ''));
    }

    function mb(bytes) {
        var v = bytes / (1024 * 1024);
        return (v >= 10 ? Math.round(v) : Math.round(v * 10) / 10) + ' MB';
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
        var photoMaxMb = parseFloat(input.getAttribute('data-photo-max-mb') || '0') || 0;
        var videoMaxMb = parseFloat(input.getAttribute('data-video-max-mb') || '0') || 0;
        var batchMaxMb = parseFloat(input.getAttribute('data-batch-max-mb') || '0') || 0;
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
                var videos = queue.filter(isVideo).length;
                var photos = queue.length - videos;
                var parts = [];
                if (photos) {
                    parts.push(photos + (photos === 1 ? ' photo' : ' photos'));
                }
                if (videos) {
                    parts.push(videos + (videos === 1 ? ' video' : ' videos'));
                }
                var count = document.createElement('li');
                count.className = 'mm-upload-queue__count';
                count.textContent = parts.join(' and ') + ' ready to upload · ' + mb(totalBytes());
                list.appendChild(count);
                if (overBatch()) {
                    var warn = document.createElement('li');
                    warn.className = 'mm-upload-queue__item mm-upload-queue__item--warn';
                    warn.textContent = 'Total is over the ' + batchMaxMb + ' MB upload limit. Remove some files and upload them in a second batch.';
                    list.appendChild(warn);
                }
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
                name.textContent = (isVideo(file) ? 'Video: ' : '') + file.name + ' (' + mb(file.size) + ')';
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

        function totalBytes() {
            return queue.reduce(function (sum, file) { return sum + (file.size || 0); }, 0);
        }

        function overBatch() {
            return batchMaxMb > 0 && totalBytes() > batchMaxMb * 1024 * 1024;
        }

        /** Too-big files are listed as errors instead of being queued. */
        function tooBig(file) {
            var limit = isVideo(file) ? videoMaxMb : photoMaxMb;
            if (limit > 0 && file.size > limit * 1024 * 1024) {
                convertErrors.push(file.name + ' is ' + mb(file.size) + ', over the ' + limit + ' MB ' + (isVideo(file) ? 'video' : 'photo') + ' limit.');
                return true;
            }
            return false;
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
                } else if (!tooBig(file)) {
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
            if (overBatch()) {
                e.preventDefault();
                render();
                return;
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="mm-upload-status__spinner mm-upload-status__spinner--btn" aria-hidden="true"></span> Uploading…';
            }
            showStatus(queue.some(isVideo) ? 'Uploading photos & videos…' : 'Uploading photos…');

            // Upload in the background so we can show progress (videos can be large).
            if (typeof XMLHttpRequest === 'undefined' || typeof FormData === 'undefined') {
                return; // very old browser: normal form submit
            }
            e.preventDefault();
            var xhr = new XMLHttpRequest();
            xhr.open('POST', form.getAttribute('action') || window.location.href, true);
            xhr.upload.addEventListener('progress', function (ev) {
                if (!ev.lengthComputable) {
                    return;
                }
                var pct = Math.min(100, Math.round((ev.loaded / ev.total) * 100));
                showStatus(pct < 100
                    ? 'Uploading… ' + pct + '% (' + mb(ev.loaded) + ' of ' + mb(ev.total) + ')'
                    : 'Saving on the server…');
            });
            xhr.addEventListener('load', function () {
                // The server answers with a redirect back to the dashboard (+ message).
                if (xhr.status >= 200 && xhr.status < 400 && xhr.responseURL) {
                    window.location.href = xhr.responseURL;
                    return;
                }
                hideStatus();
                convertErrors = ['Upload failed (server error ' + xhr.status + '). Try fewer or smaller files.'];
                render();
            });
            xhr.addEventListener('error', function () {
                hideStatus();
                convertErrors = ['Upload interrupted. Check your connection and try again.'];
                render();
            });
            xhr.send(new FormData(form));
        });

        var modal = input.closest('.modal');
        if (modal) {
            modal.addEventListener('show.bs.modal', reset);
        }
    }

    document.querySelectorAll('input[data-photo-queue]').forEach(bindQueue);
})();
