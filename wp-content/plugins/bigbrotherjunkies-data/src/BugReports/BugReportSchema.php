<?php

namespace BigBrotherJunkies\Data\BugReports;

/**
 * Database schema definitions for Bug Report System
 */
class BugReportSchema
{
    public const TABLE_BUG_REPORTS = 'bbj_bug_reports';

    /**
     * Get full table name with prefix
     */
    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    /**
     * Get the bug reports table schema
     */
    public static function getBugReportsTableSchema(): string
    {
        $table = self::table(self::TABLE_BUG_REPORTS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,

            -- Classification
            type ENUM('ui_visual', 'functionality', 'performance', 'content', 'other') NOT NULL DEFAULT 'other',
            severity ENUM('critical', 'major', 'minor', 'cosmetic') NOT NULL DEFAULT 'minor',
            status ENUM('open', 'in_progress', 'resolved', 'closed', 'wont_fix') NOT NULL DEFAULT 'open',

            -- Report details
            page_url VARCHAR(2048) DEFAULT NULL,
            description TEXT NOT NULL,
            steps_to_reproduce TEXT DEFAULT NULL,
            expected_behavior TEXT DEFAULT NULL,
            screenshot_url VARCHAR(2048) DEFAULT NULL,

            -- Auto-captured data
            browser_info JSON DEFAULT NULL,
            console_errors JSON DEFAULT NULL,

            -- Admin fields
            admin_notes TEXT DEFAULT NULL,

            -- Timestamps
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at DATETIME DEFAULT NULL,
            resolved_by BIGINT(20) UNSIGNED DEFAULT NULL,

            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_status (status),
            KEY idx_severity (severity),
            KEY idx_type (type),
            KEY idx_created (created_at)
        ) {$charset};";
    }

    /**
     * Get all table schemas
     */
    public static function getAllSchemas(): array
    {
        return [
            self::TABLE_BUG_REPORTS => self::getBugReportsTableSchema(),
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
