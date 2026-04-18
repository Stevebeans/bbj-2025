<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Comments\NotificationService;

/**
 * Notification API Routes
 *
 * Provides endpoints for:
 * - Getting user notifications
 * - Getting unread count
 * - Marking notifications as read
 * - Deleting notifications
 * - User search for @mentions
 */
class NotificationRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        // Get user's notifications
        register_rest_route($namespace, '/notifications', [
            'methods' => 'GET',
            'callback' => [$this, 'getNotifications'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'page' => [
                    'default' => 1,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default' => 20,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Get unread notification count
        register_rest_route($namespace, '/notifications/unread-count', [
            'methods' => 'GET',
            'callback' => [$this, 'getUnreadCount'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
        ]);

        // Mark notifications as read
        register_rest_route($namespace, '/notifications/mark-read', [
            'methods' => 'POST',
            'callback' => [$this, 'markAsRead'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'sanitize_callback' => function ($ids) {
                        return array_map(function ($id) {
                            // Allow ann_ prefixed IDs for announcements
                            if (is_string($id) && str_starts_with($id, 'ann_')) {
                                return 'ann_' . absint(substr($id, 4));
                            }
                            return absint($id);
                        }, (array) $ids);
                    },
                ],
                'all' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        // Delete a notification
        register_rest_route($namespace, '/notifications/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteNotification'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Search users for @mentions autocomplete
        register_rest_route($namespace, '/users/search', [
            'methods' => 'GET',
            'callback' => [$this, 'searchUsers'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'q' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'default' => 10,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Get user's notifications
     */
    public function getNotifications(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $page = $request->get_param('page');
        $perPage = min($request->get_param('per_page'), 50);

        $result = NotificationService::getNotifications($userId, $page, $perPage);

        return new \WP_REST_Response([
            'success' => true,
            ...$result,
        ], 200);
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $count = NotificationService::getUnreadCount($userId);

        return new \WP_REST_Response([
            'success' => true,
            'count' => $count,
        ], 200);
    }

    /**
     * Mark notifications as read
     */
    public function markAsRead(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $markAll = $request->get_param('all');
        $ids = $request->get_param('ids');

        if ($markAll) {
            $count = NotificationService::markAsRead($userId, null);
        } else {
            $count = NotificationService::markAsRead($userId, $ids ?: []);
        }

        return new \WP_REST_Response([
            'success' => true,
            'marked_count' => $count,
        ], 200);
    }

    /**
     * Delete a notification
     */
    public function deleteNotification(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $notificationId = $request->get_param('id');

        $success = NotificationService::deleteNotification($notificationId, $userId);

        if (!$success) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Notification not found or not owned by user',
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Notification deleted',
        ], 200);
    }

    /**
     * Search users for @mentions autocomplete
     */
    public function searchUsers(\WP_REST_Request $request): \WP_REST_Response
    {
        $query = $request->get_param('q');
        $limit = min($request->get_param('limit'), 20);

        if (strlen($query) < 1) {
            return new \WP_REST_Response([
                'success' => true,
                'users' => [],
            ], 200);
        }

        $users = NotificationService::searchUsers($query, $limit);

        return new \WP_REST_Response([
            'success' => true,
            'users' => $users,
        ], 200);
    }

    /**
     * Permission callback: Check if user is logged in
     */
    public function checkUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }
}
