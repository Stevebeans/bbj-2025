<?php
/**
 * BBJ v2 Theme bootstrap.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BBJ_V2_THEME_PATH', get_template_directory());
define('BBJ_V2_THEME_URL',  get_template_directory_uri());
define('BBJ_V2_THEME_VERSION', '1.0.0');

// Optional composer autoload — only if composer install has been run
if (file_exists(BBJ_V2_THEME_PATH . '/vendor/autoload.php')) {
    require_once BBJ_V2_THEME_PATH . '/vendor/autoload.php';
}

require_once BBJ_V2_THEME_PATH . '/inc/setup.php';
require_once BBJ_V2_THEME_PATH . '/inc/enqueue.php';
require_once BBJ_V2_THEME_PATH . '/inc/template-functions.php';
require_once BBJ_V2_THEME_PATH . '/inc/dark-mode.php';
require_once BBJ_V2_THEME_PATH . '/inc/auth.php';
require_once BBJ_V2_THEME_PATH . '/inc/homepage-data.php';
require_once BBJ_V2_THEME_PATH . '/inc/admin-shell.php';
