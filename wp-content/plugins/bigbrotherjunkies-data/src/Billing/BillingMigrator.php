<?php

namespace BigBrotherJunkies\Data\Billing;

/**
 * Handles database migrations for Billing System
 */
class BillingMigrator
{
    public const DB_VERSION_OPTION = 'bbj_billing_db_version';
    public const CURRENT_VERSION = '1.0.0';

    /**
     * Run migrations if needed
     */
    public static function migrate(): array
    {
        $results = [];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (BillingSchema::getAllSchemas() as $tableName => $schema) {
            dbDelta($schema);
            $results[$tableName] = self::tableExists($tableName);
        }

        update_option(self::DB_VERSION_OPTION, self::CURRENT_VERSION);

        return $results;
    }

    /**
     * Check if a table exists
     */
    public static function tableExists(string $tableName): bool
    {
        global $wpdb;
        $fullTableName = BillingSchema::table($tableName);
        $result = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $fullTableName)
        );
        return $result === $fullTableName;
    }

    /**
     * Get table row count
     */
    public static function getTableRowCount(string $tableName): int
    {
        global $wpdb;
        $fullTableName = BillingSchema::table($tableName);

        if (!self::tableExists($tableName)) {
            return -1;
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$fullTableName}");
    }

    /**
     * Get status of all billing tables
     */
    public static function getTablesStatus(): array
    {
        $tables = [
            BillingSchema::TABLE_SUBSCRIPTIONS,
        ];

        $status = [];
        foreach ($tables as $table) {
            $status[$table] = [
                'exists' => self::tableExists($table),
                'rows' => self::getTableRowCount($table),
                'full_name' => BillingSchema::table($table),
            ];
        }

        return $status;
    }

    /**
     * Check if migration is needed
     */
    public static function needsMigration(): bool
    {
        $currentVersion = get_option(self::DB_VERSION_OPTION, '0.0.0');
        return version_compare($currentVersion, self::CURRENT_VERSION, '<');
    }

    /**
     * Get current database version
     */
    public static function getCurrentVersion(): string
    {
        return get_option(self::DB_VERSION_OPTION, 'Not installed');
    }

    /**
     * Drop all billing tables (for dev/testing)
     */
    public static function dropAllTables(): array
    {
        global $wpdb;

        $results = [];
        $tables = [
            BillingSchema::TABLE_SUBSCRIPTIONS,
        ];

        foreach ($tables as $table) {
            $fullTableName = BillingSchema::table($table);
            $wpdb->query("DROP TABLE IF EXISTS {$fullTableName}");
            $results[$table] = !self::tableExists($table);
        }

        delete_option(self::DB_VERSION_OPTION);

        return $results;
    }
}
