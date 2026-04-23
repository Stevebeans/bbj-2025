/**
 * Admin Feed Updates pane — client-side logic.
 *
 * Three flows: create (form submit), edit (inline row), delete (inline confirm).
 * All fetch against /wp-json/bbjd/v1/feed-updates/* with the X-WP-Nonce header.
 * Keeps a pure-DOM approach — no jQuery, no framework.
 */
(function () {
    'use strict';

    var cfg = window.BBJ_FEED;
    if (!cfg || !cfg.restRoot) return;

    // Strip trailing slash from restRoot so path concat is predictable.
    var REST_ROOT = cfg.restRoot.replace(/\/$/, '');

    var form = document.getElementById('bbj-feed-form');
    var list = document.getElementById('bbj-feed-list');
    var toastContainer = document.getElementById('bbj-feed-toasts');
    if (!form || !list || !toastContainer) return;

    // ---- Toast helper -------------------------------------------------------

    function toast(message, tone) {
        var el = document.createElement('div');
        el.className =
            'px-3 py-2 text-sm text-white shadow pointer-events-auto ' +
            (tone === 'error'
                ? 'bg-accent-red'
                : tone === 'warn'
                ? 'bg-secondary-500 text-primary-500'
                : 'bg-primary-500');
        el.textContent = message;
        toastContainer.appendChild(el);
        setTimeout(function () {
            el.style.transition = 'opacity 0.3s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 300);
        }, 3000);
    }

    // ---- REST wrapper -------------------------------------------------------

    function restFetch(path, options) {
        options = options || {};
        options.headers = options.headers || {};
        options.headers['X-WP-Nonce'] = cfg.nonce;
        return fetch(REST_ROOT + path, options);
    }

    // ---- Image preview ------------------------------------------------------

    var imageInput = form.querySelector('input[name="image"]');
    var imagePreview = form.querySelector('[data-image-preview]');
    var imagePreviewImg = imagePreview ? imagePreview.querySelector('img') : null;
    var imageClear = form.querySelector('[data-image-clear]');

    if (imageInput) {
        imageInput.addEventListener('change', function () {
            if (!imageInput.files || !imageInput.files[0]) {
                imagePreview.classList.add('hidden');
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                imagePreviewImg.src = e.target.result;
                imagePreview.classList.remove('hidden');
            };
            reader.readAsDataURL(imageInput.files[0]);
        });
    }
    if (imageClear) {
        imageClear.addEventListener('click', function () {
            imageInput.value = '';
            imagePreview.classList.add('hidden');
        });
    }

    // ---- Create flow --------------------------------------------------------

    var headlineInput = form.querySelector('input[name="title"]');
    var headlineError = form.querySelector('[data-headline-error]');

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var headline = headlineInput.value.trim();
        if (!headline) {
            headlineError.classList.remove('hidden');
            headlineInput.focus();
            return;
        }
        headlineError.classList.add('hidden');

        var submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Posting…';

        var fd = new FormData(form);
        // FormData from the form already carries title/details/image/update_type/mode.
        // Social checkboxes only serialize when checked, matching the endpoint's
        // post_to_* bool params.

        var controller = new AbortController();
        var timeoutId = setTimeout(function () { controller.abort(); }, 15000);

        restFetch('/create', { method: 'POST', body: fd, signal: controller.signal })
            .then(function (res) {
                return res.json().then(function (json) { return { ok: res.ok, json: json }; });
            })
            .then(function (result) {
                if (!result.ok || !result.json.success) {
                    throw new Error(result.json.message || 'Post failed');
                }
                prependRow(result.json.update, result.json.social_results || null);
                form.reset();
                if (imagePreview) imagePreview.classList.add('hidden');
                headlineInput.focus();

                var msg = 'Posted ✓';
                var social = result.json.social_results || {};
                if (social.bluesky && social.bluesky.error) msg += ' — Bluesky failed';
                if (social.facebook && social.facebook.error) msg += ' — Facebook failed';
                toast(msg);
            })
            .catch(function (err) {
                var msg = err && err.name === 'AbortError' ? 'Request timed out' : (err.message || 'Request failed — check connection');
                toast(msg, 'error');
            })
            .finally(function () {
                clearTimeout(timeoutId);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Post Update';
            });
    });

    // ---- Row rendering ------------------------------------------------------

    function prependRow(update, socialResults) {
        var li = document.createElement('li');
        li.className = 'p-3';
        li.setAttribute('data-id', String(update.id));
        li.setAttribute('data-title', update.title || '');
        li.setAttribute('data-content', update.raw_content || '');
        li.setAttribute('data-term-id', '0');
        li.setAttribute('data-term-name', '');
        li.setAttribute('data-mode', 'display');

        // Display subtree — mirrors the PHP-rendered markup in the pane template.
        var social = '';
        if (socialResults) {
            if (socialResults.bluesky && socialResults.bluesky.posted) social += '<span class="text-green-600" title="Posted to Bluesky">✓BS</span> ';
            else if (socialResults.bluesky && socialResults.bluesky.error) social += '<span class="text-accent-red" title="' + escAttr(socialResults.bluesky.error) + '">✗BS</span> ';
            if (socialResults.facebook && socialResults.facebook.posted) social += '<span class="text-green-600" title="Posted to Facebook">✓FB</span>';
            else if (socialResults.facebook && socialResults.facebook.error) social += '<span class="text-accent-red" title="' + escAttr(socialResults.facebook.error) + '">✗FB</span>';
        }

        var thumb = update.thumbnail
            ? '<img src="' + escAttr(update.thumbnail) + '" alt="" class="h-8 w-8 object-cover border border-stone-200 dark:border-slate-700 shrink-0">'
            : '<div class="h-8 w-8 shrink-0"></div>';

        li.innerHTML =
            '<div class="flex items-center gap-3" data-row-display>' +
                thumb +
                '<span class="flex-1 truncate text-sm text-stone-800 dark:text-slate-200 font-medium" title="' + escAttr(update.title) + '">' + escHtml(update.title) + '</span>' +
                '<span class="text-xs text-stone-500 dark:text-slate-500 shrink-0" data-nosnippet>' + escHtml(update.time_ago || 'just now') + '</span>' +
                '<span class="text-xs text-stone-600 dark:text-slate-400 shrink-0" title="Votes">&#9650;0</span>' +
                '<span class="text-xs shrink-0" data-social>' + social + '</span>' +
                '<span class="flex items-center gap-2 shrink-0" data-row-actions>' +
                    '<button type="button" data-action="edit" class="text-xs text-primary-500 hover:underline">Edit</button>' +
                    '<button type="button" data-action="delete" class="text-xs text-accent-red hover:underline">Delete</button>' +
                '</span>' +
            '</div>' +
            '<div class="hidden space-y-2" data-row-edit></div>' +
            '<div class="hidden mt-2 text-sm" data-row-confirm></div>';

        list.insertBefore(li, list.firstChild);
    }

    // ---- HTML-escape helpers (minimal) --------------------------------------

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
    function escAttr(s) {
        return escHtml(s).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ---- Edit flow ----------------------------------------------------------

    list.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-action]');
        if (!btn) return;
        var li = btn.closest('li[data-id]');
        if (!li) return;

        var action = btn.getAttribute('data-action');
        if (action === 'edit')                openEdit(li);
        else if (action === 'save')           saveEdit(li, btn);
        else if (action === 'cancel')         closeEdit(li);
        else if (action === 'delete')         openConfirmDelete(li);
        else if (action === 'confirm-delete') confirmDelete(li, btn);
        else if (action === 'cancel-delete')  closeConfirmDelete(li);
    });

    function openEdit(li) {
        // If the row didn't come with hydrated edit markup (e.g. a row prepended
        // by prependRow after create), build it from data-* attrs on the fly.
        var editEl = li.querySelector('[data-row-edit]');
        if (editEl && !editEl.querySelector('[data-edit-title]')) {
            buildEditSubtree(li, editEl);
        }
        li.querySelector('[data-row-display]').classList.add('hidden');
        editEl.classList.remove('hidden');
        li.setAttribute('data-mode', 'edit');
        var titleInput = editEl.querySelector('[data-edit-title]');
        if (titleInput) titleInput.focus();
    }

    function closeEdit(li) {
        li.querySelector('[data-row-edit]').classList.add('hidden');
        li.querySelector('[data-row-display]').classList.remove('hidden');
        li.setAttribute('data-mode', 'display');
    }

    function saveEdit(li, btn) {
        var editEl = li.querySelector('[data-row-edit]');
        var title   = editEl.querySelector('[data-edit-title]').value.trim();
        var details = editEl.querySelector('[data-edit-details]').value;
        var termId  = editEl.querySelector('[data-edit-category]').value;
        var id      = li.getAttribute('data-id');

        if (!title) {
            toast('Headline required', 'warn');
            return;
        }

        btn.disabled = true;
        var originalLabel = btn.textContent;
        btn.textContent = 'Saving…';

        var controller = new AbortController();
        var timeoutId = setTimeout(function () { controller.abort(); }, 15000);

        restFetch('/' + encodeURIComponent(id), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                title: title,
                details: details,
                update_type: termId
            }),
            signal: controller.signal
        })
            .then(function (res) {
                if (res.status === 404) throw new Error('Update disappeared — refresh');
                return res.json().then(function (json) { return { ok: res.ok, json: json }; });
            })
            .then(function (result) {
                if (!result.ok || !result.json.success) {
                    throw new Error(result.json.message || 'Save failed');
                }
                applyRowUpdate(li, result.json.update, termId, editEl);
                closeEdit(li);
                toast('Updated ✓');
            })
            .catch(function (err) {
                var msg = err && err.name === 'AbortError' ? 'Request timed out' : err.message;
                toast(msg, 'error');
            })
            .finally(function () {
                clearTimeout(timeoutId);
                btn.disabled = false;
                btn.textContent = originalLabel;
            });
    }

    function buildEditSubtree(li, editEl) {
        // For rows prepended client-side (after a create), the edit subtree
        // is empty. Populate it from data-* attrs so the edit form works.
        var title   = li.getAttribute('data-title') || '';
        var content = li.getAttribute('data-content') || '';
        var termId  = li.getAttribute('data-term-id') || '0';

        // Build category options — clone from the main form's select so the
        // taxonomy list stays in sync with the page.
        var mainSelect = document.getElementById('bbj-feed-category');
        var optsHtml = '<option value="">(none)</option>';
        if (mainSelect) {
            Array.prototype.forEach.call(mainSelect.querySelectorAll('option'), function (opt) {
                if (opt.value === '') return;
                var selected = (opt.value === termId) ? ' selected' : '';
                optsHtml += '<option value="' + escAttr(opt.value) + '"' + selected + '>' + escHtml(opt.textContent) + '</option>';
            });
        }

        editEl.innerHTML =
            '<div class="flex gap-2">' +
                '<input type="text" data-edit-title class="flex-1 px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">' +
                '<select data-edit-category class="px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">' + optsHtml + '</select>' +
                '<button type="button" data-action="save" class="px-3 py-1 bg-primary-500 hover:bg-primary-600 text-white text-xs font-semibold">Save</button>' +
                '<button type="button" data-action="cancel" class="px-2 py-1 text-xs text-stone-500 hover:underline">Cancel</button>' +
            '</div>' +
            '<textarea data-edit-details rows="3" class="w-full px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"></textarea>';

        // Set .value (not innerHTML / not attribute) for inputs so raw HTML in
        // details doesn't get parsed, and smart quotes in titles round-trip.
        editEl.querySelector('[data-edit-title]').value = title;
        editEl.querySelector('[data-edit-details]').value = content;
    }

    function applyRowUpdate(li, update, submittedTermId, editEl) {
        // Update data-* attrs so subsequent edits start from fresh values.
        li.setAttribute('data-title', update.title || '');
        li.setAttribute('data-content', update.raw_content != null ? update.raw_content : editEl.querySelector('[data-edit-details]').value);
        li.setAttribute('data-term-id', submittedTermId || '0');

        // Refresh display subtree text. Keep markup structure stable;
        // only touch the nodes that changed.
        var display = li.querySelector('[data-row-display]');
        if (display) {
            var titleSpan = display.querySelector('.flex-1.truncate');
            if (titleSpan) {
                titleSpan.textContent = update.title;
                titleSpan.setAttribute('title', update.title);
            }
            // Category pill: find the one fixed-width font-mono span, or
            // insert one if the update didn't previously have a category.
            var pill = display.querySelector('.font-mono');
            var termName = '';
            if (submittedTermId) {
                var mainOpt = document.querySelector('#bbj-feed-category option[value="' + CSS.escape(submittedTermId) + '"]');
                if (mainOpt) termName = mainOpt.textContent;
            }
            if (termName) {
                if (pill) {
                    pill.textContent = termName;
                } else {
                    var newPill = document.createElement('span');
                    newPill.className = 'px-1.5 py-0.5 text-xs font-mono bg-stone-100 text-stone-600 border border-stone-200 dark:bg-slate-800 dark:text-slate-400';
                    newPill.textContent = termName;
                    // Insert after title span (second child after thumb)
                    titleSpan.parentNode.insertBefore(newPill, titleSpan.nextSibling);
                }
                li.setAttribute('data-term-name', termName);
            } else if (pill) {
                pill.remove();
                li.setAttribute('data-term-name', '');
            }
        }
    }

    // ---- Delete flow (next task) --------------------------------------------

    function openConfirmDelete(li) {
        var confirmEl = li.querySelector('[data-row-confirm]');
        if (!confirmEl.innerHTML.trim()) {
            confirmEl.innerHTML =
                '<span class="text-stone-700 dark:text-slate-300 mr-2">Delete this update?</span>' +
                '<button type="button" data-action="confirm-delete" class="px-2 py-0.5 bg-accent-red hover:opacity-90 text-white text-xs font-semibold">Confirm</button>' +
                '<button type="button" data-action="cancel-delete" class="px-2 py-0.5 text-xs text-stone-500 hover:underline">Cancel</button>';
        }
        confirmEl.classList.remove('hidden');
    }
    function closeConfirmDelete(li) {
        li.querySelector('[data-row-confirm]').classList.add('hidden');
    }
    function confirmDelete(li, btn) {
        var id = li.getAttribute('data-id');
        btn.disabled = true;
        var originalLabel = btn.textContent;
        btn.textContent = 'Deleting…';

        var controller = new AbortController();
        var timeoutId = setTimeout(function () { controller.abort(); }, 15000);

        restFetch('/' + encodeURIComponent(id), { method: 'DELETE', signal: controller.signal })
            .then(function (res) {
                if (res.status === 404) {
                    // Treat as already-deleted — remove the row anyway
                    toast('Already deleted', 'warn');
                    li.remove();
                    return;
                }
                if (!res.ok) {
                    return res.json().then(function (json) {
                        throw new Error(json.message || 'Delete failed');
                    });
                }
                li.remove();
                toast('Deleted');
            })
            .catch(function (err) {
                var msg = err && err.name === 'AbortError' ? 'Request timed out' : err.message;
                toast(msg, 'error');
                btn.disabled = false;
                btn.textContent = originalLabel;
            })
            .finally(function () {
                clearTimeout(timeoutId);
            });
    }

    // Debug handle
    window.BBJ_FEED_DEBUG = { toast: toast, prependRow: prependRow };

})();
