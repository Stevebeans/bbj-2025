<?php
/**
 * Single Player Profile Template
 */
get_header();

global $wpdb;
$player = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bbj_players WHERE ID = %d",
    get_the_ID()
));
?>

<main class="mx-auto max-w-screen-xl px-4 py-6 flex gap-6">
    <div class="flex-1 min-w-0 space-y-6">
        <?php while (have_posts()) : the_post(); ?>

            <!-- Player Header -->
            <div class="bg-white rounded-lg shadow overflow-hidden dark:bg-gray-800">
                <?php if ($player && $player->player_banner) : ?>
                    <div class="w-full h-48 md:h-64 overflow-hidden bg-primary-500">
                        <?php echo wp_get_attachment_image($player->player_banner, 'player-banner', false, ['class' => 'w-full h-full object-cover']); ?>
                    </div>
                <?php else : ?>
                    <div class="w-full h-32 bg-gradient-to-r from-primary-500 to-primary-400"></div>
                <?php endif; ?>

                <div class="p-6 md:p-8 flex flex-col md:flex-row gap-6 items-start">
                    <!-- Profile Picture -->
                    <div class="-mt-20 md:-mt-24 shrink-0">
                        <?php if ($player && $player->profile_picture) : ?>
                            <?php echo wp_get_attachment_image($player->profile_picture, 'bbj_v2_profile_image', false, [
                                'class' => 'w-32 h-32 md:w-40 md:h-40 rounded-full border-4 border-white shadow-lg object-cover',
                            ]); ?>
                        <?php else : ?>
                            <div class="w-32 h-32 md:w-40 md:h-40 rounded-full border-4 border-white shadow-lg bg-gray-200 flex items-center justify-center">
                                <span class="text-gray-400 text-4xl">?</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Player Info -->
                    <div>
                        <h1 class="font-display text-3xl md:text-4xl text-primary-500 dark:text-secondary-500">
                            <?php the_title(); ?>
                        </h1>
                        <?php if ($player) : ?>
                            <p class="text-gray-500 dark:text-gray-400 mt-1">
                                <?php if (!empty($player->occupation)) echo esc_html($player->occupation); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Bio / Content -->
            <div class="bg-white rounded-lg shadow p-6 md:p-8 dark:bg-gray-800">
                <h2 class="section-header mb-4">About</h2>
                <div class="prose max-w-none dark:prose-invert">
                    <?php the_content(); ?>
                </div>
            </div>

            <!-- Season History Placeholder -->
            <div class="bg-white rounded-lg shadow p-6 md:p-8 dark:bg-gray-800">
                <h2 class="section-header mb-4">Season History</h2>
                <p class="text-gray-500">Season appearances and results will be displayed here.</p>
            </div>

            <!-- Competition Stats Placeholder -->
            <div class="bg-white rounded-lg shadow p-6 md:p-8 dark:bg-gray-800">
                <h2 class="section-header mb-4">Competition Stats</h2>
                <p class="text-gray-500">HoH wins, PoV wins, nominations, and other stats will appear here.</p>
            </div>

            <!-- Social Links -->
            <?php if ($player) : ?>
                <?php
                $socials = array_filter([
                    'Facebook'  => $player->facebook ?? '',
                    'Instagram' => $player->instagram ?? '',
                    'Twitter'   => $player->twitter ?? '',
                    'TikTok'    => $player->tiktok ?? '',
                ]);
                ?>
                <?php if (!empty($socials)) : ?>
                    <div class="bg-white rounded-lg shadow p-6 md:p-8 dark:bg-gray-800">
                        <h2 class="section-header mb-4">Social Media</h2>
                        <div class="flex flex-wrap gap-3">
                            <?php foreach ($socials as $platform => $url) : ?>
                                <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"
                                   class="btn-primary text-sm">
                                    <?php echo esc_html($platform); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        <?php endwhile; ?>
    </div>

    <?php get_sidebar(); ?>
</main>

<?php get_footer(); ?>
