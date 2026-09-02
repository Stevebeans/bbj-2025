<?php

namespace BigBrotherJunkies\Data\Stories;

/**
 * Stories subsystem tables. dbDelta-compatible (two spaces before column
 * types are NOT required, but keep one column per line and the exact
 * "PRIMARY KEY  (id)" double-space that dbDelta expects).
 *
 * `show` is a MySQL reserved word: the column is show_slug everywhere.
 */
class Schema
{
    public const TABLE_ITEMS = 'bbj_story_items';
    public const TABLE_QUEUE = 'bbj_story_queue';
    public const TABLE_RUNS  = 'bbj_story_runs';

    public const DB_VERSION        = '1.0.1';
    public const DB_VERSION_OPTION = 'bbjd_stories_db_version';

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

    /** @return array<string,string> */
    public static function getAllSchemas(): array
    {
        return [
            self::TABLE_ITEMS => self::items(),
            self::TABLE_QUEUE => self::queue(),
            self::TABLE_RUNS  => self::runs(),
        ];
    }

    public static function maybeMigrate(): void
    {
        if (version_compare((string) get_option(self::DB_VERSION_OPTION, '0'), self::DB_VERSION, '>=')) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach (self::getAllSchemas() as $sql) {
            dbDelta($sql);
        }
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    private static function items(): string
    {
        $t = self::table(self::TABLE_ITEMS);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            show_slug VARCHAR(32) NOT NULL DEFAULT '',
            source VARCHAR(12) NOT NULL DEFAULT 'rss',
            source_key VARCHAR(190) NOT NULL DEFAULT '',
            url VARCHAR(255) NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            excerpt TEXT NOT NULL,
            author VARCHAR(190) NOT NULL DEFAULT '',
            media_type VARCHAR(10) NOT NULL DEFAULT '',
            media_url TEXT NULL,
            thumbnail_url TEXT NULL,
            published_at DATETIME NOT NULL,
            fetched_at DATETIME NOT NULL,
            trust VARCHAR(10) NOT NULL DEFAULT 'standard',
            score INT(11) NOT NULL DEFAULT 0,
            processed TINYINT(1) NOT NULL DEFAULT 0,
            cluster_id BIGINT(20) UNSIGNED NULL,
            inbox_status VARCHAR(10) NOT NULL DEFAULT 'new',
            used_queue_id BIGINT(20) UNSIGNED NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_url (url),
            KEY idx_show_processed (show_slug, processed, published_at),
            KEY idx_show_inbox (show_slug, inbox_status, score),
            KEY idx_published (published_at)
        ) " . self::charset() . ";";
    }

    private static function queue(): string
    {
        $t = self::table(self::TABLE_QUEUE);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            show_slug VARCHAR(32) NOT NULL DEFAULT '',
            cluster_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            label VARCHAR(10) NOT NULL DEFAULT 'rumor',
            blurb TEXT NOT NULL,
            evidence LONGTEXT NOT NULL,
            is_sensitive TINYINT(1) NOT NULL DEFAULT 0 /* was `sensitive` - a reserved word */,
            sensitive_reason VARCHAR(190) NOT NULL DEFAULT '',
            matches_post_id BIGINT(20) UNSIGNED NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            resolved_at DATETIME NULL,
            resolved_by BIGINT(20) UNSIGNED NULL,
            published_post_id BIGINT(20) UNSIGNED NULL,
            PRIMARY KEY  (id),
            KEY idx_show_status (show_slug, status, created_at),
            KEY idx_expires (status, expires_at)
        ) " . self::charset() . ";";
    }

    private static function runs(): string
    {
        $t = self::table(self::TABLE_RUNS);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            show_slug VARCHAR(32) NOT NULL DEFAULT '',
            kind VARCHAR(10) NOT NULL DEFAULT 'ingest',
            started_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            ingested INT(11) NOT NULL DEFAULT 0,
            clusters INT(11) NOT NULL DEFAULT 0,
            published INT(11) NOT NULL DEFAULT 0,
            queued INT(11) NOT NULL DEFAULT 0,
            dropped INT(11) NOT NULL DEFAULT 0,
            errors TEXT NULL,
            PRIMARY KEY  (id),
            KEY idx_kind_started (kind, started_at)
        ) " . self::charset() . ";";
    }
}
