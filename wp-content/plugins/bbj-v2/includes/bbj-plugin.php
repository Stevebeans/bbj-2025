<?php 

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// include helpers which has the log 

// include global variables
require_once BBJ_V2_INCLUDES . 'Helpers/global-vars.php';
// include helpers
require_once BBJ_V2_INCLUDES . 'Helpers/global-functions.php';

// Include Logger
require_once BBJ_V2_INCLUDES . 'Helpers/bbj-log.php';
require_once BBJ_V2_INCLUDES . 'Helpers/create-db-tables.php';


// include post types
require_once BBJ_V2_INCLUDES . 'PostTypes/FeedUpdates.php';
require_once BBJ_V2_INCLUDES . 'PostTypes/Players.php';
require_once BBJ_V2_INCLUDES . 'PostTypes/Seasons.php';
require_once BBJ_V2_INCLUDES . 'PostTypes/PlayerSeasonLink.php';

// include admin menu 
require_once BBJ_V2_INCLUDES . 'Helpers/admin-menu.php';

// include routes
require_once BBJ_V2_ROUTES   . 'Search/search-route.php';
require_once BBJ_V2_ROUTES   . 'feed-update.php';

// include actions 
require_once BBJ_V2_INCLUDES . 'Actions/action-list.php';

// include shortcodes
require_once BBJ_V2_INCLUDES . 'Public/shortcodes/spoiler-bar.php';
require_once BBJ_V2_INCLUDES . 'Public/shortcodes/summary-table.php';
require_once BBJ_V2_INCLUDES . 'Public/shortcodes/standings-table.php';
require_once BBJ_V2_INCLUDES . 'Public/shortcodes/hot-posts.php';

function bbj_is_aioseo_active() {
  return function_exists('aioseo') || defined('AIOSEO_VERSION');
}
