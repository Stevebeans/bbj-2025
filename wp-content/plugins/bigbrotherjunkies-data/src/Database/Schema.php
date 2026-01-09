<?php

namespace BigBrotherJunkies\Data\Database;

/**
 * Database schema definitions for Ad Manager tables
 */
class Schema
{
    public const TABLE_ADS = 'bbjd_ads';
    public const TABLE_SLOTS = 'bbjd_ad_slots';
    public const TABLE_ASSIGNMENTS = 'bbjd_ad_slot_assignments';

    /**
     * Get full table name with prefix
     */
    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    /**
     * Get the ads table schema
     */
    public static function getAdsTableSchema(): string
    {
        $table = self::table(self::TABLE_ADS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'html',
            content_desktop LONGTEXT,
            content_mobile LONGTEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            priority INT(11) NOT NULL DEFAULT 0,
            settings LONGTEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY priority (priority)
        ) {$charset};";
    }

    /**
     * Get the slots table schema
     */
    public static function getSlotsTableSchema(): string
    {
        $table = self::table(self::TABLE_SLOTS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'manual',
            description TEXT,
            settings LONGTEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY type (type),
            KEY status (status)
        ) {$charset};";
    }

    /**
     * Get the assignments table schema
     */
    public static function getAssignmentsTableSchema(): string
    {
        $table = self::table(self::TABLE_ASSIGNMENTS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ad_id BIGINT(20) UNSIGNED NOT NULL,
            slot_id BIGINT(20) UNSIGNED NOT NULL,
            weight INT(11) NOT NULL DEFAULT 100,
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ad_slot (ad_id, slot_id),
            KEY ad_id (ad_id),
            KEY slot_id (slot_id),
            KEY schedule (start_date, end_date)
        ) {$charset};";
    }

    /**
     * Get all table schemas
     */
    public static function getAllSchemas(): array
    {
        return [
            self::TABLE_ADS => self::getAdsTableSchema(),
            self::TABLE_SLOTS => self::getSlotsTableSchema(),
            self::TABLE_ASSIGNMENTS => self::getAssignmentsTableSchema(),
        ];
    }

    /**
     * Get charset collate string
     */
    private static function getCharset(): string
    {
        global $wpdb;
        return $wpdb->get_charset_collate();
    }
}
