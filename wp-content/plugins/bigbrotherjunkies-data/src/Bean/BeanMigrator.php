<?php

namespace BigBrotherJunkies\Data\Bean;

/**
 * Creates / updates the Ask the Bean tables (idempotent via dbDelta).
 */
class BeanMigrator
{
    private const VERSION = '1.0.0';
    private const OPTION = 'bbj_bean_db_version';

    public static function needsMigration(): bool
    {
        return get_option(self::OPTION) !== self::VERSION;
    }

    public static function migrate(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta(BeanSchema::getUsageTableSchema());
        update_option(self::OPTION, self::VERSION);
    }
}
