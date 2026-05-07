/**
 * Comments island bootstrap.
 * - Watches #bbj-comments-root with IntersectionObserver
 * - Lazy-imports main.js when within 500px of viewport
 * - Retries up to 3 times on import failure, then falls back to ?bbjcomments=plain
 */

const ROOT_ID = 'bbj-comments-root';
const MAX_RETRIES = 3;

function fallbackToPlain(root) {
  const fallbackHref = new URL(window.location.href);
  fallbackHref.searchParams.set('bbjcomments', 'plain');
  root.innerHTML =
    '<div class="text-sm text-gray-600">' +
      'Comments couldn\'t load. ' +
      '<a class="text-primary-500 underline" href="' + fallbackHref.toString() + '">View on full page →</a>' +
    '</div>';
}

function showRetry(root, onRetry) {
  root.innerHTML =
    '<div class="text-sm text-gray-600">' +
      'Comments couldn\'t load. ' +
      '<button type="button" class="text-primary-500 underline" id="bbj-comments-retry">Retry</button>' +
    '</div>';
  document.getElementById('bbj-comments-retry').addEventListener('click', onRetry, { once: true });
}

function loadMain(root, attempt = 1) {
  import(/* webpackChunkName: "main" */ './main.js')
    .then(({ default: mount }) => mount(root, window.bbjComments))
    .catch((err) => {
      console.error('[bbj-comments] main chunk load failed', { attempt, err });
      if (attempt >= MAX_RETRIES) {
        fallbackToPlain(root);
      } else {
        showRetry(root, () => loadMain(root, attempt + 1));
      }
    });
}

function init() {
  const root = document.getElementById(ROOT_ID);
  if (!root || !window.bbjComments) return;
  if (!('IntersectionObserver' in window)) {
    loadMain(root);
    return;
  }
  const io = new IntersectionObserver((entries) => {
    if (entries.some((e) => e.isIntersecting)) {
      io.disconnect();
      loadMain(root);
    }
  }, { rootMargin: '500px 0px' });
  io.observe(root);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
  init();
}
