<?php
/**
 * Mobile-only header stack: utility strip, logo row, menu panel, action bar.
 * Desktop uses the separate utility-strip / logo-bar / nav parts.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="md:hidden">

    <div class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400">
        <div class="px-3 py-1.5 flex items-center justify-between">
            <span class="flex items-center gap-2">
                <span>Follow:</span>
                <a href="https://facebook.com/bigbrotherjunkies" target="_blank" rel="noopener" aria-label="Facebook"
                   class="hover:text-primary-500 transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                      <path d="M22 12.07C22 6.5 17.52 2 12 2S2 6.5 2 12.07C2 17.1 5.66 21.25 10.44 22V14.9H7.9v-2.83h2.54V9.85c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.77-1.63 1.56v1.87h2.78l-.44 2.83h-2.34V22C18.34 21.25 22 17.1 22 12.07z"/>
                    </svg>
                </a>
                <a href="https://instagram.com/bigbrotherjunkies" target="_blank" rel="noopener" aria-label="Instagram"
                   class="hover:text-primary-500 transition">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                      <path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23a3.7 3.7 0 0 1-.9 1.38 3.7 3.7 0 0 1-1.38.9c-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16zm0 5.84A4 4 0 1 0 12 16a4 4 0 0 0 0-8zm5.2-.95a.93.93 0 1 1 0-1.86.93.93 0 0 1 0 1.86zM12 10a2 2 0 1 1 0 4 2 2 0 0 1 0-4z"/>
                    </svg>
                </a>
            </span>
            <span data-nosnippet>BB Time: <?php echo esc_html(bbj_v2_bb_time()); ?></span>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900 px-3 py-2 flex items-center justify-between">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="block" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
            <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/bbj-logo-sm.png'); ?>"
                 alt="<?php echo esc_attr(get_bloginfo('name')); ?>"
                 class="w-10 h-10 rounded-full object-contain"
                 width="40" height="40" decoding="async">
        </a>
        <div class="flex items-center gap-2">
            <button type="button"
                    class="p-2 text-gray-700 dark:text-gray-200 hover:text-primary-500 transition"
                    data-bbj-search-open
                    aria-label="<?php esc_attr_e('Search', 'bbj-v2-theme'); ?>">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                </svg>
            </button>

            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo esc_url(admin_url('profile.php')); ?>"
                   class="p-2 text-gray-700 dark:text-gray-200 hover:text-primary-500 transition"
                   aria-label="<?php esc_attr_e('Profile', 'bbj-v2-theme'); ?>">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </a>
            <?php else : ?>
                <button type="button"
                        data-bbj-auth-open="login"
                        class="p-2 text-gray-700 dark:text-gray-200 hover:text-primary-500 transition"
                        aria-label="<?php esc_attr_e('Log In', 'bbj-v2-theme'); ?>">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </button>
            <?php endif; ?>

            <button type="button"
                    class="p-2 text-gray-700 dark:text-gray-200 hover:text-primary-500 transition"
                    data-bbj-mnav-toggle
                    aria-expanded="false"
                    aria-controls="bbj-mnav-panel"
                    aria-label="<?php esc_attr_e('Open menu', 'bbj-v2-theme'); ?>">
                <span class="bbj-mnav-burger" aria-hidden="true">
                    <span></span><span></span><span></span>
                </span>
            </button>
        </div>
    </div>

    <div id="bbj-mnav-panel"
         class="bbj-mnav-panel bbj-mnav-panel--inline"
         data-bbj-mnav-panel
         hidden>
        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => false,
            'menu_class'     => 'bbj-mnav flex flex-col',
            'depth'          => 1,
            'fallback_cb'    => 'bbj_v2_fallback_mobile_menu',
            'link_class'     => 'bbj-mnav-link',
        ]);
        ?>
    </div>

    <div class="bbj-action-bar">
        <a href="<?php echo esc_url(home_url('/watch-feeds/')); ?>" class="bbj-action-link">
            <span class="underline"><?php esc_html_e('Watch Feeds', 'bbj-v2-theme'); ?></span>
            <span class="bbj-live-pill" aria-hidden="true">
                <span class="bbj-live-dot"></span>
                <?php esc_html_e('LIVE', 'bbj-v2-theme'); ?>
            </span>
        </a>
        <a href="<?php echo esc_url(home_url('/go-ad-free/')); ?>" class="bbj-action-link underline">
            <?php esc_html_e('Go Ad Free', 'bbj-v2-theme'); ?>
        </a>
    </div>

</div>
