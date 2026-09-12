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
    public const DB_VERSION        = '1.3.0';

    public static function maybeMigrate(): void
    {
        if (version_compare(get_option(self::DB_VERSION_OPTION, '0'), self::DB_VERSION, '>=')) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach (DmSchema::getAllSchemas() as $schema) {
            dbDelta($schema);
        }
        // 1.2.0: staff became unblockable/unmutable — retroactively clear any
        // existing rows that target them (runs on a web request, so the live
        // permission config is authoritative).
        self::purgeProtectedTargets();
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    // -----------------------------------------------------------------------
    // Privacy + blocks
    // -----------------------------------------------------------------------

    public const PRIVACY_META = 'bbj_dm_privacy';

    /** Rate limits: messages per minute, new threads per day. */
    public const RL_MSGS_PER_MIN    = 20;
    public const RL_THREADS_PER_DAY = 10;

    public static function privacyFor(int $userId): string
    {
        $v = (string) get_user_meta($userId, self::PRIVACY_META, true);
        return in_array($v, DmLogic::PRIVACY_VALUES, true) ? $v : 'everyone';
    }

    public static function savePrivacy(int $userId, string $value): string
    {
        $value = in_array($value, DmLogic::PRIVACY_VALUES, true) ? $value : 'everyone';
        update_user_meta($userId, self::PRIVACY_META, $value);
        return $value;
    }

    public static function isBlockedEitherWay(int $a, int $b): bool
    {
        global $wpdb;
        $t = DmSchema::table(DmSchema::TABLE_BLOCKS);
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$t} WHERE (blocker_id = %d AND blocked_id = %d)
                OR (blocker_id = %d AND blocked_id = %d) LIMIT 1",
            $a, $b, $b, $a
        ));
    }

    public static function block(int $blocker, int $blocked): void
    {
        global $wpdb;
        $t = DmSchema::table(DmSchema::TABLE_BLOCKS);
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$t} (blocker_id, blocked_id, created_at) VALUES (%d, %d, %s)",
            $blocker, $blocked, current_time('mysql', true)
        ));
    }

    public static function unblock(int $blocker, int $blocked): void
    {
        global $wpdb;
        $t = DmSchema::table(DmSchema::TABLE_BLOCKS);
        $wpdb->delete($t, ['blocker_id' => $blocker, 'blocked_id' => $blocked]);
    }

    public static function blockedList(int $blocker): array
    {
        global $wpdb;
        $t = DmSchema::table(DmSchema::TABLE_BLOCKS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT b.blocked_id AS user_id, u.user_login AS username, u.display_name
               FROM {$t} b INNER JOIN {$wpdb->users} u ON u.ID = b.blocked_id
              WHERE b.blocker_id = %d ORDER BY b.created_at DESC",
            $blocker
        ), ARRAY_A) ?: [];
        foreach ($rows as &$r) {
            $r['user_id'] = (int) $r['user_id'];
        }
        return $rows;
    }

    public static function blockedIds(int $blocker): array
    {
        global $wpdb;
        $t = DmSchema::table(DmSchema::TABLE_BLOCKS);
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT blocked_id FROM {$t} WHERE blocker_id = %d", $blocker
        )));
    }

    // -----------------------------------------------------------------------
    // Comment mutes (one-way, comments-only; see comment-mute-block spec)
    // -----------------------------------------------------------------------

    public static function mute(int $muter, int $muted): void
    {
        global $wpdb;
        $t = DmSchema::table(DmSchema::TABLE_MUTES);
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$t} (muter_id, muted_id, created_at) VALUES (%d, %d, %s)",
            $muter, $muted, current_time('mysql', true)
        ));
    }

    public static function unmute(int $muter, int $muted): void
    {
        global $wpdb;
        $t = DmSchema::table(DmSchema::TABLE_MUTES);
        $wpdb->delete($t, ['muter_id' => $muter, 'muted_id' => $muted]);
    }

    public static function mutedIds(int $muter): array
    {
        global $wpdb;
        $t = DmSchema::table(DmSchema::TABLE_MUTES);
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT muted_id FROM {$t} WHERE muter_id = %d", $muter
        )));
    }

    /**
     * Admins and comment moderators can't be muted or blocked — members were
     * blocking mods and then wondering why moderation replies never showed.
     * "Staff" = anyone the live permission config grants comment_moderation
     * (administrators are always in that set). The conduct system account is
     * also protected: it must stay reachable so warnings can land as DMs.
     */
    public static function isProtectedTarget(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if ($userId === \BigBrotherJunkies\Data\Conduct\ConductStore::systemUserId(false)) {
            return true;
        }
        return \BigBrotherJunkies\Data\Permissions\PermissionChecker::userCan('comment_moderation', $userId);
    }

    /** Remove any existing block/mute rows whose target is protected staff. */
    public static function purgeProtectedTargets(): void
    {
        global $wpdb;
        $tables = [
            [DmSchema::table(DmSchema::TABLE_BLOCKS), 'blocked_id'],
            [DmSchema::table(DmSchema::TABLE_MUTES),  'muted_id'],
        ];
        foreach ($tables as [$table, $col]) {
            $targets = array_map('intval', $wpdb->get_col("SELECT DISTINCT {$col} FROM {$table}"));
            foreach ($targets as $target) {
                if (self::isProtectedTarget($target)) {
                    $wpdb->delete($table, [$col => $target]);
                }
            }
        }
    }

    public static function mutedList(int $muter): array
    {
        global $wpdb;
        $t = DmSchema::table(DmSchema::TABLE_MUTES);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.muted_id AS user_id, u.user_login AS username, u.display_name
               FROM {$t} m INNER JOIN {$wpdb->users} u ON u.ID = m.muted_id
              WHERE m.muter_id = %d ORDER BY m.created_at DESC",
            $muter
        ), ARRAY_A) ?: [];
        foreach ($rows as &$r) {
            $r['user_id'] = (int) $r['user_id'];
        }
        return $rows;
    }

    public static function recipientFollowsSender(int $recipient, int $sender): bool
    {
        global $wpdb;
        $t = $wpdb->prefix . \BigBrotherJunkies\Data\Comments\CommentSchema::TABLE_FOLLOWS;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$t} WHERE follower_id = %d AND following_id = %d LIMIT 1",
            $recipient, $sender
        ));
    }

    // -----------------------------------------------------------------------
    // Send
    // -----------------------------------------------------------------------

    /** @return array{ok:bool,reason?:string,thread_id?:int,message?:array} */
    public static function send(int $senderId, int $recipientId, string $rawBody): array
    {
        if ($senderId === $recipientId) {
            return ['ok' => false, 'reason' => 'self'];
        }
        if (!get_userdata($recipientId)) {
            return ['ok' => false, 'reason' => 'no_user'];
        }

        // Conduct: an active ban silences DMs entirely.
        if (\BigBrotherJunkies\Data\Conduct\ConductStore::activeBan($senderId) !== null) {
            return ['ok' => false, 'reason' => 'suspended'];
        }

        $valid = DmLogic::validBody(sanitize_textarea_field(html_entity_decode($rawBody, ENT_QUOTES, 'UTF-8')));
        if (!$valid['ok']) {
            return ['ok' => false, 'reason' => $valid['reason']];
        }

        $gate = DmLogic::canMessage(
            self::privacyFor($recipientId),
            self::recipientFollowsSender($recipientId, $senderId),
            self::isBlockedEitherWay($senderId, $recipientId)
        );
        if (!$gate['ok']) {
            return ['ok' => false, 'reason' => $gate['reason']];
        }

        // Per-minute message rate limit.
        $rlKey = 'bbj_dm_rl_' . $senderId;
        $sent = (int) get_transient($rlKey);
        if ($sent >= self::RL_MSGS_PER_MIN) {
            return ['ok' => false, 'reason' => 'rate_limited'];
        }

        global $wpdb;
        $threads  = DmSchema::table(DmSchema::TABLE_THREADS);
        $messages = DmSchema::table(DmSchema::TABLE_MESSAGES);
        [$lo, $hi] = DmLogic::pair($senderId, $recipientId);
        $now = current_time('mysql', true);

        $threadId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$threads} WHERE user_lo = %d AND user_hi = %d",
            $lo, $hi
        ));

        if (!$threadId) {
            // Conduct: a dm_init timeout stops new conversations (replies still work).
            if (\BigBrotherJunkies\Data\Conduct\ConductStore::activeRestriction($senderId, 'dm_init') !== null) {
                return ['ok' => false, 'reason' => 'suspended'];
            }

            // New-threads-per-day limit only applies when opening a conversation.
            $ntKey = 'bbj_dm_nt_' . $senderId;
            $opened = (int) get_transient($ntKey);
            if ($opened >= self::RL_THREADS_PER_DAY) {
                return ['ok' => false, 'reason' => 'thread_limited'];
            }
            $inserted = $wpdb->insert($threads, [
                'user_lo' => $lo, 'user_hi' => $hi,
                'created_at' => $now, 'last_message_at' => $now,
                'last_sender_id' => $senderId,
            ]);
            if ($inserted === false) {
                // Lost a race to the UNIQUE key: reread.
                $threadId = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$threads} WHERE user_lo = %d AND user_hi = %d",
                    $lo, $hi
                ));
                if (!$threadId) {
                    return ['ok' => false, 'reason' => 'db'];
                }
            } else {
                $threadId = (int) $wpdb->insert_id;
                set_transient($ntKey, $opened + 1, DAY_IN_SECONDS);
            }
        }

        $encoded = wp_encode_emoji($valid['body']);
        $okIns = $wpdb->insert($messages, [
            'thread_id' => $threadId, 'sender_id' => $senderId,
            'body' => $encoded, 'created_at' => $now,
        ]);
        if ($okIns === false) {
            return ['ok' => false, 'reason' => 'db'];
        }
        $messageId = (int) $wpdb->insert_id;

        // Sender has read their own message: bump their cursor with last_message_at.
        $readCol = $senderId === $lo ? 'lo_read_at' : 'hi_read_at';
        $wpdb->update($threads, [
            'last_message_at' => $now,
            'last_sender_id'  => $senderId,
            $readCol          => $now,
        ], ['id' => $threadId]);

        set_transient($rlKey, $sent + 1, MINUTE_IN_SECONDS);

        return ['ok' => true, 'thread_id' => $threadId, 'message' => [
            'id' => $messageId, 'thread_id' => $threadId, 'sender_id' => $senderId,
            'body' => $valid['body'], 'created_at' => $now,
        ]];
    }

    /**
     * Moderation/system delivery: skips privacy, blocks, and rate limits.
     * Only ConductStore calls this; never expose it through a member route.
     *
     * @return array{ok:bool,thread_id?:int}
     */
    public static function sendSystem(int $senderId, int $recipientId, string $rawBody): array
    {
        if ($senderId <= 0 || $recipientId <= 0 || !get_userdata($recipientId)) {
            return ['ok' => false];
        }

        $valid = DmLogic::validBody(sanitize_textarea_field(html_entity_decode($rawBody, ENT_QUOTES, 'UTF-8')));
        if (!$valid['ok']) {
            return ['ok' => false];
        }

        global $wpdb;
        $threads  = DmSchema::table(DmSchema::TABLE_THREADS);
        $messages = DmSchema::table(DmSchema::TABLE_MESSAGES);
        [$lo, $hi] = DmLogic::pair($senderId, $recipientId);
        $now = current_time('mysql', true);

        $threadId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$threads} WHERE user_lo = %d AND user_hi = %d",
            $lo, $hi
        ));

        if (!$threadId) {
            $inserted = $wpdb->insert($threads, [
                'user_lo' => $lo, 'user_hi' => $hi,
                'created_at' => $now, 'last_message_at' => $now,
                'last_sender_id' => $senderId,
            ]);
            if ($inserted === false) {
                // Lost a race to the UNIQUE key: reread.
                $threadId = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$threads} WHERE user_lo = %d AND user_hi = %d",
                    $lo, $hi
                ));
                if (!$threadId) {
                    return ['ok' => false];
                }
            } else {
                $threadId = (int) $wpdb->insert_id;
            }
        }

        $encoded = wp_encode_emoji($valid['body']);
        $okIns = $wpdb->insert($messages, [
            'thread_id' => $threadId, 'sender_id' => $senderId,
            'body' => $encoded, 'created_at' => $now,
        ]);
        if ($okIns === false) {
            return ['ok' => false];
        }

        // Sender has read their own message: bump their cursor with last_message_at.
        $readCol = $senderId === $lo ? 'lo_read_at' : 'hi_read_at';
        $wpdb->update($threads, [
            'last_message_at' => $now,
            'last_sender_id'  => $senderId,
            $readCol          => $now,
        ], ['id' => $threadId]);

        return ['ok' => true, 'thread_id' => $threadId];
    }

    // -----------------------------------------------------------------------
    // Reads
    // -----------------------------------------------------------------------

    public static function inbox(int $userId, int $page, int $perPage): array
    {
        global $wpdb;
        $threads  = DmSchema::table(DmSchema::TABLE_THREADS);
        $messages = DmSchema::table(DmSchema::TABLE_MESSAGES);
        $blocks   = DmSchema::table(DmSchema::TABLE_BLOCKS);
        $offset   = max(0, ($page - 1) * $perPage);

        // Blocked threads are hidden both ways.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, u.user_login AS other_username, u.display_name AS other_name,
                    CASE WHEN t.user_lo = %d THEN t.user_hi ELSE t.user_lo END AS other_id
               FROM {$threads} t
               INNER JOIN {$wpdb->users} u
                       ON u.ID = CASE WHEN t.user_lo = %d THEN t.user_hi ELSE t.user_lo END
              WHERE (t.user_lo = %d OR t.user_hi = %d)
                AND NOT EXISTS (
                    SELECT 1 FROM {$blocks} b
                     WHERE (b.blocker_id = t.user_lo AND b.blocked_id = t.user_hi)
                        OR (b.blocker_id = t.user_hi AND b.blocked_id = t.user_lo))
              ORDER BY t.last_message_at DESC
              LIMIT %d OFFSET %d",
            $userId, $userId, $userId, $userId, $perPage, $offset
        ), ARRAY_A) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $otherId = (int) $r['other_id'];
            $myRead  = ((int) $r['user_lo'] === $userId) ? $r['lo_read_at'] : $r['hi_read_at'];
            $preview = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT body FROM {$messages} WHERE thread_id = %d ORDER BY id DESC LIMIT 1",
                (int) $r['id']
            ));
            $preview = html_entity_decode($preview, ENT_QUOTES, 'UTF-8');
            $out[] = [
                'id'              => (int) $r['id'],
                'other'           => [
                    'id'       => $otherId,
                    'username' => (string) $r['other_username'],
                    'name'     => (string) $r['other_name'],
                    'avatar'   => get_avatar_url($otherId, ['size' => 64, 'default' => 'mm']),
                    'founding_badge' => \BigBrotherJunkies\Data\Billing\FoundingBadge::has($otherId),
                ],
                'last_message'    => mb_strlen($preview) > 120 ? mb_substr($preview, 0, 120) . '…' : $preview,
                'last_message_at' => (string) $r['last_message_at'],
                'unread'          => DmLogic::isUnread(
                    (string) $r['last_message_at'], $myRead, (int) $r['last_sender_id'], $userId
                ),
            ];
        }
        return $out;
    }

    /** Null when $userId is not a participant of the thread. */
    public static function messages(int $threadId, int $userId, ?int $beforeId, int $limit): ?array
    {
        global $wpdb;
        $threads  = DmSchema::table(DmSchema::TABLE_THREADS);
        $messages = DmSchema::table(DmSchema::TABLE_MESSAGES);

        $thread = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$threads} WHERE id = %d", $threadId
        ), ARRAY_A);
        if (!$thread || ((int) $thread['user_lo'] !== $userId && (int) $thread['user_hi'] !== $userId)) {
            return null;
        }

        $where = $beforeId ? $wpdb->prepare("AND id < %d", $beforeId) : '';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, thread_id, sender_id, body, created_at FROM {$messages}
              WHERE thread_id = %d {$where} ORDER BY id DESC LIMIT %d",
            $threadId, $limit
        ), ARRAY_A) ?: [];

        $rows = array_reverse($rows); // oldest -> newest for rendering
        foreach ($rows as &$r) {
            $r['id']        = (int) $r['id'];
            $r['thread_id'] = (int) $r['thread_id'];
            $r['sender_id'] = (int) $r['sender_id'];
            $r['body']      = html_entity_decode((string) $r['body'], ENT_QUOTES, 'UTF-8');
        }
        return $rows;
    }

    public static function markRead(int $threadId, int $userId): bool
    {
        global $wpdb;
        $threads = DmSchema::table(DmSchema::TABLE_THREADS);
        $thread = $wpdb->get_row($wpdb->prepare(
            "SELECT user_lo, user_hi FROM {$threads} WHERE id = %d", $threadId
        ), ARRAY_A);
        if (!$thread) {
            return false;
        }
        $col = (int) $thread['user_lo'] === $userId ? 'lo_read_at'
             : ((int) $thread['user_hi'] === $userId ? 'hi_read_at' : '');
        if ($col === '') {
            return false;
        }
        $wpdb->update($threads, [$col => current_time('mysql', true)], ['id' => $threadId]);
        return true;
    }

    /** Threads with something unread for me (cheap badge query). */
    public static function unreadCount(int $userId): int
    {
        global $wpdb;
        $threads = DmSchema::table(DmSchema::TABLE_THREADS);
        $blocks  = DmSchema::table(DmSchema::TABLE_BLOCKS);
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$threads} t
              WHERE t.last_sender_id != %d
                AND ((t.user_lo = %d AND (t.lo_read_at IS NULL OR t.last_message_at > t.lo_read_at))
                  OR (t.user_hi = %d AND (t.hi_read_at IS NULL OR t.last_message_at > t.hi_read_at)))
                AND NOT EXISTS (
                    SELECT 1 FROM {$blocks} b
                     WHERE (b.blocker_id = t.user_lo AND b.blocked_id = t.user_hi)
                        OR (b.blocker_id = t.user_hi AND b.blocked_id = t.user_lo))",
            $userId, $userId, $userId
        ));
    }

    // -----------------------------------------------------------------------
    // Admin moderation
    // -----------------------------------------------------------------------

    /**
     * Reports for the admin screen, newest first: reporter + participant
     * names, decoded note and reported message, plus a short decoded excerpt
     * of the thread for context. Reports are rare enough that the per-row
     * excerpt query is fine.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function reportsList(bool $openOnly, int $page, int $perPage): array
    {
        global $wpdb;
        $reports  = DmSchema::table(DmSchema::TABLE_REPORTS);
        $threads  = DmSchema::table(DmSchema::TABLE_THREADS);
        $messages = DmSchema::table(DmSchema::TABLE_MESSAGES);
        $offset   = max(0, ($page - 1) * $perPage);
        $where    = $openOnly ? 'WHERE r.resolved_at IS NULL' : '';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.reporter_id, r.thread_id, r.message_id, r.reason, r.note,
                    r.created_at, r.resolved_at,
                    ru.user_login AS reporter_username,
                    lo.user_login AS lo_username, hi.user_login AS hi_username,
                    rm.body AS reported_body
               FROM {$reports} r
               LEFT JOIN {$wpdb->users} ru ON ru.ID = r.reporter_id
               LEFT JOIN {$threads} t ON t.id = r.thread_id
               LEFT JOIN {$wpdb->users} lo ON lo.ID = t.user_lo
               LEFT JOIN {$wpdb->users} hi ON hi.ID = t.user_hi
               LEFT JOIN {$messages} rm ON rm.id = r.message_id
               {$where}
              ORDER BY r.created_at DESC
              LIMIT %d OFFSET %d",
            $perPage,
            $offset
        ), ARRAY_A) ?: [];

        foreach ($rows as &$r) {
            $threadId = (int) $r['thread_id'];
            $excerptRows = $wpdb->get_results($wpdb->prepare(
                "SELECT m.id, m.sender_id, m.body, m.created_at, u.user_login AS sender_username
                   FROM {$messages} m LEFT JOIN {$wpdb->users} u ON u.ID = m.sender_id
                  WHERE m.thread_id = %d ORDER BY m.id DESC LIMIT 10",
                $threadId
            ), ARRAY_A) ?: [];
            $excerpt = [];
            foreach (array_reverse($excerptRows) as $m) {
                $excerpt[] = [
                    'id'              => (int) $m['id'],
                    'sender_id'       => (int) $m['sender_id'],
                    'sender_username' => (string) $m['sender_username'],
                    'body'            => html_entity_decode((string) $m['body'], ENT_QUOTES, 'UTF-8'),
                    'created_at'      => (string) $m['created_at'],
                ];
            }

            $r['id']            = (int) $r['id'];
            $r['reporter_id']   = (int) $r['reporter_id'];
            $r['thread_id']     = $threadId;
            $r['message_id']    = $r['message_id'] !== null ? (int) $r['message_id'] : null;
            $r['note']          = html_entity_decode((string) $r['note'], ENT_QUOTES, 'UTF-8');
            $r['reported_body'] = $r['reported_body'] !== null
                ? html_entity_decode((string) $r['reported_body'], ENT_QUOTES, 'UTF-8')
                : null;
            $r['excerpt']       = $excerpt;
        }

        return $rows;
    }

    /** Stamp a report resolved. Returns false for an unknown id. */
    public static function resolveReport(int $reportId): bool
    {
        global $wpdb;
        $reports = DmSchema::table(DmSchema::TABLE_REPORTS);
        $updated = $wpdb->update(
            $reports,
            ['resolved_at' => current_time('mysql', true)],
            ['id' => $reportId]
        );
        return $updated !== false && $updated > 0;
    }

    public static function report(int $reporterId, int $threadId, ?int $messageId, string $note, string $reason = 'other'): bool
    {
        global $wpdb;
        $reason = in_array($reason, \BigBrotherJunkies\Data\Conduct\ConductLogic::REASONS, true) ? $reason : 'other';
        $reports = DmSchema::table(DmSchema::TABLE_REPORTS);
        $ok = $wpdb->insert($reports, [
            'reporter_id' => $reporterId,
            'thread_id'   => $threadId,
            'message_id'  => $messageId,
            'reason'      => $reason,
            'note'        => wp_encode_emoji(mb_substr(sanitize_text_field($note), 0, 500)),
            'created_at'  => current_time('mysql', true),
        ]);
        if ($ok === false) {
            return false;
        }
        $reporter = get_userdata($reporterId);
        wp_mail(
            get_option('admin_email'),
            'BBJ: DM report from ' . ($reporter ? $reporter->user_login : "user {$reporterId}"),
            "Thread #{$threadId}" . ($messageId ? ", message #{$messageId}" : '') . "\n\nReason: {$reason}\n\nNote: {$note}\n\nReview in admin."
        );
        return true;
    }

    public static function messageBelongsToThread(int $messageId, int $threadId): bool
    {
        global $wpdb;
        $t = DmSchema::table(DmSchema::TABLE_MESSAGES);
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t} WHERE id = %d AND thread_id = %d", $messageId, $threadId
        ));
    }
}
