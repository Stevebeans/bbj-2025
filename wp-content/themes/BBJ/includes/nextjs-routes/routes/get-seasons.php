<?php 

function get_seasons() {
  global $wpdb;

  bbj_log3(print_r("GET SEASONS", true));

    // Define table names
    $season_table = $wpdb->prefix . "bbj_seasons";
    $relationship = $wpdb->prefix . "bbj_play_season_rel";
    $players = $wpdb->prefix . "bbj_players";

    // Prepare the SQL query
    $query = $wpdb->prepare("
        SELECT 
            s.*,
            CONCAT(p1.first_name, ' ', p1.last_name) as winner,
            CONCAT(p2.first_name, ' ', p2.last_name) as afp,
            CONCAT(p3.first_name, ' ', p3.last_name) as runner_up,
            p1.ID as winner_id,
            p2.ID as afp_id,
            p3.ID as runner_up_id
        FROM $season_table AS s
        LEFT JOIN $relationship AS r1 ON s.ID = r1.season_id AND r1.winner = 1
        LEFT JOIN $players AS p1 ON p1.ID = r1.player_id
        LEFT JOIN $relationship AS r2 ON s.ID = r2.season_id AND r2.afp = 1
        LEFT JOIN $players AS p2 ON p2.ID = r2.player_id
        LEFT JOIN $relationship AS r3 ON s.ID = r3.season_id AND r3.runner_up = 1
        LEFT JOIN $players AS p3 ON p3.ID = r3.player_id
        GROUP BY s.ID
        ORDER BY s.start_date DESC
    ");

  // Execute the query and handle potential errors
  try {
    $results = $wpdb->get_results($query, ARRAY_A);    

    $results_filtered = array_map(function($result) {
        $bannerID = $result['season_banner_image'];
        $player_banner = wp_get_attachment_image_src($bannerID, 'player-banner');
        // Check if the image URL exists and get the URL
        $player_banner_url = !empty($player_banner) ? $player_banner[0] : '';

        return [
            'full_name' => $result['full_name'],
            'start_date' => $result['start_date'],
            'end_date' => $result['end_date'],
            'ID' => $result['ID'],
            'season_banner_image' =>  $player_banner_url,
            'winner' => $result['winner'],
            'afp' => $result['afp'],
            'runner_up' => $result['runner_up'],
            'winner_id' => $result['winner_id'],
            'afp_id' => $result['afp_id'],
            'runner_up_id' => $result['runner_up_id'],
            'season_number' => $result['season_number'],
            'abbreviation' => $result['abbreviation'],
        ];
    }, $results);

    if ($results === null) {
        throw new Exception($wpdb->last_error);
    }

    if (empty($results)) {
        return new WP_REST_Response(['message' => 'No seasons found'], 404);
    }

    return new WP_REST_Response($results_filtered, 200);
  } catch (Exception $e) {
      return new WP_Error('database_error', 'Database query failed: ' . $e->getMessage(), ['status' => 500]);
  }
}
