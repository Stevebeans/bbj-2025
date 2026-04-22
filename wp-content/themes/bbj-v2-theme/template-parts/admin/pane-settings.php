<?php
/**
 * Admin shell — Settings pane.
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_season_id = (int) get_option('bbj_v2_current_season', 0);
$seasons           = function_exists('bbj_v2_get_seasons')
    ? bbj_v2_get_seasons('start_date', 'DESC')
    : [];

$current_season = $current_season_id > 0 && function_exists('bbj_v2_get_season_by_id')
    ? bbj_v2_get_season_by_id($current_season_id)
    : null;

// Extract display values for the info card
$display_name  = '';
$display_abbr  = '';
$display_dates = '';
$edit_url      = '';

if (is_array($current_season)) {
    $display_name = (string) ($current_season['full_name'] ?? '');
    $display_abbr = (string) ($current_season['abbreviation'] ?? '');
    $start_ts     = !empty($current_season['start_date']) ? strtotime($current_season['start_date']) : 0;
    $end_ts       = !empty($current_season['end_date'])   ? strtotime($current_season['end_date'])   : 0;

    if ($start_ts && $end_ts) {
        $display_dates = date_i18n('M j', $start_ts) . ' – ' . date_i18n('M j, Y', $end_ts);
    } elseif ($start_ts) {
        $display_dates = date_i18n('M j, Y', $start_ts) . ' – TBD';
    }

    $edit_url = esc_url(
        add_query_arg(
            ['tab' => 'seasons', 'edit' => $current_season_id],
            home_url('/admin/')
        ) . '#spoiler'
    );
}

$saved = !empty($_GET['updated']);
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">

    <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500 mb-2">
        Settings
    </h1>
    <p class="text-sm text-stone-600 dark:text-slate-400 mb-6">
        Site-wide configuration for Big Brother Junkies.
    </p>

    <?php if ($saved): ?>
        <div class="mb-4 p-3 bg-green-50 text-green-800 border border-green-200 dark:bg-green-900/20 dark:text-green-200 dark:border-green-800"
             data-bbj-autodismiss="3000">
            Current season updated.
        </div>
    <?php endif; ?>

    <!-- Current season info card -->
    <h2 class="font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500 mb-2">
        Current Season
    </h2>
    <div class="p-4 mb-6 bg-stone-50 dark:bg-slate-900/40 border border-stone-200 dark:border-slate-700">
        <?php if (is_array($current_season)): ?>
            <div class="flex items-center gap-2 mb-1">
                <span class="font-semibold text-stone-800 dark:text-slate-200">
                    <?php echo esc_html($display_name !== '' ? $display_name : '(Untitled season)'); ?>
                </span>
                <?php if ($display_abbr !== ''): ?>
                    <span class="px-1.5 py-0.5 text-xs font-mono bg-stone-100 text-stone-600 border border-stone-200 dark:bg-slate-800 dark:text-slate-400">
                        <?php echo esc_html($display_abbr); ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($display_dates !== ''): ?>
                <div class="text-sm text-stone-600 dark:text-slate-400 mb-3">
                    <?php echo esc_html($display_dates); ?>
                </div>
            <?php endif; ?>
            <?php if ($edit_url !== ''): ?>
                <a href="<?php echo $edit_url; ?>"
                   class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-500 hover:bg-primary-600 text-white transition-colors">
                    Edit spoiler bar →
                </a>
            <?php endif; ?>
        <?php elseif ($current_season_id > 0): ?>
            <p class="text-stone-600 dark:text-slate-400">
                (Season id <?php echo (int) $current_season_id; ?> not found — pick a valid season below.)
            </p>
        <?php else: ?>
            <p class="text-stone-600 dark:text-slate-400">No current season selected yet.</p>
        <?php endif; ?>
    </div>

    <!-- Change current season form -->
    <h2 class="font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500 mb-2">
        Change Current Season
    </h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="flex items-center gap-3">
        <?php wp_nonce_field('bbj_v2_set_current_season_action', 'bbj_v2_set_current_season_nonce'); ?>
        <input type="hidden" name="action" value="bbj_v2_set_current_season">

        <select name="bbj_v2_season"
                class="px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm min-w-[220px]"
                <?php echo empty($seasons) ? 'disabled' : ''; ?>>
            <?php if (empty($seasons)): ?>
                <option value="">No seasons available</option>
            <?php else: ?>
                <?php foreach ($seasons as $s):
                    $row = (object) $s; // ARRAY_A → object for consistent access
                    $sid = (int) ($row->id ?? 0);
                    $nm  = (string) ($row->full_name ?? '');
                    if ($sid <= 0) continue;
                ?>
                    <option value="<?php echo esc_attr($sid); ?>"
                            <?php selected($sid === $current_season_id); ?>>
                        <?php echo esc_html($nm !== '' ? $nm : '(Untitled draft)'); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>

        <button type="submit"
                class="px-5 py-2 bg-primary-500 hover:bg-primary-600 text-white font-semibold text-sm transition-colors"
                <?php echo empty($seasons) ? 'disabled' : ''; ?>>
            Save
        </button>
    </form>

    <!-- Tiny JS: auto-dismiss the success notice -->
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
