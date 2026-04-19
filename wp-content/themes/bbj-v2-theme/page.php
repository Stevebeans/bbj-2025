<?php
/**
 * Generic page template — shares single.php's typography wrapper but strips
 * the editorial chrome (byline, share bar, tags, author box, related posts).
 */

get_header();

while (have_posts()) : the_post(); ?>

<article class="bbj-page">

    <header class="mx-auto max-w-screen-xl px-4 pt-8 pb-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-8">
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl leading-[1.05] font-bold text-gray-900 dark:text-gray-100 mb-4">
                    <?php the_title(); ?>
                </h1>
                <?php if (has_excerpt()) : ?>
                    <p class="text-lg md:text-xl text-gray-600 dark:text-gray-400 leading-relaxed">
                        <?php echo esc_html(get_the_excerpt()); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if (has_post_thumbnail()) : ?>
        <div class="mx-auto max-w-screen-xl px-4 mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div class="lg:col-span-8">
                    <?php the_post_thumbnail('bbj_v2_index_hero', [
                        'class'         => 'w-full h-auto rounded-lg',
                        'fetchpriority' => 'high',
                        'loading'       => 'eager',
                        'decoding'      => 'async',
                    ]); ?>
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
            </div>
            <div class="lg:col-span-4">
                <?php get_sidebar(); ?>
            </div>
        </div>
    </div>

</article>

<?php endwhile; ?>

<?php get_footer(); ?>
