<?php
/**
 * Comments island — replaces comments_template() with the React island placeholder.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('comments_template', 'bbj_v2_comments_island_template', 20);
function bbj_v2_comments_island_template(string $original): string {
    if (!comments_open()) {
        return $original;
    }
    if (!empty($_GET['bbjcomments']) && $_GET['bbjcomments'] === 'plain') {
        return get_template_directory() . '/template-parts/comments/plain-fallback.php';
    }
    return get_template_directory() . '/template-parts/comments/island-placeholder.php';
}

add_action('wp_enqueue_scripts', 'bbj_v2_comments_island_enqueue');
function bbj_v2_comments_island_enqueue(): void {
    if (!is_singular() || !comments_open()) return;
    if (!empty($_GET['bbjcomments']) && $_GET['bbjcomments'] === 'plain') return;

    $themeUri  = get_template_directory_uri();
    $buildPath = get_template_directory() . '/build/comments/bootstrap.js';
    $version   = file_exists($buildPath) ? (string) filemtime($buildPath) : '1';

    wp_enqueue_script(
        'bbj-comments-bootstrap',
        $themeUri . '/build/comments/bootstrap.js',
        [],
        $version,
        true
    );

    $user = wp_get_current_user();
    wp_localize_script('bbj-comments-bootstrap', 'bbjComments', [
        'user' => $user && $user->ID > 0 ? [
            'id'           => (int) $user->ID,
            'display_name' => $user->display_name,
            'avatar_url'   => get_avatar_url($user->ID, ['size' => 80]),
            'rank'         => function_exists('bbjd_get_user_rank') ? bbjd_get_user_rank($user->ID) : null,
            'can_moderate' => current_user_can('moderate_comments'),
        ] : null,
        'nonce'           => wp_create_nonce('bbj_comments'),
        'nonceRefreshUrl' => esc_url_raw(rest_url('bbjd/v1/auth/refresh-nonce')),
        'endpoints'       => ['base' => esc_url_raw(rest_url('bbjd/v1'))],
        'config'          => ['perPage' => 20, 'maxDepth' => 3, 'sortDefault' => 'newest'],
        'postId'          => (int) get_queried_object_id(),
    ]);
}
