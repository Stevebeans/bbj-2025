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
     * Roles swapped out when a member's paid tier changes. `lifetime` is
     * deliberately absent: legacy Lifetime members keep that role for life,
     * even when they gain another paid role like full_bean.
     */
    public const REPLACEABLE_ROLES = ['subscriber', 'supporter', 'full_bean'];

    /**
     * Resolve a checkout/plan key (e.g. 'full_bean_monthly') to ENUM-safe
     * DB values + role. Returns null for unknown keys.
     */
    public static function resolvePlan(string $planKey): ?array
    {
        $plan = StripeService::PLANS[$planKey] ?? null;
        if (!$plan) {
            return null;
        }

        return [
            'plan_type' => $plan['plan_type'],
            'tier' => $plan['tier'] ?? 'supporter',
            'role' => $plan['role'],
            'amount_cents' => $plan['amount'],
            'name' => $plan['name'],
        ];
    }

    /**
     * A subscription row matches a plan key only when BOTH plan_type and
     * tier match (plan_type alone is ambiguous: supporter monthly and Full
     * Bean monthly share plan_type 'monthly').
     */
    public static function isSamePlan(array $subscription, string $planKey): bool
    {
        $target = self::resolvePlan($planKey);
        if (!$target) {
            return false;
        }

        return ($subscription['plan_type'] ?? null) === $target['plan_type']
            && ($subscription['tier'] ?? 'supporter') === $target['tier'];
    }

    /**
     * Human-readable plan name from the stored (plan_type, tier) pair.
     */
    public static function displayName(string $planType, string $tier = 'supporter'): string
    {
        if ($tier === 'full_bean') {
            return $planType === 'annual' ? 'Full Bean Annual' : 'Full Bean Monthly';
        }

        $names = [
            'monthly' => 'Monthly Supporter',
            'annual' => 'All-Access Pass',
            'lifetime' => 'Lifetime Member',
        ];

        return $names[$planType] ?? $planType;
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

            // Founders window: All-Access and Lifetime include the Founding Junkie badge.
            FoundingBadge::maybeGrantForPlan(
                (int) $insertData['user_id'],
                (string) ($insertData['plan_type'] ?? '')
            );

            // Assign role to user
            $this->assignSupporterRole(
                $insertData['user_id'],
                $insertData['plan_type'],
                $insertData['tier'] ?? 'supporter'
            );

            // Send the premium welcome/receipt email (idempotent per subscription row).
            $row = array_merge($insertData, ['id' => $subscriptionId]);
            \BigBrotherJunkies\Data\Email\WelcomeEmailService::sendPremium((int) $insertData['user_id'], $row);

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
                    $this->assignSupporterRole(
                        $subscription['user_id'],
                        $subscription['plan_type'],
                        $subscription['tier'] ?? 'supporter'
                    );
                } elseif (in_array($status, ['expired', 'canceled'])) {
                    // Only remove role if subscription actually ended (not cancel_at_period_end)
                    if ($status === 'expired' || !($additionalData['cancel_at_period_end'] ?? false)) {
                        // ...and never strip a role a NEWER subscription still
                        // pays for (member re-subscribed/upgraded before an
                        // old pending-cancel row lapsed).
                        if (!$this->getActiveSubscription((int) $subscription['user_id'])) {
                            $this->removeSupporterRole($subscription['user_id']);
                        }
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
     * Blocks new checkouts only while a subscription is active AND still
     * renewing. A cancel-at-period-end member is on the way out — let them
     * start a new (e.g. Full Bean) subscription without waiting for the
     * lapse; the role-removal guard keeps the old row's expiry from
     * stripping the new subscription's role.
     */
    public function hasBlockingSubscription(int $userId): bool
    {
        return $this->hasBlockingSubscriptionFor($userId, '');
    }

    /**
     * The same guard, aware of what the member is trying to buy.
     *
     * Founders decision (2026-09-12): a SUPPORTER MONTHLY member may buy
     * Lifetime mid-cycle. Completing that purchase cancels the monthly
     * subscription (see supersedeWithLifetime()), so the running plan is not a
     * blocker for that one target.
     *
     * The tier check is load-bearing, not decoration: `full_bean_monthly`
     * resolves to the same plan_type 'monthly', and Lifetime is a
     * supporter-tier product. Letting a Full Bean member through would take
     * $99, cancel their $14.99/mo plan and strip the full_bean role - a paid
     * downgrade. Full Bean members of either interval stay blocked, as do
     * annual members (proration is a separate decision) and anyone who
     * already holds a lifetime row. Every other target is unchanged.
     */
    public function hasBlockingSubscriptionFor(int $userId, string $targetPlan = ''): bool
    {
        $sub = $this->getActiveSubscription($userId);

        if ($sub === null || !empty($sub['cancel_at_period_end'])) {
            return false;
        }

        if ($targetPlan === 'lifetime'
            && ($sub['status'] ?? '') === 'active'
            && ($sub['plan_type'] ?? '') === 'monthly'
            && ($sub['tier'] ?? 'supporter') === 'supporter') {
            return false;
        }

        return true;
    }

    /** What to tell the member when hasBlockingSubscriptionFor() refuses. */
    public function blockingMessageFor(int $userId, string $targetPlan = ''): string
    {
        if ($targetPlan === 'lifetime') {
            $sub = $this->getActiveSubscription($userId);

            if (($sub['tier'] ?? 'supporter') === 'full_bean') {
                return "Lifetime doesn't include Full Bean. Message Steve if you want to switch.";
            }

            if (($sub['plan_type'] ?? '') === 'annual') {
                return "You're on an annual plan. Lifetime upgrades for annual members are coming.";
            }
        }

        return 'You already have an active subscription';
    }

    /**
     * The member's live recurring row - never a lifetime row, optionally
     * ignoring one id (the lifetime row that was just created) and optionally
     * restricted to one tier.
     *
     * @param string $tier '' for any tier, or 'supporter' / 'full_bean'.
     */
    public function getActiveRecurringSubscription(int $userId, int $excludeId = 0, string $tier = ''): ?array
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        $sql = "SELECT * FROM {$table}
             WHERE user_id = %d
               AND status = 'active'
               AND plan_type <> 'lifetime'
               AND id <> %d";
        $args = [$userId, $excludeId];

        if ($tier !== '') {
            $sql .= " AND tier = %s";
            $args[] = $tier;
        }

        $sql .= " ORDER BY created_at DESC LIMIT 1";

        $row = $wpdb->get_row($wpdb->prepare($sql, $args), ARRAY_A);

        return $row ?: null;
    }

    /**
     * A completed Lifetime purchase supersedes the member's running recurring
     * plan: stop the billing at the processor and close the row, so nobody
     * pays monthly for something they now own outright.
     *
     * Called AFTER the lifetime row exists, deliberately: the role-removal
     * guard inside updateSubscriptionStatus() then sees the live lifetime row
     * and leaves the member's roles alone.
     *
     * Never fatal. The member has already paid; a processor failure closes the
     * row locally and shouts in the log so the charge can be killed by hand.
     *
     * @return bool true when a recurring row was found and closed.
     */
    public function supersedeWithLifetime(int $userId, int $newLifetimeRowId = 0): bool
    {
        // Supporter tier only, belt to hasBlockingSubscriptionFor()'s braces:
        // even if a Full Bean member reached a Lifetime completion somehow,
        // their $14.99/mo plan is never the thing this cancels.
        $old = $this->getActiveRecurringSubscription($userId, $newLifetimeRowId, 'supporter');

        if (!$old) {
            return false;
        }

        $processor = (string) $old['processor'];
        $externalId = $processor === 'paypal'
            ? (string) ($old['paypal_subscription_id'] ?? '')
            : (string) ($old['stripe_subscription_id'] ?? '');

        // Offline escape hatch for scripts/founders-money-verify.php ONLY, so
        // it can exercise this branch without calling Stripe or PayPal. It
        // lives here rather than in cancelAtProcessor() on purpose: the shared
        // helper is also what member-facing cancellation uses, and a filter
        // that silently skips the processor there would tell a member they had
        // cancelled while the charges kept coming.
        $result = apply_filters('bbjd_billing_skip_processor_cancel_for_verify', false, $old)
            ? ['success' => true, 'message' => 'Processor cancel skipped (verify only)']
            : $this->cancelAtProcessor($old, true);

        if (isset($result['error'])) {
            error_log(
                'BBJ Billing: lifetime upgrade - FAILED to cancel ' . $processor . ' sub '
                . ($externalId === '' ? '(none stored)' : $externalId) . ' for user ' . $userId
                . ': ' . $result['error'] . ' - CANCEL IT MANUALLY'
            );
        } else {
            error_log(
                'BBJ Billing: lifetime upgrade - cancelled monthly ' . $processor . ' sub '
                . $externalId . ' for user ' . $userId
            );
        }

        $this->updateSubscriptionStatus((int) $old['id'], 'canceled', [
            'canceled_at' => current_time('mysql'),
        ]);

        return true;
    }

    /**
     * Stop future billing for one row at its processor. Shared by
     * cancelSubscription() and supersedeWithLifetime() so the processor
     * plumbing lives in exactly one place.
     *
     * $immediate only means anything to Stripe. PayPal's cancel always stops
     * future billing at once; the caller decides what the local row does.
     *
     * No bypass filter lives here by design - member-facing cancellation runs
     * through this method and must always reach the processor.
     *
     * @return array ['success' => true, ...] or ['error' => string]
     */
    private function cancelAtProcessor(array $subscription, bool $immediate): array
    {
        $processor = $subscription['processor'] ?? '';

        if ($processor === 'stripe' && !empty($subscription['stripe_subscription_id'])) {
            $stripe = new StripeService();
            return $immediate
                ? $stripe->cancelSubscriptionImmediately($subscription['stripe_subscription_id'])
                : $stripe->cancelSubscription($subscription['stripe_subscription_id']);
        }

        if ($processor === 'paypal' && !empty($subscription['paypal_subscription_id'])) {
            $paypal = new PayPalService();
            return $paypal->cancelSubscription($subscription['paypal_subscription_id']);
        }

        return ['error' => 'Unknown subscription processor'];
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

        $isStripe = $subscription['processor'] === 'stripe' && $subscription['stripe_subscription_id'];
        $isPayPal = $subscription['processor'] === 'paypal' && $subscription['paypal_subscription_id'];

        if (!$isStripe && !$isPayPal) {
            return ['error' => 'Unknown subscription processor'];
        }

        // Only Stripe honours the immediate/period-end distinction; PayPal's
        // cancel always stops future billing at once.
        $result = $this->cancelAtProcessor($subscription, $immediate && $isStripe);

        if (isset($result['error'])) {
            return $result;
        }

        if ($isStripe && $immediate) {
            $this->updateSubscriptionStatus($subscription['id'], 'canceled', [
                'canceled_at' => current_time('mysql'),
            ]);

            return [
                'success' => true,
                'message' => 'Subscription has been canceled immediately',
            ];
        }

        // PayPal stops future billing immediately, but the member already
        // paid through current_period_end — keep access until then, like
        // Stripe and the FAQ promise. BillingExpiryCron flips the row to
        // 'expired' (removing the role) once the period lapses.
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

    /**
     * Change a user's subscription plan (upgrade/downgrade, incl. across
     * tiers). Stripe-only; immediate with proration. $newPlanKey is a
     * checkout key ('monthly'|'annual'|'full_bean_monthly'|'full_bean_annual').
     */
    public function changePlan(int $userId, string $newPlanKey): array
    {
        $subscription = $this->getActiveSubscription($userId);

        if (!$subscription) {
            return ['error' => 'No active subscription found'];
        }

        if ($subscription['status'] === 'lifetime') {
            return ['error' => 'Lifetime subscriptions cannot be changed'];
        }

        $target = self::resolvePlan($newPlanKey);

        if (!$target) {
            return ['error' => 'Invalid plan type'];
        }

        if ($target['plan_type'] === 'lifetime') {
            return ['error' => 'To upgrade to Lifetime, please cancel your current subscription and purchase Lifetime separately.'];
        }

        if (self::isSamePlan($subscription, $newPlanKey)) {
            return ['error' => 'You are already on this plan'];
        }

        if ((bool) $subscription['cancel_at_period_end']) {
            return ['error' => 'Cannot change plan while cancellation is pending. Please resubscribe after your current period ends.'];
        }

        // Only Stripe supports mid-cycle changes
        if ($subscription['processor'] === 'paypal') {
            return ['error' => "PayPal subscriptions can't switch plans here. Cancel your current plan, then resubscribe with the new plan after your period ends."];
        }

        if ($subscription['processor'] !== 'stripe' || empty($subscription['stripe_subscription_id'])) {
            return ['error' => 'Cannot change plan for this subscription type'];
        }

        $stripe = new StripeService();
        $result = $stripe->updateSubscription($subscription['stripe_subscription_id'], $newPlanKey);

        if (isset($result['error'])) {
            return $result;
        }

        // Update local DB with ENUM-safe plan_type + the new tier
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        $wpdb->update(
            $table,
            [
                'plan_type' => $target['plan_type'],
                'tier' => $target['tier'],
                'amount_cents' => $target['amount_cents'],
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $subscription['id']]
        );

        // Reassign the role — a tier change (supporter <-> full_bean) must
        // grant/revoke the corresponding role immediately.
        $this->assignSupporterRole($userId, $target['plan_type'], $target['tier']);

        // A switch INTO All-Access during the window buys the same thing a
        // fresh All-Access checkout buys, badge included. Same rule, one place.
        FoundingBadge::maybeGrantForPlan($userId, $target['plan_type']);

        return [
            'success' => true,
            'message' => 'Plan changed to ' . self::displayName($target['plan_type'], $target['tier']) . ' successfully!',
        ];
    }

    /**
     * Assign supporter role to user.
     *
     * @param string $tier 'supporter' (default) or 'full_bean' — Full Bean is the
     *                      $14.99 tier (Sonnet, unlimited, memory) and is ad-free
     *                      via the `full_bean` role in global hidden roles.
     */
    private function assignSupporterRole(int $userId, string $planType, string $tier = 'supporter'): void
    {
        $user = get_user_by('ID', $userId);
        if (!$user) {
            return;
        }

        if ($tier === 'full_bean') {
            $role = 'full_bean';
        } else {
            $role = $planType === 'lifetime' ? 'lifetime' : 'supporter';
        }

        // Remove existing tier roles that get replaced by the new one.
        // REPLACEABLE_ROLES excludes 'lifetime' on purpose.
        foreach (self::REPLACEABLE_ROLES as $replaceable) {
            $user->remove_role($replaceable);
        }

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
        $user->remove_role('full_bean');

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
                SUM(CASE WHEN tier = 'full_bean' AND status IN ('active', 'lifetime') THEN 1 ELSE 0 END) as full_bean,
                SUM(CASE WHEN tier = 'supporter' AND status IN ('active', 'lifetime') THEN 1 ELSE 0 END) as supporter_tier,
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
            'full_bean' => 0,
            'supporter_tier' => 0,
            'stripe_total' => 0,
            'paypal_total' => 0,
        ];
    }

    /**
     * Revenue aggregates for the admin Premium tab, from the amounts each
     * subscription actually bills (amount_cents). MRR = monthly billers at
     * face value + annual billers spread across 12 months. Lifetime is
     * one-time money, reported separately and excluded from MRR.
     */
    public function getRevenueStats(): array
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        $row = $wpdb->get_row(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'active' AND plan_type = 'monthly' THEN amount_cents END), 0) as monthly_billed_cents,
                COALESCE(SUM(CASE WHEN status = 'active' AND plan_type = 'annual' THEN amount_cents END), 0) as annual_billed_cents,
                COALESCE(SUM(CASE WHEN status = 'lifetime' THEN amount_cents END), 0) as lifetime_collected_cents,
                SUM(CASE WHEN status = 'active' AND amount_cents IS NULL THEN 1 ELSE 0 END) as unpriced_active
             FROM {$table}",
            ARRAY_A
        ) ?: [];

        $monthly = (int) ($row['monthly_billed_cents'] ?? 0);
        $annual  = (int) ($row['annual_billed_cents'] ?? 0);

        return [
            'monthly_billed_cents'     => $monthly,
            'annual_billed_cents'      => $annual,
            'mrr_cents'                => $monthly + (int) round($annual / 12),
            'lifetime_collected_cents' => (int) ($row['lifetime_collected_cents'] ?? 0),
            'unpriced_active'          => (int) ($row['unpriced_active'] ?? 0),
        ];
    }

    /**
     * Rolling activity counts for the admin Premium tab: new subscription
     * rows in the last 7/30 days and cancellations in the last 30.
     */
    public function getTrendStats(): array
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        $stats = $wpdb->get_row(
            "SELECT
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as joins_7d,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as joins_30d,
                SUM(CASE WHEN canceled_at IS NOT NULL AND canceled_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as cancels_30d
             FROM {$table}",
            ARRAY_A
        );

        return $stats ?: ['joins_7d' => 0, 'joins_30d' => 0, 'cancels_30d' => 0];
    }
}
