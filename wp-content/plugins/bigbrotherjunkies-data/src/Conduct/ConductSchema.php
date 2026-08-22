<?php

namespace BigBrotherJunkies\Data\Conduct;

class ConductSchema
{
    public const TABLE_ACTIONS = 'bbj_conduct_actions';

    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    public static function getAllSchemas(): array
    {
        return [self::TABLE_ACTIONS => self::getActionsTableSchema()];
    }

    /**
     * Enforcement + audit log, one row per moderator action against a member.
     * action: warning|timeout|ban|lift. scope: commenting|dm_init|both ('' for
     * warning/lift). expires_at NULL = permanent (ban). revoked_at set = lifted
     * early. All datetimes UTC.
     */
    public static function getActionsTableSchema(): string
    {
        global $wpdb;
        $t = self::table(self::TABLE_ACTIONS);
        $charset = $wpdb->get_charset_collate();
        return "CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            moderator_id BIGINT(20) UNSIGNED NOT NULL,
            action VARCHAR(20) NOT NULL,
            scope VARCHAR(20) NOT NULL DEFAULT '',
            note VARCHAR(1000) NOT NULL DEFAULT '',
            expires_at DATETIME NULL DEFAULT NULL,
            revoked_at DATETIME NULL DEFAULT NULL,
            revoked_by BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_user_created (user_id, created_at),
            KEY idx_user_action (user_id, action)
        ) {$charset};";
    }
}
