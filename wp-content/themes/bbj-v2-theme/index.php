<?php
/**
 * Fallback template. Used when no more specific template matches.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<?php get_template_part('template-parts/layout/primary-container', null, ['state' => 'open']); ?>

    <?php if (have_posts()) : ?>
        <div class="space-y-6">
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('pb-6 border-b border-gray-200 dark:border-gray-700 last:border-0'); ?>>
                    <h2 class="section-header mb-2">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h2>
                    <?php the_excerpt(); ?>
                </article>
            <?php endwhile; ?>
        </div>

        <div class="mt-6">
            <?php the_posts_pagination(['mid_size' => 2]); ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e('No content found.', 'bbj-v2-theme'); ?></p>
    <?php endif; ?>

<?php get_template_part('template-parts/layout/primary-container', null, ['state' => 'close']); ?>

<?php get_footer();
