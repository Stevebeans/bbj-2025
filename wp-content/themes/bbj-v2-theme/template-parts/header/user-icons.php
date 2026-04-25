<?php
/**
 * Header user icons — shown for logged-in users only.
 * Displays (right-to-left): avatar, bell, pencil (if edit_posts), shield (if manage_options).
 *
 * Rendered by logo-bar.php inside the right-side `justify-self-end` block.
 * Unlike the /admin and /dashboard templates, this partial does NOT require
 * a safeguard — it's an additive nav element, not a protected surface.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in()) {
    return;
}

$current_user = wp_get_current_user();
$can_admin    = current_user_can('manage_options');
$can_edit     = current_user_can('edit_posts');
$avatar_url   = get_avatar_url($current_user->ID, ['size' => 64]);
?>
<div class="flex items-center gap-2">
    <?php if ($can_admin): ?>
        <a href="<?php echo esc_url(add_query_arg('tab', 'roadmap', home_url('/admin/'))); ?>"
           class="inline-flex items-center justify-center w-9 h-9 text-stone-600 hover:text-primary-500 hover:bg-stone-100 dark:text-slate-300 dark:hover:bg-slate-800 transition"
           title="<?php esc_attr_e('Roadmap', 'bbj-v2-theme'); ?>"
           aria-label="<?php esc_attr_e('Roadmap & page coverage', 'bbj-v2-theme'); ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 6.75V15m0-8.25l-6 3.75v8.25l6-3.75m0-8.25l6 3.75m0 0v8.25m0-8.25l6-3.75v8.25l-6 3.75m0-8.25v8.25m-6-3.75v3.75"/>
            </svg>
        </a>

        <a href="<?php echo esc_url(home_url('/admin/')); ?>"
           class="inline-flex items-center justify-center w-9 h-9 text-stone-600 hover:text-primary-500 hover:bg-stone-100 dark:text-slate-300 dark:hover:bg-slate-800 transition"
           title="<?php esc_attr_e('Admin', 'bbj-v2-theme'); ?>"
           aria-label="<?php esc_attr_e('Admin dashboard', 'bbj-v2-theme'); ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
        </a>
    <?php endif; ?>

    <?php if ($can_edit): ?>
        <a href="<?php echo esc_url(add_query_arg('tab', 'posts', home_url('/admin/'))); ?>"
           class="inline-flex items-center justify-center w-9 h-9 text-stone-600 hover:text-primary-500 hover:bg-stone-100 dark:text-slate-300 dark:hover:bg-slate-800 transition"
           title="<?php esc_attr_e('New post', 'bbj-v2-theme'); ?>"
           aria-label="<?php esc_attr_e('New post', 'bbj-v2-theme'); ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
            </svg>
        </a>
    <?php endif; ?>

    <a href="<?php echo esc_url(add_query_arg('tab', 'notifications', home_url('/dashboard/'))); ?>"
       class="inline-flex items-center justify-center w-9 h-9 text-stone-600 hover:text-primary-500 hover:bg-stone-100 dark:text-slate-300 dark:hover:bg-slate-800 transition"
       title="<?php esc_attr_e('Notifications', 'bbj-v2-theme'); ?>"
       aria-label="<?php esc_attr_e('Notifications', 'bbj-v2-theme'); ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
    </a>

    <a href="<?php echo esc_url(home_url('/dashboard/')); ?>"
       class="inline-flex items-center justify-center"
       title="<?php esc_attr_e('My dashboard', 'bbj-v2-theme'); ?>"
       aria-label="<?php esc_attr_e('My dashboard', 'bbj-v2-theme'); ?>">
        <img src="<?php echo esc_url($avatar_url); ?>"
             alt=""
             class="w-9 h-9 rounded-full object-cover"
             width="36" height="36"
             loading="lazy"
             decoding="async">
    </a>
</div>
