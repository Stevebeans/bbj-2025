<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Billing\FoundersWindow;
use BigBrotherJunkies\Data\Billing\FoundingBadge;
use BigBrotherJunkies\Data\Billing\SubscriptionManager;
use BigBrotherJunkies\Data\Permissions\PermissionChecker;

/**
 * Admin premium-members endpoints (the /admin/premium tab): subscription list
 * with derived fields, aggregate stats + trends, an end-of-period cancel, and
 * the Founders window controls (open/close, closes-at, lifetime cap) plus its
 * spots/badge counters. Thin REST layer over SubscriptionManager and
 * FoundersWindow/FoundingBadge (which own the Stripe/PayPal calls, DB writes,
 * and role revocation). Gated on `user_management` like the Users tab.
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

        register_rest_route(self::NAMESPACE, '/admin/founders', [
            'methods' => 'GET',
            'callback' => [$this, 'getFounders'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/founders', [
            'methods' => 'POST',
            'callback' => [$this, 'saveFounders'],
            'permission_callback' => [$this, 'checkAccess'],
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
            'revenue' => $manager->getRevenueStats(),
            'founders' => [
                'spots_sold' => FoundersWindow::lifetimeSpotsSold(),
                'spots_left' => FoundersWindow::lifetimeSpotsLeft(),
                'badge_count' => FoundingBadge::count(),
                'window_open' => FoundersWindow::isOpen(),
            ],
        ]);
    }

    public function getFounders(): \WP_REST_Response
    {
        return new \WP_REST_Response(self::foundersPayload());
    }

    /**
     * Patch the Founders window option. `enabled` is validated with
     * filter_var(..., FILTER_VALIDATE_BOOLEAN) rather than a raw (bool) cast,
     * since (bool) "false" is true in PHP — a JSON string "false" must not
     * silently open the window. `closes_at` must be the empty string
     * (never-closes) or parse via strtotime(); a non-empty value is resolved
     * against UTC (appending ' UTC' before parsing, matching how
     * FoundersWindow::isOpenPure() reads it back) and stored as an absolute
     * 'Y-m-d H:i:s' via gmdate(), so a relative value like "tomorrow" or
     * "+3 days" is fixed at save time instead of drifting on every later
     * read. An unparseable value returns 400 with no write at all, rather
     * than silently reverting to the previous value. `lifetime_cap` is
     * clamped 1-500. Enabling the window while `opened_at` is still empty
     * stamps it with current_time('mysql') (site-local, same clock as
     * bbj_subscriptions.created_at) — see FoundersWindow's docblock.
     */
    public function saveFounders(\WP_REST_Request $request): \WP_REST_Response
    {
        $patch = (array) $request->get_json_params();
        $settings = FoundersWindow::settings();
        $wasEnabled = !empty($settings['enabled']);

        if (array_key_exists('enabled', $patch)) {
            $settings['enabled'] = filter_var($patch['enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('closes_at', $patch)) {
            $closesAt = trim((string) $patch['closes_at']);
            if ($closesAt === '') {
                $settings['closes_at'] = '';
            } else {
                $closesTs = strtotime($closesAt . ' UTC');
                if ($closesTs === false) {
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => 'closes_at must be a date like 2026-10-08 16:00:00 (UTC)',
                    ], 400);
                }
                $settings['closes_at'] = gmdate('Y-m-d H:i:s', $closesTs);
            }
        }

        if (array_key_exists('lifetime_cap', $patch)) {
            $settings['lifetime_cap'] = max(1, min(500, (int) $patch['lifetime_cap']));
        }

        if ($settings['enabled'] && $settings['opened_at'] === '') {
            $settings['opened_at'] = current_time('mysql');
        }

        update_option(FoundersWindow::OPTION, $settings);

        // Opening the window retroactively includes the members who already
        // bought a qualifying plan. Only on the closed->open edge, never on an
        // ordinary save (cap tweak, date change) - the backfill walks every
        // annual and lifetime row.
        $backfilled = 0;
        if (!$wasEnabled && $settings['enabled']) {
            $backfilled = FoundingBadge::backfillExisting();
        }

        return new \WP_REST_Response(array_merge(self::foundersPayload(), ['backfilled' => $backfilled]));
    }

    /** Shared GET/POST response shape for /admin/founders. */
    private static function foundersPayload(): array
    {
        return [
            'settings' => FoundersWindow::settings(),
            'spots_sold' => FoundersWindow::lifetimeSpotsSold(),
            'spots_left' => FoundersWindow::lifetimeSpotsLeft(),
            'badge_count' => FoundingBadge::count(),
            'window_open' => FoundersWindow::isOpen(),
        ];
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
