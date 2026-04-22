<?php
/**
 * Player Directory Archive
 */
get_header(); ?>

<main class="mx-auto max-w-screen-xl px-4 py-6 flex gap-6">
    <div class="flex-1 min-w-0">
        <h1 class="font-display text-3xl text-primary-500 dark:text-secondary-500 mb-6">Player Directory</h1>

        <!-- Filter Placeholder -->
        <div class="bg-white rounded-lg shadow p-4 mb-6 dark:bg-gray-800">
            <div class="flex flex-wrap gap-3 items-center">
                <span class="text-sm text-gray-500 font-osw uppercase">Filters:</span>
                <span class="text-sm text-gray-400">Season &bull; Gender &bull; Status — coming soon</span>
            </div>
        </div>

        <?php if (have_posts()) : ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php while (have_posts()) : the_post(); ?>
                    <?php get_template_part('template-parts/content/player-card'); ?>
                <?php endwhile; ?>
            </div>
            <?php get_template_part('template-parts/components/pagination'); ?>
        <?php else : ?>
            <div class="bg-white rounded-lg shadow p-8 text-center dark:bg-gray-800">
                <p class="text-gray-500">No players found.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php get_sidebar(); ?>
</main>

<?php get_footer(); ?>
