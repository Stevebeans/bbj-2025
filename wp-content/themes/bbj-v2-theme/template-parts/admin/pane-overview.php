<?php
/**
 * Admin shell — Overview pane (the only non-stub pane in v1).
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$first_name   = $current_user->first_name ?: $current_user->display_name ?: $current_user->user_login;
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">
    <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500 mb-2">
        <?php printf(
            /* translators: %s: display or first name */
            esc_html__('Welcome, %s.', 'bbj-v2-theme'),
            esc_html($first_name)
        ); ?>
    </h1>
    <p class="text-stone-600 dark:text-slate-400">
        <?php esc_html_e('This is your admin dashboard. Pick a section from the sidebar to get started. Feature panes are being built out — the shell is live, the rooms are still being furnished.', 'bbj-v2-theme'); ?>
    </p>
</section>
