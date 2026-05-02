<?php
/**
 * Houseboard — HoH / PoV / Noms / Week #.
 * Sidebar variant: single column stack. Used in the right-hand column.
 */

if (!defined('ABSPATH')) {
    exit;
}

$houseboard = function_exists('bbj_get_houseboard') ? bbj_get_houseboard() : [];
$hoh_name   = $houseboard['hoh']['name']  ?? 'TBD';
$pov_name   = $houseboard['pov']['name']  ?? 'TBD';
$noms       = $houseboard['noms']['names'] ?? [];
$week       = isset($houseboard['week']) ? (int) $houseboard['week'] : 0;
?>
<section class="bbj-sidebar-card">
    <h2 class="section-header mb-3"><?php esc_html_e('The House', 'bbj-v2-theme'); ?></h2>

    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
        <li class="py-2 flex items-center justify-between text-sm">
            <span class="font-osw uppercase tracking-wider text-emerald-600 dark:text-emerald-400">HoH</span>
            <span class="font-medium"><?php echo esc_html($hoh_name); ?></span>
        </li>
        <li class="py-2 flex items-center justify-between text-sm">
            <span class="font-osw uppercase tracking-wider text-yellow-600 dark:text-yellow-400">PoV</span>
            <span class="font-medium"><?php echo esc_html($pov_name); ?></span>
        </li>
        <li class="py-2 flex items-start justify-between text-sm gap-3">
            <span class="font-osw uppercase tracking-wider text-red-600 dark:text-red-400 shrink-0">Nominees</span>
            <span class="font-medium text-right">
                <?php if (!empty($noms)) : ?>
                    <?php echo esc_html(implode(', ', (array) $noms)); ?>
                <?php else : ?>
                    <span class="text-gray-400">TBD</span>
                <?php endif; ?>
            </span>
        </li>
        <?php if ($week > 0) : ?>
            <li class="py-2 flex items-center justify-between text-sm">
                <span class="font-osw uppercase tracking-wider text-gray-600 dark:text-gray-400">Week</span>
                <span class="font-medium"><?php echo (int) $week; ?></span>
            </li>
        <?php endif; ?>
    </ul>
</section>
