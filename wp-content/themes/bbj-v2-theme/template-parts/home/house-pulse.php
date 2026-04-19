<?php
/**
 * House Pulse — server-rendered 8-hour bar chart of live-feed-updates/hour.
 * Hidden off-season.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!bbj_v2_is_active_season()) {
    return;
}

$buckets = bbj_v2_homepage_house_pulse(8);
$max     = max(array_map(static fn($b) => (int) $b['count'], $buckets) ?: [0]);
$total   = array_sum(array_map(static fn($b) => (int) $b['count'], $buckets));
?>
<section id="house-pulse" class="bbj-house-pulse" aria-label="<?php esc_attr_e('Feed activity last 8 hours', 'bbj-v2-theme'); ?>">
    <div class="flex items-baseline justify-between mb-2">
        <h2 class="section-header"><?php esc_html_e('House Pulse', 'bbj-v2-theme'); ?></h2>
        <span class="text-xs text-gray-500 dark:text-gray-400 font-osw uppercase tracking-wider">
            <?php esc_html_e('Updates/hr · last 8 hours', 'bbj-v2-theme'); ?>
        </span>
    </div>

    <?php if ($total === 0) : ?>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            <?php esc_html_e('Quiet house · no updates in the last 8 hours.', 'bbj-v2-theme'); ?>
        </p>
    <?php else : ?>
        <div class="flex gap-1 items-end h-12" role="img" aria-label="<?php esc_attr_e('Updates per hour, last 8 hours', 'bbj-v2-theme'); ?>">
            <?php foreach ($buckets as $b) :
                $count = (int) $b['count'];
                $ratio = $max > 0 ? $count / $max : 0;
                if ($ratio === 0)        { $color = 'bg-gray-200 dark:bg-gray-700'; }
                elseif ($ratio <= 0.2)   { $color = 'bg-amber-200'; }
                elseif ($ratio <= 0.5)   { $color = 'bg-amber-400'; }
                elseif ($ratio <= 0.8)   { $color = 'bg-red-400'; }
                else                      { $color = 'bg-red-600'; }
            ?>
                <div class="flex-1 <?php echo esc_attr($color); ?> rounded-sm" style="height: <?php echo max(10, (int) round($ratio * 100)); ?>%;">
                    <span class="sr-only"><?php echo (int) $count; ?> updates at <?php echo esc_html($b['label']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="flex gap-1 mt-1 text-[10px] text-gray-500 dark:text-gray-400 font-osw uppercase tracking-wider">
            <?php foreach ($buckets as $i => $b) : ?>
                <div class="flex-1 text-center"><?php echo ($i % 2 === 0) ? esc_html($b['label']) : '&nbsp;'; ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
