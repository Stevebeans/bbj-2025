<div class="flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
    <!-- Breadcrumbs -->
    <a href="<?php echo esc_url(home_url('/')); ?>" class="hover:text-secondary-500 transition">Home</a>
    <span>&rsaquo;</span>
    <?php
    $categories = get_the_category();
    if (!empty($categories)) :
        $cat = $categories[0];
    ?>
        <a href="<?php echo esc_url(get_category_link($cat->term_id)); ?>" class="hover:text-secondary-500 transition">
            <?php echo esc_html($cat->name); ?>
        </a>
        <span>&rsaquo;</span>
    <?php endif; ?>
    <span class="text-gray-700 dark:text-gray-300"><?php the_title(); ?></span>

    <span class="mx-2">|</span>

    <!-- Date + Author -->
    <time datetime="<?php echo get_the_date('c'); ?>"><?php echo get_the_date(); ?></time>
    <span>&bull;</span>
    <span>By <?php the_author(); ?></span>
</div>
