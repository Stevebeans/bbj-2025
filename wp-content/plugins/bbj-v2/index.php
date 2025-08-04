<?php
/**
 * Plugin Name:     BBJ v2
 * Plugin URI:      https://yourdomain.com/bbj-v2
 * Description:     Version 2 of the BBJ plugin.
 * Version:         1.0.0
 * Author:          Steve Beans
 * Text Domain:     bbj-v2
 * Domain Path:     /languages
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// Plugin version
if ( ! defined( 'BBJ_V2_VERSION' ) ) {
    define( 'BBJ_V2_VERSION', '1.0.0' );
}

define( 'BBJ_V2_PATH',      plugin_dir_path( __FILE__ ) );
define( 'BBJ_V2_URL',       plugin_dir_url(  __FILE__ ) );
define( 'BBJ_V2_INCLUDES',  BBJ_V2_PATH . 'includes/' );
define( 'BBJ_V2_PUBLIC',    BBJ_V2_INCLUDES . 'Public/' );

// global table variables 
define( 'BBJ_V2_TABLE_PLAYERS', 'wp_bbj_players' );
define( 'BBJ_V2_TABLE_SEASONS', 'wp_bbj_seasons' );
define( 'BBJ_V2_TABLE_LINKS',   'wp_bbj_v2_player_season' );

// Action variables 
define ('BBJ_FORM_SUBMITS', BBJ_V2_INCLUDES . 'Actions/form-submits/');

// Activation hook
function bbj_v2_activate() {
   // Reserved for future use, e.g. creating custom tables, flushing rewrite rules
}
register_activation_hook( __FILE__, 'bbj_v2_activate' );

add_action( 'admin_enqueue_scripts', 'bbj_v2_enqueue_admin_assets' );
function bbj_v2_enqueue_admin_assets( $hook ) {
    // only load on our BBJ pages:
    $allowed = [
        'toplevel_page_bbj-main',
        'bbj_page_bbj-v2-settings',
        'bbj_page_bbj-v2-seasons',
        'bbj_page_bbj-v2-edit-season',
        'bbj_page_bbj-v2-add-edit-player'
    ];
    if ( ! in_array( $hook, $allowed, true ) ) {
        return;
    }

    $ver = defined( 'BBJ_V2_VERSION' ) ? BBJ_V2_VERSION : false;

    wp_enqueue_style(
        'bbj-v2-admin-css',
        BBJ_V2_URL . 'build/index.css',
        [],
        $ver
    );
    wp_enqueue_script(
        'bbj-v2-admin-js',
        BBJ_V2_URL . 'build/index.js',
        [ 'wp-element' ],
        $ver,
        true
    );
}


// Deactivation hook
function bbj_v2_deactivate() {
    // Reserved for future use, e.g. cleanup transient data, flushing rewrite rules
}
register_deactivation_hook( __FILE__, 'bbj_v2_deactivate' );

// Include the main plugin file
require_once BBJ_V2_PATH . 'includes/bbj-plugin.php';

// Registration hook for creating database tables
register_activation_hook( __FILE__, 'bbj_create_player_season_table' );
register_activation_hook( __FILE__, 'bbj_create_players_table' );
register_activation_hook( __FILE__, 'bbj_create_seasons_table' );

