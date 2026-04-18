<?php

namespace BigBrotherJunkies\Data\Billing;

/**
 * Manages subscription business logic
 * Handles role assignment, subscription records, and status updates
 */
class SubscriptionManager
{
    private static ?SubscriptionManager $instance = null;

    public static function getInstance(): SubscriptionManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get user's active subscription
     */
    public function getActiveSubscription(int $userId): ?array
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE user_id = %d
               AND status IN ('active', 'lifetime')
             ORDER BY created_at DESC
             LIMIT 1",
            $userId
        ), ARRAY_A);

        return $subscription ?: null;
    }

    /**
     * Get user's subscription history
     */
    public function getSubscriptionHistory(int $userId, int $limit = 10): array
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE user_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $userId,
            $limit
        ), ARRAY_A) ?: [];
    }

    /**
     * Create a new subscription record
     */
    public function createSubscription(array $data): ?int
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        $defaults = [
            'currency' => 'USD',
            'cancel_at_period_end' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $insertData = array_merge($defaults, $data);

        $result = $wpdb->insert($table, $insertData);

        if ($result) {
            $subscriptionId = $wpdb->insert_id;

            // Assign role to user
            $this->assignSupporterRole($insertData['user_id'], $insertData['plan_type']);

            return $subscriptionId;
        }

        return null;
    }

    /**
     * Update subscription status
     */
    public function updateSubscriptionStatus(int $subscriptionId, string $status, array $additionalData = []): bool
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        $updateData = array_merge([
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ], $additionalData);

        $result = $wpdb->update(
            $table,
            $updateData,
            ['id' => $subscriptionId]
        );

        if ($result !== false) {
            // Get subscription to update user role
            $subscription = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $subscriptionId
            ), ARRAY_A);

            if ($subscription) {
                if (in_array($status, ['active', 'lifetime'])) {
                    $this->assignSupporterRole($subscription['user_id'], $subscription['plan_type']);
                } elseif (in_array($status, ['expired', 'canceled'])) {
                    // Only remove role if subscription actually ended (not cancel_at_period_end)
                    if ($status === 'expired' || !($additionalData['cancel_at_period_end'] ?? false)) {
                        $this->removeSupporterRole($subscription['user_id']);
                    }
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Update subscription by Stripe subscription ID
     */
    public function updateByStripeSubscriptionId(string $stripeSubId, array $data): bool
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $table,
            $data,
            ['stripe_subscription_id' => $stripeSubId]
        );

        return $result !== false;
    }

    /**
     * Update subscription by PayPal subscription ID
     */
    public function updateByPayPalSubscriptionId(string $paypalSubId, array $data): bool
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $table,
            $data,
            ['paypal_subscription_id' => $paypalSubId]
        );

        return $result !== false;
    }

    /**
     * Find subscription by Stripe subscription ID
     */
    public function findByStripeSubscriptionId(string $stripeSubId): ?array
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE stripe_subscription_id = %s",
            $stripeSubId
        ), ARRAY_A) ?: null;
    }

    /**
     * Find subscription by PayPal subscription ID
     */
    public function findByPayPalSubscriptionId(string $paypalSubId): ?array
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE paypal_subscription_id = %s",
            $paypalSubId
        ), ARRAY_A) ?: null;
    }

    /**
     * Check if user has active subscription
     */
    public function hasActiveSubscription(int $userId): bool
    {
        return $this->getActiveSubscription($userId) !== null;
    }

    /**
     * Handle subscription cancellation
     */
    public function cancelSubscription(int $userId, bool $immediate = false): array
    {
        $subscription = $this->getActiveSubscription($userId);

        if (!$subscription) {
            return ['error' => 'No active subscription found'];
        }

        if ($subscription['status'] === 'lifetime') {
            return ['error' => 'Lifetime subscriptions cannot be canceled'];
        }

        if ($subscription['processor'] === 'stripe' && $subscription['stripe_subscription_id']) {
            $stripe = new StripeService();

            if ($immediate) {
                $result = $stripe->cancelSubscriptionImmediately($subscription['stripe_subscription_id']);

                if (isset($result['error'])) {
                    return $result;
                }

                $this->updateSubscriptionStatus($subscription['id'], 'canceled', [
                    'canceled_at' => current_time('mysql'),
                ]);

                return [
                    'success' => true,
                    'message' => 'Subscription has been canceled immediately',
                ];
            }

            $result = $stripe->cancelSubscription($subscription['stripe_subscription_id']);

            if (isset($result['error'])) {
                return $result;
            }

            $this->updateSubscriptionStatus($subscription['id'], 'active', [
                'cancel_at_period_end' => 1,
                'canceled_at' => current_time('mysql'),
            ]);

            return [
                'success' => true,
                'message' => 'Subscription will be canceled at the end of the billing period',
                'cancel_at' => $subscription['current_period_end'],
            ];
        }

        if ($subscription['processor'] === 'paypal' && $subscription['paypal_subscription_id']) {
            $paypal = new PayPalService();
            $result = $paypal->cancelSubscription($subscription['paypal_subscription_id']);

            if (isset($result['error'])) {
                return $result;
            }

            // PayPal always cancels immediately
            $this->updateSubscriptionStatus($subscription['id'], 'canceled', [
                'canceled_at' => current_time('mysql'),
            ]);

            return [
                'success' => true,
                'message' => 'Subscription has been canceled',
            ];
        }

        return ['error' => 'Unknown subscription processor'];
    }

    /**
     * Change a user's subscription plan (upgrade/downgrade)
     */
    public function changePlan(int $userId, string $newPlanType): array
    {
        $subscription = $this->getActiveSubscription($userId);

        if (!$subscription) {
            return ['error' => 'No active subscription found'];
        }

        if ($subscription['status'] === 'lifetime') {
            return ['error' => 'Lifetime subscriptions cannot be changed'];
        }

        if ($subscription['plan_type'] === $newPlanType) {
            return ['error' => 'You are already on this plan'];
        }

        if ((bool) $subscription['cancel_at_period_end']) {
            return ['error' => 'Cannot change plan while cancellation is pending. Please resubscribe after your current period ends.'];
        }

        if ($newPlanType === 'lifetime') {
            return ['error' => 'To upgrade to Lifetime, please cancel your current subscription and purchase Lifetime separately.'];
        }

        // Only Stripe supports mid-cycle changes
        if ($subscription['processor'] === 'paypal') {
            return ['error' => 'PayPal subscriptions cannot be changed mid-cycle. Please cancel your current subscription and resubscribe with the new plan.'];
        }

        if ($subscription['processor'] !== 'stripe' || empty($subscription['stripe_subscription_id'])) {
            return ['error' => 'Cannot change plan for this subscription type'];
        }

        $stripe = new StripeService();
        $result = $stripe->updateSubscription($subscription['stripe_subscription_id'], $newPlanType);

        if (isset($result['error'])) {
            return $result;
        }

        // Update local DB
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);
        $newPlan = $stripe->getPlanConfig($newPlanType);

        $wpdb->update(
            $table,
            [
                'plan_type' => $newPlanType,
                'amount_cents' => $newPlan['amount'] ?? null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $subscription['id']]
        );

        $planNames = [
            'monthly' => 'Monthly Supporter',
            'annual' => 'Season Pass',
        ];

        return [
            'success' => true,
            'message' => 'Plan changed to ' . ($planNames[$newPlanType] ?? $newPlanType) . ' successfully!',
        ];
    }

    /**
     * Assign supporter role to user
     */
    private function assignSupporterRole(int $userId, string $planType): void
    {
        $user = get_user_by('ID', $userId);
        if (!$user) {
            return;
        }

        $role = $planType === 'lifetime' ? 'lifetime' : 'supporter';

        // Remove existing roles that get replaced by supporter/lifetime
        $user->remove_role('subscriber');
        $user->remove_role('supporter');
        $user->remove_role('lifetime');

        // Add the new role
        $user->add_role($role);

        // Set supporter since date if not already set
        $supporterSince = get_user_meta($userId, 'bbj_supporter_since', true);
        if (!$supporterSince) {
            update_user_meta($userId, 'bbj_supporter_since', current_time('mysql'));
        }

        // Log the role assignment
        error_log("BBJ Billing: Assigned {$role} role to user {$userId}");
    }

    /**
     * Remove supporter role from user
     */
    private function removeSupporterRole(int $userId): void
    {
        $user = get_user_by('ID', $userId);
        if (!$user) {
            return;
        }

        // Don't remove if user has another active subscription
        if ($this->hasActiveSubscription($userId)) {
            return;
        }

        $user->remove_role('supporter');
        $user->remove_role('lifetime');

        // Restore subscriber role so user still has a role
        if (empty($user->roles)) {
            $user->add_role('subscriber');
        }

        // Log the role removal
        error_log("BBJ Billing: Removed supporter role from user {$userId}");
    }

    /**
     * Check and expire subscriptions past their period end
     * Called by cron job
     */
    public function checkExpiredSubscriptions(): int
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        // Find subscriptions that are active but past their period end
        // and have cancel_at_period_end set
        $expired = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE status = 'active'
               AND cancel_at_period_end = 1
               AND current_period_end < NOW()",
            ARRAY_A
        );

        $count = 0;
        foreach ($expired as $subscription) {
            $this->updateSubscriptionStatus($subscription['id'], 'expired');
            $count++;
        }

        return $count;
    }

    /**
     * Find a subscription by its ID
     */
    public function findById(int $subscriptionId): ?array
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $subscriptionId
        ), ARRAY_A) ?: null;
    }

    /**
     * Get all subscriptions with user data, optional filter, pagination
     */
    public function getAllSubscriptions(?string $statusFilter = null, int $page = 1, int $perPage = 25): array
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);
        $usersTable = $wpdb->users;

        $where = '';
        $params = [];

        if ($statusFilter) {
            $where = 'WHERE s.status = %s';
            $params[] = $statusFilter;
        }

        // Count total
        $countSql = "SELECT COUNT(*) FROM {$table} s {$where}";
        $total = $params
            ? (int) $wpdb->get_var($wpdb->prepare($countSql, ...$params))
            : (int) $wpdb->get_var($countSql);

        $pages = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        // Fetch items
        $sql = "SELECT s.*, u.display_name, u.user_email, u.user_login
                FROM {$table} s
                LEFT JOIN {$usersTable} u ON s.user_id = u.ID
                {$where}
                ORDER BY s.created_at DESC
                LIMIT %d OFFSET %d";

        $queryParams = array_merge($params, [$perPage, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($sql, ...$queryParams), ARRAY_A) ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * Get subscription statistics
     */
    public function getStats(): array
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'lifetime' THEN 1 ELSE 0 END) as lifetime,
                SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as canceled,
                SUM(CASE WHEN plan_type = 'monthly' AND status = 'active' THEN 1 ELSE 0 END) as monthly,
                SUM(CASE WHEN plan_type = 'annual' AND status = 'active' THEN 1 ELSE 0 END) as annual,
                SUM(CASE WHEN processor = 'stripe' THEN 1 ELSE 0 END) as stripe_total,
                SUM(CASE WHEN processor = 'paypal' THEN 1 ELSE 0 END) as paypal_total
             FROM {$table}",
            ARRAY_A
        );

        return $stats ?: [
            'total' => 0,
            'active' => 0,
            'lifetime' => 0,
            'canceled' => 0,
            'monthly' => 0,
            'annual' => 0,
            'stripe_total' => 0,
            'paypal_total' => 0,
        ];
    }
}
