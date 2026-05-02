<article <?php post_class('bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow dark:bg-gray-800'); ?>>
    <?php if (has_post_thumbnail()) : ?>
        <a href="<?php the_permalink(); ?>" class="block mb-3">
            <?php the_post_thumbnail('featured-thumbnail', [
                'class' => 'w-full h-48 object-cover rounded-lg',
                'loading' => 'lazy',
            ]); ?>
        </a>
    <?php endif; ?>

    <h2 class="font-osw text-xl mb-1">
        <a href="<?php the_permalink(); ?>" class="text-primary-500 hover:text-secondary-500 dark:text-secondary-500 dark:hover:text-secondary-400 transition">
            <?php the_title(); ?>
        </a>
    </h2>

    <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">
        <time datetime="<?php echo get_the_date('c'); ?>"><?php echo get_the_date(); ?></time>
        <span class="mx-1">&bull;</span>
        <?php the_author(); ?>
    </div>

    <div class="text-gray-700 dark:text-gray-300 text-sm line-clamp-3">
        <?php the_excerpt(); ?>
    </div>
</article>
