<?php

namespace BigBrotherJunkies\Data\Messages;

/**
 * Data layer for direct messages. House rules:
 *  - Bodies are wp_encode_emoji'd before INSERT (utf8mb3), decoded on read.
 *  - No Revalidation / cache-tag / CF-purge calls: DM data is client-fetched.
 */
class DmStore
{
    public const DB_VERSION_OPTION = 'bbj_dm_db_version';
    public const DB_VERSION        = '1.0.0';

    public static function maybeMigrate(): void
    {
        if (version_compare(get_option(self::DB_VERSION_OPTION, '0'), self::DB_VERSION, '>=')) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach (DmSchema::getAllSchemas() as $schema) {
            dbDelta($schema);
        }
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }
}
