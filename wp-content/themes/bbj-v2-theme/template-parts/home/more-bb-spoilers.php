<?php
/**
 * Center-column block: "More BB<N> Spoilers" — 3 latest posts in
 * (current-season-slug AND spoilers) categories, with a square ad below.
 */

if (!defined('ABSPATH')) {
    exit;
}

$hero        = bbj_v2_homepage_hero_post();
$exclude     = $hero ? [$hero->ID] : [];
$posts       = bbj_v2_homepage_more_spoilers($exclude);
$season_num  = bbj_v2_current_season_number();
$active      = bbj_v2_is_active_season();
$heading     = $active
    ? sprintf(__('More BB%d Spoilers', 'bbj-v2-theme'), $season_num)
    : sprintf(__('BB%d Recap', 'bbj-v2-theme'), $season_num);
?>
<section id="more-bb-spoilers" class="bbj-more-spoilers">
    <h2 class="section-header mb-4">
        <a href="<?php echo esc_url(home_url('/category/spoilers/')); ?>" class="no-underline hover:text-secondary-500">
            <?php echo esc_html($heading); ?>
        </a>
    </h2>

    <?php if (empty($posts)) : ?>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            <?php esc_html_e('No recent spoilers yet. Check back soon.', 'bbj-v2-theme'); ?>
        </p>
    <?php else : ?>
        <ol class="space-y-3">
            <?php foreach ($posts as $i => $p) : ?>
                <li class="flex gap-3 items-start border-b border-gray-200 dark:border-gray-700 pb-3 last:border-b-0">
                    <span class="font-osw text-primary-500 dark:text-secondary-500 text-lg w-7 shrink-0" aria-hidden="true">
                        <?php echo str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT); ?>
                    </span>
                    <a href="<?php echo esc_url(get_permalink($p->ID)); ?>" class="flex gap-3 group">
                        <?php if (has_post_thumbnail($p->ID)) : ?>
                            <div class="w-16 h-16 shrink-0 overflow-hidden rounded bg-gray-100 dark:bg-gray-800">
                                <?php echo get_the_post_thumbnail($p->ID, 'featured-thumbnail', [
                                    'class'    => 'w-full h-full object-cover',
                                    'loading'  => 'lazy',
                                    'decoding' => 'async',
                                ]); ?>
                            </div>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <h3 class="text-sm font-osw uppercase tracking-wide text-gray-900 dark:text-gray-100 group-hover:text-primary-500 dark:group-hover:text-secondary-500 transition-colors leading-snug">
                                <?php echo esc_html($p->post_title); ?>
                            </h3>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400" data-nosnippet>
                                <time datetime="<?php echo esc_attr(get_the_date('c', $p)); ?>"><?php echo esc_html(get_the_date('M j', $p)); ?></time>
                            </div>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <div class="mt-6">
        <?php get_template_part('template-parts/components/ad-placeholder', null, [
            'slot'        => 'homepage_right_mpu',
            'size'        => '300x250',
            'mobile_size' => '300x250',
            'note'        => __('Homepage · Square / MPU', 'bbj-v2-theme'),
        ]); ?>
    </div>
</section>
