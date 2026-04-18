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
    public const TABLE_CONTENT_QUEUE = 'bbj_content_queue';
    public const TABLE_NEWS_FEED = 'bbj_news_feed';

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
     * Get the content queue table schema
     */
    public static function getContentQueueTableSchema(): string
    {
        $table = self::table(self::TABLE_CONTENT_QUEUE);
        $charset = self::getCharset();

        return "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            status ENUM('draft','scheduled','posted','failed') DEFAULT 'draft',
            source ENUM('manual','image_paste','news_scan','template','on_this_day') DEFAULT 'manual',
            content_type ENUM('facebook_post','blog_post','both') DEFAULT 'facebook_post',
            title VARCHAR(255) DEFAULT NULL,
            body TEXT NOT NULL,
            image_url VARCHAR(500) DEFAULT NULL,
            image_data LONGBLOB DEFAULT NULL,
            target_page VARCHAR(50) DEFAULT NULL,
            target_page_name VARCHAR(100) DEFAULT NULL,
            wp_post_id BIGINT DEFAULT NULL,
            scheduled_at DATETIME DEFAULT NULL,
            posted_at DATETIME DEFAULT NULL,
            fb_post_id VARCHAR(100) DEFAULT NULL,
            template_type VARCHAR(50) DEFAULT NULL,
            source_url VARCHAR(500) DEFAULT NULL,
            ai_variations TEXT DEFAULT NULL,
            author_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_scheduled (status, scheduled_at),
            INDEX idx_source (source),
            INDEX idx_target_page (target_page)
        ) {$charset};";
    }

    /**
     * Get the news feed table schema
     */
    public static function getNewsFeedTableSchema(): string
    {
        $table = self::table(self::TABLE_NEWS_FEED);
        $charset = self::getCharset();

        return "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(500) NOT NULL,
            url VARCHAR(500) NOT NULL,
            source_name VARCHAR(100) DEFAULT NULL,
            excerpt TEXT DEFAULT NULL,
            thumbnail VARCHAR(500) DEFAULT NULL,
            published_at DATETIME DEFAULT NULL,
            fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            used TINYINT(1) DEFAULT 0,
            UNIQUE KEY idx_url (url(191)),
            INDEX idx_published (published_at),
            INDEX idx_used (used)
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
            self::TABLE_CONTENT_QUEUE => self::getContentQueueTableSchema(),
            self::TABLE_NEWS_FEED => self::getNewsFeedTableSchema(),
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
