/**
 * Auth form submission handlers.
 *
 * Delegated listener on [data-bbj-auth-form]; routes by the form's data
 * attribute value. On successful auth we reload — server is the source of
 * truth for "logged in" state.
 */
(function () {
    'use strict';

    if (typeof BBJAuth === 'undefined') return; // Config missing — theme enqueue issue.

    const modal = document.getElementById('bbj-auth-modal');
    if (!modal) return;

    /**
     * POST JSON to an endpoint with the page-scoped nonce attached.
     * Returns { ok, status, data }.
     */
    async function postJSON(path, body) {
        const url = BBJAuth.api + path;
        const payload = Object.assign({ wp_session: 1 }, body || {});
        let res, data;
        try {
            res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': BBJAuth.nonce,
                },
                body: JSON.stringify(payload),
            });
        } catch (err) {
            return { ok: false, status: 0, data: { error: 'Network error. Please check your connection and try again.' } };
        }
        try {
            data = await res.json();
        } catch (_) {
            data = { error: 'Unexpected response from the server.' };
        }
        return { ok: res.ok, status: res.status, data };
    }

    /**
     * Show / hide the form-level error banner in a view.
     */
    function setFormError(viewEl, message) {
        const banner = viewEl.querySelector('[data-bbj-form-error]');
        if (!banner) return;
        if (!message) {
            banner.classList.add('hidden');
            banner.textContent = '';
            return;
        }
        banner.classList.remove('hidden');
        banner.textContent = message;
    }

    /**
     * Disable submit button and replace its label. Returns a restore fn.
     */
    function busy(btn, loadingLabel) {
        if (!btn) return () => {};
        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = loadingLabel;
        return () => { btn.disabled = false; btn.textContent = original; };
    }

    /**
     * Full page reload after successful auth. Strips reset-link params so
     * a follow-up visit doesn't re-open the reset view.
     */
    function reloadOnSuccess() {
        const target = new URL(window.location.href);
        target.searchParams.delete('bbj_rp');
        target.searchParams.delete('key');
        target.searchParams.delete('login');
        window.location.href = target.toString();
    }

    async function handleLogin(form) {
        const view = form.closest('.bbj-modal-view');
        setFormError(view, '');
        const fd = new FormData(form);
        const payload = {
            username: (fd.get('username') || '').trim(),
            password: fd.get('password') || '',
            remember_me: !!fd.get('remember_me'),
        };
        if (!payload.username || !payload.password) {
            setFormError(view, 'Username and password are required.');
            return;
        }
        const restore = busy(form.querySelector('[data-bbj-submit]'), 'Logging you in…');
        const { ok, data } = await postJSON('auth/login', payload);
        if (ok && data && data.success) {
            reloadOnSuccess();
            setTimeout(restore, 3000);
            return;
        }
        restore();
        setFormError(view, (data && (data.error || data.message)) || 'Login failed.');
    }

    // Delegated submit listener.
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('[data-bbj-auth-form]');
        if (!form || !modal.contains(form)) return;
        e.preventDefault();
        const kind = form.getAttribute('data-bbj-auth-form');
        if (kind === 'login') return handleLogin(form);
        // Other handlers attached in later tasks.
    });
})();
