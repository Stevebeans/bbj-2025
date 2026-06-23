<?php

namespace BigBrotherJunkies\Data\Bean;

/**
 * Database schema for Ask the Bean (usage metering for tier caps).
 */
class BeanSchema
{
    public const TABLE_USAGE = 'bbj_bean_usage';

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
}
