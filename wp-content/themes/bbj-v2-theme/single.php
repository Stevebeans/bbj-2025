<?php
/**
 * Single post template — editorial layout (Vox / Ringer style).
 * Pages use page.php which delegates the body to the same typography wrapper.
 */

get_header();

while (have_posts()) : the_post();
    $post_id   = get_the_ID();
    $author_id = (int) get_the_author_meta('ID');
    $categories = get_the_category();
    $word_count = str_word_count(wp_strip_all_tags(strip_shortcodes(get_the_content())));
    $read_min   = max(1, (int) round($word_count / 200));
    $comments   = (int) get_comments_number();
    $thumb_id   = (int) get_post_thumbnail_id($post_id);
    $permalink  = get_permalink();
    $share      = rawurlencode($permalink);
    $share_text = rawurlencode(get_the_title());
?>

<article class="bbj-single">

    <header class="mx-auto max-w-screen-xl px-4 pt-8 pb-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-8">

                <?php if (!empty($categories)) : ?>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mb-4 text-xs font-osw uppercase tracking-wider text-accent-red">
                        <span class="inline-flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-accent-red" aria-hidden="true"></span>
                            <?php foreach ($categories as $i => $cat) : ?>
                                <?php if ($i > 0) : ?><span class="text-gray-400 dark:text-gray-500" aria-hidden="true">·</span><?php endif; ?>
                                <a href="<?php echo esc_url(get_category_link($cat->term_id)); ?>" class="hover:text-accent-red/80"><?php echo esc_html($cat->name); ?></a>
                            <?php endforeach; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl leading-[1.05] font-bold text-gray-900 dark:text-gray-100 mb-4">
                    <?php the_title(); ?>
                </h1>

                <?php if (has_excerpt()) : ?>
                    <p class="text-lg md:text-xl text-gray-600 dark:text-gray-400 leading-relaxed mb-6">
                        <?php echo esc_html(get_the_excerpt()); ?>
                    </p>
                <?php endif; ?>

                <div class="flex items-center justify-between gap-4 flex-wrap border-t border-b border-gray-200 dark:border-gray-700 py-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <?php echo get_avatar($author_id, 44, '', '', ['class' => 'rounded-full shrink-0']); ?>
                        <div class="min-w-0">
                            <div class="font-osw uppercase tracking-wider text-sm text-gray-900 dark:text-gray-100 truncate">
                                <?php echo esc_html(get_the_author_meta('display_name', $author_id)); ?>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 font-osw uppercase tracking-wider flex flex-wrap gap-x-2" data-nosnippet>
                                <span><?php esc_html_e('Published', 'bbj-v2-theme'); ?> <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('M j, Y')); ?> · <?php echo esc_html(get_the_date('g:i A')); ?> PT</time></span>
                                <span aria-hidden="true">·</span>
                                <span><?php printf(esc_html__('%d min read', 'bbj-v2-theme'), $read_min); ?></span>
                                <?php if ($comments > 0) : ?>
                                    <span aria-hidden="true">·</span>
                                    <a href="#comments" class="hover:text-primary-500 dark:hover:text-secondary-500">
                                        <?php printf(esc_html(_n('%d comment', '%d comments', $comments, 'bbj-v2-theme')), $comments); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0" aria-label="<?php esc_attr_e('Share this post', 'bbj-v2-theme'); ?>">
                        <a href="https://twitter.com/intent/tweet?url=<?php echo $share; ?>&text=<?php echo $share_text; ?>"
                           target="_blank" rel="noopener" class="bbj-share-btn" aria-label="<?php esc_attr_e('Share on X', 'bbj-v2-theme'); ?>">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.9 2H22l-7.5 8.6L23 22h-6.8l-5.3-6.9L4.7 22H1.6l8-9.2L1 2h6.9l4.8 6.3L18.9 2z"/></svg>
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share; ?>"
                           target="_blank" rel="noopener" class="bbj-share-btn" aria-label="<?php esc_attr_e('Share on Facebook', 'bbj-v2-theme'); ?>">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12.07C22 6.5 17.52 2 12 2S2 6.5 2 12.07C2 17.1 5.66 21.25 10.44 22V14.9H7.9v-2.83h2.54V9.85c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.77-1.63 1.56v1.87h2.78l-.44 2.83h-2.34V22C18.34 21.25 22 17.1 22 12.07z"/></svg>
                        </a>
                        <a href="https://www.reddit.com/submit?url=<?php echo $share; ?>&title=<?php echo $share_text; ?>"
                           target="_blank" rel="noopener" class="bbj-share-btn" aria-label="<?php esc_attr_e('Share on Reddit', 'bbj-v2-theme'); ?>">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm5.22 11a1.77 1.77 0 0 1-1.12 1.63 3.92 3.92 0 0 1 .07.7c0 2.2-2.75 4-6.17 4s-6.17-1.78-6.17-4a3.92 3.92 0 0 1 .07-.7A1.78 1.78 0 0 1 5.85 11.5a1.78 1.78 0 0 1 1.3.57 7.45 7.45 0 0 1 3.92-1.12l.67-3.13 2.25.47a1.22 1.22 0 1 1-.18.82l-1.89-.4-.59 2.7a7.54 7.54 0 0 1 3.89 1.14 1.78 1.78 0 0 1 1.3-.57A1.77 1.77 0 0 1 18 11z"/></svg>
                        </a>
                        <button type="button" class="bbj-share-btn" data-bbj-copy-link
                                aria-label="<?php esc_attr_e('Copy link', 'bbj-v2-theme'); ?>"
                                data-url="<?php echo esc_attr($permalink); ?>">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </header>

    <?php if ($thumb_id) : ?>
        <div class="mx-auto max-w-screen-xl px-4 mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div class="lg:col-span-8">
                    <figure class="bbj-single-figure">
                        <?php echo wp_get_attachment_image($thumb_id, 'bbj_v2_index_hero', false, [
                            'class'         => 'w-full h-auto',
                            'fetchpriority' => 'high',
                            'loading'       => 'eager',
                            'decoding'      => 'async',
                        ]); ?>
                        <?php
                        $caption = wp_get_attachment_caption($thumb_id);
                        if ($caption) : ?>
                            <figcaption class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                <strong class="font-osw uppercase tracking-wider mr-1"><?php esc_html_e('Pictured:', 'bbj-v2-theme'); ?></strong>
                                <?php echo esc_html($caption); ?>
                            </figcaption>
                        <?php endif; ?>
                    </figure>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="mx-auto max-w-screen-xl px-4 pb-12">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <div class="lg:col-span-8 min-w-0">
                <div class="bbj-post-body">
                    <?php the_content(); ?>
                </div>

                <?php $tags = get_the_tags();
                $cats = get_the_category();
                $all_terms = array_merge($cats ?: [], $tags ?: []); ?>
                <?php if (!empty($all_terms)) : ?>
                    <div class="mt-10 flex flex-wrap gap-2">
                        <?php foreach ($all_terms as $t) : ?>
                            <a href="<?php echo esc_url(get_term_link($t)); ?>"
                               class="inline-flex items-center px-3 py-1 rounded-full border border-gray-300 dark:border-gray-600 text-xs font-osw uppercase tracking-wider text-gray-700 dark:text-gray-300 hover:border-primary-500 hover:text-primary-500 dark:hover:text-secondary-500 transition-colors">
                                <?php echo esc_html($t->name); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php
                $author_bio = get_the_author_meta('description', $author_id);
                $author_url = get_author_posts_url($author_id);
                ?>
                <section class="mt-10 bbj-author-box flex flex-col sm:flex-row gap-4 border-t border-gray-200 dark:border-gray-700 pt-8">
                    <div class="shrink-0">
                        <?php echo get_avatar($author_id, 72, '', '', ['class' => 'rounded-full']); ?>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-osw uppercase tracking-wider text-lg text-primary-500 dark:text-secondary-500">
                            <a href="<?php echo esc_url($author_url); ?>" class="no-underline hover:underline">
                                <?php echo esc_html(get_the_author_meta('display_name', $author_id)); ?>
                            </a>
                        </h3>
                        <?php if ($author_bio !== '') : ?>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300"><?php echo esc_html($author_bio); ?></p>
                        <?php endif; ?>
                        <p class="mt-2 text-xs font-osw uppercase tracking-wider">
                            <a href="<?php echo esc_url($author_url); ?>" class="text-primary-500 dark:text-secondary-500 hover:underline">
                                <?php esc_html_e('All posts →', 'bbj-v2-theme'); ?>
                            </a>
                        </p>
                    </div>
                </section>

                <?php
                $cat_ids = wp_list_pluck($cats, 'term_id');
                $related = !empty($cat_ids) ? new WP_Query([
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'posts_per_page' => 3,
                    'category__in'   => $cat_ids,
                    'post__not_in'   => [$post_id],
                    'no_found_rows'  => true,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                ]) : null;
                if ($related && $related->have_posts()) : ?>
                    <section class="mt-12 border-t border-gray-200 dark:border-gray-700 pt-8">
                        <h2 class="section-header mb-4"><?php esc_html_e('Keep Reading', 'bbj-v2-theme'); ?></h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <?php foreach ($related->posts as $r) : ?>
                                <article class="v2-primary-container-inner">
                                    <a href="<?php echo esc_url(get_permalink($r->ID)); ?>" class="block group">
                                        <?php if (has_post_thumbnail($r->ID)) : ?>
                                            <div class="aspect-[4/3] overflow-hidden bg-gray-100 dark:bg-gray-800">
                                                <?php echo get_the_post_thumbnail($r->ID, 'featured-thumbnail', [
                                                    'class'    => 'w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-300',
                                                    'loading'  => 'lazy',
                                                    'decoding' => 'async',
                                                ]); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="p-4">
                                            <h3 class="font-display text-lg leading-snug text-gray-900 dark:text-gray-100 group-hover:text-primary-500 dark:group-hover:text-secondary-500 transition-colors">
                                                <?php echo esc_html($r->post_title); ?>
                                            </h3>
                                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 font-osw uppercase tracking-wider" data-nosnippet>
                                                <time datetime="<?php echo esc_attr(get_the_date('c', $r)); ?>"><?php echo esc_html(get_the_date('M j', $r)); ?></time>
                                            </div>
                                        </div>
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; wp_reset_postdata(); ?>

                <?php if (comments_open() || get_comments_number()) : ?>
                    <section class="mt-12 border-t border-gray-200 dark:border-gray-700 pt-8">
                        <?php comments_template(); ?>
                    </section>
                <?php endif; ?>

            </div>

            <div class="lg:col-span-4">
                <?php get_sidebar(); ?>
            </div>

        </div>
    </div>

</article>

<?php endwhile; ?>

<script>
(function () {
    var btn = document.querySelector('[data-bbj-copy-link]');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var url = btn.getAttribute('data-url') || location.href;
        (navigator.clipboard && navigator.clipboard.writeText
            ? navigator.clipboard.writeText(url)
            : Promise.resolve()
        ).then(function () {
            btn.classList.add('is-copied');
            setTimeout(function () { btn.classList.remove('is-copied'); }, 1200);
        });
    });
})();
</script>

<?php get_footer(); ?>
