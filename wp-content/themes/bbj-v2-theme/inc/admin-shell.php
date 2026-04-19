<?php
/**
 * Admin / user-dashboard shell helpers.
 *
 * Every `/admin/*` and `/dashboard/*` front-end template MUST call one of
 * the safeguards below on line 1 (see feedback_admin_page_safeguard memory).
 * Front-end admin URLs are public — WP core does NOT auto-enforce a capability
 * check the way add_menu_page does. Forgetting the safeguard exposes the page.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Require an admin (manage_options) user. Redirect to login if logged-out,
 * return 403 if logged-in but not an admin.
 */
function bbj_v2_require_admin(): void
{
    add_filter('wp_robots', 'wp_robots_no_robots');

    if (!is_user_logged_in()) {
        $current = home_url(add_query_arg([]));
        wp_safe_redirect(wp_login_url($current));
        exit;
    }

    if (!current_user_can('manage_options')) {
        status_header(403);
        wp_die(
            esc_html__('You do not have permission to access this page.', 'bbj-v2-theme'),
            esc_html__('Access Denied', 'bbj-v2-theme'),
            ['response' => 403]
        );
    }
}

/**
 * Require any logged-in user. Redirect to login with return URL if logged-out.
 */
function bbj_v2_require_logged_in(): void
{
    add_filter('wp_robots', 'wp_robots_no_robots');

    if (!is_user_logged_in()) {
        $current = home_url(add_query_arg([]));
        wp_safe_redirect(wp_login_url($current));
        exit;
    }
}

/**
 * Register `tab` as an allowed query var so sub-pane routing works.
 */
add_filter('query_vars', function (array $vars): array {
    $vars[] = 'tab';
    return $vars;
});
