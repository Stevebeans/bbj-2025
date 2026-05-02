<?php 


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// actions 


// include outside files here 
require_once BBJ_ACTION_LIST . 'ad-functions.php';


// Add players to seasons
add_action( 'admin_post_bbj_v2_add_player', 'BBJ_load_add_player_handler' );

function BBJ_load_add_player_handler() {
    // only now load the heavy logic
    require_once BBJ_FORM_SUBMITS . 'add-player-to-season.php';
    bbj_v2_add_player();
}

// Update Season Information 
add_action( 'admin_post_bbj_v2_update_season', 'BBJ_load_update_season_handler' );

function BBJ_load_update_season_handler() {
    // only now load the heavy logic
    require_once BBJ_FORM_SUBMITS . 'update-season.php';
    bbj_v2_update_season();
}

// Update Season Information 
add_action( 'admin_post_bbj_v2_edit_season_info', 'BBJ_load_update_season_info_handler' );

function BBJ_load_update_season_info_handler() {
    // only now load the heavy logic
    require_once BBJ_FORM_SUBMITS . 'update-season.php';
    bbj_v2_edit_season_info();
}

// Create a new season (draft)
add_action( 'admin_post_bbj_v2_create_season', 'BBJ_load_create_season_handler' );

function BBJ_load_create_season_handler() {
    // only now load the heavy logic
    require_once BBJ_FORM_SUBMITS . 'create-season.php';
    bbj_v2_create_season();
}

// Purge spoiler-bar cache for a single season (no DB writes)
add_action( 'admin_post_bbj_v2_purge_season_cache', 'BBJ_load_purge_season_cache_handler' );

function BBJ_load_purge_season_cache_handler() {
    // only now load the heavy logic
    require_once BBJ_FORM_SUBMITS . 'purge-season-cache.php';
    bbj_v2_purge_season_cache();
}

// Set the current season (global option bbj_v2_current_season)
add_action( 'admin_post_bbj_v2_set_current_season', 'BBJ_load_set_current_season_handler' );

function BBJ_load_set_current_season_handler() {
    // only now load the heavy logic
    require_once BBJ_FORM_SUBMITS . 'set-current-season.php';
    bbj_v2_set_current_season();
}

// Add or Edit Player Information
add_action( 'admin_post_bbj_v2_add_edit_player', 'BBJ_load_add_edit_player_handler' );

function BBJ_load_add_edit_player_handler() {
    // only now load the heavy logic
    require_once BBJ_FORM_SUBMITS . 'add-edit-player.php';
    bbj_add_edit_player();
}


// mu-plugins/extend-auth-cookie.php
add_filter('auth_cookie_expiration', function ($seconds, $user_id, $remember) {
  return $remember ? 30 * DAY_IN_SECONDS : $seconds; // bump to 30 days
}, 10, 3);
