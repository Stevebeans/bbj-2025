<?php
global $wpdb;
$player = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bbj_players WHERE ID = %d",
    get_the_ID()
));
?>
<a href="<?php the_permalink(); ?>" class="block bg-white rounded-lg shadow hover:shadow-md transition-shadow overflow-hidden dark:bg-gray-800">
    <?php if ($player && $player->profile_picture) : ?>
        <div class="aspect-square overflow-hidden bg-gray-100">
            <?php echo wp_get_attachment_image($player->profile_picture, 'bbj_v2_profile_image', false, [
                'class' => 'w-full h-full object-cover',
                'loading' => 'lazy',
            ]); ?>
        </div>
    <?php else : ?>
        <div class="aspect-square bg-gray-200 flex items-center justify-center">
            <span class="text-gray-400 text-3xl">?</span>
        </div>
    <?php endif; ?>

    <div class="p-3">
        <h3 class="font-osw text-base text-primary-500 dark:text-secondary-500 line-clamp-1">
            <?php the_title(); ?>
        </h3>
        <?php if ($player && !empty($player->occupation)) : ?>
            <p class="text-xs text-gray-500 line-clamp-1 mt-0.5">
                <?php echo esc_html($player->occupation); ?>
            </p>
        <?php endif; ?>
    </div>
</a>
