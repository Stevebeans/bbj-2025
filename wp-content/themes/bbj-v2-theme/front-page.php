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

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <div class="lg:col-span-8 space-y-8">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <?php get_template_part('template-parts/home/hero-post'); ?>
                </div>
                <div>
                    <?php get_template_part('template-parts/home/more-bb-spoilers'); ?>
                </div>
            </div>

            <div class="lg:hidden">
                <?php get_template_part('template-parts/home/houseboard'); ?>
            </div>

            <?php get_template_part('template-parts/home/house-pulse'); ?>

            <?php get_template_part('template-parts/home/latest-feeds'); ?>

            <?php get_template_part('template-parts/components/ad-placeholder', null, [
                'slot'        => 'homepage_leaderboard',
                'size'        => '728x90',
                'mobile_size' => '320x50',
                'note'        => __('Homepage · between feeds and stories', 'bbj-v2-theme'),
            ]); ?>

            <?php get_template_part('template-parts/home/more-bb-stories'); ?>

        </div>

        <aside class="lg:col-span-4 space-y-6" aria-label="<?php esc_attr_e('Sidebar', 'bbj-v2-theme'); ?>">
            <div class="hidden lg:block">
                <?php get_template_part('template-parts/home/houseboard'); ?>
            </div>
            <?php get_template_part('template-parts/sidebar/season-stats'); ?>
            <?php get_template_part('template-parts/sidebar/recent-comments'); ?>
            <?php get_template_part('template-parts/sidebar/sticky-ad'); ?>
            <?php get_template_part('template-parts/sidebar/paramount-plus'); ?>
            <?php get_template_part('template-parts/sidebar/socials'); ?>
            <?php get_template_part('template-parts/sidebar/newsletter'); ?>
        </aside>

    </div>

</main>

<?php get_footer(); ?>
