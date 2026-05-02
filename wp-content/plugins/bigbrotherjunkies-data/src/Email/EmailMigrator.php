<?php

namespace BigBrotherJunkies\Data\Email;

/**
 * Handles database migrations for Email System
 */
class EmailMigrator
{
    public const DB_VERSION_OPTION = 'bbj_email_db_version';
    public const CURRENT_VERSION = '1.0.0';

    public static function migrate(): array
    {
        $results = [];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (EmailSchema::getAllSchemas() as $tableName => $schema) {
            dbDelta($schema);
            $results[$tableName] = self::tableExists($tableName);
        }

        // Seed the default post-notifications list if it doesn't exist
        self::seedDefaultList();

        update_option(self::DB_VERSION_OPTION, self::CURRENT_VERSION);

        return $results;
    }

    private static function seedDefaultList(): void
    {
        global $wpdb;

        $table = EmailSchema::table(EmailSchema::TABLE_LISTS);

        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", 'post-notifications')
        );

        if (!$exists) {
            $wpdb->insert($table, [
                'slug' => 'post-notifications',
                'name' => 'Post Notifications',
                'description' => 'Get notified when a new blog post is published.',
                'is_active' => 1,
            ]);
        }
    }

    public static function needsMigration(): bool
    {
        $currentVersion = get_option(self::DB_VERSION_OPTION, '0.0.0');
        return version_compare($currentVersion, self::CURRENT_VERSION, '<');
    }

    public static function tableExists(string $tableName): bool
    {
        global $wpdb;
        $fullTableName = EmailSchema::table($tableName);
        $result = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $fullTableName)
        );
        return $result === $fullTableName;
    }

    public static function getTableRowCount(string $tableName): int
    {
        global $wpdb;
        $fullTableName = EmailSchema::table($tableName);

        if (!self::tableExists($tableName)) {
            return -1;
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$fullTableName}");
    }

    public static function getTablesStatus(): array
    {
        $tables = [
            EmailSchema::TABLE_SUBSCRIBERS,
            EmailSchema::TABLE_LISTS,
            EmailSchema::TABLE_LIST_SUBSCRIBERS,
            EmailSchema::TABLE_SENDS,
        ];

        $status = [];
        foreach ($tables as $table) {
            $status[$table] = [
                'exists' => self::tableExists($table),
                'rows' => self::getTableRowCount($table),
                'full_name' => EmailSchema::table($table),
            ];
        }

        return $status;
    }

    public static function getCurrentVersion(): string
    {
        return get_option(self::DB_VERSION_OPTION, 'Not installed');
    }

    public static function dropAllTables(): array
    {
        global $wpdb;

        $results = [];
        $tables = [
            EmailSchema::TABLE_LIST_SUBSCRIBERS, // Drop junction table first
            EmailSchema::TABLE_SENDS,
            EmailSchema::TABLE_SUBSCRIBERS,
            EmailSchema::TABLE_LISTS,
        ];

        foreach ($tables as $table) {
            $fullTableName = EmailSchema::table($table);
            $wpdb->query("DROP TABLE IF EXISTS {$fullTableName}");
            $results[$table] = !self::tableExists($table);
        }

        delete_option(self::DB_VERSION_OPTION);

        return $results;
    }
}
