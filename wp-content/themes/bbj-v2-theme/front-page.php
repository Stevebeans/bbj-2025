<?php
/**
 * Homepage template — 3-column editorial grid.
 * Mobile: hero → houseboard (pulled up) → more spoilers → square ad → pulse
 *         → latest feeds → leaderboard ad → bb stories → rest of sidebar.
 */

get_header();

$hero = bbj_v2_homepage_hero_post();
?>

<?php get_template_part('template-parts/home/status-strip'); ?>

<main id="site-content" class="mx-auto max-w-screen-xl px-4 py-6" role="main">

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">

        <div class="space-y-8 min-w-0">

            <?php get_template_part('template-parts/home/hero-post'); ?>

            <?php get_template_part('template-parts/home/the-house-strip'); ?>

            <?php get_template_part('template-parts/home/house-pulse'); ?>

            <?php get_template_part('template-parts/components/ad-placeholder', null, [
                'slot'        => 'homepage_above_feeds',
                'size'        => '728x90',
                'mobile_size' => '300x250',
                'note'        => __('Homepage · between house bar and feeds', 'bbj-v2-theme'),
            ]); ?>

            <?php get_template_part('template-parts/home/latest-feeds'); ?>

            <?php get_template_part('template-parts/components/ad-placeholder', null, [
                'slot'        => 'homepage_leaderboard',
                'size'        => '728x90',
                'mobile_size' => '320x50',
                'note'        => __('Homepage · between feeds and stories', 'bbj-v2-theme'),
            ]); ?>

            <?php get_template_part('template-parts/home/more-bb-stories'); ?>

        </div>

        <aside class="space-y-6" aria-label="<?php esc_attr_e('Sidebar', 'bbj-v2-theme'); ?>">
            <?php get_template_part('template-parts/sidebar/paramount-plus'); ?>
            <?php get_template_part('template-parts/sidebar/season-stats'); ?>
            <?php get_template_part('template-parts/sidebar/newsletter'); ?>
            <?php get_template_part('template-parts/sidebar/recent-comments'); ?>
            <?php get_template_part('template-parts/sidebar/sticky-ad'); ?>
        </aside>

    </div>

</main>

<?php get_footer(); ?>
