<?php
/**
 * Homepage hero post — left column of the 3-col grid.
 */

if (!defined('ABSPATH')) {
    exit;
}

$hero = bbj_v2_homepage_hero_post();
if (!$hero) {
    return;
}

$thumb_id = (int) get_post_thumbnail_id($hero->ID);
$alt      = $thumb_id ? (get_post_meta($thumb_id, '_wp_attachment_image_alt', true) ?: $hero->post_title) : $hero->post_title;
?>
<article class="bbj-hero-post">
    <a href="<?php echo esc_url(get_permalink($hero->ID)); ?>" class="block group">
        <?php if ($thumb_id) : ?>
            <div class="aspect-[4/3] overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                <?php echo wp_get_attachment_image(
                    $thumb_id,
                    'bbj_v2_index_hero',
                    false,
                    [
                        'alt'           => esc_attr($alt),
                        'class'         => 'w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-300',
                        'fetchpriority' => 'high',
                        'loading'       => 'eager',
                        'decoding'      => 'async',
                    ]
                ); ?>
            </div>
        <?php endif; ?>
        <h1 class="mt-4 font-display text-3xl md:text-4xl lg:text-5xl leading-tight text-primary-500 dark:text-secondary-500 group-hover:text-primary-600 dark:group-hover:text-secondary-400 transition-colors">
            <?php echo esc_html($hero->post_title); ?>
        </h1>
    </a>

    <?php
    $excerpt = $hero->post_excerpt !== ''
        ? $hero->post_excerpt
        : wp_trim_words(strip_shortcodes($hero->post_content), 40, '…');
    ?>
    <?php if ($excerpt !== '') : ?>
        <p class="mt-3 text-gray-700 dark:text-gray-300 leading-relaxed"><?php echo esc_html($excerpt); ?></p>
    <?php endif; ?>

    <div class="mt-3 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-osw" data-nosnippet>
        <time datetime="<?php echo esc_attr(get_the_date('c', $hero)); ?>"><?php echo esc_html(get_the_date('F j, Y', $hero)); ?></time>
        <span class="mx-1">·</span>
        <?php echo esc_html(get_the_author_meta('display_name', $hero->post_author)); ?>
    </div>
</article>
