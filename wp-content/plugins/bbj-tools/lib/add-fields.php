<?php 

function add_custom_columns_to_bbj_play_season_rel() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'bbj_play_season_rel';

  $charset_collate = $wpdb->get_charset_collate();

  $column_names = [
      'total_hoh' => 'INT',
      'total_pov' => 'INT',
      'total_nom' => 'INT',
      'total_saved' => 'INT',
      'total_weeks' => 'INT',
      'total_votes' => 'INT',
      'total_comp' => 'INT',
      'total_comp_played' => 'INT',
      'total_veto_played' => 'INT',
      'total_hoh_played' => 'INT',
      'save_per' => 'DECIMAL(4,2)',
      'hoh_per' => 'DECIMAL(4,2)',
      'pov_per' => 'DECIMAL(4,2)',
      'comp_per' => 'DECIMAL(4,2)',
      'votes_nom' => 'DECIMAL(4,2)',
        'finish' => 'INT',
  ];

  $alter_statements = [];
  foreach ($column_names as $column_name => $column_type) {
      $column_exists = $wpdb->get_var($wpdb->prepare("
          SELECT COUNT(*) 
          FROM information_schema.COLUMNS 
          WHERE TABLE_SCHEMA = %s
          AND TABLE_NAME = %s
          AND COLUMN_NAME = %s
      ", DB_NAME, $table_name, $column_name));

      if (!$column_exists) {
          $alter_statements[] = "ADD COLUMN {$column_name} {$column_type}";
      }
  }

  if (empty($alter_statements)) {
      return true; // All columns already exist, nothing to do
  }

  $sql = "ALTER TABLE {$table_name} " . implode(", ", $alter_statements) . ";";

  $result = $wpdb->query($sql);

  // Check if the query was successful
  if ($result !== false) {
      return true; // Columns were added successfully
  } else {
      return false; // There was an error
  }
}
