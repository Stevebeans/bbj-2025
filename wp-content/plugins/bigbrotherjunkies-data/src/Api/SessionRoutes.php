<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Comments\CommentSchema;

/**
 * User Session/Online Status API Routes
 *
 * Handles session heartbeats for online status indicators
 */
class SessionRoutes
{
    /**
     * Session timeout in minutes
     */
    public const SESSION_TIMEOUT = 5;

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        // Send heartbeat (update session)
        register_rest_route($namespace, '/session/heartbeat', [
            'methods' => 'POST',
            'callback' => [$this, 'heartbeat'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
        ]);

        // Check if user(s) are online
        register_rest_route($namespace, '/users/online', [
            'methods' => 'GET',
            'callback' => [$this, 'checkOnlineStatus'],
            'permission_callback' => '__return_true',
            'args' => [
                'user_ids' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Comma-separated list of user IDs',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Get online users count
        register_rest_route($namespace, '/users/online/count', [
            'methods' => 'GET',
            'callback' => [$this, 'getOnlineCount'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Update user's session heartbeat
     */
    public function heartbeat(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $userId = get_current_user_id();
        $table = CommentSchema::table(CommentSchema::TABLE_SESSIONS);
        // Use GMT time to match MySQL NOW() in queries
        $now = current_time('mysql', true);

        // Check for existing session
        $existingSession = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE user_id = %d",
            $userId
        ));

        if ($existingSession) {
            // Update existing session
            $wpdb->update(
                $table,
                ['last_activity' => $now],
                ['user_id' => $userId],
                ['%s'],
                ['%d']
            );
        } else {
            // Create new session
            $wpdb->insert($table, [
                'user_id' => $userId,
                'last_activity' => $now,
            ], ['%d', '%s']);
        }

        return new \WP_REST_Response([
            'success' => true,
            'timestamp' => $now,
        ], 200);
    }

    /**
     * Check online status for specified users
     */
    public function checkOnlineStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $userIdsParam = $request->get_param('user_ids');
        $userIds = array_filter(array_map('absint', explode(',', $userIdsParam)));

        if (empty($userIds)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'No valid user IDs provided',
            ], 400);
        }

        $table = CommentSchema::table(CommentSchema::TABLE_SESSIONS);
        $timeoutMinutes = self::SESSION_TIMEOUT;

        // Get online status for requested users
        $placeholders = implode(',', array_fill(0, count($userIds), '%d'));
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, last_activity,
                CASE WHEN last_activity > DATE_SUB(NOW(), INTERVAL {$timeoutMinutes} MINUTE) THEN 1 ELSE 0 END as is_online
            FROM {$table}
            WHERE user_id IN ({$placeholders})",
            ...$userIds
        ), ARRAY_A);

        // Build response map
        $onlineStatus = [];
        foreach ($userIds as $userId) {
            $onlineStatus[$userId] = false;
        }
        foreach ($results as $row) {
            $onlineStatus[(int)$row['user_id']] = (bool)$row['is_online'];
        }

        return new \WP_REST_Response([
            'success' => true,
            'online_status' => $onlineStatus,
        ], 200);
    }

    /**
     * Get count of online users
     */
    public function getOnlineCount(): \WP_REST_Response
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_SESSIONS);
        $timeoutMinutes = self::SESSION_TIMEOUT;

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id)
            FROM {$table}
            WHERE last_activity > DATE_SUB(NOW(), INTERVAL {$timeoutMinutes} MINUTE)"
        );

        return new \WP_REST_Response([
            'success' => true,
            'count' => $count,
        ], 200);
    }

    /**
     * Clean up old sessions (for cron)
     */
    public static function cleanupOldSessions(): int
    {
        global $wpdb;

        $table = CommentSchema::table(CommentSchema::TABLE_SESSIONS);

        // Delete sessions older than 24 hours
        $deleted = $wpdb->query(
            "DELETE FROM {$table} WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        return $deleted ?: 0;
    }

    /**
     * Permission callback: Check if user is logged in
     */
    public function checkUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }
}
