<?php
/**
 * Weeks tab for the season editor.
 *
 * Pulls all weeks for the season, lets the user pick one (or add a new one),
 * and renders the per-week edit form.
 *
 * Expected args (from get_template_part via seasons-edit-tabs.php):
 *   - season_post_id (int) — wp_bbj_seasons.id / post_id (same value)
 */

if (!defined('ABSPATH')) {
    exit;
}

bbj_v2_require_admin();

$args = $args ?? [];
$season_post_id = (int) ($args['season_post_id'] ?? 0);
if ($season_post_id <= 0) {
    echo '<p class="text-stone-500 italic">Missing season ID.</p>';
    return;
}

$season_row   = bbj_v2_get_season_by_id($season_post_id);
$season_start = (string) ($season_row['start_date'] ?? '');
$season_end   = (string) ($season_row['end_date']   ?? '');

$weeks = bbj_v2_season_weeks($season_post_id);
$selected_week_id = isset($_GET['week']) ? (int) $_GET['week'] : ((int) ($weeks[0]['id'] ?? 0));
$current_week = null;
foreach ($weeks as $w) {
    if ((int) $w['id'] === $selected_week_id) {
        $current_week = $w;
        break;
    }
}

$msg = isset($_GET['bbj_msg']) ? sanitize_key($_GET['bbj_msg']) : '';
?>

<div class="space-y-4">

    <?php if ($msg === 'week_saved'): ?>
        <div class="rounded-md bg-green-50 border border-green-200 px-4 py-2 text-sm text-green-900">Week saved.</div>
    <?php elseif ($msg === 'week_added'): ?>
        <div class="rounded-md bg-green-50 border border-green-200 px-4 py-2 text-sm text-green-900">New week added.</div>
    <?php endif; ?>

    <!-- Week picker + Add Week button -->
    <div class="flex flex-wrap items-center gap-2 border-b border-stone-200 pb-3">
        <?php if (empty($weeks)): ?>
            <span class="text-stone-500 italic text-sm">No weeks yet.</span>
        <?php else: foreach ($weeks as $w):
            $url = add_query_arg(['week' => $w['id']]);
            $is_active = ((int) $w['id'] === $selected_week_id);
        ?>
            <a href="<?php echo esc_url($url); ?>#weeks"
               class="px-3 py-1 text-xs font-osw uppercase tracking-wider rounded <?php echo $is_active ? 'bg-secondary-500 text-primary-500 dark:bg-secondary-600 dark:text-white' : 'bg-stone-100 text-stone-700 hover:bg-stone-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600'; ?>">
                Week <?php echo (int) $w['week_num']; ?>
                <?php if ((int) $w['evicted_count'] > 0): ?>
                    <span class="ml-1 text-[10px] opacity-70">&middot;E</span>
                <?php endif; ?>
            </a>
        <?php endforeach; endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ml-auto">
            <?php wp_nonce_field('bbj_v2_add_week'); ?>
            <input type="hidden" name="action" value="bbj_v2_add_week">
            <input type="hidden" name="season_post_id" value="<?php echo (int) $season_post_id; ?>">
            <button type="submit"
                    class="px-3 py-1 bg-primary-500 text-white text-xs font-osw uppercase tracking-wider rounded hover:bg-primary-600 transition-colors">
                + Add Week
            </button>
        </form>
    </div>

    <!-- Selected week form -->
    <?php if ($current_week): ?>
        <?php get_template_part('template-parts/admin/partials/week-edit-form', null, [
            'season_post_id' => $season_post_id,
            'week'           => $current_week,
            'season_start'   => $season_start,
            'season_end'     => $season_end,
        ]); ?>
    <?php elseif (!empty($weeks)): ?>
        <p class="text-stone-500 italic text-sm">Select a week above to edit.</p>
    <?php endif; ?>

</div>
