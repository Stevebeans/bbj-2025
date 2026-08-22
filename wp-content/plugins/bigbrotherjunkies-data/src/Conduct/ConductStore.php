<?php

namespace BigBrotherJunkies\Data\Conduct;

/**
 * Data access for the conduct/enforcement system. Mirrors DmStore conventions:
 * static methods, UTC datetimes, dbDelta migration gated on a version option.
 */
class ConductStore
{
    public const DB_VERSION_OPTION = 'bbj_conduct_db_version';
    public const DB_VERSION = '1.0.0';

    public static function maybeMigrate(): void
    {
        if (get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach (ConductSchema::getAllSchemas() as $schema) {
            dbDelta($schema);
        }
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }
}
