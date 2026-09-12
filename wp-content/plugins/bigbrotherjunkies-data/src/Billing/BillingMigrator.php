<?php

namespace BigBrotherJunkies\Data\Billing;

/**
 * Handles database migrations for Billing System
 */
class BillingMigrator
{
    public const DB_VERSION_OPTION = 'bbj_billing_db_version';
    // 1.2.0: bbj_subscriptions.stripe_payment_intent_id (lifetime refunds must be matchable).
    public const CURRENT_VERSION = '1.2.0';

    /** The Full Bean role slug ($14.99 tier: Sonnet, unlimited, memory, ad-free). */
    public const FULL_BEAN_ROLE = 'full_bean';

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

        // Register the Full Bean role + make it ad-free (idempotent).
        self::ensureFullBeanRole();

        update_option(self::DB_VERSION_OPTION, self::CURRENT_VERSION);

        return $results;
    }

    /**
     * Register the `full_bean` role (subscriber-level caps) and ensure it is in
     * the ad system's global hidden-roles list so Full Bean subscribers are
     * ad-free. Both steps are idempotent and safe to run repeatedly.
     */
    public static function ensureFullBeanRole(): void
    {
        // Register the role if it doesn't exist yet. add_role() is a no-op when
        // the role already exists. Base it on subscriber capabilities.
        if (!get_role(self::FULL_BEAN_ROLE)) {
            $subscriber = get_role('subscriber');
            $caps = $subscriber ? $subscriber->capabilities : ['read' => true];
            add_role(self::FULL_BEAN_ROLE, 'Full Bean', $caps);
        }

        // Add full_bean to the ad system's global hidden roles (ad-free perk).
        $option = \BigBrotherJunkies\Data\Ads\AdManager::SETTINGS_OPTION;
        $settings = get_option($option, []);
        $hidden = (array) ($settings['global_hidden_roles'] ?? []);
        if (!in_array(self::FULL_BEAN_ROLE, $hidden, true)) {
            $hidden[] = self::FULL_BEAN_ROLE;
            $settings['global_hidden_roles'] = $hidden;
            update_option($option, $settings);
        }
    }

    /** The lifetime role predates this plugin; make sure it exists before we sell it again. */
    public static function ensureLifetimeRole(): void
    {
        if (get_role('lifetime') === null) {
            $subscriber = get_role('subscriber');
            add_role('lifetime', 'Lifetime Member', $subscriber ? $subscriber->capabilities : ['read' => true]);
        }
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
