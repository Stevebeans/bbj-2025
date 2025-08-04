<?php 

function bbj_v2_get_season_players($season_id) {
    global $wpdb;

    // Fetch players linked to the given season
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
          FROM " . BBJ_V2_TABLE_PLAYERS . "
         ORDER BY {$order_by} {$order}";

    $players = $wpdb->get_results($sql, ARRAY_A);

    bbj_log3(print_r($wpdb->last_query, true));

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
            FROM " . BBJ_V2_TABLE_PLAYERS . "
            WHERE id = %d",
            $player_id
        ),
        ARRAY_A
    );

    bbj_log3(print_r($wpdb->last_query, true));

    return $player;
}