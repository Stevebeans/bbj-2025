<?php
/**
 * Single Feed Update Template
 */
get_header(); ?>

<main class="mx-auto max-w-screen-xl px-4 py-6 flex gap-6">
    <div class="flex-1 min-w-0">
        <?php while (have_posts()) : the_post(); ?>
            <article class="bg-white rounded-lg shadow p-6 md:p-8 dark:bg-gray-800">
                <!-- Meta -->
                <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                    <time datetime="<?php echo get_the_date('c'); ?>">
                        <?php echo get_the_date('F j, Y \a\t g:i A'); ?> PT
                    </time>
                </div>

                <!-- Title -->
                <h1 class="font-display text-3xl text-primary-500 dark:text-secondary-500 mb-4">
                    <?php the_title(); ?>
                </h1>

                <!-- Content -->
                <div class="prose prose-lg max-w-none dark:prose-invert">
                    <?php the_content(); ?>
                </div>

                <!-- Voting Placeholder -->
                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 flex items-center gap-4">
                    <button class="text-gray-400 hover:text-green-500 transition" aria-label="Upvote">&#9650; Upvote</button>
                    <span class="text-gray-500 font-osw">0</span>
                    <button class="text-gray-400 hover:text-red-500 transition" aria-label="Downvote">&#9660; Downvote</button>
                </div>
            </article>

            <!-- Comments -->
            <?php if (comments_open() || get_comments_number()) : ?>
                <div class="mt-6">
                    <?php comments_template(); ?>
                </div>
            <?php endif; ?>

        <?php endwhile; ?>
    </div>

    <?php get_sidebar(); ?>
</main>

<?php get_footer(); ?>
