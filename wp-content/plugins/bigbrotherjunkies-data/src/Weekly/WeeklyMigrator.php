<?php

namespace BigBrotherJunkies\Data\Weekly;

class WeeklyMigrator
{
    public const DB_VERSION_OPTION = 'bbj_weekly_db_version';
    public const CURRENT_VERSION = '1.1.0';

    public static function needsMigration(): bool
    {
        $current = get_option(self::DB_VERSION_OPTION, '0.0.0');
        return version_compare($current, self::CURRENT_VERSION, '<');
    }

    public static function migrate(): array
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $results = [];
        foreach (WeeklySchema::getAllSchemas() as $tableName => $schema) {
            dbDelta($schema);
            $results[$tableName] = self::tableExists($tableName);
        }

        self::seedDefaultCompTypes();
        update_option(self::DB_VERSION_OPTION, self::CURRENT_VERSION);

        return $results;
    }

    private static function seedDefaultCompTypes(): void
    {
        global $wpdb;
        $table = WeeklySchema::table(WeeklySchema::TABLE_COMP_TYPES);
        $defaults = [
            ['slug' => 'hoh',  'name' => 'HOH',           'sort_order' => 10],
            ['slug' => 'pov',  'name' => 'Power of Veto',  'sort_order' => 20],
            ['slug' => 'misc', 'name' => 'Misc Comp',      'sort_order' => 30],
        ];
        foreach ($defaults as $d) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE slug = %s",
                $d['slug']
            ));
            if (!$exists) {
                $wpdb->insert($table, $d);
            }
        }
    }

    private static function tableExists(string $tableName): bool
    {
        global $wpdb;
        $fullTableName = WeeklySchema::table($tableName);
        $result = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $fullTableName)
        );
        return $result === $fullTableName;
    }
}
