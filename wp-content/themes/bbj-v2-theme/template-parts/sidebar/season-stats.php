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
    echo '<div class="mb-3 last:mb-0">';
    echo '<div class="font-osw uppercase tracking-wider text-xs text-gray-600 dark:text-gray-400 mb-2">' . esc_html($label) . '</div>';
    echo '<ul class="space-y-1">';
    foreach ($rows as $r) {
        $name  = !empty($r['official_nickname'])
            ? $r['official_nickname']
            : trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        $count = (int) $r['count'];
        echo '<li class="flex items-center justify-between text-sm"><span>' . esc_html($name) . '</span><span class="font-bold text-primary-500 dark:text-secondary-500">' . $count . '</span></li>';
    }
    echo '</ul>';
    echo '</div>';
};
?>
<section class="bbj-sidebar-card">
    <h2 class="section-header mb-3"><?php esc_html_e('Season Stats', 'bbj-v2-theme'); ?></h2>
    <?php
    $render_row($stats['hoh'],  __('Top HoH', 'bbj-v2-theme'));
    $render_row($stats['pov'],  __('Top PoV', 'bbj-v2-theme'));
    $render_row($stats['noms'], __('Most Nominated', 'bbj-v2-theme'));
    ?>
</section>
