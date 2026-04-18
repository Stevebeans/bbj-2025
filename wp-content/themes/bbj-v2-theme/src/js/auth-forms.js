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
                    // Custom header — WP's rest_cookie_check_errors hijacks X-WP-Nonce
                    // and validates it against the 'wp_rest' action, which breaks our
                    // bbj_auth nonce when WP cookies are present on the client.
                    'X-BBJ-Nonce': BBJAuth.nonce,
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

    function setFieldError(form, fieldName, message) {
        const el = form.querySelector('[data-bbj-field-error="' + fieldName + '"]');
        if (!el) return;
        if (!message) {
            el.classList.add('hidden');
            el.textContent = '';
            return;
        }
        el.classList.remove('hidden');
        el.textContent = message;
    }

    function clearAllFieldErrors(form) {
        form.querySelectorAll('[data-bbj-field-error]').forEach(el => {
            el.classList.add('hidden');
            el.textContent = '';
        });
    }

    function debounce(fn, ms) {
        let t;
        return function () {
            clearTimeout(t);
            const args = arguments, self = this;
            t = setTimeout(() => fn.apply(self, args), ms);
        };
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

    async function handleRegister(form) {
        const view = form.closest('.bbj-modal-view');
        setFormError(view, '');
        clearAllFieldErrors(form);

        const fd = new FormData(form);
        const username = (fd.get('username') || '').toString().toLowerCase().trim();
        const email = (fd.get('email') || '').toString().trim();
        const password = (fd.get('password') || '').toString();
        const confirm = (fd.get('confirm_password') || '').toString();
        const displayName = (fd.get('display_name') || '').toString().trim();
        const subscribe = !!fd.get('subscribe_newsletter');

        let failed = false;
        if (!/^[a-z0-9]{3,}$/.test(username)) {
            setFieldError(form, 'username', 'Username must be 3+ lowercase letters and numbers only.');
            failed = true;
        }
        if (!/\S+@\S+\.\S+/.test(email)) {
            setFieldError(form, 'email', 'Please enter a valid email.');
            failed = true;
        }
        if (password.length < 8) {
            setFieldError(form, 'password', 'Password must be at least 8 characters.');
            failed = true;
        }
        if (password !== confirm) {
            setFieldError(form, 'confirm_password', 'Passwords do not match.');
            failed = true;
        }
        if (failed) return;

        const restore = busy(form.querySelector('[data-bbj-submit]'), 'Creating account…');
        const { ok, data } = await postJSON('auth/register', {
            username,
            email,
            password,
            display_name: displayName,
            subscribe_newsletter: subscribe,
        });
        if (ok && data && data.success) {
            reloadOnSuccess();
            setTimeout(restore, 3000);
            return;
        }
        restore();
        if (data && data.field) {
            setFieldError(form, data.field, data.error || data.message || 'Invalid.');
        } else {
            setFormError(view, (data && (data.error || data.message)) || 'Registration failed.');
        }
    }

    // Debounced availability checks.
    const checkUsername = debounce(async function (input) {
        const value = (input.value || '').toLowerCase().trim();
        const form = input.closest('form');
        if (value.length < 3) { setFieldError(form, 'username', ''); return; }
        const { ok, data } = await postJSON('auth/check-username', { username: value });
        if (!ok) return;
        if (data && data.valid === false) {
            setFieldError(form, 'username', data.message || 'Username not available.');
        } else {
            setFieldError(form, 'username', '');
        }
    }, 500);

    const checkEmail = debounce(async function (input) {
        const value = (input.value || '').trim();
        const form = input.closest('form');
        if (!value.includes('@')) { setFieldError(form, 'email', ''); return; }
        const { ok, data } = await postJSON('auth/check-email', { email: value });
        if (!ok) return;
        if (data && data.exists === true) {
            setFieldError(form, 'email', 'An account with this email already exists.');
        } else {
            setFieldError(form, 'email', '');
        }
    }, 500);

    document.addEventListener('input', function (e) {
        if (!modal.contains(e.target)) return;
        if (e.target.matches('form[data-bbj-auth-form="register"] input[name="username"]')) checkUsername(e.target);
        if (e.target.matches('form[data-bbj-auth-form="register"] input[name="email"]'))    checkEmail(e.target);
    });

    // Delegated submit listener.
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('[data-bbj-auth-form]');
        if (!form || !modal.contains(form)) return;
        e.preventDefault();
        const kind = form.getAttribute('data-bbj-auth-form');
        if (kind === 'login')    return handleLogin(form);
        if (kind === 'register') return handleRegister(form);
        // Other handlers attached in later tasks.
    });
})();
