<?php

namespace BigBrotherJunkies\Data\Push;

class PushMigrator
{
    public const VERSION_OPTION = 'bbj_push_db_version';
    public const CURRENT_VERSION = '1.0.0';

    public static function migrate(): void
    {
        if (version_compare((string) get_option(self::VERSION_OPTION, '0.0.0'), self::CURRENT_VERSION, '>=')) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta(PushSchema::createSql());
        update_option(self::VERSION_OPTION, self::CURRENT_VERSION);
    }
}
