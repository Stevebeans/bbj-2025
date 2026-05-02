(function () {
    'use strict';

    const toggle = document.querySelector('[data-bbj-mnav-toggle]');
    const panel  = document.querySelector('[data-bbj-mnav-panel]');
    if (!toggle || !panel) return;

    const MD_BREAKPOINT = 768; // tailwind md
    const focusableSel = 'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])';
    let focusables = [];
    let lastFocus = null;

    function cacheFocusables() {
        focusables = Array.from(panel.querySelectorAll(focusableSel));
    }

    function isOpen() {
        return panel.classList.contains('is-open');
    }

    function open() {
        lastFocus = document.activeElement;
        panel.hidden = false;
        // Force reflow so the max-height transition runs instead of snapping to open.
        void panel.offsetHeight;
        panel.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', toggle.dataset.closeLabel || 'Close menu');
        document.body.style.overflow = 'hidden';
        cacheFocusables();
        if (focusables[0]) focusables[0].focus();
    }

    function close() {
        if (!isOpen()) return;
        panel.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', toggle.dataset.openLabel || 'Open menu');
        document.body.style.overflow = '';
        // Hide on transitionend OR a 300ms fallback (transition cancellation / missing
        // CSS transition would otherwise leave the panel displayed but styled closed).
        let hidden = false;
        const finish = () => {
            if (hidden) return;
            hidden = true;
            if (!isOpen()) panel.hidden = true;
        };
        panel.addEventListener('transitionend', finish, { once: true });
        setTimeout(finish, 300);
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    toggle.dataset.openLabel = toggle.getAttribute('aria-label') || 'Open menu';
    toggle.dataset.closeLabel = 'Close menu';

    toggle.addEventListener('click', function (e) {
        e.preventDefault();
        isOpen() ? close() : open();
    });

    panel.addEventListener('click', function (e) {
        if (e.target.closest('a')) close();
    });

    document.addEventListener('click', function (e) {
        if (!isOpen()) return;
        if (panel.contains(e.target) || toggle.contains(e.target)) return;
        close();
    });

    document.addEventListener('keydown', function (e) {
        if (!isOpen()) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
            toggle.focus();
            return;
        }
        if (e.key === 'Tab' && focusables.length) {
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

    window.addEventListener('resize', function () {
        if (window.innerWidth >= MD_BREAKPOINT && isOpen()) close();
    });
})();
