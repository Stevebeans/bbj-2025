/**
 * Season profile — sticky tab nav scroll-spy.
 * Marks the tab whose section is currently in view with .on.
 * Smooth-scroll on tab clicks with header offset.
 *
 * Loaded only on single-bigbrother-seasons pages via inc/enqueue.php.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var nav = document.querySelector('.sectionnav');
    if (!nav) return;

    var tabs = Array.prototype.slice.call(nav.querySelectorAll('a[href^="#"]'));
    if (tabs.length === 0) return;

    var sections = tabs
      .map(function (a) { return document.getElementById(a.getAttribute('href').slice(1)); })
      .filter(Boolean);

    if (sections.length === 0) return;

    function setActive(id) {
      tabs.forEach(function (a) {
        a.classList.toggle('on', a.getAttribute('href') === '#' + id);
      });
    }

    var observer = new IntersectionObserver(
      function (entries) {
        var visible = entries.filter(function (e) { return e.isIntersecting; });
        if (visible.length === 0) return;
        visible.sort(function (a, b) { return a.target.offsetTop - b.target.offsetTop; });
        setActive(visible[0].target.id);
      },
      { rootMargin: '-50px 0px -60% 0px', threshold: 0 }
    );

    sections.forEach(function (s) { observer.observe(s); });

    // Smooth scroll on click (~60px header offset)
    tabs.forEach(function (a) {
      a.addEventListener('click', function (e) {
        var target = document.getElementById(this.getAttribute('href').slice(1));
        if (!target) return;
        e.preventDefault();
        var y = target.getBoundingClientRect().top + window.pageYOffset - 60;
        window.scrollTo({ top: y, behavior: 'smooth' });
        history.replaceState(null, '', this.getAttribute('href'));
      });
    });
  });
})();
