<?php

namespace BigBrotherJunkies\Data\Weekly;

/**
 * Canonical weekly-tracking table schemas. dbDelta-compatible.
 * Tables already exist in production-cloned DBs from the legacy bbj-tools /
 * v2-theme work; dbDelta is idempotent so this just asserts ownership.
 */
class WeeklySchema
{
    public const TABLE_COMP_TYPES    = 'bbj_comp_types';
    public const TABLE_WEEKS         = 'bbj_weeks';
    public const TABLE_WEEKS_PLAYERS = 'bbj_weeks_players';
    public const TABLE_WEEK_COMPS    = 'bbj_week_comps';

    private static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    private static function charset(): string
    {
        global $wpdb;
        return $wpdb->get_charset_collate();
    }

    public static function getAllSchemas(): array
    {
        return [
            self::TABLE_COMP_TYPES    => self::compTypes(),
            self::TABLE_WEEKS         => self::weeks(),
            self::TABLE_WEEKS_PLAYERS => self::weeksPlayers(),
            self::TABLE_WEEK_COMPS    => self::weekComps(),
        ];
    }

    private static function compTypes(): string
    {
        $t = self::table(self::TABLE_COMP_TYPES);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(40) NOT NULL,
            name VARCHAR(80) NOT NULL,
            sort_order INT(11) NOT NULL DEFAULT 0,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) " . self::charset() . ";";
    }

    private static function weeks(): string
    {
        $t = self::table(self::TABLE_WEEKS);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season_id BIGINT(20) NOT NULL,
            week_num INT(11) NOT NULL,
            start_date DATE DEFAULT NULL,
            end_date DATE DEFAULT NULL,
            summary TEXT,
            PRIMARY KEY (id),
            KEY idx_season (season_id)
        ) " . self::charset() . ";";
    }

    private static function weeksPlayers(): string
    {
        $t = self::table(self::TABLE_WEEKS_PLAYERS);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            week_id BIGINT(20) NOT NULL,
            player_id BIGINT(20) NOT NULL,
            season_id BIGINT(20) NOT NULL,
            nom TINYINT(1) NOT NULL DEFAULT 0,
            evicted TINYINT(1) NOT NULL DEFAULT 0,
            veto_played TINYINT(1) NOT NULL DEFAULT 0,
            voted_for BIGINT(20) NOT NULL DEFAULT 0,
            saved_by_player_id BIGINT(20) DEFAULT NULL,
            vote_to_win TINYINT(1) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY idx_week (week_id),
            KEY idx_player (player_id),
            KEY idx_season (season_id)
        ) " . self::charset() . ";";
    }

    private static function weekComps(): string
    {
        $t = self::table(self::TABLE_WEEK_COMPS);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            week_id BIGINT(20) NOT NULL,
            player_id BIGINT(20) NOT NULL,
            comp_type_id BIGINT(20) NOT NULL,
            opponents_count INT(11) DEFAULT NULL,
            notes VARCHAR(140) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_week_player_type (week_id, player_id, comp_type_id),
            KEY idx_player_type (player_id, comp_type_id)
        ) " . self::charset() . ";";
    }
}
