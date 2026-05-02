<?php
/**
 * Admin shell — uncached spoiler-bar preview inside the Spoiler Bar edit tab.
 *
 * Receives $args['season_id'] — int, the season being edited.
 */

if (!defined('ABSPATH')) {
    exit;
}

$season_id = isset($args['season_id']) ? (int) $args['season_id'] : 0;
if ($season_id <= 0) {
    return;
}
?>

<div class="mb-6">
    <div class="text-xs uppercase tracking-wide text-stone-500 dark:text-slate-500 mb-2 font-osw">
        Live preview (uncached)
    </div>
    <div class="p-3 border border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-900/40 overflow-x-auto">
        <?php
        // Render the public spoiler bar for this specific season, bypassing the 300s cache.
        echo bbj_render_spoiler_bar($season_id, true);
        ?>
    </div>
    <p class="text-xs text-stone-500 dark:text-slate-500 mt-1">
        This preview rebuilds from the DB on every page load. Public visitors still see the cached version until the cache expires (or you click Purge Cache).
    </p>
</div>
