<?php
/**
 * Homepage page-header strip — H1 (season spoilers) + slow ticker of the
 * three latest feed-update headlines. The H1 anchors the page name so
 * cards below can use h2 section-kickers without losing hierarchy.
 */

if (!defined('ABSPATH')) {
    exit;
}

$season_id    = (int) get_option('bbj_v2_current_season', 0);
$season_num   = bbj_v2_current_season_number();
$season_row   = ($season_id > 0 && function_exists('bbj_v2_get_season_by_id'))
    ? bbj_v2_get_season_by_id($season_id) : null;
$season_label = trim((string) ($season_row['full_name'] ?? ''));
if ($season_label === '' && $season_num > 0) {
    $season_label = sprintf('Big Brother %d', $season_num);
}
$heading = $season_label !== '' ? $season_label . ' Spoilers' : __('Spoilers', 'bbj-v2-theme');

$ticker_items = function_exists('bbj_v2_homepage_latest_feeds')
    ? bbj_v2_homepage_latest_feeds(3)
    : [];
?>
<section class="bbj-status-strip" aria-label="<?php esc_attr_e('Page header', 'bbj-v2-theme'); ?>">
    <div class="mx-auto max-w-screen-xl px-4 py-3 flex items-center gap-6">

        <h1 class="font-display text-xl md:text-2xl font-bold text-white shrink-0 m-0 leading-none">
            <a href="<?php echo esc_url(home_url('/category/spoilers/')); ?>" class="no-underline text-white hover:text-secondary-500">
                <?php echo esc_html($heading); ?>
            </a>
        </h1>

        <?php if (!empty($ticker_items)) : ?>
            <div class="bbj-ticker hidden md:block" aria-label="<?php esc_attr_e('Latest feed updates', 'bbj-v2-theme'); ?>">
                <div class="bbj-ticker-track font-osw uppercase tracking-wider text-xs text-gray-300">
                    <?php
                    // Render the headline list TWICE so the marquee loops seamlessly.
                    for ($pass = 0; $pass < 2; $pass++) :
                        foreach ($ticker_items as $item) :
                            $post = $item['post'] ?? null;
                            if (!$post) continue;
                    ?>
                        <a href="<?php echo esc_url(get_permalink($post->ID)); ?>"
                           class="inline-flex items-center gap-2 px-4 no-underline text-gray-200 hover:text-secondary-500"
                           <?php echo $pass === 1 ? 'aria-hidden="true" tabindex="-1"' : ''; ?>>
                            <span class="w-1.5 h-1.5 rounded-full bg-accent-red shrink-0" aria-hidden="true"></span>
                            <span><?php echo esc_html(get_the_title($post)); ?></span>
                        </a>
                    <?php
                        endforeach;
                    endfor;
                    ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>
