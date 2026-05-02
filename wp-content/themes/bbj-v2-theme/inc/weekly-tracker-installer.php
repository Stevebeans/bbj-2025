<?php
/**
 * Weekly Tracker — schema-only auto-installer.
 *
 * Mirrors the schema steps from
 * docs/repairs/2026-04-25-week-comps-junction-bootstrap.sql
 * (creates tables + adds columns + seeds comp types) but skips the BB23-25
 * boolean-to-junction backfill — that stays in the manual SQL so it can be
 * audited and re-run intentionally.
 *
 * Runs once per schema-version bump on the next /admin/* page load.
 * Guarded by the bbj_v2_weekly_tracker_schema_version option.
 */

if (!defined('ABSPATH')) {
    exit;
}

const BBJ_V2_WEEKLY_TRACKER_SCHEMA_VERSION = 1;

function bbj_v2_weekly_tracker_install(): void
{
    $current = (int) get_option('bbj_v2_weekly_tracker_schema_version', 0);
    if ($current >= BBJ_V2_WEEKLY_TRACKER_SCHEMA_VERSION) {
        return;
    }

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $comp_types      = $wpdb->prefix . 'bbj_comp_types';
    $week_comps      = $wpdb->prefix . 'bbj_week_comps';
    $weeks           = $wpdb->prefix . 'bbj_weeks';
    $weeks_players   = $wpdb->prefix . 'bbj_weeks_players';

    dbDelta("CREATE TABLE {$comp_types} (
        id BIGINT NOT NULL AUTO_INCREMENT,
        slug VARCHAR(40) NOT NULL,
        name VARCHAR(80) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_archived TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_slug (slug)
    ) {$charset_collate};");

    dbDelta("CREATE TABLE {$week_comps} (
        id BIGINT NOT NULL AUTO_INCREMENT,
        week_id BIGINT NOT NULL,
        player_id BIGINT NOT NULL,
        comp_type_id BIGINT NOT NULL,
        opponents_count INT NULL,
        notes VARCHAR(140) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_player_type (player_id, comp_type_id),
        UNIQUE KEY uniq_week_player_type (week_id, player_id, comp_type_id)
    ) {$charset_collate};");

    bbj_v2_weekly_tracker_add_column_if_missing(
        $weeks_players,
        'saved_by_player_id',
        'BIGINT NULL AFTER saved'
    );
    bbj_v2_weekly_tracker_add_column_if_missing(
        $weeks,
        'summary',
        'TEXT NULL AFTER end_date'
    );

    $seeded = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$comp_types}");
    if ($seeded === 0) {
        $wpdb->query(
            "INSERT IGNORE INTO {$comp_types} (slug, name, sort_order) VALUES
                ('hoh',  'HOH',           10),
                ('pov',  'Power of Veto', 20),
                ('misc', 'Misc Comp',     30)"
        );
    }

    update_option('bbj_v2_weekly_tracker_schema_version', BBJ_V2_WEEKLY_TRACKER_SCHEMA_VERSION, false);
}

function bbj_v2_weekly_tracker_add_column_if_missing(string $table, string $column, string $definition): void
{
    global $wpdb;

    $exists = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = %s
                AND COLUMN_NAME = %s",
            $table,
            $column
        )
    );

    if ($exists === 0) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}
