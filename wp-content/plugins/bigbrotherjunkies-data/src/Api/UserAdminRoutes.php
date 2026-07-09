<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Billing\BillingSchema;
use BigBrotherJunkies\Data\Billing\SubscriptionManager;
use BigBrotherJunkies\Data\Comments\AvatarUploader;
use BigBrotherJunkies\Data\Comments\CommentSchema;
use BigBrotherJunkies\Data\Permissions\PermissionChecker;

/**
 * Admin user-management endpoints (member support).
 * All routes gated on the `user_management` permission (admin-only).
 */
class UserAdminRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/admin/users', [
            'methods' => 'GET',
            'callback' => [$this, 'listUsers'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/users/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getUser'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);
    }

    public function checkAccess(): bool
    {
        return PermissionChecker::userCan('user_management');
    }

    /**
     * Paginated, searchable user list.
     * Response shape mirrors the players admin list exactly.
     */
    public function listUsers(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $page = max(1, (int) $request->get_param('page') ?: 1);
        $perPage = min(100, max(1, (int) $request->get_param('per_page') ?: 25));
        $search = trim((string) $request->get_param('search'));
        $role = trim((string) $request->get_param('role'));
        $orderby = in_array($request->get_param('orderby'), ['registered', 'display_name'], true)
            ? $request->get_param('orderby')
            : 'registered';

        $args = [
            'number' => $perPage,
            'paged' => $page,
            'orderby' => $orderby,
            'order' => $orderby === 'display_name' ? 'ASC' : 'DESC',
            'count_total' => true,
            'fields' => 'all',
        ];
        if ($search !== '') {
            $args['search'] = '*' . esc_attr($search) . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }
        if ($role !== '') {
            $args['role__in'] = [$role];
        }

        $query = new \WP_User_Query($args);
        $users = $query->get_results();
        $total = (int) $query->get_total();
        $ids = array_map(static fn($u) => (int) $u->ID, $users);

        // Batch: approved comment counts (no per-row N+1)
        $commentCounts = [];
        // Batch: latest subscription status per user
        $subStatuses = [];
        if ($ids) {
            $idList = implode(',', $ids);
            $rows = $wpdb->get_results(
                "SELECT user_id, COUNT(*) AS cnt FROM {$wpdb->comments}
                 WHERE user_id IN ({$idList}) AND comment_approved = '1'
                 GROUP BY user_id"
            );
            foreach ($rows as $r) {
                $commentCounts[(int) $r->user_id] = (int) $r->cnt;
            }

            $subTable = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);
            $rows = $wpdb->get_results(
                "SELECT user_id, status FROM {$subTable}
                 WHERE user_id IN ({$idList}) ORDER BY id ASC"
            );
            foreach ($rows as $r) {
                $subStatuses[(int) $r->user_id] = $r->status; // last row wins
            }
        }

        $out = array_map(function ($u) use ($commentCounts, $subStatuses) {
            $id = (int) $u->ID;
            return [
                'id' => $id,
                'display_name' => $u->display_name,
                'user_login' => $u->user_login,
                'email' => $u->user_email,
                'roles' => array_values($u->roles),
                'registered' => $u->user_registered,
                'avatar' => AvatarUploader::getAvatarUrl($id, 48),
                'comment_count' => $commentCounts[$id] ?? 0,
                'subscription_status' => $subStatuses[$id] ?? null,
            ];
        }, $users);

        return new \WP_REST_Response([
            'users' => $out,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Full member-support detail: profile, karma, subscription snapshot, recent votes.
     */
    public function getUser(\WP_REST_Request $request)
    {
        global $wpdb;
        $id = (int) $request['id'];
        $u = get_user_by('id', $id);
        if (!$u) {
            return new \WP_Error('not_found', 'User not found', ['status' => 404]);
        }

        $commentCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_approved = '1'", $id
        ));

        // Karma: same upvotes-minus-downvotes calc UserProfileRoutes uses for public profiles.
        $votesTable = CommentSchema::table(CommentSchema::TABLE_VOTES);
        $votesReceived = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN v.vote_type = 1 THEN 1 ELSE 0 END), 0) as upvotes,
                COALESCE(SUM(CASE WHEN v.vote_type = -1 THEN 1 ELSE 0 END), 0) as downvotes
             FROM {$votesTable} v
             JOIN {$wpdb->comments} c ON v.comment_id = c.comment_ID
             WHERE c.user_id = %d",
            $id
        ), ARRAY_A);
        $karma = (int) (($votesReceived['upvotes'] ?? 0) - ($votesReceived['downvotes'] ?? 0));

        $subTable = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);
        $sub = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$subTable} WHERE user_id = %d ORDER BY id DESC LIMIT 1", $id
        ), ARRAY_A);
        $subscription = $sub ? [
            'plan_type' => $sub['plan_type'],
            'tier' => $sub['tier'] ?? 'supporter',
            'plan_name' => SubscriptionManager::displayName($sub['plan_type'], $sub['tier'] ?? 'supporter'),
            'status' => $sub['status'] ?? null,
            'processor' => $sub['processor'] ?? null,
            'current_period_end' => $sub['current_period_end'] ?? null,
            'stripe_customer_id' => $sub['stripe_customer_id'] ?? null,
            'stripe_subscription_id' => $sub['stripe_subscription_id'] ?? null,
            'paypal_subscription_id' => $sub['paypal_subscription_id'] ?? null,
        ] : null;

        // Last 10 feed-update votes. bbj_feed_ratings columns: update_id, user_id, rating,
        // created_at (confirmed against FeedUpdateRoutes.php + a live DESCRIBE — the table
        // has no post_id/vote/created columns as originally assumed).
        $ratings = $wpdb->prefix . 'bbj_feed_ratings';
        $votes = $wpdb->get_results($wpdb->prepare(
            "SELECT r.update_id AS post_id, r.rating AS vote, r.created_at AS created,
                    p.post_title AS title, p.post_name AS slug
             FROM {$ratings} r JOIN {$wpdb->posts} p ON p.ID = r.update_id
             WHERE r.user_id = %d ORDER BY r.id DESC LIMIT 10", $id
        ), ARRAY_A);

        return new \WP_REST_Response(['user' => [
            'id' => $id,
            'display_name' => $u->display_name,
            'user_login' => $u->user_login,
            'email' => $u->user_email,
            'roles' => array_values($u->roles),
            'registered' => $u->user_registered,
            'avatar' => AvatarUploader::getAvatarUrl($id, 96),
            'comment_count' => $commentCount,
            'karma' => $karma,
            'subscription' => $subscription,
            'recent_votes' => $votes ?: [],
        ]]);
    }
}
