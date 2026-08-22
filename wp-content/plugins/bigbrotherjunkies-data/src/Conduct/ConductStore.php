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

    public const SYSTEM_USER_OPTION = 'bbjd_conduct_system_user_id';
    public const SYSTEM_USER_LOGIN  = 'bbj-moderation';

    /**
     * The account warnings/notices are sent from. Lazily created; pass
     * $createIfMissing = false for read-only checks (isProtectedTarget) so a
     * hot path never creates users.
     */
    public static function systemUserId(bool $createIfMissing = true): int
    {
        $id = (int) get_option(self::SYSTEM_USER_OPTION, 0);
        if ($id > 0 && get_userdata($id)) {
            return $id;
        }
        if (!$createIfMissing) {
            return 0;
        }
        $existing = get_user_by('login', self::SYSTEM_USER_LOGIN);
        if ($existing) {
            update_option(self::SYSTEM_USER_OPTION, (int) $existing->ID);
            return (int) $existing->ID;
        }
        $id = wp_insert_user([
            'user_login'   => self::SYSTEM_USER_LOGIN,
            'user_email'   => self::SYSTEM_USER_LOGIN . '@bigbrotherjunkies.com',
            'user_pass'    => wp_generate_password(32, true, true),
            'display_name' => 'BBJ Moderation',
            'role'         => 'subscriber',
        ]);
        if (is_wp_error($id)) {
            return 0;
        }
        update_option(self::SYSTEM_USER_OPTION, (int) $id);
        return (int) $id;
    }

    /**
     * Apply a ladder action: validate, guard protected targets, log, enforce
     * side effects (blacklist row on ban), notify the member by system DM.
     * The DM is best-effort; the action itself never fails on DM problems.
     */
    public static function applyAction(int $userId, int $moderatorId, string $action, string $scope, string $duration, string $note): array
    {
        $valid = ConductLogic::validAction($action, $scope, $duration);
        if (!$valid['ok']) {
            return $valid;
        }
        if (!get_userdata($userId)) {
            return ['ok' => false, 'reason' => 'no_user'];
        }
        if (\BigBrotherJunkies\Data\Messages\DmStore::isProtectedTarget($userId)) {
            return ['ok' => false, 'reason' => 'protected'];
        }

        global $wpdb;
        $body = '';
        $actionId = 0;

        if ($action === 'lift') {
            self::liftAll($userId, $moderatorId);
            return ['ok' => true, 'dm_sent' => false];
        }

        if ($action === 'warning') {
            $actionId = self::record($userId, $moderatorId, 'warning', '', null, $note);
            $body = ConductLogic::warningBody($note);
        } elseif ($action === 'timeout') {
            $expires = gmdate('Y-m-d H:i:s', time() + ConductLogic::DURATIONS[$duration] * 3600);
            $actionId = self::record($userId, $moderatorId, 'timeout', $scope, $expires, $note);
            $body = ConductLogic::timeoutBody($scope, $duration, $note);
        } elseif ($action === 'ban') {
            $actionId = self::record($userId, $moderatorId, 'ban', 'both', null, $note);
            // Paper-trail row in the existing comment blacklist so the legacy
            // enforcement + admin UI stay consistent with the conduct log.
            $bl = $wpdb->prefix . 'bbj_comment_blacklist';
            $already = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$bl} WHERE user_id = %d AND reason = %s AND is_active = 1", $userId, self::BAN_BLACKLIST_REASON
            ));
            if (!$already) {
                $wpdb->insert($bl, [
                    'user_id'    => $userId,
                    'reason'     => self::BAN_BLACKLIST_REASON,
                    'created_by' => $moderatorId,
                    'created_at' => current_time('mysql', true),
                    'is_active'  => 1,
                ]);
            }
            $body = ConductLogic::banBody($note);
        }

        $dmSent = false;
        $sys = self::systemUserId();
        if ($sys > 0 && $body !== '') {
            $res = \BigBrotherJunkies\Data\Messages\DmStore::sendSystem($sys, $userId, $body);
            $dmSent = !empty($res['ok']);
        }
        return ['ok' => true, 'action_id' => $actionId, 'dm_sent' => $dmSent];
    }
}
