/**
 * Auth modal controller.
 *
 * Responsibilities:
 *  - open/close the modal (toggles .is-open + aria-hidden)
 *  - switch between views (login/register/link/forgot/reset)
 *  - trap focus within the dialog while open
 *  - close on Esc / backdrop click / [data-bbj-auth-close]
 *
 * Consumers dispatch events rather than importing — any [data-bbj-auth-open]
 * button opens the modal; JS modules can call window.BBJAuthModal.open(view).
 */
(function () {
    'use strict';

    const modal = document.getElementById('bbj-auth-modal');
    if (!modal) return; // Logged-in users have no modal.

    const titleEl = modal.querySelector('[data-bbj-auth-title]');
    const views = modal.querySelectorAll('[data-bbj-auth-view]');
    const focusableSel = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

    const titles = {
        login:    'Log In',
        register: 'Create Account',
        link:     'Link Your Account',
        forgot:   'Reset Password',
        reset:    'Set a New Password',
    };

    let lastFocus = null;

    function showView(name) {
        let found = false;
        views.forEach(v => {
            const match = v.getAttribute('data-bbj-auth-view') === name;
            v.classList.toggle('is-active', match);
            if (match) found = true;
        });
        if (!found) {
            showView('login');
            return;
        }
        if (titleEl && titles[name]) {
            titleEl.textContent = titles[name];
        }
        const firstInput = modal.querySelector('.bbj-modal-view.is-active input:not([type="hidden"]), .bbj-modal-view.is-active button');
        if (firstInput) firstInput.focus();
        modal.dispatchEvent(new CustomEvent('bbj-auth:view', { detail: { view: name } }));
    }

    function open(view) {
        lastFocus = document.activeElement;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        showView(view || 'login');
        modal.dispatchEvent(new CustomEvent('bbj-auth:opened', { detail: { view: view || 'login' } }));
    }

    function close() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    // Track where mousedown started so we only close on a true backdrop click.
    // Without this, dragging text out of a field (release outside the dialog)
    // lands mouseup on the backdrop and closes the modal mid-selection.
    let mouseDownTarget = null;
    document.addEventListener('mousedown', function (e) {
        mouseDownTarget = e.target;
    });

    // Delegated click handlers.
    document.addEventListener('click', function (e) {
        const opener = e.target.closest('[data-bbj-auth-open]');
        if (opener) {
            e.preventDefault();
            open(opener.getAttribute('data-bbj-auth-open'));
            return;
        }
        const switcher = e.target.closest('[data-bbj-auth-switch]');
        if (switcher && modal.contains(switcher)) {
            e.preventDefault();
            showView(switcher.getAttribute('data-bbj-auth-switch'));
            return;
        }
        if (e.target.closest('[data-bbj-auth-close]')) {
            e.preventDefault();
            close();
            return;
        }
        // Backdrop click: only close if BOTH mousedown and mouseup landed on
        // the modal root itself (not inside the dialog).
        if (e.target === modal && mouseDownTarget === modal) {
            close();
        }
    });

    // Esc closes; Tab traps focus within dialog.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            e.preventDefault();
            close();
            return;
        }
        if (e.key === 'Tab' && modal.classList.contains('is-open')) {
            const focusables = Array.from(modal.querySelectorAll(focusableSel)).filter(el => !el.hasAttribute('aria-hidden'));
            if (focusables.length === 0) return;
            const first = focusables[0];
            const last = focusables[focusables.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    // Auto-open on password-reset landing.
    const resetPayload = document.getElementById('bbj-reset-payload');
    if (resetPayload) {
        try {
            const data = JSON.parse(resetPayload.textContent);
            modal.dataset.resetKey = data.key;
            modal.dataset.resetLogin = data.login;
            open('reset');
        } catch (_) { /* ignore bad payload */ }
    }

    // Expose a minimal API for other modules.
    window.BBJAuthModal = { open, close, showView };
})();
