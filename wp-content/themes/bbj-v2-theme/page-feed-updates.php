<?php
/**
 * Template Name: Feed Updates
 *
 * Feed Updates Hub Page
 */
get_header(); ?>

<main class="mx-auto max-w-screen-xl px-4 py-6 flex gap-6">
    <div class="flex-1 min-w-0">
        <h1 class="font-display text-3xl text-primary-500 dark:text-secondary-500 mb-6">Live Feed Updates</h1>

        <!-- Filter Controls Placeholder -->
        <div class="bg-white rounded-lg shadow p-4 mb-6 dark:bg-gray-800">
            <div class="flex flex-wrap gap-3 items-center">
                <span class="text-sm text-gray-500 font-osw uppercase">Filter by:</span>
                <span class="text-sm text-gray-400">Today &bull; Yesterday &bull; This Week &bull; Search — coming soon</span>
            </div>
        </div>

        <!-- Feed Updates List -->
        <?php
        $feed_updates = new WP_Query([
            'post_type'      => 'live-feed-updates',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        ?>

        <?php if ($feed_updates->have_posts()) : ?>
            <div class="space-y-4">
                <?php while ($feed_updates->have_posts()) : $feed_updates->the_post(); ?>
                    <?php get_template_part('template-parts/content/feed-update-card'); ?>
                <?php endwhile; ?>
            </div>
            <?php wp_reset_postdata(); ?>
        <?php else : ?>
            <div class="bg-white rounded-lg shadow p-8 text-center dark:bg-gray-800">
                <p class="text-gray-500">No feed updates yet. Check back during the Big Brother season!</p>
            </div>
        <?php endif; ?>
    </div>

    <?php get_sidebar(); ?>
</main>

<?php get_footer(); ?>
