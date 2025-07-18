<?php 

function update_week($data, $season_id) {
  global $wpdb;

  // Sanitize and prepare the data
  $week_id = intval($data['week_id']);
  $week_num = intval($data['week_num']);
  $start_date = sanitize_text_field($data['start_date']);
  $end_date = sanitize_text_field($data['end_date']);

  // Check if the required data is present
  if (!$week_id || !$week_num || !$start_date || !$end_date) {
      return false;
  }

  $weeks_table = $wpdb->prefix . 'bbj_weeks';

  // Update the week in the database
  $wpdb->update(
      $weeks_table,
      array(
          'week_num' => $week_num,
          'start_date' => $start_date,
          'end_date' => $end_date
      ),
      array(
          'id' => $week_id,
          'season_id' => $season_id
      ),
      array(
          '%d', // week_num
          '%s', // start_date
          '%s'  // end_date
      ),
      array('%d', '%d') // id and season_id
  );
}
