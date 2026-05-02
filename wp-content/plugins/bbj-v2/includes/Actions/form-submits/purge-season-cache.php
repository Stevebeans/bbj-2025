<?php
/**
 * Purges the spoiler-bar cache for a single season.
 * No DB writes — pure cache wipe.
 */

if (!defined('ABSPATH')) {
    exit;
}

function bbj_v2_purge_season_cache() {
    // 1. Security + capability
    if (
        empty($_POST['bbj_v2_purge_season_cache_nonce']) ||
        ! wp_verify_nonce($_POST['bbj_v2_purge_season_cache_nonce'], 'bbj_v2_purge_season_cache_action') ||
        ! current_user_can('manage_options')
    ) {
        wp_die('Permission check failed');
    }

    $season_id = isset($_POST['season_id']) ? absint($_POST['season_id']) : 0;
    if ($season_id <= 0) {
        wp_die('Invalid season id');
    }

    bbj_spoiler_bar_bust_cache($season_id);

    // 2. Redirect back with purged=1
    $redirect = wp_get_referer() ?: add_query_arg(
        ['tab' => 'seasons', 'edit' => $season_id],
        home_url('/admin/')
    );
    wp_safe_redirect(add_query_arg('purged', '1', $redirect));
    exit;
}
