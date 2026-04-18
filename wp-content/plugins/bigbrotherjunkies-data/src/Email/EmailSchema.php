<?php

namespace BigBrotherJunkies\Data\Email;

/**
 * Database schema definitions for Email System tables
 */
class EmailSchema
{
    public const TABLE_SUBSCRIBERS = 'bbj_email_subscribers';
    public const TABLE_LISTS = 'bbj_email_lists';
    public const TABLE_LIST_SUBSCRIBERS = 'bbj_email_list_subscribers';
    public const TABLE_SENDS = 'bbj_email_sends';

    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    public static function getSubscribersTableSchema(): string
    {
        $table = self::table(self::TABLE_SUBSCRIBERS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            email VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'unconfirmed',
            confirm_token VARCHAR(64) DEFAULT NULL,
            source VARCHAR(50) NOT NULL DEFAULT 'widget',
            subscribed_at DATETIME DEFAULT NULL,
            confirmed_at DATETIME DEFAULT NULL,
            unsubscribed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_confirm_token (confirm_token)
        ) {$charset};";
    }

    public static function getListsTableSchema(): string
    {
        $table = self::table(self::TABLE_LISTS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(100) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$charset};";
    }

    public static function getListSubscribersTableSchema(): string
    {
        $table = self::table(self::TABLE_LIST_SUBSCRIBERS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            subscriber_id BIGINT(20) UNSIGNED NOT NULL,
            list_id BIGINT(20) UNSIGNED NOT NULL,
            subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (subscriber_id, list_id),
            KEY idx_list_id (list_id)
        ) {$charset};";
    }

    public static function getSendsTableSchema(): string
    {
        $table = self::table(self::TABLE_SENDS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subscriber_id BIGINT(20) UNSIGNED NOT NULL,
            list_id BIGINT(20) UNSIGNED DEFAULT NULL,
            subject VARCHAR(255) NOT NULL,
            resend_id VARCHAR(100) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'sent',
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            delivered_at DATETIME DEFAULT NULL,
            opened_at DATETIME DEFAULT NULL,
            clicked_at DATETIME DEFAULT NULL,
            bounced_at DATETIME DEFAULT NULL,
            bounce_type VARCHAR(50) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_subscriber_id (subscriber_id),
            KEY idx_resend_id (resend_id),
            KEY idx_status (status),
            KEY idx_list_id (list_id)
        ) {$charset};";
    }

    public static function getAllSchemas(): array
    {
        return [
            self::TABLE_SUBSCRIBERS => self::getSubscribersTableSchema(),
            self::TABLE_LISTS => self::getListsTableSchema(),
            self::TABLE_LIST_SUBSCRIBERS => self::getListSubscribersTableSchema(),
            self::TABLE_SENDS => self::getSendsTableSchema(),
        ];
    }

    private static function getCharset(): string
    {
        global $wpdb;
        return $wpdb->get_charset_collate();
    }
}
