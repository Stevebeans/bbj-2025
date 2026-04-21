<?php
/**
 * Admin shell — single season row in the seasons list.
 * Receives $args['season'] — a wp_bbj_seasons row (object).
 * Receives $args['current_season_id'] — int, the active season id.
 */

if (!defined('ABSPATH')) {
    exit;
}

$season            = $args['season'];
$current_season_id = (int) ($args['current_season_id'] ?? 0);

$season_id   = (int) $season->id;
$is_current  = $season_id === $current_season_id;
$full_name   = (string) ($season->full_name ?? '');
$abbrev      = (string) ($season->abbreviation ?? '');
$start_date  = (string) ($season->start_date ?? '');
$end_date    = (string) ($season->end_date ?? '');
$season_num  = (string) ($season->season_number ?? '');
$winner_id   = (int) ($season->season_winner ?? 0);

// Compute status
$today_ts = strtotime(current_time('Y-m-d'));
$start_ts = $start_date ? strtotime($start_date) : 0;
$end_ts   = $end_date   ? strtotime($end_date)   : 0;

if ($full_name === '') {
    $status = ['label' => 'Draft',     'classes' => 'bg-red-100 text-red-700 border-red-200'];
} elseif ($is_current) {
    $status = ['label' => 'Current',   'classes' => 'bg-yellow-100 text-yellow-900 border-yellow-200'];
} elseif ($start_ts && $start_ts > $today_ts) {
    $status = ['label' => 'Upcoming',  'classes' => 'bg-blue-100 text-blue-700 border-blue-200'];
} elseif ($end_ts && $end_ts < $today_ts) {
    $status = ['label' => 'Completed', 'classes' => 'bg-stone-100 text-stone-600 border-stone-200'];
} else {
    $status = ['label' => 'Active',    'classes' => 'bg-green-100 text-green-700 border-green-200'];
}

// Winner name
$winner_name = $winner_id ? (get_the_title($winner_id) ?: '—') : '—';

// Dates — "Jul 17 – Oct 13, 2024" or "Jul 10 – TBD" or "—"
$date_display = '—';
if ($start_ts && $end_ts) {
    $date_display = date_i18n('M j', $start_ts) . ' – ' . date_i18n('M j, Y', $end_ts);
} elseif ($start_ts) {
    $date_display = date_i18n('M j, Y', $start_ts) . ' – TBD';
}

$edit_url = esc_url(add_query_arg(['tab' => 'seasons', 'edit' => $season_id], home_url('/admin/')));

// Current-season row gets a yellow left-border accent
$row_classes = 'group border-b border-stone-200 dark:border-slate-800 hover:bg-stone-50 dark:hover:bg-slate-800/40 transition-colors';
if ($is_current) {
    $row_classes .= ' border-l-4 border-l-secondary-500';
}
?>

<tr class="<?php echo esc_attr($row_classes); ?>">
    <td class="px-4 py-3">
        <a href="<?php echo $edit_url; ?>" class="font-semibold text-primary-500 hover:text-primary-600 dark:text-secondary-500">
            <?php echo esc_html($full_name !== '' ? $full_name : '(Untitled draft)'); ?>
        </a>
        <?php if ($abbrev !== ''): ?>
            <span class="ml-2 inline-block px-1.5 py-0.5 text-xs font-mono bg-stone-100 text-stone-600 border border-stone-200 dark:bg-slate-800 dark:text-slate-400">
                <?php echo esc_html($abbrev); ?>
            </span>
        <?php endif; ?>
    </td>
    <td class="px-4 py-3 text-stone-600 dark:text-slate-400">
        <?php echo esc_html($season_num); ?>
    </td>
    <td class="px-4 py-3 text-stone-600 dark:text-slate-400">
        <?php echo esc_html($date_display); ?>
    </td>
    <td class="px-4 py-3 text-stone-600 dark:text-slate-400">
        <?php echo esc_html($winner_name); ?>
    </td>
    <td class="px-4 py-3">
        <span class="inline-block px-2 py-0.5 text-xs font-semibold border <?php echo esc_attr($status['classes']); ?>">
            <?php echo esc_html($status['label']); ?>
        </span>
    </td>
</tr>
