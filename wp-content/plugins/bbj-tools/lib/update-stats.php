<?php 

// Update player stats

function update_player_stats($season) {

  bbj_log2(print_r('update stats function', true));
  //bbj_log2(print_r($season, true));
  

  global $wpdb;

  $weeks_db = 'wp_bbj_weeks_players';
  $update_db = 'wp_bbj_play_season_rel';

  $season_id = $season;

  $player_list = $wpdb->get_results("SELECT 
  player_id, 
  SUM(hoh) AS hoh_count,
  SUM(pov) AS pov_count,
  SUM(nom) AS nom_count,
  sum(active) AS active_count,
  SUM(saved) AS saved_count,
  SUM(veto_played) AS veto_played_count,
  SUM(misc_comp) AS misc_comp_count,
  (SELECT COUNT(*) FROM $weeks_db AS sub WHERE sub.voted_for = main.player_id AND sub.season_id = $season_id) AS total_votes
FROM $weeks_db AS main
WHERE season_id = $season_id
GROUP BY player_id");


  foreach ($player_list as $player):


    $playerID = $player->player_id;
    $hohTotal = $player->hoh_count;
    $povTotal = $player->pov_count;
    $nomTotal = $player->nom_count;
    $activeTotal = $player->active_count;
    $savedTotal = $player->saved_count;
    $vetoPlayedTotal = $player->veto_played_count;
    $miscTotal = $player->misc_comp_count;
    $totalCompWins = $hohTotal + $povTotal + $miscTotal;
    $totalVotes = $player->total_votes;
    $hoh_played = max($activeTotal - max($hohTotal - 1, 0), 0);
    $totalcompPlayed = $hoh_played + $vetoPlayedTotal;
    $save_per = get_average_two($savedTotal, $nomTotal);
    $pov_per = get_average_two($povTotal, $vetoPlayedTotal);
    $hoh_per = get_average_two($hohTotal, $hoh_played);
    $comp_per = get_average_two($totalCompWins, $totalcompPlayed);
    $votes_nom = get_average_two($totalVotes, $nomTotal);

    // bbj_log2(print_r($save_per, true));
     //bbj_log2(print_r($player, true));

    $fields_to_update = array(
      'total_hoh' => $hohTotal,
      'total_pov' => $povTotal,
      'total_nom' => $nomTotal,
      'total_weeks' => $activeTotal,
      'total_saved' => $savedTotal,
      'total_veto_played' => $vetoPlayedTotal,
      'total_comp' => $totalCompWins,
      'total_votes' => $totalVotes,
      'total_hoh_played' => $hoh_played,
      'save_per' => $save_per,
      'pov_per' => $pov_per,
      'hoh_per' => $hoh_per,
      'comp_per' => $comp_per,
      'votes_nom' => $votes_nom,
    );

    //bbj_log2(print_r($fields_to_update, true));

    // This updates the basic stats with no calculations
    update_stat_fields($playerID, $season_id, $update_db, $fields_to_update);

    // This gets the averag

  endforeach;

}

function get_average($val, $total) {
  if ($total == 0) {
    return '0';
  }
  return number_format(($val / $total), 3);
}

function get_average_two($val, $total) {
  if ($total == 0) {
    return '0';
  }
  return number_format(($val / $total), 2);
}


function update_stat_fields($player_id, $season_id, $table, $fields) {
  global $wpdb;

  //bbj_log2(print_r($fields, true));

  // Prepare data and format arrays
  $data = array();
  $data_format = array();

  foreach ($fields as $field => $value) {
      $data[$field] = $value;
      // Check if the value is a decimal and set the data format accordingly
      $data_format[] = strpos((string)$value, '.') !== false ? '%f' : '%d';
  }

  $result = $wpdb->update(
      $table,
      $data, // Data to update
      array('player_id' => $player_id, 'season_id' => $season_id), // Where clause
      $data_format, // Value data types
      array('%d', '%d') // player_id and season_id data types (integers)
  );

  // bbj_log2(print_r($fields, true));
  // bbj_log2(print_r($data_format, true));

  // Check if the update was successful
  if ($result === false) {
      // Log the error
      bbj_log2("Error updating fields for player_id: {$player_id} in season_id: {$season_id}");
      return false;
  }

  //bbj_log2(print_r($wpdb->last_query, true));

  return true;
}

