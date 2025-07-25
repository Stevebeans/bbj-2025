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

define( 'BBJ_V2_PATH', plugin_dir_path( __FILE__ ) );
define( 'BBJ_V2_URL',  plugin_dir_url(  __FILE__ ) );

// Activation hook
function bbj_v2_activate() {
   // Reserved for future use, e.g. creating custom tables, flushing rewrite rules
}
register_activation_hook( __FILE__, 'bbj_v2_activate' );

// Deactivation hook
function bbj_v2_deactivate() {
    // Reserved for future use, e.g. cleanup transient data, flushing rewrite rules
}
register_deactivation_hook( __FILE__, 'bbj_v2_deactivate' );

// Include the main plugin file
require_once BBJ_V2_PATH . 'includes/bbj-plugin.php';