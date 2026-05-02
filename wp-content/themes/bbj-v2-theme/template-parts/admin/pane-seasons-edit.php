<?php
/**
 * Admin shell — Seasons pane (edit view).
 * Receives $args['season_id'] — int, the wp_bbj_seasons.id to edit.
 */

if (!defined('ABSPATH')) {
    exit;
}

$season_id = isset($args['season_id']) ? (int) $args['season_id'] : 0;
$season    = $season_id > 0 ? bbj_v2_get_season_by_id($season_id) : null;

if (!$season) {
    wp_safe_redirect(add_query_arg(
        ['tab' => 'seasons', 'error' => 'not_found'],
        home_url('/admin/')
    ));
    exit;
}

$season = (object) $season;
// bbj_v2_get_seasons() returns ARRAY_A rows — cast each to object for consistent property access.
// Sort by start_date DESC since season_number is VARCHAR and sorts lexicographically.
$all_seasons = array_map(function($r) { return (object) $r; }, bbj_v2_get_seasons('start_date', 'DESC'));
$full_name   = (string) ($season->full_name ?? '');
$display_name = $full_name !== '' ? $full_name : 'New Season';
$post_id     = (int) ($season->post_id ?? 0);
$view_url    = $post_id ? get_permalink($post_id) : '';

$notices = [];
if (!empty($_GET['created'])) {
    $notices[] = ['tone' => 'info', 'text' => 'Season created. Fill in the details below and save.'];
}
if (!empty($_GET['updated'])) {
    $notices[] = ['tone' => 'success', 'text' => 'Season saved.'];
}
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">

    <!-- Breadcrumb + view link -->
    <div class="flex items-start justify-between gap-4 mb-4">
        <div>
            <nav class="text-sm text-stone-500 dark:text-slate-500 mb-1">
                <a href="<?php echo esc_url(add_query_arg('tab', 'seasons', home_url('/admin/'))); ?>" class="hover:text-primary-500">Seasons</a>
                <span class="mx-1">›</span>
                <span>Edit <?php echo esc_html($display_name); ?></span>
            </nav>
            <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500">
                Edit <?php echo esc_html($display_name); ?>
            </h1>
        </div>
        <?php if ($view_url && $full_name !== ''): ?>
            <a href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-3 py-1.5 text-sm text-stone-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-stone-200 dark:border-slate-700 hover:bg-stone-50 dark:hover:bg-slate-700 transition-colors">
                View season ↗
            </a>
        <?php endif; ?>
    </div>

    <!-- Notices -->
    <?php foreach ($notices as $notice):
        $tone_classes = $notice['tone'] === 'success'
            ? 'bg-green-50 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-200 dark:border-green-800'
            : 'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-900/20 dark:text-blue-200 dark:border-blue-800';
    ?>
        <div class="mb-4 p-3 border <?php echo esc_attr($tone_classes); ?>"
             data-bbj-autodismiss="<?php echo $notice['tone'] === 'success' ? '3000' : ''; ?>">
            <?php echo esc_html($notice['text']); ?>
        </div>
    <?php endforeach; ?>

    <!-- Season Switcher -->
    <div class="flex items-center gap-2 mb-6 text-sm">
        <label for="bbj-season-switcher" class="text-stone-600 dark:text-slate-400">Switch to:</label>
        <select id="bbj-season-switcher"
                onchange="if (this.value) { window.location = this.value; }"
                class="px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
            <?php foreach ($all_seasons as $s):
                $opt_url = esc_url(add_query_arg(
                    ['tab' => 'seasons', 'edit' => (int) $s->id],
                    home_url('/admin/')
                ));
                $opt_name = $s->full_name !== '' ? $s->full_name : '(Untitled draft)';
                $selected = ((int) $s->id === $season_id) ? 'selected' : '';
            ?>
                <option value="<?php echo $opt_url; ?>" <?php echo $selected; ?>>
                    <?php echo esc_html($opt_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Tab nav + bodies -->
    <?php get_template_part('template-parts/admin/partials/seasons-edit-tabs', null, [
        'season'    => $season,
        'season_id' => $season_id,
    ]); ?>

    <!-- Tiny JS: auto-dismiss success notices -->
    <script>
    (function () {
        document.querySelectorAll('[data-bbj-autodismiss]').forEach(function (el) {
            var delay = parseInt(el.getAttribute('data-bbj-autodismiss'), 10);
            if (!delay) return;
            setTimeout(function () {
                el.style.transition = 'opacity 0.3s';
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 300);
            }, delay);
        });
    })();
    </script>

</section>
