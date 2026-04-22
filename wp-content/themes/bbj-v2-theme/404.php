<?php
/**
 * 404 Not Found Template
 */
get_header(); ?>

<main class="mx-auto max-w-screen-xl px-4 py-6">
    <div class="bg-white rounded-lg shadow p-8 md:p-12 text-center dark:bg-gray-800">
        <h1 class="font-display text-5xl text-primary-500 dark:text-secondary-500 mb-4">404</h1>
        <h2 class="font-osw text-2xl text-gray-700 dark:text-gray-300 mb-4">Page Not Found</h2>
        <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-md mx-auto">
            The page you're looking for doesn't exist or has been moved. Try searching for what you need.
        </p>
        <div class="max-w-sm mx-auto mb-6">
            <?php get_search_form(); ?>
        </div>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-primary inline-block">
            Back to Home
        </a>
    </div>
</main>

<?php get_footer(); ?>
