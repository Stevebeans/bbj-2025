<?php
/**
 * Search Results Template
 */
get_header(); ?>

<main class="mx-auto max-w-screen-xl px-4 py-6 flex gap-6">
    <div class="flex-1 min-w-0">
        <h1 class="font-display text-3xl text-primary-500 dark:text-secondary-500 mb-6">
            Search Results for: <span class="text-secondary-600"><?php echo esc_html(get_search_query()); ?></span>
        </h1>

        <?php if (have_posts()) : ?>
            <div class="space-y-4">
                <?php while (have_posts()) : the_post(); ?>
                    <?php get_template_part('template-parts/content/post-card'); ?>
                <?php endwhile; ?>
            </div>
            <?php get_template_part('template-parts/components/pagination'); ?>
        <?php else : ?>
            <div class="bg-white rounded-lg shadow p-8 text-center dark:bg-gray-800">
                <p class="text-gray-500 mb-4">No results found. Try a different search term.</p>
                <?php get_search_form(); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php get_sidebar(); ?>
</main>

<?php get_footer(); ?>
