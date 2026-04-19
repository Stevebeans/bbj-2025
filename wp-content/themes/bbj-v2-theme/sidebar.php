<aside class="w-80 shrink-0 hidden lg:block space-y-6">
    <?php if (function_exists('bbjd_ad')) : ?>
        <div class="bg-white rounded-lg shadow p-4 dark:bg-gray-800">
            <?php bbjd_ad('sidebar-top'); ?>
        </div>
    <?php endif; ?>

    <?php dynamic_sidebar('main-sidebar'); ?>

    <!-- Newsletter Signup Placeholder -->
    <?php get_template_part('template-parts/sidebar/newsletter'); ?>
</aside>
