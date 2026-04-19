<?php
/**
 * Header logo bar.
 * Left: site wordmark. Center: search. Right: Log In.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="hidden md:block bg-white dark:bg-gray-900">
    <div class="mx-auto max-w-screen-xl px-4 py-3 grid grid-cols-3 items-center gap-4">

        <a href="<?php echo esc_url(home_url('/')); ?>"
           class="justify-self-start block"
           aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
            <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/bbjlogo2020.png'); ?>"
                 alt="<?php echo esc_attr(get_bloginfo('name')); ?>"
                 class="max-h-10 md:max-h-12 w-auto h-auto"
                 width="395" height="37" decoding="async">
        </a>

        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>"
              class="justify-self-center w-full max-w-md">
            <label class="sr-only" for="bbj-search"><?php esc_html_e('Search', 'bbj-v2-theme'); ?></label>
            <div class="relative">
                <input type="search" id="bbj-search" name="s"
                       class="input pr-12"
                       placeholder="<?php esc_attr_e('Search posts, players, seasons...', 'bbj-v2-theme'); ?>"
                       value="<?php echo esc_attr(get_search_query()); ?>">
                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary-500" aria-label="<?php esc_attr_e('Submit search', 'bbj-v2-theme'); ?>">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </button>
            </div>
        </form>

        <div class="justify-self-end flex items-center gap-3">
            <?php get_template_part('template-parts/header/user-icons'); ?>

            <?php if (!is_user_logged_in()) : ?>
                <button type="button"
                        data-bbj-auth-open="login"
                        class="flex items-center gap-1 text-gray-700 dark:text-gray-200 hover:text-primary-500 transition">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span class="text-sm font-osw uppercase tracking-wider"><?php esc_html_e('Log In', 'bbj-v2-theme'); ?></span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
