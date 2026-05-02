<?php

namespace BigBrotherJunkies\Data\Announcements;

use BigBrotherJunkies\Data\Comments\CommentSchema;
use BigBrotherJunkies\Data\Comments\AvatarUploader;

class AnnouncementService
{
    /**
     * Create a new announcement
     */
    public static function create(string $message, int $createdBy): int
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_ANNOUNCEMENTS);

        $wpdb->insert($table, [
            'message' => $message,
            'created_by' => $createdBy,
        ], ['%s', '%d']);

        return $wpdb->insert_id;
    }

    /**
     * Get announcements with pagination (for admin history)
     */
    public static function getAll(int $page = 1, int $perPage = 20): array
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_ANNOUNCEMENTS);
        $offset = ($page - 1) * $perPage;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        $announcements = $wpdb->get_results($wpdb->prepare("
            SELECT a.*, u.display_name as author_name
            FROM {$table} a
            LEFT JOIN {$wpdb->users} u ON a.created_by = u.ID
            ORDER BY a.created_at DESC
            LIMIT %d OFFSET %d
        ", $perPage, $offset), ARRAY_A);

        return [
            'announcements' => $announcements,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Delete an announcement (also cleans up reads)
     */
    public static function delete(int $id): bool
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_ANNOUNCEMENTS);
        $readsTable = CommentSchema::table(CommentSchema::TABLE_ANNOUNCEMENT_READS);

        $wpdb->delete($readsTable, ['announcement_id' => $id], ['%d']);
        return (bool) $wpdb->delete($table, ['id' => $id], ['%d']);
    }

    /**
     * Get unread announcement count for a user
     */
    public static function getUnreadCount(int $userId): int
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_ANNOUNCEMENTS);
        $readsTable = CommentSchema::table(CommentSchema::TABLE_ANNOUNCEMENT_READS);

        return (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table} a
            LEFT JOIN {$readsTable} r ON r.announcement_id = a.id AND r.user_id = %d
            WHERE r.user_id IS NULL
        ", $userId));
    }

    /**
     * Get recent announcements for a user with read status, formatted as notifications
     */
    public static function getForUser(int $userId, int $limit = 10): array
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_ANNOUNCEMENTS);
        $readsTable = CommentSchema::table(CommentSchema::TABLE_ANNOUNCEMENT_READS);

        $announcements = $wpdb->get_results($wpdb->prepare("
            SELECT a.*, u.display_name as author_name,
                   CASE WHEN r.user_id IS NOT NULL THEN 1 ELSE 0 END as is_read
            FROM {$table} a
            LEFT JOIN {$readsTable} r ON r.announcement_id = a.id AND r.user_id = %d
            LEFT JOIN {$wpdb->users} u ON a.created_by = u.ID
            ORDER BY a.created_at DESC
            LIMIT %d
        ", $userId, $limit), ARRAY_A);

        return array_map([self::class, 'formatAsNotification'], $announcements);
    }

    /**
     * Mark announcements as read for a user
     *
     * @param int $userId
     * @param array $announcementIds Array of announcement IDs
     * @return int Number marked
     */
    public static function markAsRead(int $userId, array $announcementIds): int
    {
        global $wpdb;

        $readsTable = CommentSchema::table(CommentSchema::TABLE_ANNOUNCEMENT_READS);
        $count = 0;

        foreach ($announcementIds as $annId) {
            $result = $wpdb->replace($readsTable, [
                'user_id' => $userId,
                'announcement_id' => (int) $annId,
            ], ['%d', '%d']);

            if ($result) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Mark ALL announcements as read for a user
     */
    public static function markAllAsRead(int $userId): int
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_ANNOUNCEMENTS);
        $readsTable = CommentSchema::table(CommentSchema::TABLE_ANNOUNCEMENT_READS);

        // Get all unread announcement IDs
        $unreadIds = $wpdb->get_col($wpdb->prepare("
            SELECT a.id FROM {$table} a
            LEFT JOIN {$readsTable} r ON r.announcement_id = a.id AND r.user_id = %d
            WHERE r.user_id IS NULL
        ", $userId));

        if (empty($unreadIds)) {
            return 0;
        }

        return self::markAsRead($userId, $unreadIds);
    }

    /**
     * Format an announcement as a notification item
     */
    private static function formatAsNotification(array $announcement): array
    {
        $actorId = (int) $announcement['created_by'];

        return [
            'id' => 'ann_' . $announcement['id'],
            'type' => 'announcement',
            'is_read' => (bool) ($announcement['is_read'] ?? false),
            'created_at' => $announcement['created_at'],
            'time_ago' => human_time_diff(strtotime($announcement['created_at']), time()) . ' ago',
            'actor' => [
                'id' => $actorId,
                'name' => $announcement['author_name'] ?? 'Admin',
                'avatar' => AvatarUploader::getAvatarUrl($actorId, 32),
            ],
            'message' => $announcement['message'],
            'post' => null,
            'comment' => null,
            'data' => null,
        ];
    }
}
