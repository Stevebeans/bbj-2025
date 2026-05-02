(function () {
    'use strict';

    const modal = document.querySelector('[data-bbj-search-modal]');
    if (!modal) return;

    const input = modal.querySelector('#bbj-search-modal-input');
    let lastFocus = null;

    function isOpen() {
        return modal.classList.contains('is-open');
    }

    function open() {
        lastFocus = document.activeElement;
        modal.hidden = false;
        void modal.offsetHeight;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (input) {
            input.value = '';
            setTimeout(() => input.focus(), 50);
        }
    }

    function close() {
        if (!isOpen()) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        const onEnd = () => { if (!isOpen()) modal.hidden = true; };
        modal.addEventListener('transitionend', onEnd, { once: true });
        setTimeout(onEnd, 300);
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-bbj-search-open]')) {
            e.preventDefault();
            open();
            return;
        }
        if (e.target.closest('[data-bbj-search-close]')) {
            e.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen()) {
            e.preventDefault();
            close();
        }
    });
})();
