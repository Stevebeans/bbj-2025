<?php

namespace BigBrotherJunkies\Data\Cron;

use BigBrotherJunkies\Data\Comments\CommentSchema;

/**
 * Notification Cleanup Cron Job
 *
 * Deletes notifications older than 90 days to keep the database clean.
 * Scheduled to run weekly.
 */
class NotificationCleanup
{
    /**
     * Cron hook name
     */
    public const CRON_HOOK = 'bbj_notification_cleanup';

    /**
     * Retention period in days
     */
    public const RETENTION_DAYS = 90;

    /**
     * Initialize the cron job
     */
    public function init(): void
    {
        // Register the cron action
        add_action(self::CRON_HOOK, [$this, 'cleanup']);

        // Schedule the cron job if not already scheduled
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'weekly', self::CRON_HOOK);
        }
    }

    /**
     * Unschedule the cron job (call on plugin deactivation)
     */
    public static function unschedule(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Run the cleanup
     *
     * Deletes:
     * - Notifications older than 90 days
     * - Read notifications older than 30 days (optional, can be uncommented)
     *
     * @return int Number of notifications deleted
     */
    public function cleanup(): int
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_NOTIFICATIONS);
        $retentionDays = self::RETENTION_DAYS;

        // Delete notifications older than retention period
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retentionDays
        ));

        // Log the cleanup
        if ($deleted > 0) {
            error_log(sprintf(
                '[BBJ] Notification cleanup: Deleted %d notifications older than %d days',
                $deleted,
                $retentionDays
            ));
        }

        return (int) $deleted;
    }

    /**
     * Manually run cleanup (for admin tools)
     *
     * @return array Cleanup results
     */
    public static function runManual(): array
    {
        $instance = new self();
        $deleted = $instance->cleanup();

        return [
            'success' => true,
            'deleted' => $deleted,
            'retention_days' => self::RETENTION_DAYS,
            'message' => sprintf('Deleted %d notifications older than %d days', $deleted, self::RETENTION_DAYS),
        ];
    }

    /**
     * Get cleanup stats (for admin dashboard)
     *
     * @return array Stats about notifications
     */
    public static function getStats(): array
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_NOTIFICATIONS);

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $unread = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_read = 0");
        $old = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            self::RETENTION_DAYS
        ));

        $nextRun = wp_next_scheduled(self::CRON_HOOK);

        return [
            'total' => $total,
            'unread' => $unread,
            'old' => $old,
            'retention_days' => self::RETENTION_DAYS,
            'next_run' => $nextRun ? date('Y-m-d H:i:s', $nextRun) : null,
        ];
    }
}
