<?php
/**
 * More BB<N> Stories — 3×3 grid of current-season posts, excluding the hero
 * and any post already surfaced in the "More BB<N> Spoilers" list.
 */

if (!defined('ABSPATH')) {
    exit;
}

$hero           = bbj_v2_homepage_hero_post();
$spoiler_posts  = bbj_v2_homepage_more_spoilers($hero ? [$hero->ID] : []);
$exclude        = array_merge(
    $hero ? [$hero->ID] : [],
    array_map(static fn($p) => (int) $p->ID, $spoiler_posts)
);
$stories        = bbj_v2_homepage_bb_stories($exclude);
$season_num     = bbj_v2_current_season_number();
if (empty($stories)) {
    return;
}
?>
<section id="more-bb-stories" class="bbj-more-bb-stories">
    <h2 class="section-header mb-4">
        <a href="<?php echo esc_url(home_url('/category/' . bbj_v2_current_season_slug() . '/')); ?>" class="no-underline hover:text-secondary-500">
            <?php printf(esc_html__('More BB%d Stories', 'bbj-v2-theme'), $season_num); ?>
        </a>
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($stories as $p) : ?>
            <article class="v2-primary-container-inner">
                <a href="<?php echo esc_url(get_permalink($p->ID)); ?>" class="block group">
                    <?php if (has_post_thumbnail($p->ID)) : ?>
                        <div class="aspect-[4/3] overflow-hidden bg-gray-100 dark:bg-gray-800">
                            <?php echo get_the_post_thumbnail($p->ID, 'featured-thumbnail', [
                                'class'    => 'w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-300',
                                'loading'  => 'lazy',
                                'decoding' => 'async',
                            ]); ?>
                        </div>
                    <?php endif; ?>
                    <div class="p-4">
                        <h3 class="font-display text-lg leading-snug text-gray-900 dark:text-gray-100 group-hover:text-primary-500 dark:group-hover:text-secondary-500 transition-colors">
                            <?php echo esc_html($p->post_title); ?>
                        </h3>
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 font-osw uppercase tracking-wider" data-nosnippet>
                            <time datetime="<?php echo esc_attr(get_the_date('c', $p)); ?>"><?php echo esc_html(get_the_date('M j', $p)); ?></time>
                        </div>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
