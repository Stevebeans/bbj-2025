<?php
// in bbj-tools/index.php (or your main plugin bootstrap file)

/**
 * Create (or update) the wp_bbj_v2_player_season table.
 */
function bbj_create_player_season_table() {
    global $wpdb;

    // 2) Table name with WP prefix
    $table_name      = $wpdb->prefix . 'bbj_v2_player_season';
    $charset_collate = $wpdb->get_charset_collate();

    // 3) SQL for table creation
    $sql = "CREATE TABLE {$table_name} (
        id                      BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id                 BIGINT(20) UNSIGNED NOT NULL,
        bbj_season              BIGINT(20) UNSIGNED NOT NULL,
        bbj_player              BIGINT(20) UNSIGNED NOT NULL,
        bbj_evicted_date        DATE             DEFAULT NULL,
        finish_place            TINYINT(3) UNSIGNED DEFAULT NULL,
        bbj_total_hoh           INT(11)          NOT NULL DEFAULT 0,
        bbj_total_pov           INT(11)          NOT NULL DEFAULT 0,
        bbj_total_nom           INT(11)          NOT NULL DEFAULT 0,
        bbj_total_havenot       INT(11)          NOT NULL DEFAULT 0,
        bbj_total_saved         INT(11)          NOT NULL DEFAULT 0,
        bbj_total_misc          INT(11)          NOT NULL DEFAULT 0,
        bbj_votes_received      INT(11)          NOT NULL DEFAULT 0,
        bbj_veto_played         INT(11)          NOT NULL DEFAULT 0,
        current_hoh             TINYINT(1)       NOT NULL DEFAULT 0,
        current_pov             TINYINT(1)       NOT NULL DEFAULT 0,
        current_nom             TINYINT(1)       NOT NULL DEFAULT 0,
        current_havenot         TINYINT(1)       NOT NULL DEFAULT 0,
        current_evicted         TINYINT(1)       NOT NULL DEFAULT 0,
        current_misc            TINYINT(1)       NOT NULL DEFAULT 0,
        current_jury            TINYINT(1)       NOT NULL DEFAULT 0,
        current_safe            TINYINT(1)       NOT NULL DEFAULT 0,
        misc_notes              VARCHAR(255)     DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY post_id    (post_id),
        KEY bbj_season (bbj_season),
        KEY bbj_player (bbj_player)
    ) {$charset_collate};";

    // 4) Load WP upgrade functions and run dbDelta
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}



/**
 * Create (or update) the wp_bbj_players table.
 */
function bbj_create_players_table() {
    global $wpdb;

    // 1) Build the table name with your WP prefix
    $table_name      = $wpdb->prefix . 'bbj_players';
    // 2) Grab the right charset/collation
    $charset_collate = $wpdb->get_charset_collate();

    // 3) Define the SQL
    $sql = "CREATE TABLE {$table_name} (
        id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id             BIGINT(20) UNSIGNED NOT NULL,
        first_name          VARCHAR(255)        NOT NULL,
        last_name           VARCHAR(255)        NOT NULL,
        official_nickname   VARCHAR(255)        NOT NULL,
        player_gender       VARCHAR(20)         NOT NULL,
        profile_picture     BIGINT(20) UNSIGNED DEFAULT NULL,
        player_banner       BIGINT(20) UNSIGNED DEFAULT NULL,
        date_of_birth       DATE                DEFAULT NULL,
        occupation          VARCHAR(255)        DEFAULT '',
        facebook            VARCHAR(255)        DEFAULT '',
        instagram           VARCHAR(255)        DEFAULT '',
        twitter             VARCHAR(255)        DEFAULT '',
        tiktok              VARCHAR(255)        DEFAULT '',
        PRIMARY KEY  (id),
        KEY post_id  (post_id)
    ) {$charset_collate};";

    // 4) Load WP’s dbDelta and run it
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}


/**
 * Create (or update) the wp_bbj_seasons table.
 */
function bbj_create_seasons_table() {
    global $wpdb;

    // 1) Build the table name
    $table_name      = $wpdb->prefix . 'bbj_seasons';
    // 2) Get the proper charset/collation
    $charset_collate = $wpdb->get_charset_collate();

    // 3) Define the SQL to match your meta‑box fields
    $sql = "CREATE TABLE {$table_name} (
        id                      BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id                 BIGINT(20) UNSIGNED NOT NULL,
        full_name               VARCHAR(255)        NOT NULL,
        start_date              DATE                DEFAULT NULL,
        end_date                DATE                DEFAULT NULL,
        abbreviation            VARCHAR(50)         DEFAULT '',
        season_number           VARCHAR(20)         DEFAULT '',
        season_picture          BIGINT(20) UNSIGNED DEFAULT NULL,
        season_banner_image     BIGINT(20) UNSIGNED DEFAULT NULL,
        season_winner           BIGINT(20) UNSIGNED DEFAULT NULL,
        runner_up               BIGINT(20) UNSIGNED DEFAULT NULL,
        afp                     BIGINT(20) UNSIGNED DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY post_id  (post_id)
    ) {$charset_collate};";

    // 4) Let WP handle creating/updating the table
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}


