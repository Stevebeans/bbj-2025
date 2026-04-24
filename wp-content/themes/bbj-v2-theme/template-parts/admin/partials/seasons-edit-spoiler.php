<?php
/**
 * Admin shell — Spoiler Bar tab body.
 *
 * Receives $args['season'] — the wp_bbj_seasons row object.
 * Receives $args['season_id'] — int.
 */

if (!defined('ABSPATH')) {
    exit;
}

$season    = $args['season'];
$season_id = (int) $args['season_id'];

// Pull roster
$players = function_exists('bbj_v2_get_season_players')
    ? bbj_v2_get_season_players($season_id, 'bbj_v2_profile_image')
    : [];

// Split into Active / Eliminated (pre-sort by name / finish_place + date respectively)
$active = [];
$eliminated = [];
foreach ($players as $p) {
    if (!empty($p['current_evicted'])) {
        $eliminated[] = $p;
    } else {
        $active[] = $p;
    }
}

// Active: sort by first_name
usort($active, function ($a, $b) {
    return strcasecmp(
        trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')),
        trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''))
    );
});

// Eliminated: finish_place ASC (NULL last), then evicted_date DESC
usort($eliminated, function ($a, $b) {
    $fa = (isset($a['finish_place']) && $a['finish_place'] !== null && $a['finish_place'] !== '')
        ? (int) $a['finish_place'] : null;
    $fb = (isset($b['finish_place']) && $b['finish_place'] !== null && $b['finish_place'] !== '')
        ? (int) $b['finish_place'] : null;
    if ($fa !== null && $fb !== null && $fa !== $fb) return $fa <=> $fb;
    if ($fa !== null && $fb === null) return -1;
    if ($fa === null && $fb !== null) return 1;

    $da = strtotime($a['bbj_evicted_date'] ?? '') ?: 0;
    $db = strtotime($b['bbj_evicted_date'] ?? '') ?: 0;
    return $db <=> $da;
});

$active_count = count($active);
$eliminated_count = count($eliminated);
$roster_is_empty = $active_count === 0 && $eliminated_count === 0;

// Notices
$saved   = !empty($_GET['updated']);
$purged  = !empty($_GET['purged']);
?>

<?php get_template_part('template-parts/admin/partials/spoiler-preview-strip', null, [
    'season_id' => $season_id,
]); ?>

<?php if ($saved): ?>
    <div class="mb-4 p-3 bg-green-50 text-green-800 border border-green-200 dark:bg-green-900/20 dark:text-green-200 dark:border-green-800"
         data-bbj-autodismiss="3000">
        Spoiler bar saved.
    </div>
<?php endif; ?>
<?php if ($purged): ?>
    <div class="mb-4 p-3 bg-blue-50 text-blue-800 border border-blue-200 dark:bg-blue-900/20 dark:text-blue-200 dark:border-blue-800"
         data-bbj-autodismiss="3000">
        Cache purged.
    </div>
<?php endif; ?>

<!-- Purge Cache form (separate, tiny) -->
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="inline">
    <?php wp_nonce_field('bbj_v2_purge_season_cache_action', 'bbj_v2_purge_season_cache_nonce'); ?>
    <input type="hidden" name="action" value="bbj_v2_purge_season_cache">
    <input type="hidden" name="season_id" value="<?php echo esc_attr($season_id); ?>">
    <button type="submit"
            class="float-right mb-2 px-3 py-1.5 text-xs font-semibold text-stone-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-stone-300 dark:border-slate-700 hover:bg-stone-50 dark:hover:bg-slate-700 transition-colors">
        Purge Cache
    </button>
</form>

<!-- Save form: wraps all player cards -->
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="clear-both">
    <?php wp_nonce_field('add_player_action', 'add_player_nonce'); ?>
    <input type="hidden" name="action" value="bbj_v2_update_season">
    <input type="hidden" name="season_id" value="<?php echo esc_attr($season_id); ?>">

    <?php if ($roster_is_empty): ?>
        <div class="p-6 bg-stone-50 dark:bg-slate-900/40 border border-dashed border-stone-300 dark:border-slate-700 text-center">
            <p class="text-stone-600 dark:text-slate-400">No players yet.</p>
            <p class="text-sm text-stone-500 dark:text-slate-500 mt-1">Add players from the Season Info tab.</p>
        </div>
    <?php else: ?>

        <?php if ($active_count > 0): ?>
            <h2 class="font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500 mt-4 mb-2">
                Active (<?php echo $active_count; ?>)
            </h2>
            <?php foreach ($active as $player):
                get_template_part('template-parts/admin/partials/spoiler-player-card', null, [
                    'player' => $player,
                    'season' => $season,
                ]);
            endforeach; ?>
        <?php endif; ?>

        <?php if ($eliminated_count > 0): ?>
            <h2 class="font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500 mt-6 mb-2">
                Eliminated (<?php echo $eliminated_count; ?>)
            </h2>
            <?php foreach ($eliminated as $player):
                get_template_part('template-parts/admin/partials/spoiler-player-card', null, [
                    'player' => $player,
                    'season' => $season,
                ]);
            endforeach; ?>
        <?php endif; ?>

        <div class="mt-6 flex items-center justify-end gap-3">
            <button type="submit"
                    class="px-5 py-2 bg-primary-500 hover:bg-primary-600 text-white font-semibold text-sm transition-colors">
                Save Spoiler Bar
            </button>
        </div>

    <?php endif; ?>
</form>
