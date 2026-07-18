<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Bluesky Feed-Intel monitor tables. dbDelta-compatible.
 */
class SocialSchema
{
    public const TABLE_POSTS     = 'bbj_social_posts';
    public const TABLE_SUMMARIES = 'bbj_social_summaries';
    public const TABLE_QUOTES    = 'bbj_player_quotes';

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
            self::TABLE_POSTS     => self::posts(),
            self::TABLE_SUMMARIES => self::summaries(),
            self::TABLE_QUOTES    => self::quotes(),
        ];
    }

    private static function posts(): string
    {
        $t = self::table(self::TABLE_POSTS);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source VARCHAR(10) NOT NULL DEFAULT 'tag',
            uri VARCHAR(255) NOT NULL,
            cid VARCHAR(120) NOT NULL DEFAULT '',
            handle VARCHAR(190) NOT NULL,
            display_name VARCHAR(190) NOT NULL DEFAULT '',
            text TEXT NOT NULL,
            posted_at DATETIME NOT NULL,
            fetched_at DATETIME NOT NULL,
            summarized TINYINT(1) NOT NULL DEFAULT 0,
            likes INT(10) UNSIGNED NOT NULL DEFAULT 0,
            reposts INT(10) UNSIGNED NOT NULL DEFAULT 0,
            replies INT(10) UNSIGNED NOT NULL DEFAULT 0,
            quotes INT(10) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_uri (uri),
            KEY idx_source_posted (source, posted_at),
            KEY idx_summarized (summarized),
            KEY idx_posted (posted_at)
        ) " . self::charset() . ";";
    }

    private static function summaries(): string
    {
        $t = self::table(self::TABLE_SUMMARIES);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            kind VARCHAR(12) NOT NULL DEFAULT 'digest',
            window_from DATETIME NOT NULL,
            window_to DATETIME NOT NULL,
            model VARCHAR(60) NOT NULL DEFAULT '',
            post_count INT(11) NOT NULL DEFAULT 0,
            trusted_count INT(11) NOT NULL DEFAULT 0,
            content LONGTEXT NOT NULL,
            PRIMARY KEY (id),
            KEY idx_created (created_at),
            KEY idx_kind_created (kind, created_at)
        ) " . self::charset() . ";";
    }

    /** Steve-approved houseguest quotes (candidates are never stored here). */
    private static function quotes(): string
    {
        $t = self::table(self::TABLE_QUOTES);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season_id BIGINT(20) UNSIGNED NOT NULL,
            player_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            quote_text TEXT NOT NULL,
            context VARCHAR(255) NOT NULL DEFAULT '',
            said_on DATE NOT NULL,
            source_count INT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_player (player_id),
            KEY idx_season_day (season_id, said_on)
        ) " . self::charset() . ";";
    }
}
