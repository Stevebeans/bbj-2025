<?php 

add_filter('breeze_response_headers', function($headers) {
  if ( ! is_user_logged_in() && is_front_page() ) {
    // 0s browser TTL, 600s Varnish TTL
    $headers['Cache-Control'] = 'public, max-age=0, s-maxage=600';
  }
  return $headers;
});


function currentSeason($type) {
    $current_season = get_option('current_season');

    //bbj_log2(print_r($current_season, true));

    if (!is_array($current_season)) {
        return "Invalid season data";
    }

    switch ($type) {
        case 'name':
            return isset($current_season['full_name']) ? $current_season['full_name'] : '';
        case 'small_name':
            return isset($current_season['abbreviation']) ? $current_season['abbreviation'] : '';
        case 'season_number':
            return isset($current_season['season_number']) ? intval($current_season['season_number']) : 0;
        case 'ID':
            return isset($current_season['ID']) ? intval($current_season['ID']) : 0;
        default:
            return $current_season;
    }
}

function evicted_check($evict, $week_end) {
    

    if ($evict >= $week_end) {
        return true;
    } else {
        return false;
    }
}

function bbj_log2($log_msg) {
    if (is_array($log_msg)) {
      $log_msg = print_r($log_msg, true);
    }
  
    $log_filename = dirname(__FILE__) . '/../logs/';
  
    if (!file_exists($log_filename)) {
      // create directory/folder uploads.
      mkdir($log_filename, 0777, true);
    }
    $log_file_data = $log_filename . "/log_" . date("M-Y") . ".log";
  
    file_put_contents($log_file_data, $log_msg . "\n", FILE_APPEND);
  }


  

function add_week($info, $season_id) {
  global $wpdb;

  $weekNum = $info['week'];
  $weekStart = $info['start_date'];
    $weekEnd = $info['end_date'];

  //bbj_log2('add_week');
  //bbj_log2(print_r($info, true));

  if ($weekNum && $weekStart && $weekEnd) {
      $wpdb->insert(
          $wpdb->prefix . 'bbj_weeks',
          array(
              'season_id' => $season_id,
              'week_num' => $weekNum,
              'start_date' => $weekStart,
              'end_date' => $weekEnd,
          )
      );


      $week_id = $wpdb->insert_id;

        //   // Insert records into wp_bbj_weeks_players for each player in the season
        $query = $wpdb->prepare("
        SELECT rel.player_id, rel.season_id, rel.evict_date, weeks.end_date
        FROM {$wpdb->prefix}bbj_play_season_rel AS rel
        LEFT JOIN {$wpdb->prefix}bbj_weeks AS weeks ON weeks.ID = %d
        WHERE rel.season_id = %d
    ", $week_id, $season_id);
    
    $players = $wpdb->get_results($query);
    


    

        // bbj_log2('players');
        // bbj_log2(print_r($players, true));
        // bbj_log2(print_r('qquery', true));
        // bbj_log2(print_r($wpdb->last_query, true));

        foreach ($players as $player) {
        $wpdb->insert($wpdb->prefix . 'bbj_weeks_players', array(
            'week_id' => $week_id,
            'player_id' => $player->player_id,
            'season_id' => $season_id,
        ));
        }


  }



}





function delete_week($week_id, $season_id) {
    global $wpdb;

    // Delete records from wp_bbj_weeks_players for the given week
    $wpdb->delete($wpdb->prefix . 'bbj_weeks_players', array('week_id' => $week_id));

    // Delete the week from wp_bbj_weeks
    $wpdb->delete($wpdb->prefix . 'bbj_weeks', array('id' => $week_id, 'season_id' => $season_id));
}

  

function update_relationship_entries($entries, $player_array) {
  global $wpdb;

  $updated_rows = 0;

  foreach ($entries as $entry) {
      $player_id = $entry->player_id;
      $finish = isset($_POST['finish_' . $player_id]) ? $_POST['finish_' . $player_id] : 0;
      $winner = isset($_POST['winner_' . $player_id]) ? 1 : 0;
      $runner_up = isset($_POST['runner_up_' . $player_id]) ? 1 : 0;
      $afp = isset($_POST['afp_' . $player_id]) ? 1 : 0;
      $evicted = isset($_POST['evicted_' . $player_id]) ? 1 : 0;
      $jury = isset($_POST['jury_' . $player_id]) ? 1 : 0;
      $evict_date = isset($_POST['evict_date_' . $player_id]) ? $_POST['evict_date_' . $player_id] : '';

      //bbj_log2(print_r($entry, true));

      $result = $wpdb->update(
          $wpdb->prefix . 'bbj_play_season_rel',
          array(
                'finish' => $finish,
              'winner' => $winner,
              'runner_up' => $runner_up,
              'afp' => $afp,
              'evicted' => $evicted,
              'jury' => $jury,
                'evict_date' => $evict_date,
          ),
          array(
              'player_id' => $player_id,
              'season_id' => $entry->season_id,
          )
      );

      if ($result === false) {
          echo 'Error: Unable to update player-season relationship for player ID ' . $player_id . '<br>';
      } else {
          $updated_rows += $result;
      }
  }

  if ($updated_rows > 0) {
      echo 'Player-season relationships updated successfully!';
  }

  // Redirect back to the same page after updating the database
header("Location: ".$_SERVER['REQUEST_URI']);
exit;
}



function delete_relationship_entry($deleteID) {

  global $wpdb;
    $entry_id = intval($deleteID);
    if ($wpdb->delete($wpdb->prefix . 'bbj_play_season_rel', array('ID' => $entry_id))) {
        $message = 'User deleted';
    } else {
        $message = 'Error deleting user';
    }
    wp_redirect($_SERVER['REQUEST_URI'] . '&message=' . urlencode($message));
    exit;
    
}





function add_player_season_relationship($season_id) {
  if (isset($_POST['submit']) && current_user_can('manage_options')) {
      // Get form data
      $player_id = isset($_POST['player']) ? intval($_POST['player']) : 0;
      $winner = isset($_POST['winner']) ? 1 : 0;
      $runner_up = isset($_POST['runner_up']) ? 1 : 0;
      $afp = isset($_POST['afp']) ? 1 : 0;
      $evicted = isset($_POST['evicted']) ? 1 : 0;
      $jury = isset($_POST['jury']) ? 1 : 0;

      // Save to database
      global $wpdb;
      if ($wpdb->insert(
          $wpdb->prefix . 'bbj_play_season_rel',
          array(
              'player_id' => $player_id,
              'season_id' => $season_id,
              'winner' => $winner,
              'runner_up' => $runner_up,
              'afp' => $afp,
              'evicted' => $evicted,
              'jury' => $jury,
          ),
          array(
              '%d',
              '%d',
              '%d',
              '%d',
              '%d',
              '%d',
              '%d',
          )
      )) {
          // Success message
          $message = 'Player-Season relationship added successfully';
      } else {
          // Error message
          $message = 'Error adding player-season relationship';
      }

      // Redirect back to page
      wp_redirect($_SERVER['REQUEST_URI'] . '&message=' . urlencode($message));
      exit;
  }

  // Display success or error message
  if (isset($_GET['message'])) {
      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($_GET['message']) . '</p></div>';
  }
}

function get_season($season_id) {
    global $wpdb;

    $season = $wpdb->get_row($wpdb->prepare("
        SELECT *
        FROM {$wpdb->prefix}bbj_seasons
        WHERE id = %d   
    ", $season_id));

    return $season;
}

