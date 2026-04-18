(function () {
    'use strict';

    const STORAGE_KEY = 'bbj:spoiler:collapsed';

    document.addEventListener('DOMContentLoaded', function () {
        initCollapse();
        initDragScroll();
    });

    function initCollapse() {
        const bars = document.querySelectorAll('[data-bbj-spoiler]');
        bars.forEach(function (bar) {
            const btn   = bar.querySelector('[data-bbj-spoiler-toggle]');
            const body  = bar.querySelector('[data-bbj-spoiler-body]');
            const label = bar.querySelector('[data-bbj-spoiler-label]');
            const icon  = bar.querySelector('.bbj-spoiler-toggle-icon');
            if (!btn || !body) return;

            const setState = function (collapsed) {
                bar.classList.toggle('is-collapsed', collapsed);
                btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                if (label) label.textContent = collapsed ? 'Expand' : 'Collapse';
                if (icon)  icon.textContent  = collapsed ? '+' : '−';
            };

            // Default: collapsed on mobile, expanded on desktop, when no preference set.
            // Honor an explicit user choice in either direction.
            let initial = false;
            try {
                const stored = localStorage.getItem(STORAGE_KEY);
                initial = stored !== null ? stored === '1' : window.innerWidth < 768;
            } catch (_) {}
            setState(initial);

            btn.addEventListener('click', function () {
                const next = !bar.classList.contains('is-collapsed');
                setState(next);
                try { localStorage.setItem(STORAGE_KEY, next ? '1' : '0'); } catch (_) {}
            });
        });
    }

    function initDragScroll() {
        const lists = document.querySelectorAll('[data-spoiler-bar]');
        lists.forEach(function (list) {
            let isDragging = false;
            let startX = 0;
            let scrollLeft = 0;
            let moved = false;

            list.addEventListener('mousedown', function (e) {
                isDragging = true;
                moved = false;
                startX = e.pageX - list.offsetLeft;
                scrollLeft = list.scrollLeft;
            });
            list.addEventListener('mouseleave', function () { isDragging = false; });
            list.addEventListener('mouseup', function () {
                isDragging = false;
                // Swallow the synthetic click that follows a drag so the anchor doesn't navigate.
                if (moved) {
                    const stop = function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        list.removeEventListener('click', stop, true);
                    };
                    list.addEventListener('click', stop, true);
                }
            });
            list.addEventListener('mousemove', function (e) {
                if (!isDragging) return;
                e.preventDefault();
                const x = e.pageX - list.offsetLeft;
                const dx = x - startX;
                if (Math.abs(dx) > 5) moved = true;
                list.scrollLeft = scrollLeft - dx * 1.5;
            });
        });
    }
})();
