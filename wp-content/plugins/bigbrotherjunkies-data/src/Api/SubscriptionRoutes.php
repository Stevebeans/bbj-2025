<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Comments\CommentSchema;

/**
 * Post Subscription API Routes
 *
 * Provides endpoints for managing post/thread subscriptions:
 * - Subscribe to a post (get notifications for all new comments)
 * - Unsubscribe from a post
 * - List user's subscriptions
 * - Check subscription status
 */
class SubscriptionRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        // Subscribe to a post
        register_rest_route($namespace, '/subscriptions/posts/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'subscribe'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Unsubscribe from a post
        register_rest_route($namespace, '/subscriptions/posts/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'unsubscribe'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Get subscription status for a post
        register_rest_route($namespace, '/subscriptions/posts/(?P<id>\d+)/status', [
            'methods' => 'GET',
            'callback' => [$this, 'getStatus'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // List user's subscriptions
        register_rest_route($namespace, '/subscriptions/posts', [
            'methods' => 'GET',
            'callback' => [$this, 'listSubscriptions'],
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
    }

    /**
     * Subscribe to a post
     */
    public function subscribe(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $userId = get_current_user_id();
        $postId = $request->get_param('id');

        // Verify post exists
        $post = get_post($postId);
        if (!$post || $post->post_status !== 'publish') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Post not found',
            ], 404);
        }

        $table = CommentSchema::table(CommentSchema::TABLE_POST_SUBSCRIPTIONS);

        // Check if already subscribed
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d AND post_id = %d",
            $userId,
            $postId
        ));

        if ($existing) {
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Already subscribed',
                'subscribed' => true,
            ], 200);
        }

        // Create subscription
        $result = $wpdb->insert($table, [
            'user_id' => $userId,
            'post_id' => $postId,
        ], ['%d', '%d']);

        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to subscribe',
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Subscribed to thread',
            'subscribed' => true,
        ], 200);
    }

    /**
     * Unsubscribe from a post
     */
    public function unsubscribe(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $userId = get_current_user_id();
        $postId = $request->get_param('id');

        $table = CommentSchema::table(CommentSchema::TABLE_POST_SUBSCRIPTIONS);

        $result = $wpdb->delete($table, [
            'user_id' => $userId,
            'post_id' => $postId,
        ], ['%d', '%d']);

        return new \WP_REST_Response([
            'success' => true,
            'message' => $result ? 'Unsubscribed from thread' : 'Was not subscribed',
            'subscribed' => false,
        ], 200);
    }

    /**
     * Get subscription status for a post
     */
    public function getStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $userId = get_current_user_id();
        $postId = $request->get_param('id');

        $table = CommentSchema::table(CommentSchema::TABLE_POST_SUBSCRIPTIONS);

        $subscribed = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d AND post_id = %d",
            $userId,
            $postId
        ));

        return new \WP_REST_Response([
            'success' => true,
            'subscribed' => $subscribed,
        ], 200);
    }

    /**
     * List user's subscriptions
     */
    public function listSubscriptions(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $userId = get_current_user_id();
        $page = $request->get_param('page');
        $perPage = min($request->get_param('per_page'), 50);
        $offset = ($page - 1) * $perPage;

        $table = CommentSchema::table(CommentSchema::TABLE_POST_SUBSCRIPTIONS);

        // Get total count
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
            $userId
        ));

        // Get subscriptions with post details
        $subscriptions = $wpdb->get_results($wpdb->prepare("
            SELECT
                s.id as subscription_id,
                s.post_id,
                s.created_at as subscribed_at,
                p.post_title,
                p.guid
            FROM {$table} s
            INNER JOIN {$wpdb->posts} p ON s.post_id = p.ID
            WHERE s.user_id = %d
            ORDER BY s.created_at DESC
            LIMIT %d OFFSET %d
        ", $userId, $perPage, $offset), ARRAY_A);

        $formatted = [];
        foreach ($subscriptions as $sub) {
            $formatted[] = [
                'id' => (int) $sub['subscription_id'],
                'post_id' => (int) $sub['post_id'],
                'post_title' => $sub['post_title'],
                'post_url' => get_permalink($sub['post_id']),
                'subscribed_at' => $sub['subscribed_at'],
                'subscribed_ago' => human_time_diff(strtotime($sub['subscribed_at']), time()) . ' ago',
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'subscriptions' => $formatted,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => ceil($total / $perPage),
            ],
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
