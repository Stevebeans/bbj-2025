<?php
/**
 * Homepage status strip — BB<N> · Day X · % elapsed · Next CBS show · BB Time.
 * Off-season variant: OFF-SEASON · LAST SEASON: BB<N> · BB<N+1> PREMIERES <date>.
 */

if (!defined('ABSPATH')) {
    exit;
}

$status = bbj_v2_homepage_status();
$sep    = '<span class="px-2 text-gray-500" aria-hidden="true">·</span>';
?>
<section class="bbj-status-strip" aria-label="<?php esc_attr_e('Current season status', 'bbj-v2-theme'); ?>">
    <div class="mx-auto max-w-screen-xl px-4 py-2 flex items-center flex-wrap gap-y-1 font-osw uppercase tracking-wider text-xs sm:text-sm text-white">

        <?php if ($status['active'] && $status['season_number'] > 0) : ?>
            <span class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-accent-red animate-pulse" aria-hidden="true"></span>
                <strong class="text-secondary-500">BB<?php echo (int) $status['season_number']; ?></strong>
                <?php if ($status['day_number'] !== null) : ?>
                    <span>· Day <?php echo (int) $status['day_number']; ?></span>
                <?php endif; ?>
            </span>

            <?php if ($status['percent_elapsed'] !== null) : ?>
                <?php echo $sep; ?>
                <span><?php echo (int) $status['percent_elapsed']; ?>% Elapsed</span>
            <?php endif; ?>

            <?php if (!empty($status['next_show'])) : ?>
                <?php echo $sep; ?>
                <span>Next CBS show: <?php echo esc_html($status['next_show']); ?></span>
            <?php endif; ?>

            <?php echo $sep; ?>
            <span data-nosnippet>BB Time <?php echo esc_html(bbj_v2_bb_time()); ?></span>

        <?php else : ?>
            <span class="text-secondary-500 font-bold">Off-season</span>

            <?php if ($status['season_number'] > 0) : ?>
                <?php echo $sep; ?>
                <span>Last season: BB<?php echo (int) $status['season_number']; ?></span>
            <?php endif; ?>

            <?php if (!empty($status['premiere_label'])) : ?>
                <?php echo $sep; ?>
                <span>BB<?php echo (int) $status['season_number'] + 1; ?> premieres <?php echo esc_html($status['premiere_label']); ?></span>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</section>
