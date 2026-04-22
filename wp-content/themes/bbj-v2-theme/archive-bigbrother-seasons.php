<?php
/**
 * Season Directory Archive
 */
get_header(); ?>

<main class="mx-auto max-w-screen-xl px-4 py-6 flex gap-6">
    <div class="flex-1 min-w-0">
        <h1 class="font-display text-3xl text-primary-500 dark:text-secondary-500 mb-6">All Seasons</h1>

        <?php if (have_posts()) : ?>
            <div class="space-y-4">
                <?php while (have_posts()) : the_post(); ?>
                    <?php
                    global $wpdb;
                    $season = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}bbj_seasons WHERE ID = %d",
                        get_the_ID()
                    ));
                    ?>
                    <a href="<?php the_permalink(); ?>" class="block bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow dark:bg-gray-800">
                        <div class="flex items-center gap-4">
                            <?php if ($season && $season->season_picture) : ?>
                                <?php echo wp_get_attachment_image($season->season_picture, 'profile-picture', false, [
                                    'class' => 'w-16 h-16 rounded-lg object-cover',
                                ]); ?>
                            <?php else : ?>
                                <div class="w-16 h-16 rounded-lg bg-primary-500 flex items-center justify-center text-white font-osw text-xl">
                                    <?php echo $season ? esc_html($season->season_number) : '?'; ?>
                                </div>
                            <?php endif; ?>

                            <div>
                                <h2 class="font-osw text-xl text-primary-500 dark:text-secondary-500">
                                    <?php echo $season ? esc_html($season->full_name) : get_the_title(); ?>
                                </h2>
                                <?php if ($season && $season->start_date) : ?>
                                    <p class="text-sm text-gray-500"><?php echo esc_html($season->start_date); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
            <?php get_template_part('template-parts/components/pagination'); ?>
        <?php else : ?>
            <div class="bg-white rounded-lg shadow p-8 text-center dark:bg-gray-800">
                <p class="text-gray-500">No seasons found.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php get_sidebar(); ?>
</main>

<?php get_footer(); ?>
