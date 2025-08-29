<?php

define("BBJ_THEME_VERSION", "3.2.9");
define("BBJ_ROOT", dirname(__FILE__));
define("BBJ_INCLUDES", BBJ_ROOT . "/includes");
define("BBJ_IMAGES", get_theme_file_uri("/images"));
define("BBJ_MB_FILES", BBJ_INCLUDES . "/MB");
define("BBJ_THEME_URL", get_theme_file_uri());
define("BBJ_THEME_PATH", get_theme_file_uri() . "/");
define("BBJ_THEME_DIST_PATH", BBJ_THEME_PATH . "build/");
define("BBJ_THEME_DIST_URL", BBJ_THEME_URL . "/build/");
define("BBJ_THEME_INC", BBJ_THEME_PATH . "includes/");
define("BBJ_THEME_BLOCK_DIR", BBJ_THEME_INC . "blocks/");
define('BBJ_ADMIN_ROUTES', BBJ_INCLUDES . '/nextjs-routes/routes');

require_once BBJ_INCLUDES . "/core.php";
require_once BBJ_INCLUDES . "/director.php";
require_once BBJ_INCLUDES . "/freestarads.php";

require "includes/cpt.php";
require "includes/breadcrumbs.php";
require "includes/routes.php";
//require "includes/search-route.php";
require 'includes/nextroutes.php';
require 'includes/nextjs-routes/admin-routes.php';
require "includes/custom-jwt-functions.php";
require 'includes/ads.php';

//Include Metabox Files
require_once BBJ_MB_FILES . "/create-tables.php";
//require_once BBJ_MB_FILES . "/relationships.php";
require_once BBJ_MB_FILES . "/custom-blocks.php";

// Testing Github

// Load general scripts
function load_assets()
{
  wp_enqueue_style("needed", BBJ_THEME_PATH . "style.css", [], BBJ_THEME_VERSION);
  wp_enqueue_script("frontend", BBJ_THEME_DIST_PATH . "index.js", ["jquery", "wp-element"], BBJ_THEME_VERSION, true);
  wp_enqueue_style("frontend", BBJ_THEME_DIST_PATH . "index.css", [], BBJ_THEME_VERSION);
  wp_enqueue_style("tailwind", BBJ_THEME_DIST_PATH . "index-style.css", [], BBJ_THEME_VERSION);
  wp_enqueue_style("font-awesome", "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css");

  wp_localize_script("frontend", "playerData", [
    "root_url" => get_site_url(),
    "nonce" => wp_create_nonce("wp_rest"),
  ]);
}

add_action("wp_enqueue_scripts", "load_assets");

function admin_includes()
{
  wp_register_style("bbj-admin-css", get_template_directory_uri() . "/includes/blocks/feed-updates/style.css", [], BBJ_THEME_VERSION);
  wp_enqueue_style("bbj-admin-css");
}
add_action("admin_enqueue_scripts", "admin_includes");

// Create Menu
function bbj_menu()
{
  register_nav_menus([
    "bbj-main-menu" => _("Main Menu"),
    "bbj-secondary-menu" => _("Second Menu"),
    "bbj-footer-menu" => _("Footer Menu"),
  ]);
}
add_action("init", "bbj_menu");



// Load admin scripts
function load_admin_scripts()
{
}

// Add options page
if (function_exists("acf_add_options_page")) {
  acf_add_options_page();
}

// always update values of all bidirectional fields
add_filter("acfe/bidirectional/force_update", "__return_true");

// or target a specific field only
add_filter("acfe/bidirectional/force_update/name=my_field", "__return_true");

// if (function_exists("register_sidebar")) {
//   register_sidebar();
// }

add_filter('comment_flood_filter', '__return_false');




// v2 INCLUDES 
define ('BBJ_V2', BBJ_INCLUDES . '/V2');




function get_google_db_connection() {
  $autoload_path = __DIR__ . '/vendor/autoload.php';


  if (file_exists($autoload_path)) {
      require_once $autoload_path;

      $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
      $dotenv->load();


      // get the environment variab

      $host = $_ENV['GOOGLE_DB_HOST'];
      $user = $_ENV['GOOGLE_DB_USER'];
      $password = $_ENV['GOOGLE_DB_PASSWORD'];
      $dbname = $_ENV['GOOGLE_DB_NAME'];
      $port = $_ENV['GOOGLE_DB_PORT'];


   

      if ($host && $user && $password && $dbname) {
     
          
          $conn = @new mysqli($host, $user, $password, $dbname, $port);

          if ($conn->connect_error) {
              bbj_log3('Connection failed: (' . $conn->connect_errno . ') ' . $conn->connect_error);
              return null;
          } else {
              
              return $conn;
          }
      } else {
          bbj_log3('Environment variables not loaded correctly');
      }
  } else {
      bbj_log3('autoload.php file does not exist');
  }

  return null;
}


add_action('wp_head', function () {
    if (!is_front_page()) return;

    global $hero_id;
    if (!$hero_id) return;

    $src    = wp_get_attachment_image_url(get_post_thumbnail_id($hero_id), 'bbj_v2_index_mobile');
    $srcset = wp_get_attachment_image_srcset(get_post_thumbnail_id($hero_id), 'bbj_v2_index_mobile');

    if ($src): ?>
      <link rel="preload"
            as="image"
            href="<?= esc_url($src) ?>"
            imagesrcset="<?= esc_attr($srcset) ?>"
            imagesizes="100vw"
            fetchpriority="high"
            media="(max-width: 767px)">
    <?php endif;
});

