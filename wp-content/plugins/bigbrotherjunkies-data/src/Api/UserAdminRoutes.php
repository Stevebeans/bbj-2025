<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Billing\BillingSchema;
use BigBrotherJunkies\Data\Comments\AvatarUploader;
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
}
