<?php
/**
 * More BB<N> Stories — 8 current-season posts in a 3×3 grid with an ad
 * occupying the dead-center cell (position 5 of 9).
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
$stories        = array_slice(bbj_v2_homepage_bb_stories($exclude), 0, 8);
$season_num     = bbj_v2_current_season_number();
$season_id      = (int) get_option('bbj_v2_current_season', 0);
$season_row     = ($season_id > 0 && function_exists('bbj_v2_get_season_by_id'))
    ? bbj_v2_get_season_by_id($season_id) : null;
$season_label   = trim((string) ($season_row['full_name'] ?? ''));
if ($season_label === '' && $season_num > 0) {
    $season_label = sprintf('Big Brother %d', $season_num);
}
$heading = $season_label !== ''
    ? sprintf(__('More %s Stories', 'bbj-v2-theme'), $season_label)
    : __('More Stories', 'bbj-v2-theme');
if (empty($stories)) {
    return;
}

$first_half  = array_slice($stories, 0, 4);
$second_half = array_slice($stories, 4, 4);

$render_card = static function (WP_Post $p): void {
    $author   = get_the_author_meta('display_name', $p->post_author);
    $comments = (int) get_comments_number($p->ID);
    ?>
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
                <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400 font-osw uppercase tracking-wider">
                    <time datetime="<?php echo esc_attr(get_the_date('c', $p)); ?>" data-nosnippet><?php echo esc_html(get_the_date('M j', $p)); ?></time>
                    <?php if ($author !== '') : ?>
                        <span aria-hidden="true">·</span>
                        <span><?php echo esc_html($author); ?></span>
                    <?php endif; ?>
                    <span aria-hidden="true">·</span>
                    <span class="inline-flex items-center gap-1" aria-label="<?php echo esc_attr(sprintf(_n('%d comment', '%d comments', $comments, 'bbj-v2-theme'), $comments)); ?>">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <?php echo (int) $comments; ?>
                    </span>
                </div>
            </div>
        </a>
    </article>
<?php };
?>
<section id="more-bb-stories" class="bbj-card bbj-more-bb-stories">

    <div class="pb-3 mb-5 border-b" style="border-color:var(--line)">
        <h2 class="font-osw text-lg md:text-xl uppercase tracking-wide text-primary-500 dark:text-secondary-500 m-0">
            <a href="<?php echo esc_url(home_url('/category/' . bbj_v2_current_season_slug() . '/')); ?>" class="no-underline hover:text-secondary-500 dark:hover:text-secondary-400">
                <?php echo esc_html($heading); ?>
            </a>
        </h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($first_half as $p) $render_card($p); ?>

        <div class="v2-primary-container-inner flex items-center justify-center p-4">
            <?php get_template_part('template-parts/components/ad-placeholder', null, [
                'slot'        => 'homepage_stories_inline',
                'size'        => '300x250',
                'mobile_size' => '300x250',
                'note'        => __('Homepage · center of stories grid', 'bbj-v2-theme'),
            ]); ?>
        </div>

        <?php foreach ($second_half as $p) $render_card($p); ?>
    </div>
</section>
