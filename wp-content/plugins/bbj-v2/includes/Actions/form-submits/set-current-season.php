<?php
/**
 * Handles the "Set current season" form on /admin?tab=settings.
 * Writes wp_options['bbj_v2_current_season'] and busts spoiler-bar cache
 * for both the previously-active season and the newly-selected one.
 */

if (!defined('ABSPATH')) {
    exit;
}

function bbj_v2_set_current_season() {
    // 1. Security + capability
    if (
        empty($_POST['bbj_v2_set_current_season_nonce']) ||
        ! wp_verify_nonce($_POST['bbj_v2_set_current_season_nonce'], 'bbj_v2_set_current_season_action') ||
        ! current_user_can('manage_options')
    ) {
        wp_die('Permission check failed');
    }

    $new_id = isset($_POST['bbj_v2_season']) ? absint($_POST['bbj_v2_season']) : 0;
    if ($new_id <= 0) {
        wp_die('Invalid season selection');
    }

    // Validate the id points at a real season row
    $season = bbj_v2_get_season_by_id($new_id);
    if (!$season) {
        wp_die('Season not found');
    }

    // Capture the old id before the switch so we can bust its cache too
    $old_id = (int) get_option('bbj_v2_current_season', 0);

    update_option('bbj_v2_current_season', $new_id);

    // Defensive cache bust: old and new season ids both get their spoiler-bar
    // cache keys wiped. The cache is season-keyed, so old cached HTML is
    // harmless once the option changes, but busting cleanly is cheap.
    if ($old_id > 0 && $old_id !== $new_id) {
        bbj_spoiler_bar_bust_cache($old_id);
    }
    bbj_spoiler_bar_bust_cache($new_id);

    // Redirect back to the settings pane with a success flag
    $redirect = add_query_arg(
        ['tab' => 'settings', 'updated' => 1],
        home_url('/admin/')
    );
    wp_safe_redirect($redirect);
    exit;
}
