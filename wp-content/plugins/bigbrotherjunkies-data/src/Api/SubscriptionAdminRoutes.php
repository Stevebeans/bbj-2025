<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Billing\SubscriptionManager;
use BigBrotherJunkies\Data\Permissions\PermissionChecker;

/**
 * Admin premium-members endpoints (the /admin/premium tab): subscription list
 * with derived fields, aggregate stats + trends, and an end-of-period cancel.
 * Thin REST layer over SubscriptionManager (which owns the Stripe/PayPal
 * calls, DB writes, and role revocation). Gated on `user_management` like the
 * Users tab.
 */
class SubscriptionAdminRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    private const STATUSES = ['active', 'canceled', 'past_due', 'expired', 'lifetime'];

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/admin/subscriptions', [
            'methods' => 'GET',
            'callback' => [$this, 'listSubscriptions'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/subscriptions/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getStats'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/subscriptions/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'cancelSubscription'],
            'permission_callback' => [$this, 'checkAccess'],
            'args' => ['id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint']],
        ]);
    }

    public function checkAccess(): bool
    {
        return PermissionChecker::userCan('user_management');
    }

    public function listSubscriptions(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = max(1, (int) $request->get_param('page') ?: 1);
        $perPage = min(100, max(1, (int) $request->get_param('per_page') ?: 25));
        $status = trim((string) $request->get_param('status'));
        if (!in_array($status, self::STATUSES, true)) {
            $status = null;
        }

        $manager = new SubscriptionManager();
        $result = $manager->getAllSubscriptions($status ?: null, $page, $perPage);

        $items = array_map([self::class, 'shapeRow'], $result['items']);

        return new \WP_REST_Response([
            'subscriptions' => $items,
            'pagination' => [
                'total' => (int) $result['total'],
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => (int) $result['pages'],
            ],
        ]);
    }

    public function getStats(): \WP_REST_Response
    {
        $manager = new SubscriptionManager();
        return new \WP_REST_Response([
            'stats' => array_map('intval', $manager->getStats()),
            'trends' => array_map('intval', $manager->getTrendStats()),
        ]);
    }

    /**
     * End-of-period cancel for one subscription row. Row-id in, but the
     * manager cancels by USER (one-active-sub model), so verify the row IS
     * that user's current active subscription before acting.
     */
    public function cancelSubscription(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $manager = new SubscriptionManager();

        $row = $manager->findById($id);
        if (!$row) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Subscription not found.'], 404);
        }
        if ($row['status'] !== 'active') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Only active subscriptions can be canceled (lifetime never expires; this one is ' . $row['status'] . ').',
            ], 400);
        }

        $active = $manager->getActiveSubscription((int) $row['user_id']);
        if (!$active || (int) $active['id'] !== $id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => "This row is not the member's current subscription — refresh the list.",
            ], 409);
        }

        $result = $manager->cancelSubscription((int) $row['user_id'], false);
        if (isset($result['error'])) {
            return new \WP_REST_Response(['success' => false, 'message' => (string) $result['error']], 502);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => (string) ($result['message'] ?? 'Canceled.'),
            'subscription' => self::shapeRow($manager->findById($id) ?? $row),
        ]);
    }

    /**
     * One list/detail row: raw columns the UI needs plus derived fields.
     * `recurring` is derived (no stored flag): a monthly/annual plan with a
     * live processor subscription id. PayPal lifetime rows are thin (null ids,
     * defaulted tier) — nulls here are expected, not bugs.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function shapeRow(array $row): array
    {
        $recurring = in_array($row['plan_type'], ['monthly', 'annual'], true)
            && (!empty($row['stripe_subscription_id']) || !empty($row['paypal_subscription_id']));

        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'display_name' => (string) ($row['display_name'] ?? ''),
            'user_email' => (string) ($row['user_email'] ?? ''),
            'user_login' => (string) ($row['user_login'] ?? ''),
            'plan_type' => (string) $row['plan_type'],
            'tier' => (string) ($row['tier'] ?? 'supporter'),
            'plan_name' => SubscriptionManager::displayName((string) $row['plan_type'], (string) ($row['tier'] ?? 'supporter')),
            'processor' => (string) $row['processor'],
            'status' => (string) $row['status'],
            'recurring' => $recurring,
            'amount_cents' => $row['amount_cents'] !== null ? (int) $row['amount_cents'] : null,
            'currency' => (string) ($row['currency'] ?? 'USD'),
            'created_at' => (string) $row['created_at'],
            'current_period_end' => $row['current_period_end'] ?: null,
            'cancel_at_period_end' => (bool) $row['cancel_at_period_end'],
            'canceled_at' => $row['canceled_at'] ?: null,
        ];
    }
}
