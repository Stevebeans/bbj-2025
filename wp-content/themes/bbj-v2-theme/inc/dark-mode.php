<?php
/**
 * Dark mode: inject a tiny inline script early in <head> that reads localStorage
 * and sets the `dark` class on <html> before the first paint.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_head', 'bbj_v2_dark_mode_prepaint', 1);
function bbj_v2_dark_mode_prepaint(): void
{
    ?>
    <script>
    (function () {
      try {
        var pref = localStorage.getItem('bbj_dark');
        var mql  = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (pref === '1' || (pref === null && mql)) {
          document.documentElement.classList.add('dark');
        }
      } catch (e) {}
    })();
    </script>
    <?php
}
