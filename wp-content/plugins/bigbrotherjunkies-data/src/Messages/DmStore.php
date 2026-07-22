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
                    'avatar'   => get_avatar_url($otherId, ['size' => 64]),
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

    public static function report(int $reporterId, int $threadId, ?int $messageId, string $note): bool
    {
        global $wpdb;
        $reports = DmSchema::table(DmSchema::TABLE_REPORTS);
        $ok = $wpdb->insert($reports, [
            'reporter_id' => $reporterId,
            'thread_id'   => $threadId,
            'message_id'  => $messageId,
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
            "Thread #{$threadId}" . ($messageId ? ", message #{$messageId}" : '') . "\n\nNote: {$note}\n\nReview in admin."
        );
        return true;
    }
}
