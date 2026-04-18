/* BBJ v2 dark mode toggle */
(function () {
  const btn = document.querySelector('[data-bbj-dark-toggle]');
  if (!btn) return;
  btn.addEventListener('click', () => {
    const root = document.documentElement;
    const isDark = root.classList.toggle('dark');
    try { localStorage.setItem('bbj_dark', isDark ? '1' : '0'); } catch (e) {}
  });
})();
