<?php
/**
 * Plugin Name: Big Brother Junkies Data
 * Plugin URI: https://bigbrotherjunkies.com
 * Description: Core data management plugin for Big Brother Junkies
 * Version: 1.0.0
 * Author: Big Brother Junkies
 * Author URI: https://bigbrotherjunkies.com
 * License: GPL-2.0-or-later
 * Text Domain: bigbrotherjunkies-data
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('BBJD_VERSION', '1.0.0');
define('BBJD_PATH', plugin_dir_path(__FILE__));
define('BBJD_URL', plugin_dir_url(__FILE__));
define('BBJD_FILE', __FILE__);

// Load Composer autoloader
if (file_exists(BBJD_PATH . 'vendor/autoload.php')) {
    require_once BBJD_PATH . 'vendor/autoload.php';
}

// Bootstrap the plugin
add_action('plugins_loaded', function () {
    \BigBrotherJunkies\Data\Plugin::getInstance()->init();
});
