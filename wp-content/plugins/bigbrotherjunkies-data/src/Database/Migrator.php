<?php

namespace BigBrotherJunkies\Data\Database;

/**
 * Handles database migrations for Ad Manager
 */
class Migrator
{
    public const DB_VERSION_OPTION = 'bbjd_ad_db_version';
    public const CURRENT_VERSION = '1.1.0';

    /**
     * Run migrations if needed
     */
    public static function migrate(): array
    {
        $results = [];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (Schema::getAllSchemas() as $tableName => $schema) {
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
        $fullTableName = Schema::table($tableName);
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
        $fullTableName = Schema::table($tableName);

        if (!self::tableExists($tableName)) {
            return -1;
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$fullTableName}");
    }

    /**
     * Get status of all tables
     */
    public static function getTablesStatus(): array
    {
        $tables = [
            Schema::TABLE_ADS,
            Schema::TABLE_SLOTS,
            Schema::TABLE_ASSIGNMENTS,
            Schema::TABLE_CONTENT_QUEUE,
            Schema::TABLE_NEWS_FEED,
        ];

        $status = [];
        foreach ($tables as $table) {
            $status[$table] = [
                'exists' => self::tableExists($table),
                'rows' => self::getTableRowCount($table),
                'full_name' => Schema::table($table),
            ];
        }

        return $status;
    }

    /**
     * Drop all Ad Manager tables
     */
    public static function dropAllTables(): array
    {
        global $wpdb;

        $results = [];
        $tables = [
            Schema::TABLE_ASSIGNMENTS, // Drop assignments first (foreign keys)
            Schema::TABLE_ADS,
            Schema::TABLE_SLOTS,
        ];

        foreach ($tables as $table) {
            $fullTableName = Schema::table($table);
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("DROP TABLE IF EXISTS {$fullTableName}");
            $results[$table] = !self::tableExists($table);
        }

        delete_option(self::DB_VERSION_OPTION);

        return $results;
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
     * Import ads from bbj-v2 system
     */
    public static function importFromBbjV2(): array
    {
        global $wpdb;

        $results = [
            'slots_imported' => 0,
            'ads_imported' => 0,
            'assignments_created' => 0,
            'errors' => [],
        ];

        // Get existing bbj_ads option
        $existingAds = get_option('bbj_ads', []);

        if (empty($existingAds)) {
            $results['errors'][] = 'No existing ads found in bbj_ads option';
            return $results;
        }

        // Define the slots from bbj-v2
        $slotDefinitions = [
            'above_header' => ['name' => 'Above Header', 'type' => 'manual'],
            'in_header' => ['name' => 'In Header', 'type' => 'manual'],
            'in_header_misc' => ['name' => 'In Header Misc', 'type' => 'manual'],
            'below_header' => ['name' => 'Below Header', 'type' => 'manual'],
            'below_header_mobile' => ['name' => 'Below Header Mobile', 'type' => 'manual'],
            'after_post' => ['name' => 'After Post', 'type' => 'manual'],
            'index_top' => ['name' => 'Index Top', 'type' => 'manual'],
            'index_top_mobile' => ['name' => 'Index Top Mobile', 'type' => 'manual'],
            'index_mid' => ['name' => 'Index Mid', 'type' => 'manual'],
            'index_bottom' => ['name' => 'Index Bottom', 'type' => 'manual'],
            'between_feeds' => ['name' => 'Between Feeds', 'type' => 'auto_content'],
            'before_content' => ['name' => 'Before Content', 'type' => 'manual'],
            'before_comments' => ['name' => 'Before Comments', 'type' => 'manual'],
            'in_content_feeds' => ['name' => 'In Content Feeds', 'type' => 'auto_content'],
            'single_top' => ['name' => 'Single Top', 'type' => 'manual'],
            'single_mid' => ['name' => 'Single Mid', 'type' => 'manual'],
            'single_bottom' => ['name' => 'Single Bottom', 'type' => 'manual'],
            'sidebar_bottom' => ['name' => 'Sidebar Bottom', 'type' => 'manual'],
            'sidebar_top' => ['name' => 'Sidebar Top', 'type' => 'manual'],
            'footer' => ['name' => 'Footer', 'type' => 'manual'],
        ];

        $slotsTable = Schema::table(Schema::TABLE_SLOTS);
        $adsTable = Schema::table(Schema::TABLE_ADS);
        $assignmentsTable = Schema::table(Schema::TABLE_ASSIGNMENTS);

        // Import slots and ads
        foreach ($slotDefinitions as $slug => $definition) {
            // Check if slot already exists
            $existingSlot = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM {$slotsTable} WHERE slug = %s", $slug)
            );

            if (!$existingSlot) {
                // Create slot
                $wpdb->insert($slotsTable, [
                    'name' => $definition['name'],
                    'slug' => $slug,
                    'type' => $definition['type'],
                    'status' => 'active',
                ]);
                $slotId = $wpdb->insert_id;
                $results['slots_imported']++;
            } else {
                $slotId = $existingSlot;
            }

            // Check if there's ad content for this slot
            if (isset($existingAds[$slug]) && !empty($existingAds[$slug])) {
                $adContent = $existingAds[$slug];
                $adSlug = $slug . '_ad';
                $adName = $definition['name'] . ' Ad';

                // Check if ad already exists
                $existingAd = $wpdb->get_var(
                    $wpdb->prepare("SELECT id FROM {$adsTable} WHERE slug = %s", $adSlug)
                );

                if (!$existingAd) {
                    // Create ad
                    $wpdb->insert($adsTable, [
                        'name' => $adName,
                        'slug' => $adSlug,
                        'type' => 'html',
                        'content_desktop' => $adContent,
                        'status' => 'active',
                    ]);
                    $adId = $wpdb->insert_id;
                    $results['ads_imported']++;

                    // Create assignment
                    $wpdb->insert($assignmentsTable, [
                        'ad_id' => $adId,
                        'slot_id' => $slotId,
                        'weight' => 100,
                    ]);
                    $results['assignments_created']++;
                }
            }
        }

        return $results;
    }
}
