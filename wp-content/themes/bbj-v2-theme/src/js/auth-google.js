/**
 * Google Sign-In integration for auth modal.
 *
 * - Loads Google Identity Services once on demand.
 * - Renders the Google button into the active view's container.
 * - Detects FB/IG/Line WebViews and swaps the button for a CTA telling
 *   the user to open the page in Chrome/Safari — GIS needs third-party
 *   cookies, which WebViews sandbox or block.
 * - On credential: POST /auth/google. If needs_linking, switch to the
 *   link view and stash the credential + google_user on the modal.
 */
(function () {
    'use strict';

    if (typeof BBJAuth === 'undefined') return;

    const modal = document.getElementById('bbj-auth-modal');
    if (!modal) return;

    const clientId = (document.documentElement.getAttribute('data-bbj-google-client') || '').trim();

    const WEBVIEW_REGEX = /FBAN|FBAV|Instagram|Line\//i;
    function isWebView() {
        if (WEBVIEW_REGEX.test(navigator.userAgent)) return true;
        if (!BBJAuth.debug) return false;
        const p = new URLSearchParams(location.search);
        if (p.has('bbj_force_webview')) {
            document.cookie = 'bbj_force_webview=1; path=/; SameSite=Lax';
        }
        return document.cookie.includes('bbj_force_webview=1');
    }

    let gisPromise = null;
    function loadGIS() {
        if (gisPromise) return gisPromise;
        gisPromise = new Promise(function (resolve, reject) {
            if (window.google && window.google.accounts && window.google.accounts.id) {
                resolve();
                return;
            }
            const existing = document.querySelector('script[src="https://accounts.google.com/gsi/client"]');
            if (existing) {
                existing.addEventListener('load', () => resolve());
                existing.addEventListener('error', () => reject(new Error('GIS failed to load')));
                return;
            }
            const s = document.createElement('script');
            s.src = 'https://accounts.google.com/gsi/client';
            s.async = true;
            s.defer = true;
            s.onload = () => resolve();
            s.onerror = () => reject(new Error('GIS failed to load'));
            document.body.appendChild(s);
        });
        return gisPromise;
    }

    async function handleCredential(response) {
        if (!response || !response.credential) return;
        const activeView = modal.querySelector('.bbj-modal-view.is-active');
        const errorEl = activeView && activeView.querySelector('[data-bbj-form-error]');
        if (errorEl) { errorEl.classList.add('hidden'); errorEl.textContent = ''; }

        try {
            const res = await fetch(BBJAuth.api + 'auth/google', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-BBJ-Nonce': BBJAuth.nonce,
                },
                body: JSON.stringify({
                    credential: response.credential,
                    remember_me: true,
                    wp_session: 1,
                }),
            });
            const data = await res.json();
            if (data && data.needs_linking) {
                modal.dataset.googleCredential = response.credential;
                modal.dataset.googleUser = JSON.stringify(data.google_user || {});
                window.BBJAuthModal.showView('link');
                return;
            }
            if (res.ok && data && data.success) {
                window.location.reload();
                return;
            }
            if (errorEl) {
                errorEl.textContent = (data && (data.error || data.message)) || 'Google sign-in failed.';
                errorEl.classList.remove('hidden');
            }
        } catch (err) {
            if (errorEl) {
                errorEl.textContent = 'Network error during Google sign-in.';
                errorEl.classList.remove('hidden');
            }
        }
    }

    function renderButtonInto(containerEl, textMode) {
        if (!containerEl || !window.google || !window.google.accounts || !window.google.accounts.id) return;
        window.google.accounts.id.initialize({
            client_id: clientId,
            callback: handleCredential,
            auto_select: false,
        });
        containerEl.innerHTML = '';
        window.google.accounts.id.renderButton(containerEl, {
            theme: 'outline',
            size: 'large',
            width: 320,
            text: textMode, // 'continue_with' or 'signup_with'
        });
    }

    function showWebViewNotice(view) {
        const notice = view.querySelector('[data-bbj-auth-webview-notice]');
        if (notice) notice.classList.remove('hidden');
    }

    async function setupForView(viewName) {
        if (!clientId) return; // No client configured — silently skip.
        const view = modal.querySelector('[data-bbj-auth-view="' + viewName + '"]');
        if (!view) return;
        const container = view.querySelector(viewName === 'register' ? '[data-bbj-google-register-container]' : '[data-bbj-google-login-container]');
        if (!container) return;

        if (isWebView()) {
            container.classList.add('hidden');
            showWebViewNotice(view);
            return;
        }

        try {
            await loadGIS();
        } catch (_) {
            return; // Silent drop if GIS won't load.
        }
        renderButtonInto(container, viewName === 'register' ? 'signup_with' : 'continue_with');
    }

    // Render on view switch.
    modal.addEventListener('bbj-auth:view', function (e) {
        const name = e.detail && e.detail.view;
        if (name === 'login' || name === 'register') setupForView(name);
    });

    // Render on first open if the view didn't switch events yet.
    modal.addEventListener('bbj-auth:opened', function (e) {
        const name = e.detail && e.detail.view;
        if (name === 'login' || name === 'register') setupForView(name);
    });
})();
