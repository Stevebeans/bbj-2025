<?php 


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// actions 



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
    bbj_log3(print_r('hey', true));
    require_once BBJ_FORM_SUBMITS . 'update-season.php';
    bbj_v2_edit_season_info();
}