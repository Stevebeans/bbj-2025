<?php
/**
 * Homepage hero post — full-width "Latest Update" card.
 *
 * Header row: h2 "Latest Update". Body: image left, post content right
 * (kicker, h3 post title, excerpt, byline). Page H1 lives in the
 * status-strip above this card.
 */

if (!defined('ABSPATH')) {
    exit;
}

$hero = bbj_v2_homepage_hero_post();
if (!$hero) {
    return;
}

$season_num = bbj_v2_current_season_number();

$thumb_id  = (int) get_post_thumbnail_id($hero->ID);
$alt       = $thumb_id ? (get_post_meta($thumb_id, '_wp_attachment_image_alt', true) ?: $hero->post_title) : $hero->post_title;
$permalink = get_permalink($hero->ID);
$author    = get_the_author_meta('display_name', $hero->post_author);

$cats        = get_the_category($hero->ID);
$primary_cat = !empty($cats) ? $cats[0]->name : '';

$kicker_parts = array_filter([
    $season_num > 0 ? 'BB' . $season_num : '',
    $primary_cat,
]);
$kicker = implode(' · ', $kicker_parts);

$badge_text = $primary_cat !== '' ? $primary_cat : __('Featured', 'bbj-v2-theme');

$excerpt = $hero->post_excerpt !== ''
    ? $hero->post_excerpt
    : wp_trim_words(strip_shortcodes($hero->post_content), 40, '…');
?>
<article class="bbj-card bbj-hero-post">

    <div class="pb-3 mb-5 border-b" style="border-color:var(--line)">
        <h2 class="font-osw text-lg md:text-xl uppercase tracking-wide text-primary-500 dark:text-secondary-500 m-0">
            <?php esc_html_e('Latest Update', 'bbj-v2-theme'); ?>
        </h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <a href="<?php echo esc_url($permalink); ?>" class="block group relative">
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
            <span class="absolute top-3 left-3 inline-block bg-accent-red text-white px-2 py-1 text-[11px] font-osw uppercase tracking-wider">
                <?php echo esc_html($badge_text); ?>
            </span>
        </a>

        <div class="flex flex-col">
            <?php if ($kicker !== '') : ?>
                <div class="flex items-center gap-2 text-accent-red font-osw uppercase tracking-wider text-xs">
                    <span class="w-1.5 h-1.5 bg-accent-red rounded-full inline-block"></span>
                    <span><?php echo esc_html($kicker); ?></span>
                </div>
            <?php endif; ?>

            <h3 class="<?php echo $kicker !== '' ? 'mt-3' : ''; ?> font-display font-bold text-3xl md:text-4xl lg:text-5xl leading-tight text-gray-900 dark:text-gray-100">
                <a href="<?php echo esc_url($permalink); ?>" class="no-underline hover:text-primary-500 dark:hover:text-secondary-500">
                    <?php echo esc_html($hero->post_title); ?>
                </a>
            </h3>

            <?php if ($excerpt !== '') : ?>
                <p class="mt-3 text-gray-700 dark:text-gray-300 leading-relaxed"><?php echo esc_html($excerpt); ?></p>
            <?php endif; ?>

            <div class="mt-4 flex items-center gap-2 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-osw" data-nosnippet>
                <span class="inline-block w-6 h-6 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700 shrink-0">
                    <?php echo get_avatar($hero->post_author, 48, '', '', ['class' => 'w-full h-full object-cover']); ?>
                </span>
                <span class="font-semibold text-gray-700 dark:text-gray-300"><?php echo esc_html($author); ?></span>
                <span>·</span>
                <time datetime="<?php echo esc_attr(get_the_date('c', $hero)); ?>"><?php echo esc_html(get_the_date('F j, Y', $hero)); ?></time>
            </div>
        </div>
    </div>
</article>
