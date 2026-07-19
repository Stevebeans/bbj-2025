<?php

namespace BigBrotherJunkies\Data\Bean;

/**
 * Database schema for Ask the Bean (usage metering for tier caps).
 */
class BeanSchema
{
    public const TABLE_USAGE = 'bbj_bean_usage';
    public const TABLE_MEMORY = 'bbj_bean_memory';
    public const TABLE_PROMPTS = 'bbj_bean_prompts';

    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    private static function getCharset(): string
    {
        global $wpdb;
        return $wpdb->get_charset_collate();
    }

    /**
     * Per-user, per-day message counter. One row per (user, day).
     */
    public static function getUsageTableSchema(): string
    {
        $table = self::table(self::TABLE_USAGE);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            usage_date DATE NOT NULL,
            msg_count INT(11) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_day (user_id, usage_date)
        ) {$charset};";
    }

    /**
     * Per-user evolving memory note (Full Bean cross-session memory). One row per user.
     */
    public static function getMemoryTableSchema(): string
    {
        $table = self::table(self::TABLE_MEMORY);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            summary TEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) {$charset};";
    }

    /**
     * Log of user questions to the Bean (admin visibility into what members
     * actually ask — product research + FAQ mining). Questions only, never
     * answers.
     */
    public static function getPromptsTableSchema(): string
    {
        $table = self::table(self::TABLE_PROMPTS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            question TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_created (created_at),
            KEY idx_user (user_id)
        ) {$charset};";
    }
}
