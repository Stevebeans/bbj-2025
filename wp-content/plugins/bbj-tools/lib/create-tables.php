<?php
/**
 * Plugin Name: BBJ Tools
 * Description: A collection of tools for managing Big Brother reality show data.
 */

// Create the admin menu item


function create_bbj_relationships_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bbj_play_season_rel';

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) NOT NULL PRIMARY KEY AUTO_INCREMENT,
        player_id BIGINT(20) NOT NULL,
        season_id BIGINT(20) NOT NULL,
        winner BOOLEAN NOT NULL DEFAULT 0,
        runner_up BOOLEAN NOT NULL DEFAULT 0,
        afp BOOLEAN NOT NULL DEFAULT 0,
        evicted BOOLEAN NOT NULL DEFAULT 0,
        jury BOOLEAN NOT NULL DEFAULT 0,
        evict_date DATE NOT NULL DEFAULT '0000-00-00'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        echo "<p>Table '$table_name' created successfully.</p>";
    } else {
        echo "<p>Error creating table '$table_name'.</p>";
    }

}


function create_feed_ratings_table() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'bbj_feed_ratings';

  $sql = "CREATE TABLE $table_name (
    id BIGINT(20) NOT NULL PRIMARY KEY AUTO_INCREMENT,
    update_id INT NOT NULL,
    user_id BIGINT NOT NULL,
    rating INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    
    INDEX idx_update_id (update_id),
    INDEX idx_user_id (user_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci";


  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);

  if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
      echo "<p>Table '$table_name' created successfully.</p>";
  } else {
      echo "<p>Error creating table '$table_name'.</p>";
  }

}




function create_bbj_weeks_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bbj_weeks';
  
    $sql = "CREATE TABLE $table_name (
      id BIGINT(20) NOT NULL PRIMARY KEY AUTO_INCREMENT,
      season_id BIGINT(20) NOT NULL,
      week_num INT NOT NULL,
      start_date DATE NOT NULL,
      end_date DATE NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci";
  
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
  
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        echo "<p>Table '$table_name' created successfully.</p>";
    } else {
        echo "<p>Error creating table '$table_name'.</p>";
    }
  }


  function create_bbj_week_rel_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bbj_weeks_players';
  
    $sql = "CREATE TABLE $table_name (
      id BIGINT(20) NOT NULL PRIMARY KEY AUTO_INCREMENT,
      player_id BIGINT(20) NOT NULL,
      season_id BIGINT(20) NOT NULL,
      week_id INT NOT NULL,
      hoh BOOLEAN NOT NULL DEFAULT 0,
      pov BOOLEAN NOT NULL DEFAULT 0,
      nom BOOLEAN NOT NULL DEFAULT 0,
      saved BOOLEAN NOT NULL DEFAULT 0,
      evicted BOOLEAN NOT NULL DEFAULT 0,
      veto_played BOOLEAN NOT NULL DEFAULT 0,
      voted_for BIGINT(20) NOT NULL DEFAULT 0,
      vote_to_win BIGINT(20) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci";
  
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
  
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        echo "<p>Table '$table_name' created successfully.</p>";
    } else {
        echo "<p>Error creating table '$table_name'.</p>";
    }
  }



  // Create the BBJ Nominations table
function create_bbj_nominations_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bbj_nominations';
  
    $sql = "CREATE TABLE $table_name (
      nomination_id BIGINT(20) NOT NULL AUTO_INCREMENT,
      week_id BIGINT(20) NOT NULL,
      player_id BIGINT(20) NOT NULL,
      is_post_veto BOOLEAN NOT NULL DEFAULT FALSE,
      PRIMARY KEY (nomination_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci";
  
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
  
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        echo "<p>Table '$table_name' created successfully.</p>";
    } else {
        echo "<p>Error creating table '$table_name'.</p>";
    }
  }
  
  
  // Delete the BBJ Weeks table
  function delete_bbj_weeks_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bbj_weeks';
  
    $sql = "DROP TABLE IF EXISTS $table_name";
    $wpdb->query($sql);
  
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        echo "<p>Error deleting table '$table_name'.</p>";
    } else {
        echo "<p>Table '$table_name' deleted successfully.</p>";
    }
  }
  
  // Delete the BBJ Nominations table
  function delete_bbj_nominations_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bbj_nominations';
  
    $sql = "DROP TABLE IF EXISTS $table_name";
    $wpdb->query($sql);
  
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        echo "<p>Error deleting table '$table_name'.</p>";
    } else {
        echo "<p>Table '$table_name' deleted successfully.</p>";
    }
  }
