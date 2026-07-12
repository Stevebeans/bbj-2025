<?php

namespace BigBrotherJunkies\Data\FanVotes;

/**
 * Fan-favorite ("AFP") voting tables. dbDelta-compatible.
 */
class FanVoteSchema
{
    public const TABLE_VOTES     = 'bbj_fan_votes';
    public const TABLE_SNAPSHOTS = 'bbj_fan_vote_snapshots';
    public const TABLE_NOTES     = 'bbj_fan_vote_notes';
    public const TABLE_BALLOTS   = 'bbj_fan_ballots';

    public static function table(string $name): string
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
            self::TABLE_VOTES     => self::votes(),
            self::TABLE_SNAPSHOTS => self::snapshots(),
            self::TABLE_NOTES     => self::notes(),
            self::TABLE_BALLOTS   => self::ballots(),
        ];
    }

    private static function votes(): string
    {
        $t = self::table(self::TABLE_VOTES);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) NOT NULL,
            season_id BIGINT(20) NOT NULL,
            player_id BIGINT(20) NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_season (user_id, season_id),
            KEY idx_season_player (season_id, player_id)
        ) " . self::charset() . ";";
    }

    private static function snapshots(): string
    {
        $t = self::table(self::TABLE_SNAPSHOTS);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season_id BIGINT(20) NOT NULL,
            snap_date DATE NOT NULL,
            player_id BIGINT(20) NOT NULL,
            votes INT(11) NOT NULL DEFAULT 0,
            total_voters INT(11) NOT NULL DEFAULT 0,
            points DECIMAL(10,1) NOT NULL DEFAULT 0,
            total_points DECIMAL(12,1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_day_player (season_id, snap_date, player_id),
            KEY idx_season_date (season_id, snap_date)
        ) " . self::charset() . ";";
    }

    private static function notes(): string
    {
        $t = self::table(self::TABLE_NOTES);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season_id BIGINT(20) NOT NULL,
            note_date DATE NOT NULL,
            player_id BIGINT(20) NOT NULL,
            note VARCHAR(140) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY uniq_day_player_note (season_id, note_date, player_id)
        ) " . self::charset() . ";";
    }

    private static function ballots(): string
    {
        $t = self::table(self::TABLE_BALLOTS);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) NOT NULL,
            season_id BIGINT(20) NOT NULL,
            player_id BIGINT(20) NOT NULL,
            rank_pos SMALLINT(5) NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_season_player (user_id, season_id, player_id),
            KEY idx_season (season_id)
        ) " . self::charset() . ";";
    }
}
