<?php 

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// include helpers which has the log 


define( 'BBJ_V2_INCLUDES', BBJ_V2_PATH . 'includes/' );

// Include Logger
require_once BBJ_V2_INCLUDES . 'Helpers/bbj-log.php';
require_once BBJ_V2_INCLUDES . 'Helpers/create-db-tables.php';


// include post types
require_once BBJ_V2_INCLUDES . 'PostTypes/FeedUpdates.php';
require_once BBJ_V2_INCLUDES . 'PostTypes/Players.php';
require_once BBJ_V2_INCLUDES . 'PostTypes/Seasons.php';
require_once BBJ_V2_INCLUDES . 'PostTypes/PlayerSeasonLink.php';


// include public pages (shortcodes)
