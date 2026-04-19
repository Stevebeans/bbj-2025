<?php
/**
 * Hero Post Section
 */
$hero_query = new WP_Query([
    'post_type'      => 'post',
    'posts_per_page' => 1,
    'meta_key'       => '_is_hero_post',
    'meta_value'     => '1',
]);

// Fallback to latest post if no hero is set
if (!$hero_query->have_posts()) {
    $hero_query = new WP_Query([
        'post_type'      => 'post',
        'posts_per_page' => 1,
    ]);
}
?>

<?php if ($hero_query->have_posts()) : ?>
    <?php while ($hero_query->have_posts()) : $hero_query->the_post(); ?>
        <?php get_template_part('template-parts/content/post-hero'); ?>
    <?php endwhile; ?>
    <?php wp_reset_postdata(); ?>
<?php endif; ?>
