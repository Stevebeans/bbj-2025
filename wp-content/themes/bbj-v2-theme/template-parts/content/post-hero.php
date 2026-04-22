<article class="relative bg-white rounded-lg shadow-lg overflow-hidden dark:bg-gray-800">
    <?php if (has_post_thumbnail()) : ?>
        <div class="relative h-64 md:h-96">
            <?php the_post_thumbnail('bbj_v2_index_hero', [
                'class' => 'w-full h-full object-cover',
            ]); ?>
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
            <div class="absolute bottom-0 left-0 right-0 p-6 md:p-8 text-white">
                <h2 class="font-display text-3xl md:text-5xl mb-2">
                    <a href="<?php the_permalink(); ?>" class="hover:text-secondary-500 transition">
                        <?php the_title(); ?>
                    </a>
                </h2>
                <p class="text-sm text-gray-200">
                    <?php echo get_the_date(); ?> &bull; <?php the_author(); ?>
                </p>
            </div>
        </div>
    <?php else : ?>
        <div class="p-6 md:p-8">
            <h2 class="font-display text-3xl md:text-4xl text-primary-500 dark:text-secondary-500 mb-2">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h2>
            <?php the_excerpt(); ?>
        </div>
    <?php endif; ?>
</article>
