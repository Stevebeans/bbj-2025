<?php 


defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'bbj_tools_register_menu' );
function bbj_tools_register_menu() {

  
    add_menu_page(
        'Big Brother Junkies Settings',   // Page <title>
        'BBJ',                            // Menu label
        'manage_options',                 // Only admins
        'bbj-main',                       // Slug: also your bbj-main.php filename
        'bbj_tools_render_main_page',     // Callback to load content
        'dashicons-format-video',         // Icon (any Dashicon)
        3                                 // Menu position (3 = just below Posts)
    );

    // add settings submenu
    add_submenu_page(
        'bbj-main',                       // Parent slug
        'BBJ Settings',                   // Page title
        'Settings',                       // Menu label
        'manage_options',                 // Capability
        'bbj-v2-settings',                   
        'bbj_tools_render_settings_page'  // Callback to load content
    );

    // add seasons submenu
    add_submenu_page(
        'bbj-main',                       // Parent slug
        'BBJ Seasons',                    // Page title
        'Seasons',                        // Menu label
        'manage_options',                 // Capability
        'bbj-v2-seasons',
        'bbj_tools_render_seasons_page'   // Callback to load content
    );

    // edit season submenu hidden 
    add_submenu_page(
        'bbj-main',                        // parent slug
        'Edit Season',                     // <title> tag
        'Edit Season',                              // no menu title (won’t show up in menu)
        'manage_options',                  // capability
        'bbj-v2-edit-season',             // the slug you’ll link to
        'bbj_tools_render_edit_season'    // callback to output the page
    );

    // Add / Edit Player submenu
    add_submenu_page(
        'bbj-main',
        'Edit Player',
        'Add / Edit Player',
        'manage_options',
        'bbj-v2-add-edit-player',
        'bbj_tools_render_edit_player'
    );

    // Edit Ads submenu
    add_submenu_page(
        'bbj-main',
        'Edit Ads',
        'Edit Ads',
        'manage_options',
        'bbj-v2-edit-ads',
        'bbj_tools_render_edit_ads'
    );
}


function bbj_tools_render_edit_ads() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    
    // Include the edit ads template file
    include BBJ_V2_PUBLIC . 'bbj-edit-ads.php';
}



function bbj_tools_render_edit_player() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    
    // Include the edit player template file
    include BBJ_V2_PUBLIC . 'bbj-v2-add-edit-player.php';
}


function bbj_tools_render_edit_season() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    
    // Include the edit season template file
    include BBJ_V2_PUBLIC . 'bbj-v2-edit-season.php';
}


function bbj_tools_render_main_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    // include your empty stub page
    include BBJ_V2_PUBLIC . 'bbj-main.php';
}

function bbj_tools_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    // include your empty stub page
    include BBJ_V2_PUBLIC . 'bbj-v2-settings.php';
}

function bbj_tools_render_seasons_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    // include your empty stub page
    include BBJ_V2_PUBLIC . 'bbj-v2-seasons.php';
}