<?php
/**
 * Plugin Name: BBJ Tools
 * Description: A collection of tools for managing Big Brother reality show data.
 */

// Create the admin menu item



define('BBJ_TOOLS_VERSION', '1.0.5');
define( 'PLUGIN_ROOT', plugin_dir_path( __FILE__ ) );
define('LIB_PATH', PLUGIN_ROOT . 'lib/');
define ('API_PATH', LIB_PATH . 'api/');
define ('VIEWS_PATH', PLUGIN_ROOT . 'views/');
define ('MB_PATH', LIB_PATH . 'metabox/');
require_once PLUGIN_ROOT. '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__, 'bbj.env');

try {
    $dotenv->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    error_log('Unable to load bbj.env file: ' . $e->getMessage());
}


require_once(LIB_PATH . 'create-tables.php');
require_once(LIB_PATH . 'global-variables.php');
require_once(LIB_PATH . 'functions.php');
require_once(LIB_PATH . 'small-functions.php');
require_once(LIB_PATH . 'photo-uploads.php');
require_once(LIB_PATH . 'add-fields.php');
require_once(API_PATH . 'stripe.php');
require_once(API_PATH . 'paypal.php');
require_once(API_PATH . 'routes.php');
require_once(LIB_PATH . 'refresh-cache.php');
require_once(LIB_PATH . 'blocks/feed-updates.php');
require_once(LIB_PATH . 'user-settings.php');

require_once(LIB_PATH . '/shortcodes/front-page-stats.php');


// Create metaboxes
require_once(MB_PATH . 'players.php');


function bbj_tools_menu() {
  add_menu_page(
      'BBJ Tools',
      'BBJ Tools',
      'manage_options',
      'bbj-tools',
      'bbj_tools_page'
  );
  add_submenu_page(
      'bbj-tools',
      'Seasons',
      'Seasons',
      'manage_options',
      'bbj-tools-seasons',
      'showSeasons'
  );

  add_submenu_page(
    'bbj-tools',
    'Edit Player Seasons',
    'Edit Player Seasons',
    'manage_options',
    'bbj-tools-edit-player-seasons',
    'editPlayerSeasons'
);

}

// Function to enqueue CSS in block editor
function my_plugin_enqueue_block_editor_assets() {
  wp_enqueue_style( 'my-plugin-tailwind-editor', plugins_url( '/build/index-style.css', __FILE__ ), array(), BBJ_TOOLS_VERSION );
}
add_action( 'enqueue_block_editor_assets', 'my_plugin_enqueue_block_editor_assets' );

// Function to enqueue CSS on front end where the block is used
function my_plugin_enqueue_block_frontend_assets() {
    global $post; // Ensure we have access to the global $post object
    $screen = get_current_screen();

    if ( isset( $post ) && has_block( 'my-plugin/new-feed-updates', $post ) ) {
        wp_enqueue_style( 'my-plugin-tailwind-frontend', plugins_url( '/build/index-style.css', __FILE__ ), array(), BBJ_TOOLS_VERSION );
    }
}
add_action( 'wp_enqueue_scripts', 'my_plugin_enqueue_block_frontend_assets' );


function enqueue_google_maps_api() {
  global $pagenow;

  // Check if it's the admin page and our specific page parameter
  if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'bbj-tools') {
      wp_enqueue_script('google-maps-api', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyC_shPNS0EYeHXEIKFKhZDLhzpgoUphjts&libraries=places', array(), null, true);
  }
}
add_action('admin_enqueue_scripts', 'enqueue_google_maps_api');


function bbj_tools_enqueue_styles() {
  wp_enqueue_style('formidable', 'https://cdn.formidableforms.com/formidable/4.10.2/styles/formidable.min.css', array(), BBJ_TOOLS_VERSION);
  wp_enqueue_style('bbj-basic', plugin_dir_url(__FILE__) . 'views/style-basic.css', array(), BBJ_TOOLS_VERSION);
  wp_enqueue_script(
    'bbj-basic', // Handle
    plugin_dir_url(__FILE__) . 'build/index.js', // Source
    array('jquery', 'wp-element', 'wp-blocks', 'wp-api-fetch'), // Dependencies
    BBJ_TOOLS_VERSION, // Version
    true // In footer
);



  

    $screen = get_current_screen();

    

    //bbj_log2(print_r($screen, true));
    if ( $screen->base === 'toplevel_page_bbj-tools' ) {
     // wp_enqueue_style( 'bbj-tw', plugin_dir_url( __FILE__ ) . 'dist/style.css' );
     wp_enqueue_style('bbj-v3', 
     plugin_dir_url(__FILE__) . 'tailwind/index-style.css', 
      array(),
      BBJ_TOOLS_VERSION
    );

  }
    
}
add_action('admin_enqueue_scripts', 'bbj_tools_enqueue_styles');



add_action('admin_menu', 'bbj_tools_menu' ,4);


// Load the plugin page content
function bbj_tools_page() {
  require_once VIEWS_PATH . 'bbj-tools.php';
}

// Load the seasons page content
function showSeasons() {
  require_once VIEWS_PATH . 'seasons.php';
}

// Load the edit-player-season page content
function editPlayerSeasons() {
  require_once VIEWS_PATH . 'edit-player-season.php';
}

// function bbj_log3($log_msg) {
//   if (is_array($log_msg)) {
//     $log_msg = print_r($log_msg, true);
//   }

//   $log_filename = $_SERVER["DOCUMENT_ROOT"] . "/wp-content/themes/BBJ/logs";

//   if (!file_exists($log_filename)) {
//     // create directory/folder uploads.
//     mkdir($log_filename, 0777, true);
//   }
//   $log_file_data = $log_filename . "/log_" . date("M-Y") . ".log";

//   file_put_contents($log_file_data, $log_msg . "\n", FILE_APPEND);
// }