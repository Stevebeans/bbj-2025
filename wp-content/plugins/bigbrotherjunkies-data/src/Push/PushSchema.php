<?php

namespace BigBrotherJunkies\Data\Push;

class PushSchema
{
    public const TABLE = 'bbj_push_subscriptions';

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    public static function createSql(): string
    {
        global $wpdb;
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            endpoint TEXT NOT NULL,
            endpoint_hash CHAR(64) NOT NULL,
            p256dh VARCHAR(255) NOT NULL,
            auth VARCHAR(255) NOT NULL,
            ua VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_notified_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_endpoint (endpoint_hash),
            KEY idx_user (user_id)
        ) {$charset};";
    }
}
