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

    /** The legacy comment blacklist a conduct ban mirrors itself into. */
    private static function blacklistTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'bbj_comment_blacklist';
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
        // A conduct ban also wrote a comment-blacklist row; deactivate it.
        $bl = self::blacklistTable();
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

        if ($action === 'lift') {
            self::liftAll($userId, $moderatorId);
            return ['ok' => true, 'dm_sent' => false];
        }

        $body = '';
        $actionId = 0;

        if ($action === 'warning') {
            $actionId = self::record($userId, $moderatorId, 'warning', '', null, $note);
            $body = ConductLogic::warningBody($note);
        } elseif ($action === 'timeout') {
            $expires = gmdate('Y-m-d H:i:s', time() + ConductLogic::DURATIONS[$duration] * 3600);
            $actionId = self::record($userId, $moderatorId, 'timeout', $scope, $expires, $note);
            $body = ConductLogic::timeoutBody($scope, $duration, $note);
        } elseif ($action === 'ban') {
            global $wpdb;
            $actionId = self::record($userId, $moderatorId, 'ban', 'both', null, $note);
            // Paper-trail row in the existing comment blacklist so the legacy
            // enforcement + admin UI stay consistent with the conduct log.
            $bl = self::blacklistTable();
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

    /** Acting from the queue tidies it: close the member's open reports. */
    public static function resolveOpenReportsFor(int $userId, int $moderatorId, string $action): void
    {
        global $wpdb;
        $crt = \BigBrotherJunkies\Data\Comments\CommentSchema::table(\BigBrotherJunkies\Data\Comments\CommentSchema::TABLE_REPORTS);
        $drt = \BigBrotherJunkies\Data\Messages\DmSchema::table(\BigBrotherJunkies\Data\Messages\DmSchema::TABLE_REPORTS);
        $dtt = \BigBrotherJunkies\Data\Messages\DmSchema::table(\BigBrotherJunkies\Data\Messages\DmSchema::TABLE_THREADS);
        $now = current_time('mysql', true);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$crt} SET status = 'actioned', action_taken = %s, reviewed_by = %d, reviewed_at = %s
             WHERE reported_user_id = %d AND status = 'pending'",
            'conduct_' . $action, $moderatorId, $now, $userId
        ));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$drt} r INNER JOIN {$dtt} t ON t.id = r.thread_id
             SET r.resolved_at = %s
             WHERE r.resolved_at IS NULL
               AND (CASE WHEN t.user_lo = r.reporter_id THEN t.user_hi ELSE t.user_lo END) = %d",
            $now, $userId
        ));
    }

    /** Members with at least one report, merged across comment + DM pipelines. */
    public static function membersOverview(int $page, int $perPage, bool $includeProtected): array
    {
        global $wpdb;
        $crt = \BigBrotherJunkies\Data\Comments\CommentSchema::table(\BigBrotherJunkies\Data\Comments\CommentSchema::TABLE_REPORTS);
        $drt = \BigBrotherJunkies\Data\Messages\DmSchema::table(\BigBrotherJunkies\Data\Messages\DmSchema::TABLE_REPORTS);
        $dtt = \BigBrotherJunkies\Data\Messages\DmSchema::table(\BigBrotherJunkies\Data\Messages\DmSchema::TABLE_THREADS);
        $act = ConductSchema::table(ConductSchema::TABLE_ACTIONS);

        $byUser = [];
        $commentRows = $wpdb->get_results(
            "SELECT reported_user_id AS uid, COUNT(*) AS total,
                    SUM(status = 'pending') AS open, MAX(created_at) AS last_at
             FROM {$crt} WHERE reported_user_id > 0 GROUP BY reported_user_id",
            ARRAY_A
        ) ?: [];
        foreach ($commentRows as $row) {
            $byUser[(int) $row['uid']] = [
                'comment_reports' => (int) $row['total'],
                'dm_reports'      => 0,
                'open_reports'    => (int) $row['open'],
                'last_report_at'  => $row['last_at'],
            ];
        }
        // The reported member in a DM report is the thread participant who is
        // not the reporter.
        $dmRows = $wpdb->get_results(
            "SELECT CASE WHEN t.user_lo = r.reporter_id THEN t.user_hi ELSE t.user_lo END AS uid,
                    COUNT(*) AS total, SUM(r.resolved_at IS NULL) AS open, MAX(r.created_at) AS last_at
             FROM {$drt} r INNER JOIN {$dtt} t ON t.id = r.thread_id
             GROUP BY uid",
            ARRAY_A
        ) ?: [];
        foreach ($dmRows as $row) {
            $uid = (int) $row['uid'];
            if (!isset($byUser[$uid])) {
                $byUser[$uid] = ['comment_reports' => 0, 'dm_reports' => 0, 'open_reports' => 0, 'last_report_at' => null];
            }
            $byUser[$uid]['dm_reports']   = (int) $row['total'];
            $byUser[$uid]['open_reports'] += (int) $row['open'];
            if ($row['last_at'] > ($byUser[$uid]['last_report_at'] ?? '')) {
                $byUser[$uid]['last_report_at'] = $row['last_at'];
            }
        }

        $members = [];
        foreach ($byUser as $uid => $counts) {
            $user = get_userdata($uid);
            if (!$user) {
                continue;
            }
            if (!$includeProtected && \BigBrotherJunkies\Data\Messages\DmStore::isProtectedTarget($uid)) {
                continue;
            }
            $members[] = $counts + [
                'user' => [
                    'id'     => $uid,
                    'name'   => $user->display_name,
                    'avatar' => get_avatar_url($uid),
                ],
                'active'       => self::activeRestrictions($uid),
                'action_count' => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$act} WHERE user_id = %d",
                    $uid
                )),
            ];
        }
        usort($members, static function ($a, $b) {
            return [$b['open_reports'], $b['last_report_at']] <=> [$a['open_reports'], $a['last_report_at']];
        });
        $total = count($members);
        $members = array_slice($members, ($page - 1) * $perPage, $perPage);
        return [
            'members'    => $members,
            'pagination' => [
                'total' => $total, 'per_page' => $perPage,
                'current_page' => $page, 'total_pages' => (int) ceil($total / max(1, $perPage)),
            ],
        ];
    }

    /** All reports about one member, merged + evidence-attached, newest first. */
    public static function memberReports(int $userId): array
    {
        global $wpdb;
        $crt = \BigBrotherJunkies\Data\Comments\CommentSchema::table(\BigBrotherJunkies\Data\Comments\CommentSchema::TABLE_REPORTS);
        $drt = \BigBrotherJunkies\Data\Messages\DmSchema::table(\BigBrotherJunkies\Data\Messages\DmSchema::TABLE_REPORTS);
        $dtt = \BigBrotherJunkies\Data\Messages\DmSchema::table(\BigBrotherJunkies\Data\Messages\DmSchema::TABLE_THREADS);
        $out = [];

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, ru.display_name AS reporter_name, c.comment_content, p.post_title
             FROM {$crt} r
             LEFT JOIN {$wpdb->users} ru ON ru.ID = r.reporter_id
             LEFT JOIN {$wpdb->comments} c ON c.comment_ID = r.comment_id
             LEFT JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
             WHERE r.reported_user_id = %d",
            $userId
        ), ARRAY_A) ?: [];
        foreach ($rows as $r) {
            $out[] = [
                'type'          => 'comment',
                'id'            => (int) $r['id'],
                'reason'        => $r['reason'],
                'details'       => $r['details'],
                'reporter_name' => $r['reporter_name'],
                'created_at'    => $r['created_at'],
                'status'        => $r['status'],
                'evidence'      => [
                    'comment_id' => (int) $r['comment_id'],
                    'content'    => $r['comment_content'],
                    'post_title' => $r['post_title'],
                ],
            ];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, ru.display_name AS reporter_name
             FROM {$drt} r
             INNER JOIN {$dtt} t ON t.id = r.thread_id
             LEFT JOIN {$wpdb->users} ru ON ru.ID = r.reporter_id
             WHERE (CASE WHEN t.user_lo = r.reporter_id THEN t.user_hi ELSE t.user_lo END) = %d",
            $userId
        ), ARRAY_A) ?: [];
        foreach ($rows as $r) {
            $out[] = [
                'type'          => 'dm',
                'id'            => (int) $r['id'],
                'reason'        => $r['reason'] !== '' ? $r['reason'] : 'other',
                'details'       => html_entity_decode($r['note'], ENT_QUOTES, 'UTF-8'),
                'reporter_name' => $r['reporter_name'],
                'created_at'    => $r['created_at'],
                'status'        => $r['resolved_at'] ? 'resolved' : 'pending',
                'evidence'      => self::dmEvidence((int) $r['thread_id'], $r['message_id'] !== null ? (int) $r['message_id'] : null),
            ];
        }

        usort($out, static fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $out;
    }

    /**
     * Report-attached messages ONLY: the reported message + up to 5 preceding.
     * Never a general thread-read (spec: no mod browsing of DMs).
     */
    public static function dmEvidence(int $threadId, ?int $messageId): ?array
    {
        if ($messageId === null) {
            return null;
        }
        global $wpdb;
        $mt = \BigBrotherJunkies\Data\Messages\DmSchema::table(\BigBrotherJunkies\Data\Messages\DmSchema::TABLE_MESSAGES);
        $shape = static function (array $m): array {
            $sender = get_userdata((int) $m['sender_id']);
            return [
                'sender_name' => $sender ? $sender->display_name : 'Deleted user',
                'body'        => html_entity_decode($m['body'], ENT_QUOTES, 'UTF-8'),
                'created_at'  => $m['created_at'],
            ];
        };
        $reported = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$mt} WHERE id = %d AND thread_id = %d", $messageId, $threadId
        ), ARRAY_A);
        if (!$reported) {
            return null;
        }
        $context = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$mt} WHERE thread_id = %d AND id < %d ORDER BY id DESC LIMIT 5",
            $threadId, $messageId
        ), ARRAY_A) ?: [];
        return [
            'message' => $shape($reported),
            'context' => array_map($shape, array_reverse($context)),
        ];
    }
}
