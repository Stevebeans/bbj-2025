<?php
/**
 * Comments island — replaces comments_template() with the React island placeholder.
 */

if (!defined('ABSPATH')) {
    exit;
}

function bbj_v2_comments_plain_mode(): bool {
    return isset($_GET['bbjcomments']) && sanitize_key(wp_unslash($_GET['bbjcomments'])) === 'plain';
}

add_filter('comments_template', 'bbj_v2_comments_island_template', 20);
function bbj_v2_comments_island_template(string $original): string {
    if (!comments_open()) {
        return $original;
    }
    if (bbj_v2_comments_plain_mode()) {
        return get_template_directory() . '/template-parts/comments/plain-fallback.php';
    }
    return get_template_directory() . '/template-parts/comments/island-placeholder.php';
}

add_action('wp_enqueue_scripts', 'bbj_v2_comments_island_enqueue');
function bbj_v2_comments_island_enqueue(): void {
    if (!is_singular() || !comments_open()) return;
    if (bbj_v2_comments_plain_mode()) return;

    $asset_path = get_template_directory() . '/build/comments/bootstrap.asset.php';
    $asset = file_exists($asset_path) ? include $asset_path : ['dependencies' => [], 'version' => null];

    wp_enqueue_script(
        'bbj-comments-bootstrap',
        get_template_directory_uri() . '/build/comments/bootstrap.js',
        $asset['dependencies'] ?? [],
        $asset['version'] ?? bbj_v2_asset_ver('/build/comments/bootstrap.js'),
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
