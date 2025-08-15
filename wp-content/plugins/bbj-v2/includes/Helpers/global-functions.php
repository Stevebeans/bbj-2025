<?php 

// Cache group + TTL (5m safety net)
if ( ! defined('BBJ_CACHE_GROUP') ) define('BBJ_CACHE_GROUP', 'bbj_v2');
if ( ! defined('BBJ_CACHE_TTL') )   define('BBJ_CACHE_TTL', 300);

// Helpers
function bbj_spoiler_bar_cache_key( $season_id, $is_admin ) {
    return sprintf('spoiler_bar_html:s%u:a%s', (int)$season_id, $is_admin ? '1' : '0');
}
function bbj_players_cache_key( $season_id, $size ) {
    return sprintf('season_players:s%u:size:%s', (int)$season_id, $size);
}
function bbj_spoiler_bar_bust_cache( $season_id ) {
    // bust both admin/non-admin HTML
    wp_cache_delete( bbj_spoiler_bar_cache_key($season_id, false), BBJ_CACHE_GROUP );
    wp_cache_delete( bbj_spoiler_bar_cache_key($season_id, true),  BBJ_CACHE_GROUP );
    // bust common players queries (add more sizes if you use them)
    wp_cache_delete( bbj_players_cache_key($season_id, 'bbj_v2_spoiler_bar'), BBJ_CACHE_GROUP );
    wp_cache_delete( bbj_players_cache_key($season_id, 'bbj_v2_profile_image'), BBJ_CACHE_GROUP );
}


function bbj_v2_get_season_players($season_id, $size = 'bbj_v2_profile_image') {
    global $wpdb;

    $pk = bbj_players_cache_key( (int)$season_id, (string)$size );
    if ( false !== ($players = wp_cache_get($pk, BBJ_CACHE_GROUP)) ) {
        return $players;
    }

    // 1) Fetch your players exactly like before
    $players = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *
             FROM " . BBJ_V2_TABLE_PLAYERS . " p
             INNER JOIN " . BBJ_V2_TABLE_LINKS . " l
               ON p.id = l.bbj_player
             WHERE l.bbj_season = %d
             ORDER BY l.bbj_evicted_date ASC",
            $season_id
        ),
        ARRAY_A
    );

    // 2) Loop & pull in the MetaBox image
    foreach ( $players as &$player ) {
        
        $post_id = $player['id'];
        $player_id = $player[ 'bbj_player' ];

        // get permalink 
        $player['permalink'] = get_permalink($player_id);

       // if $player['profile_picture' > 0] then look up the image
        if ( ! empty( $player['profile_picture'] ) && is_numeric( $player['profile_picture'] ) ) {
            $image = wp_get_attachment_image_src( $player['profile_picture'], $size );
           
            if ( $image ) {
                $player['profile_picture_url'] = $image[0]; // URL of the image
                $player['profile_picture_width'] = $image[1]; // Width of the image
                $player['profile_picture_height'] = $image[2]; // Height of the image
            } else {
                // If no image found, set to default or empty
                $player['profile_picture_url'] = '';
                $player['profile_picture_width'] = 0;
                $player['profile_picture_height'] = 0;
            }
        } else {
            // If no profile picture, set to default or empty
            $player['profile_picture_url'] = '';
            $player['profile_picture_width'] = 0;
            $player['profile_picture_height'] = 0;
        }

        
    }

    
    wp_cache_set($pk, $players, BBJ_CACHE_GROUP, BBJ_CACHE_TTL);
    return $players;
}



function bbj_v2_get_all_players($order_by = 'name', $order = 'ASC') {
    global $wpdb;

    // Validate order_by and order parameters
    $valid_order_by = ['name', 'first_name', 'last_name', 'id'];
    $valid_order = ['ASC', 'DESC'];

    if ( ! in_array($order_by, $valid_order_by, true) ) {
        $order_by = 'first_name'; // Default to first_name if invalid
    }

    if ( ! in_array($order, $valid_order, true) ) {
        $order = 'ASC'; // Default to ASC if invalid
    }

    // Prepare the ORDER BY clause
    $order_by = in_array( $order_by, $valid_order_by, true )
        ? $order_by
        : 'name';
    $order = strtoupper( $order );
    $order = in_array( $order, $valid_order, true )
        ? $order
        : 'ASC';

    $sql = "
        SELECT *
          FROM " . BBJ_V2_TABLE_PLAYERS . " bbjP
          LEFT JOIN " . BBJ_V2_TABLE_GEO . " bbjG ON bbjG.ID = bbjP.id
         ORDER BY {$order_by} {$order}";

    $players = $wpdb->get_results($sql, ARRAY_A);

    

    return $players;
}


function bbj_v2_get_player ($player_id) {
    global $wpdb;

    // Validate player_id
    if ( ! is_numeric($player_id) || $player_id <= 0 ) {
        return null; // Invalid player ID
    }


    // Fetch player data
    $player = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
            FROM " . BBJ_V2_TABLE_PLAYERS . " bbjp
            LEFT JOIN " . BBJ_V2_TABLE_GEO . " bbjg ON bbjg.ID = bbjp.id
            WHERE bbjp.id = %d",
            $player_id
        ),
        ARRAY_A
    );

    

    return $player;
}


function bbj_v2_get_seasons ($order_by = 'season_number', $order = 'DESC') {
    global $wpdb;

    // Validate order_by and order parameters
    $valid_order_by = ['season_number', 'full_name', 'start_date', 'end_date'];
    $valid_order = ['ASC', 'DESC'];

    if ( ! in_array($order_by, $valid_order_by, true) ) {
        $order_by = 'season_number'; // Default to season_number if invalid
    }

    if ( ! in_array($order, $valid_order, true) ) {
        $order = 'DESC'; // Default to DESC if invalid
    }

    // Prepare the ORDER BY clause
    $order_by = in_array( $order_by, $valid_order_by, true )
        ? $order_by
        : 'season_number';
    $order = strtoupper( $order );
    $order = in_array( $order, $valid_order, true )
        ? $order
        : 'DESC';

    $sql = "
        SELECT *
          FROM " . BBJ_V2_TABLE_SEASONS . "
         ORDER BY {$order_by} {$order}";

    $seasons = $wpdb->get_results($sql, ARRAY_A);

    

    return $seasons;
}

function bbj_v2_get_season_by_id($season_id) {
    global $wpdb;

    // Validate season_id
    if ( ! is_numeric($season_id) || $season_id <= 0 ) {
        return null; // Invalid season ID
    }

    // Fetch season data
    $season = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
            FROM " . BBJ_V2_TABLE_SEASONS . "
            WHERE id = %d",
            $season_id
        ),
        ARRAY_A
    );

    

    return $season;
}


// Helper Function for ads 

function bbj_get_ad( string $slot ): string {
    $opts = get_option('bbj_ads', []);
    return isset($opts[$slot]) ? (string) $opts[$slot] : '';
}

function bbj_echo_ad( string $slot ): void {
    // Hide based on user role
    if ( bbj_user_has_role( 'v2Supporter' ) || bbj_user_has_role( 'administrator' ) ) {
        return; 
    }

    $code = bbj_get_ad($slot);
    if ($code) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $code;
    }
}

function bbj_echo_code( string $slot ): void {
    
    $code = bbj_get_ad($slot);
    if ( $code ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $code;
    }
}


function bbj_user_has_role( string $role ): bool {
    $user = wp_get_current_user();
    return in_array( $role, (array) $user->roles, true );
}