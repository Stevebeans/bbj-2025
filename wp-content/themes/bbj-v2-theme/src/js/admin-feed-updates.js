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

        restFetch('/create', { method: 'POST', body: fd })
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
                toast(err.message || 'Request failed — check connection', 'error');
            })
            .finally(function () {
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
        li.setAttribute('data-content', ''); // server didn't return raw content; fetch-on-edit below handles this
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
        return escHtml(s).replace(/"/g, '&quot;');
    }

    // ---- Edit + delete flows (wired in subsequent tasks) --------------------
    // window.BBJ_FEED_DEBUG gives a console handle for manual probing.
    window.BBJ_FEED_DEBUG = { toast: toast, prependRow: prependRow };

})();
