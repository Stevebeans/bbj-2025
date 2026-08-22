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
    public const BAN_BLACKLIST_REASON = 'Banned via conduct queue';

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

    public static function record(int $userId, int $moderatorId, string $action, string $scope, ?string $expiresAtUtc, string $note): int
    {
        global $wpdb;
        $wpdb->insert(ConductSchema::table(ConductSchema::TABLE_ACTIONS), [
            'user_id'      => $userId,
            'moderator_id' => $moderatorId,
            'action'       => $action,
            'scope'        => $scope,
            'note'         => wp_encode_emoji(mb_substr(sanitize_textarea_field($note), 0, 1000)),
            'expires_at'   => $expiresAtUtc,
            'created_at'   => current_time('mysql', true),
        ]);
        return (int) $wpdb->insert_id;
    }

    /** Rows that could restrict: unrevoked timeout/ban, unexpired or permanent. */
    private static function candidateRows(int $userId): array
    {
        global $wpdb;
        $t = ConductSchema::table(ConductSchema::TABLE_ACTIONS);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t}
             WHERE user_id = %d AND action IN ('timeout', 'ban') AND revoked_at IS NULL
               AND (expires_at IS NULL OR expires_at > %s)
             ORDER BY id DESC",
            $userId,
            current_time('mysql', true)
        ), ARRAY_A) ?: [];
    }

    public static function activeRestriction(int $userId, string $gate): ?array
    {
        foreach (self::candidateRows($userId) as $row) {
            if (ConductLogic::scopeCovers($row['scope'], $gate)) {
                return $row;
            }
        }
        return null;
    }

    public static function activeBan(int $userId): ?array
    {
        foreach (self::candidateRows($userId) as $row) {
            if ($row['action'] === 'ban') {
                return $row;
            }
        }
        return null;
    }

    public static function activeRestrictions(int $userId): array
    {
        return self::candidateRows($userId);
    }

    public static function historyFor(int $userId): array
    {
        global $wpdb;
        $t = ConductSchema::table(ConductSchema::TABLE_ACTIONS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name AS moderator_name
             FROM {$t} a LEFT JOIN {$wpdb->users} u ON u.ID = a.moderator_id
             WHERE a.user_id = %d ORDER BY a.id DESC",
            $userId
        ), ARRAY_A) ?: [];
        foreach ($rows as &$r) {
            $r['note'] = html_entity_decode($r['note'], ENT_QUOTES, 'UTF-8');
        }
        return $rows;
    }

    public static function liftAll(int $userId, int $moderatorId): int
    {
        global $wpdb;
        $t = ConductSchema::table(ConductSchema::TABLE_ACTIONS);
        $now = current_time('mysql', true);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$t} SET revoked_at = %s, revoked_by = %d
             WHERE user_id = %d AND action IN ('timeout', 'ban') AND revoked_at IS NULL
               AND (expires_at IS NULL OR expires_at > %s)",
            $now, $moderatorId, $userId, $now
        ));
        $revoked = (int) $wpdb->rows_affected;
        // A conduct ban also wrote a comment-blacklist row (Task 4); deactivate it.
        $bl = $wpdb->prefix . 'bbj_comment_blacklist';
        $wpdb->query($wpdb->prepare(
            "UPDATE {$bl} SET is_active = 0 WHERE user_id = %d AND reason = %s AND is_active = 1",
            $userId, self::BAN_BLACKLIST_REASON
        ));
        self::record($userId, $moderatorId, 'lift', '', null, '');
        return $revoked;
    }
}
