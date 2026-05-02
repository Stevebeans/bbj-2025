<?php

namespace BigBrotherJunkies\Data\Comments;

use BigBrotherJunkies\Data\Announcements\AnnouncementService;

/**
 * Notification Service
 *
 * Handles creation and management of notifications for:
 * - @mentions in comments
 * - Replies to comments
 */
class NotificationService
{
    public const TYPE_MENTION = 'mention';
    public const TYPE_REPLY = 'reply';
    public const TYPE_THREAD = 'thread';

    /**
     * Create a mention notification
     *
     * @param int $userId User being notified (mentioned user)
     * @param int $actorId User who mentioned them
     * @param int $commentId The comment containing the mention
     * @param int $postId The post the comment is on
     * @return int|false Notification ID or false on failure
     */
    public static function createMentionNotification(int $userId, int $actorId, int $commentId, int $postId)
    {
        // Don't notify users about their own actions
        if ($userId === $actorId) {
            return false;
        }

        return self::createNotification($userId, self::TYPE_MENTION, $actorId, $commentId, $postId);
    }

    /**
     * Create a reply notification
     *
     * @param int $userId User being notified (parent comment author)
     * @param int $actorId User who replied
     * @param int $commentId The reply comment
     * @param int $postId The post the comment is on
     * @param int $parentId The parent comment ID
     * @return int|false Notification ID or false on failure
     */
    public static function createReplyNotification(int $userId, int $actorId, int $commentId, int $postId, int $parentId)
    {
        // Don't notify users about their own actions
        if ($userId === $actorId) {
            return false;
        }

        // Check user's notification preference
        if (!self::userWantsReplyNotifications($userId)) {
            return false;
        }

        $data = ['parent_id' => $parentId];
        return self::createNotification($userId, self::TYPE_REPLY, $actorId, $commentId, $postId, $data);
    }

    /**
     * Create a thread subscription notification
     *
     * @param int $userId User being notified (subscriber)
     * @param int $actorId User who commented
     * @param int $commentId The new comment
     * @param int $postId The post the comment is on
     * @return int|false Notification ID or false on failure
     */
    public static function createThreadNotification(int $userId, int $actorId, int $commentId, int $postId)
    {
        // Don't notify users about their own comments
        if ($userId === $actorId) {
            return false;
        }

        return self::createNotification($userId, self::TYPE_THREAD, $actorId, $commentId, $postId);
    }

    /**
     * Check if user wants reply notifications
     *
     * @param int $userId User ID
     * @return bool True if user wants notifications
     */
    public static function userWantsReplyNotifications(int $userId): bool
    {
        $value = get_user_meta($userId, 'bbj_notify_replies', true);
        // Default to true if not set
        return $value === '' || (bool) $value;
    }

    /**
     * Get all users subscribed to a post
     *
     * @param int $postId The post ID
     * @return array Array of user IDs
     */
    public static function getPostSubscribers(int $postId): array
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_POST_SUBSCRIPTIONS);

        return $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE post_id = %d",
            $postId
        ));
    }

    /**
     * Notify all subscribers of a new comment on a post
     *
     * @param int $postId The post ID
     * @param int $actorId User who commented
     * @param int $commentId The new comment ID
     * @param int|null $excludeUserId User to exclude (e.g., parent comment author who got reply notification)
     * @return int Number of notifications created
     */
    public static function notifyPostSubscribers(int $postId, int $actorId, int $commentId, ?int $excludeUserId = null): int
    {
        $subscribers = self::getPostSubscribers($postId);
        $count = 0;

        foreach ($subscribers as $userId) {
            $userId = (int) $userId;

            // Skip excluded user (they may have already received a reply notification)
            if ($excludeUserId && $userId === $excludeUserId) {
                continue;
            }

            $result = self::createThreadNotification($userId, $actorId, $commentId, $postId);
            if ($result) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Create a notification record
     *
     * @param int $userId User to notify
     * @param string $type Notification type
     * @param int|null $actorId User who triggered the notification
     * @param int|null $commentId Related comment
     * @param int|null $postId Related post
     * @param array $data Additional data
     * @return int|false Notification ID or false on failure
     */
    private static function createNotification(int $userId, string $type, ?int $actorId = null, ?int $commentId = null, ?int $postId = null, array $data = [])
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_NOTIFICATIONS);

        // Check for duplicate notification (same type, actor, comment within last minute)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE user_id = %d
               AND type = %s
               AND actor_id = %d
               AND comment_id = %d
               AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            $userId,
            $type,
            $actorId,
            $commentId
        ));

        if ($existing) {
            return false;
        }

        $result = $wpdb->insert($table, [
            'user_id' => $userId,
            'type' => $type,
            'actor_id' => $actorId,
            'comment_id' => $commentId,
            'post_id' => $postId,
            'data' => !empty($data) ? json_encode($data) : null,
            'is_read' => 0,
            'is_emailed' => 0,
        ], ['%d', '%s', '%d', '%d', '%d', '%s', '%d', '%d']);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Parse @mentions from comment content
     *
     * Extracts usernames from @username patterns and resolves them to user IDs.
     *
     * @param string $content The comment content
     * @return array Array of user IDs that were mentioned
     */
    public static function parseMentions(string $content): array
    {
        // Match @username patterns (alphanumeric, underscores, hyphens)
        // Must be preceded by start or whitespace, followed by end or whitespace/punctuation
        preg_match_all('/(^|\s)@([a-zA-Z0-9_-]+)/', $content, $matches);

        if (empty($matches[2])) {
            return [];
        }

        $userIds = [];
        $usernames = array_unique($matches[2]);

        foreach ($usernames as $username) {
            $userId = self::getUserIdByDisplayName($username);
            if ($userId > 0) {
                $userIds[] = $userId;
            }
        }

        return array_unique($userIds);
    }

    /**
     * Get user ID by display name
     *
     * Tries multiple fields to find a matching user:
     * 1. display_name (exact match)
     * 2. user_login (exact match)
     * 3. user_nicename (exact match)
     *
     * @param string $name The display name/username to look up
     * @return int User ID or 0 if not found
     */
    public static function getUserIdByDisplayName(string $name): int
    {
        global $wpdb;

        // First try exact match on display_name
        $userId = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE display_name = %s",
            $name
        ));

        if ($userId) {
            return (int) $userId;
        }

        // Try user_login
        $userId = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE user_login = %s",
            $name
        ));

        if ($userId) {
            return (int) $userId;
        }

        // Try user_nicename (URL slug)
        $userId = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE user_nicename = %s",
            $name
        ));

        return $userId ? (int) $userId : 0;
    }

    /**
     * Search users for mention autocomplete
     *
     * Uses bbj_active_users table for fast searching:
     * - Only contains users who have commented (no spam)
     * - Properly indexed on display_name and user_login
     * - Uses prefix matching for index optimization
     *
     * @param string $query Search query (partial username/display name)
     * @param int $limit Max results to return
     * @return array Array of user data for autocomplete
     */
    public static function searchUsers(string $query, int $limit = 10): array
    {
        global $wpdb;

        $activeUsersTable = CommentSchema::table(CommentSchema::TABLE_ACTIVE_USERS);
        $prefixTerm = $wpdb->esc_like($query) . '%';

        // Search the fast active_users table (indexed, only real users)
        $users = $wpdb->get_results($wpdb->prepare("
            SELECT user_id, display_name, user_login, comment_count
            FROM {$activeUsersTable}
            WHERE display_name LIKE %s OR user_login LIKE %s
            ORDER BY
                comment_count DESC,
                CASE WHEN display_name LIKE %s THEN 0 ELSE 1 END,
                LENGTH(display_name)
            LIMIT %d
        ", $prefixTerm, $prefixTerm, $prefixTerm, $limit), ARRAY_A);

        $results = [];
        foreach ($users as $user) {
            $userId = (int) $user['user_id'];
            $rank = RankCalculator::calculateRank($userId);

            $results[] = [
                'id' => $userId,
                'display_name' => $user['display_name'],
                'username' => $user['user_login'],
                'avatar' => AvatarUploader::getAvatarUrl($userId, 32),
                'rank' => $rank ? [
                    'key' => $rank['key'],
                    'name' => $rank['name'],
                    'color' => $rank['color'],
                    'bg_color' => $rank['bg_color'],
                ] : null,
            ];
        }

        return $results;
    }

    /**
     * Populate/refresh the active users table
     *
     * Should be run once to populate, then updated incrementally.
     * Can be called via WP-CLI or admin action.
     *
     * @return int Number of users added
     */
    public static function populateActiveUsersTable(): int
    {
        global $wpdb;

        $activeUsersTable = CommentSchema::table(CommentSchema::TABLE_ACTIVE_USERS);

        // Insert users who have at least 1 approved comment
        $result = $wpdb->query("
            INSERT INTO {$activeUsersTable} (user_id, display_name, user_login, comment_count, last_active)
            SELECT
                u.ID,
                u.display_name,
                u.user_login,
                COUNT(c.comment_ID) as comment_count,
                MAX(c.comment_date) as last_active
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->comments} c ON c.user_id = u.ID
            WHERE c.comment_approved = '1'
            GROUP BY u.ID, u.display_name, u.user_login
            HAVING comment_count > 0
            ON DUPLICATE KEY UPDATE
                display_name = VALUES(display_name),
                user_login = VALUES(user_login),
                comment_count = VALUES(comment_count),
                last_active = VALUES(last_active)
        ");

        return $result !== false ? $result : 0;
    }

    /**
     * Update a single user in the active users table
     *
     * Called when a user posts a comment to keep the table current.
     *
     * @param int $userId The user ID
     * @return bool Success
     */
    public static function updateActiveUser(int $userId): bool
    {
        global $wpdb;

        $activeUsersTable = CommentSchema::table(CommentSchema::TABLE_ACTIVE_USERS);

        // Get user data and comment count
        $userData = $wpdb->get_row($wpdb->prepare("
            SELECT
                u.ID as user_id,
                u.display_name,
                u.user_login,
                COUNT(c.comment_ID) as comment_count,
                MAX(c.comment_date) as last_active
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->comments} c ON c.user_id = u.ID AND c.comment_approved = '1'
            WHERE u.ID = %d
            GROUP BY u.ID, u.display_name, u.user_login
        ", $userId), ARRAY_A);

        if (!$userData || $userData['comment_count'] == 0) {
            // Remove user if they have no approved comments
            $wpdb->delete($activeUsersTable, ['user_id' => $userId], ['%d']);
            return true;
        }

        // Upsert user
        return (bool) $wpdb->replace($activeUsersTable, [
            'user_id' => $userData['user_id'],
            'display_name' => $userData['display_name'],
            'user_login' => $userData['user_login'],
            'comment_count' => $userData['comment_count'],
            'last_active' => $userData['last_active'],
        ], ['%d', '%s', '%s', '%d', '%s']);
    }

    /**
     * Get unread notification count for a user (includes announcements)
     *
     * @param int $userId The user ID
     * @return int Count of unread notifications
     */
    public static function getUnreadCount(int $userId): int
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_NOTIFICATIONS);

        $notifCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
            $userId
        ));

        $annCount = AnnouncementService::getUnreadCount($userId);

        return $notifCount + $annCount;
    }

    /**
     * Get notifications for a user (with announcements merged on page 1)
     *
     * @param int $userId The user ID
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array Notifications with pagination
     */
    public static function getNotifications(int $userId, int $page = 1, int $perPage = 20): array
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_NOTIFICATIONS);
        $offset = ($page - 1) * $perPage;

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
            $userId
        ));

        $notifications = $wpdb->get_results($wpdb->prepare("
            SELECT n.*,
                   u.display_name as actor_name,
                   p.post_title,
                   c.comment_content
            FROM {$table} n
            LEFT JOIN {$wpdb->users} u ON n.actor_id = u.ID
            LEFT JOIN {$wpdb->posts} p ON n.post_id = p.ID
            LEFT JOIN {$wpdb->comments} c ON n.comment_id = c.comment_ID
            WHERE n.user_id = %d
            ORDER BY n.created_at DESC
            LIMIT %d OFFSET %d
        ", $userId, $perPage, $offset), ARRAY_A);

        $formatted = [];
        foreach ($notifications as $notification) {
            $formatted[] = self::formatNotification($notification);
        }

        // Prepend recent announcements on page 1
        if ($page === 1) {
            $announcements = AnnouncementService::getForUser($userId);
            if (!empty($announcements)) {
                $formatted = array_merge($announcements, $formatted);
                $total += count($announcements);
            }
        }

        return [
            'notifications' => $formatted,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Format a notification for API response
     *
     * @param array $notification Raw notification data
     * @return array Formatted notification
     */
    private static function formatNotification(array $notification): array
    {
        $actorId = (int) ($notification['actor_id'] ?? 0);

        return [
            'id' => (int) $notification['id'],
            'type' => $notification['type'],
            'is_read' => (bool) $notification['is_read'],
            'created_at' => $notification['created_at'],
            'time_ago' => human_time_diff(strtotime($notification['created_at']), time()) . ' ago',
            'actor' => $actorId > 0 ? [
                'id' => $actorId,
                'name' => $notification['actor_name'] ?? 'Unknown',
                'avatar' => AvatarUploader::getAvatarUrl($actorId, 32),
            ] : null,
            'post' => $notification['post_id'] ? [
                'id' => (int) $notification['post_id'],
                'title' => $notification['post_title'] ?? 'Unknown Post',
                'url' => get_permalink($notification['post_id']),
            ] : null,
            'comment' => $notification['comment_id'] ? [
                'id' => (int) $notification['comment_id'],
                'excerpt' => wp_trim_words(strip_tags($notification['comment_content'] ?? ''), 10),
            ] : null,
            'data' => $notification['data'] ? json_decode($notification['data'], true) : null,
        ];
    }

    /**
     * Mark notifications as read (handles both regular and ann_ IDs)
     *
     * @param int $userId The user ID
     * @param array|null $ids Specific notification IDs to mark read, or null for all
     * @return int Number of notifications marked read
     */
    public static function markAsRead(int $userId, ?array $ids = null): int
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_NOTIFICATIONS);

        if ($ids === null) {
            // Mark all as read (including all announcements)
            $notifCount = $wpdb->update(
                $table,
                ['is_read' => 1],
                ['user_id' => $userId, 'is_read' => 0],
                ['%d'],
                ['%d', '%d']
            );
            $annCount = AnnouncementService::markAllAsRead($userId);
            return ($notifCount ?: 0) + $annCount;
        }

        if (empty($ids)) {
            return 0;
        }

        // Separate announcement IDs from regular notification IDs
        $announcementIds = [];
        $notificationIds = [];

        foreach ($ids as $id) {
            if (is_string($id) && str_starts_with($id, 'ann_')) {
                $announcementIds[] = (int) substr($id, 4);
            } else {
                $notificationIds[] = (int) $id;
            }
        }

        $count = 0;

        // Mark regular notifications as read
        if (!empty($notificationIds)) {
            $placeholders = implode(',', array_fill(0, count($notificationIds), '%d'));
            $params = array_merge([$userId], $notificationIds);
            $count += $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET is_read = 1 WHERE user_id = %d AND id IN ({$placeholders})",
                ...$params
            ));
        }

        // Mark announcements as read
        if (!empty($announcementIds)) {
            $count += AnnouncementService::markAsRead($userId, $announcementIds);
        }

        return $count;
    }

    /**
     * Delete a notification
     *
     * @param int $notificationId The notification ID
     * @param int $userId The user ID (for ownership verification)
     * @return bool Success
     */
    public static function deleteNotification(int $notificationId, int $userId): bool
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_NOTIFICATIONS);

        return (bool) $wpdb->delete($table, [
            'id' => $notificationId,
            'user_id' => $userId,
        ], ['%d', '%d']);
    }
}
