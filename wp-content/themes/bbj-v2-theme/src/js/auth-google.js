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
            // Session-scoped cookie so the override doesn't persist forever;
            // dev can flip back by closing the tab or clearing the cookie.
            document.cookie = 'bbj_force_webview=1; path=/; SameSite=Lax; max-age=3600';
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
        const forms = window.BBJAuthForms;
        if (!forms) return; // auth-forms.js not loaded — fail silent.

        forms.setFormError(activeView, '');
        const { ok, data } = await forms.postJSON('auth/google', {
            credential: response.credential,
            remember_me: true,
        });
        if (data && data.needs_linking) {
            modal.dataset.googleCredential = response.credential;
            modal.dataset.googleUser = JSON.stringify(data.google_user || {});
            window.BBJAuthModal.showView('link');
            return;
        }
        if (ok && data && data.success) {
            window.location.reload();
            return;
        }
        forms.setFormError(activeView, (data && (data.error || data.message)) || 'Google sign-in failed.');
    }

    // GIS only needs initialize() once per page; subsequent view switches
    // just render the button into the new container.
    let gisInitialized = false;
    function renderButtonInto(containerEl, textMode) {
        if (!containerEl || !window.google || !window.google.accounts || !window.google.accounts.id) return;
        if (!gisInitialized) {
            window.google.accounts.id.initialize({
                client_id: clientId,
                callback: handleCredential,
                auto_select: false,
            });
            gisInitialized = true;
        }
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

    // bbj-auth:view fires on every open (showView is always called by open)
    // so a single listener covers both initial open and view switches.
    modal.addEventListener('bbj-auth:view', function (e) {
        const name = e.detail && e.detail.view;
        if (name === 'login' || name === 'register') setupForView(name);
    });
})();
