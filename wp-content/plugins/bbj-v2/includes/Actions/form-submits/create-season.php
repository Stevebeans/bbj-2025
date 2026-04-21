<?php
/**
 * Creates a blank draft season when the "Add Season" button is clicked.
 * Inserts a bigbrother-seasons CPT post + a companion wp_bbj_seasons row,
 * then redirects to the edit page for the new season.
 */

if (!defined('ABSPATH')) {
    exit;
}

function bbj_v2_create_season() {
    global $wpdb;

    // 1. Security + capability
    if (
        empty($_POST['bbj_v2_create_season_nonce']) ||
        ! wp_verify_nonce($_POST['bbj_v2_create_season_nonce'], 'bbj_v2_create_season_action') ||
        ! current_user_can('manage_options')
    ) {
        wp_die('Permission check failed');
    }

    // 2. Insert the CPT post as a draft
    $post_id = wp_insert_post([
        'post_type'   => 'bigbrother-seasons',
        'post_status' => 'draft',
        'post_title'  => 'New Season',
    ], true);

    if (is_wp_error($post_id) || ! $post_id) {
        wp_die('Failed to create season post.');
    }

    // 3. Insert the companion custom-table row
    $season_table = $wpdb->prefix . 'bbj_seasons';
    $inserted = $wpdb->insert(
        $season_table,
        [
            'post_id'   => $post_id,
            'full_name' => '',
        ],
        ['%d', '%s']
    );

    if (false === $inserted) {
        // Roll back the orphaned post so we don't leave garbage
        wp_delete_post($post_id, true);
        wp_die('Failed to create season row.');
    }

    $season_id = (int) $wpdb->insert_id;

    // 4. Redirect to the edit page for the new season
    $redirect = add_query_arg(
        ['tab' => 'seasons', 'edit' => $season_id, 'created' => 1],
        home_url('/admin/')
    );
    wp_safe_redirect($redirect);
    exit;
}
