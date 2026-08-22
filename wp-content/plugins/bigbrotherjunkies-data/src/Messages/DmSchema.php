<?php

namespace BigBrotherJunkies\Data\Messages;

/**
 * Direct-message tables. dbDelta-compatible (mirrors SocialSchema/FanVoteSchema).
 * user_lo/user_hi are the ordered pair (lo < hi) so one UNIQUE key covers both
 * directions of a conversation.
 */
class DmSchema
{
    public const TABLE_THREADS  = 'bbj_dm_threads';
    public const TABLE_MESSAGES = 'bbj_dm_messages';
    public const TABLE_BLOCKS   = 'bbj_dm_blocks';
    public const TABLE_REPORTS  = 'bbj_dm_reports';
    public const TABLE_MUTES    = 'bbj_user_mutes';

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
            self::TABLE_THREADS  => self::threads(),
            self::TABLE_MESSAGES => self::messages(),
            self::TABLE_BLOCKS   => self::blocks(),
            self::TABLE_REPORTS  => self::reports(),
            self::TABLE_MUTES    => self::mutes(),
        ];
    }

    private static function threads(): string
    {
        $t = self::table(self::TABLE_THREADS);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_lo BIGINT(20) UNSIGNED NOT NULL,
            user_hi BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            last_message_at DATETIME NOT NULL,
            last_sender_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            lo_read_at DATETIME NULL DEFAULT NULL,
            hi_read_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_pair (user_lo, user_hi),
            KEY idx_lo_last (user_lo, last_message_at),
            KEY idx_hi_last (user_hi, last_message_at)
        ) " . self::charset() . ";";
    }

    private static function messages(): string
    {
        $t = self::table(self::TABLE_MESSAGES);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            thread_id BIGINT(20) UNSIGNED NOT NULL,
            sender_id BIGINT(20) UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_thread_id (thread_id, id)
        ) " . self::charset() . ";";
    }

    private static function blocks(): string
    {
        $t = self::table(self::TABLE_BLOCKS);
        return "CREATE TABLE {$t} (
            blocker_id BIGINT(20) UNSIGNED NOT NULL,
            blocked_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (blocker_id, blocked_id)
        ) " . self::charset() . ";";
    }

    private static function reports(): string
    {
        $t = self::table(self::TABLE_REPORTS);
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            reporter_id BIGINT(20) UNSIGNED NOT NULL,
            thread_id BIGINT(20) UNSIGNED NOT NULL,
            message_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            reason VARCHAR(50) NOT NULL DEFAULT '',
            note VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            resolved_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_created (created_at)
        ) " . self::charset() . ";";
    }

    /** Comment-mutes. One-way; comments-only (blocks live in TABLE_BLOCKS). */
    private static function mutes(): string
    {
        $t = self::table(self::TABLE_MUTES);
        return "CREATE TABLE {$t} (
            muter_id BIGINT(20) UNSIGNED NOT NULL,
            muted_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (muter_id, muted_id)
        ) " . self::charset() . ";";
    }
}
