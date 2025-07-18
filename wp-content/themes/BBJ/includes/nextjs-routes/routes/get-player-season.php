<?php 

function player_season_rel() {
  global $wpdb;
  // pull the players from the database


  $rel_table = $wpdb->prefix . "bbj_play_season_rel";

  $query = $wpdb->prepare("SELECT * FROM $rel_table");

  bbj_log3(print_r('ifno', true));
  bbj_log3(print_r($query, true));

  try {
      $results = $wpdb->get_results($query, ARRAY_A);

      

      if ($results === null) {
          throw new Exception($wpdb->last_error);
      }

      if (empty($results)) {
          return new WP_REST_Response(['message' => 'No Relationships found'], 404);
      }


      return new WP_REST_Response($results, 200);
  } catch (Exception $e) {
      return new WP_Error('database_error', 'Database query failed: ' . $e->getMessage(), ['status' => 500]);
  }
}
