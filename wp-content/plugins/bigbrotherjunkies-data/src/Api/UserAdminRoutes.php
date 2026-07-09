<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Billing\BillingSchema;
use BigBrotherJunkies\Data\Billing\SubscriptionManager;
use BigBrotherJunkies\Data\Comments\AvatarUploader;
use BigBrotherJunkies\Data\Comments\CommentSchema;
use BigBrotherJunkies\Data\Email\WelcomeEmailService;
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

        register_rest_route(self::NAMESPACE, '/admin/users/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateUser'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/users/(?P<id>\d+)/roles', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateRoles'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/users/(?P<id>\d+)/send-password-reset', [
            'methods' => 'POST',
            'callback' => [$this, 'sendPasswordReset'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/users/(?P<id>\d+)/resend-email', [
            'methods' => 'POST',
            'callback' => [$this, 'resendEmail'],
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

    /**
     * Update email/display name. Returns the fresh detail payload on success.
     */
    public function updateUser(\WP_REST_Request $request)
    {
        $id = (int) $request['id'];
        if (!get_user_by('id', $id)) {
            return new \WP_Error('not_found', 'User not found', ['status' => 404]);
        }
        $data = ['ID' => $id];

        $email = $request->get_param('email');
        if ($email !== null && $email !== '') {
            $email = sanitize_email($email);
            if (!is_email($email)) {
                return new \WP_Error('invalid_email', 'Invalid email address', ['status' => 400]);
            }
            $existing = email_exists($email);
            if ($existing && (int) $existing !== $id) {
                return new \WP_Error('email_taken', 'Email already in use by another account', ['status' => 400]);
            }
            $data['user_email'] = $email;
        }

        $name = $request->get_param('display_name');
        if ($name !== null && trim($name) !== '') {
            $data['display_name'] = sanitize_text_field($name);
        }

        if (count($data) === 1) {
            return new \WP_Error('nothing_to_update', 'No fields provided', ['status' => 400]);
        }
        $result = wp_update_user($data);
        if (is_wp_error($result)) {
            return $result;
        }
        return $this->getUser($request); // return fresh detail payload
    }

    /**
     * Replace a user's roles. Server-side self-lockout guard: an admin can
     * never remove `administrator` from their own account via this route.
     */
    public function updateRoles(\WP_REST_Request $request)
    {
        $id = (int) $request['id'];
        $user = get_user_by('id', $id);
        if (!$user) {
            return new \WP_Error('not_found', 'User not found', ['status' => 404]);
        }
        $roles = (array) $request->get_param('roles');
        $valid = array_keys(wp_roles()->roles);
        foreach ($roles as $r) {
            if (!in_array($r, $valid, true)) {
                return new \WP_Error('invalid_role', "Unknown role: {$r}", ['status' => 400]);
            }
        }
        // Self-lockout guard
        if ($id === get_current_user_id() && !in_array('administrator', $roles, true)) {
            return new \WP_Error(
                'cannot_remove_own_admin',
                'You cannot remove administrator from your own account',
                ['status' => 400]
            );
        }
        foreach (array_values($user->roles) as $r) {
            $user->remove_role($r);
        }
        foreach ($roles as $r) {
            $user->add_role($r);
        }
        return new \WP_REST_Response(['success' => true, 'roles' => array_values($user->roles)]);
    }

    /**
     * Trigger the standard WP password-reset email. No password values ever
     * pass through this route.
     */
    public function sendPasswordReset(\WP_REST_Request $request)
    {
        $id = (int) $request['id'];
        $user = get_user_by('id', $id);
        if (!$user) {
            return new \WP_Error('not_found', 'User not found', ['status' => 404]);
        }
        $result = retrieve_password($user->user_login);
        if (is_wp_error($result)) {
            return $result;
        }
        return new \WP_REST_Response(['success' => true]);
    }

    /**
     * Manually re-send one of the transactional account emails (member support).
     */
    public function resendEmail(\WP_REST_Request $request)
    {
        global $wpdb;
        $id = (int) $request['id'];
        $user = get_user_by('id', $id);
        if (!$user) {
            return new \WP_Error('not_found', 'User not found', ['status' => 404]);
        }
        $type = (string) $request->get_param('type');

        if ($type === 'confirm') {
            // Same token flow as registration (AuthRoutes::register): regenerate
            // so the resend link is fresh, then build the same confirm URL.
            $token = wp_generate_password(32, false);
            update_user_meta($id, 'bbjd_verification_token', $token);
            update_user_meta($id, 'bbjd_verification_expires', time() + (24 * HOUR_IN_SECONDS));
            $frontend = rtrim(get_option('bbjd_email_settings', [])['frontend_url'] ?? 'https://bigbrotherjunkies.com', '/');
            $confirmUrl = $frontend . '/register/confirm?token=' . urlencode($token);
            WelcomeEmailService::sendConfirmation($id, $confirmUrl);
            return new \WP_REST_Response(['success' => true, 'type' => $type]);
        }

        if ($type === 'welcome') {
            // Manual admin resend: don't claim newsletter opt-in we haven't verified.
            WelcomeEmailService::sendWelcome($id, false);
            return new \WP_REST_Response(['success' => true, 'type' => $type]);
        }

        if ($type === 'premium') {
            $subTable = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);
            $sub = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$subTable} WHERE user_id = %d AND status IN ('active', 'lifetime')
                 ORDER BY id DESC LIMIT 1", $id
            ), ARRAY_A);
            if (!$sub) {
                return new \WP_Error('no_subscription', 'User has no active subscription to send a premium receipt for', ['status' => 400]);
            }
            // sendPremium() is idempotent-by-design (one send per subscription row, to
            // survive webhook retries) — clear its guard so an explicit admin resend
            // actually goes out instead of silently no-op'ing.
            if (!empty($sub['id'])) {
                delete_option('bbjd_premium_email_sent_' . (int) $sub['id']);
            }
            WelcomeEmailService::sendPremium($id, $sub);
            return new \WP_REST_Response(['success' => true, 'type' => $type]);
        }

        return new \WP_Error('not_implemented', "No sender for {$type}", ['status' => 501]);
    }
}
