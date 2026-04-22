<?php
/**
 * Single Season Hub Template
 */
get_header();

global $wpdb;
$season = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bbj_seasons WHERE ID = %d",
    get_the_ID()
));
?>

<main class="mx-auto max-w-screen-xl px-4 py-6 flex gap-6">
    <div class="flex-1 min-w-0 space-y-6">
        <?php while (have_posts()) : the_post(); ?>

            <!-- Season Header -->
            <div class="bg-white rounded-lg shadow overflow-hidden dark:bg-gray-800">
                <?php if ($season && $season->season_banner_image) : ?>
                    <div class="w-full h-48 md:h-64 overflow-hidden">
                        <?php echo wp_get_attachment_image($season->season_banner_image, 'player-banner', false, ['class' => 'w-full h-full object-cover']); ?>
                    </div>
                <?php else : ?>
                    <div class="w-full h-32 bg-gradient-to-r from-primary-500 to-primary-400"></div>
                <?php endif; ?>

                <div class="p-6 md:p-8">
                    <h1 class="font-display text-3xl md:text-4xl text-primary-500 dark:text-secondary-500">
                        <?php echo $season ? esc_html($season->full_name) : get_the_title(); ?>
                    </h1>
                    <?php if ($season) : ?>
                        <p class="text-gray-500 dark:text-gray-400 mt-2">
                            <?php if ($season->start_date) echo esc_html($season->start_date); ?>
                            <?php if ($season->end_date) echo ' &mdash; ' . esc_html($season->end_date); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cast Grid Placeholder -->
            <div class="bg-white rounded-lg shadow p-6 md:p-8 dark:bg-gray-800">
                <h2 class="section-header mb-4">Cast</h2>
                <p class="text-gray-500">Player cast grid with status indicators will be displayed here.</p>
            </div>

            <!-- Leaderboards Placeholder -->
            <div class="bg-white rounded-lg shadow p-6 md:p-8 dark:bg-gray-800">
                <h2 class="section-header mb-4">Competition Leaderboards</h2>
                <p class="text-gray-500">HoH and PoV competition leaderboards will appear here.</p>
            </div>

            <!-- Season Content -->
            <?php if (get_the_content()) : ?>
                <div class="bg-white rounded-lg shadow p-6 md:p-8 dark:bg-gray-800">
                    <h2 class="section-header mb-4">Season Overview</h2>
                    <div class="prose max-w-none dark:prose-invert">
                        <?php the_content(); ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endwhile; ?>
    </div>

    <?php get_sidebar(); ?>
</main>

<?php get_footer(); ?>
