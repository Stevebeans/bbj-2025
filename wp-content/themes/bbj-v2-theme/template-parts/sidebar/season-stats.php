<?php
/**
 * Season Stats sidebar card — top 3 HoH, PoV, and nomination counts.
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats = bbj_v2_homepage_season_stats();
$has_any = !empty($stats['hoh']) || !empty($stats['pov']) || !empty($stats['noms']);
if (!$has_any) {
    return;
}

$render_row = static function (array $rows, string $label): void {
    if (empty($rows)) return;
    echo '<div class="mb-4 last:mb-0">';
    echo '<div class="font-osw uppercase tracking-wider text-xs text-gray-500 dark:text-gray-400 mb-2">' . esc_html($label) . '</div>';
    echo '<ul>';
    foreach ($rows as $r) {
        $name  = !empty($r['official_nickname'])
            ? $r['official_nickname']
            : trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        $count = (int) $r['count'];
        echo '<li class="flex items-center justify-between text-sm py-2 border-b border-dashed border-gray-300 dark:border-gray-700"><span class="text-gray-900 dark:text-gray-100">' . esc_html($name) . '</span><span class="font-bold text-primary-500 dark:text-secondary-500">' . $count . '</span></li>';
    }
    echo '</ul>';
    echo '</div>';
};
?>
<section class="bbj-card bbj-season-stats">
    <div class="pb-3 mb-5 border-b" style="border-color:var(--line)">
        <h2 class="font-osw text-lg md:text-xl uppercase tracking-wide text-primary-500 dark:text-secondary-500 m-0">
            <?php esc_html_e('Season Stats', 'bbj-v2-theme'); ?>
        </h2>
    </div>
    <?php
    $render_row($stats['hoh'],  __('Head of Household', 'bbj-v2-theme'));
    $render_row($stats['pov'],  __('Power of Veto', 'bbj-v2-theme'));
    $render_row($stats['noms'], __('Nominated', 'bbj-v2-theme'));
    ?>
</section>
