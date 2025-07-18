<?php 


function nxt_add_player_to_season($request) {
  global $wpdb;

  // get player_id and season_id
  $player_id = $request['player_id'];
  $season_id = $request['season_id'];

  // check if player is already in the season
  $relationship_table = $wpdb->prefix . 'bbj_season_players';

  $check = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $relationship_table WHERE player_id = %d AND season_id = %d", $player_id, $season_id));

  if ($check > 0) {
      return new WP_Error('player_exists', 'Player already exists in the season', array('status' => 400));
  }
  

  // Insert player into the season
  $inserted = $wpdb->insert(
      $relationship_table,
      array(
          'player_id' => $player_id,
          'season_id' => $season_id
      ),
      array('%d', '%d')
  );

  if ($inserted === false) {
      return new WP_Error('database_error', 'Failed to insert player into season', array('status' => 500));
  }

  return new WP_REST_Response('Player added to season successfully', 200);
}