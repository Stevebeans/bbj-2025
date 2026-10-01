<?php

namespace BigBrotherJunkies\Data\Billing;

/**
 * Handles incoming webhooks from Stripe and PayPal
 */
class WebhookHandler
{
    private SubscriptionManager $subscriptionManager;

    public function __construct()
    {
        $this->subscriptionManager = SubscriptionManager::getInstance();
    }

    /**
     * Handle Stripe webhook
     */
    public function handleStripeWebhook(string $payload, string $signature): array
    {
        $stripe = new StripeService();

        // Verify signature
        if (!$stripe->verifyWebhookSignature($payload, $signature)) {
            error_log('BBJ Billing: Invalid Stripe webhook signature');
            return ['error' => 'Invalid signature', 'status' => 400];
        }

        $event = json_decode($payload, true);

        if (!$event || !isset($event['type'])) {
            return ['error' => 'Invalid payload', 'status' => 400];
        }

        error_log("BBJ Billing: Processing Stripe webhook: {$event['type']}");

        switch ($event['type']) {
            case 'checkout.session.completed':
                return $this->handleStripeCheckoutCompleted($event['data']['object']);

            case 'customer.subscription.updated':
                return $this->handleStripeSubscriptionUpdated($event['data']['object']);

            case 'customer.subscription.deleted':
                return $this->handleStripeSubscriptionDeleted($event['data']['object']);

            case 'invoice.paid':
                return $this->handleStripeInvoicePaid($event['data']['object']);

            case 'invoice.payment_failed':
                return $this->handleStripeInvoiceFailed($event['data']['object']);

            case 'charge.refunded':
                return $this->handleStripeChargeRefunded($event['data']['object']);

            default:
                // Acknowledge but don't process other events
                return ['success' => true, 'message' => 'Event type not handled'];
        }
    }

    /**
     * Handle Stripe checkout.session.completed
     */
    private function handleStripeCheckoutCompleted(array $session): array
    {
        $userId = (int) ($session['metadata']['wp_user_id'] ?? 0);
        $planType = $session['metadata']['plan_type'] ?? null;

        if (!$userId || !$planType) {
            error_log('BBJ Billing: Missing metadata in checkout session');
            return ['error' => 'Missing metadata', 'status' => 400];
        }

        // Founding badge: grant and stop. Never a subscription row, never a role.
        if ($planType === 'founding_badge') {
            // checkout.session.completed fires before the money settles for
            // delayed-notification methods. Only 'paid' is an entitlement.
            $paymentStatus = (string) ($session['payment_status'] ?? '');
            if ($paymentStatus !== 'paid') {
                error_log(
                    'BBJ Billing: founding badge session ' . ($session['id'] ?? '?')
                    . ' completed with payment_status=' . ($paymentStatus === '' ? '(missing)' : $paymentStatus)
                    . ' - not granting'
                );
                return ['success' => true, 'message' => 'Founding badge payment not settled'];
            }

            $alreadyHad = FoundingBadge::has($userId);
            $granted = FoundingBadge::grant($userId, 'purchase');

            if ($alreadyHad) {
                error_log('BBJ Billing: founding badge duplicate payment - user ' . $userId . ' already held the badge (session ' . ($session['id'] ?? '?') . ')');
                return ['success' => true, 'message' => 'Founding badge already held - duplicate payment logged'];
            }

            if (!$granted) {
                error_log('BBJ Billing: founding badge grant FAILED for user ' . $userId . ' (session ' . ($session['id'] ?? '?') . ')');
                return ['success' => true, 'message' => 'Founding badge grant failed - logged'];
            }

            return ['success' => true, 'message' => 'Founding badge granted'];
        }

        $stripe = new StripeService();
        $planConfig = $stripe->getPlanConfig($planType);

        if (!$planConfig) {
            return ['error' => 'Invalid plan type', 'status' => 400];
        }

        $isLifetimePurchase = ($planConfig['plan_type'] ?? '') === 'lifetime';
        $supersedeRecurring = false;

        // Check if user already has an active subscription
        if ($this->subscriptionManager->hasActiveSubscription($userId)) {
            // One exception: a completed Lifetime purchase is allowed to
            // supersede a running monthly plan, which is what the checkout
            // guard let through. Same predicate, so the two agree.
            if (!$isLifetimePurchase
                || $this->subscriptionManager->hasBlockingSubscriptionFor($userId, 'lifetime')) {
                error_log("BBJ Billing: User {$userId} already has active subscription");
                return ['success' => true, 'message' => 'User already has active subscription'];
            }

            $supersedeRecurring = true;
        }

        $subscriptionData = [
            'user_id' => $userId,
            'stripe_customer_id' => $session['customer'],
            'plan_type' => $planConfig['plan_type'] ?? $planType, // ENUM-safe
            'tier' => $planConfig['tier'] ?? 'supporter',
            'processor' => 'stripe',
            'amount_cents' => $planConfig['amount'],
            'currency' => 'USD',
        ];

        if ($session['mode'] === 'subscription') {
            // Recurring subscription
            $subscriptionData['stripe_subscription_id'] = $session['subscription'];
            $subscriptionData['status'] = 'active';

            // Get subscription details for period dates
            $subDetails = $stripe->getSubscription($session['subscription']);
            if ($subDetails && !isset($subDetails['error'])) {
                $subscriptionData['current_period_start'] = date('Y-m-d H:i:s', $subDetails['current_period_start']);
                $subscriptionData['current_period_end'] = date('Y-m-d H:i:s', $subDetails['current_period_end']);
            }
        } else {
            // One-time payment (lifetime)
            $subscriptionData['status'] = 'lifetime';

            // The PaymentIntent is the only id a later charge.refunded event
            // carries that points back at this exact purchase. Store it so a
            // refund can free the founders spot it consumed.
            if (!empty($session['payment_intent'])) {
                $subscriptionData['stripe_payment_intent_id'] = (string) $session['payment_intent'];
            }

            if (FoundersWindow::lifetimeSpotsLeft() <= 0) {
                error_log('BBJ Billing: lifetime cap overshoot - honoring paid checkout for user ' . $userId);
            }
        }

        $subscriptionId = $this->subscriptionManager->createSubscription($subscriptionData);

        if ($subscriptionId) {
            if ($supersedeRecurring) {
                $this->subscriptionManager->supersedeWithLifetime($userId, (int) $subscriptionId);
            }

            error_log("BBJ Billing: Created subscription {$subscriptionId} for user {$userId}");
            return ['success' => true, 'subscription_id' => $subscriptionId];
        }

        return ['error' => 'Failed to create subscription', 'status' => 500];
    }

    /**
     * Handle Stripe customer.subscription.updated
     */
    private function handleStripeSubscriptionUpdated(array $subscription): array
    {
        $stripeSubId = $subscription['id'];
        $existingSub = $this->subscriptionManager->findByStripeSubscriptionId($stripeSubId);

        if (!$existingSub) {
            error_log("BBJ Billing: Subscription {$stripeSubId} not found in database");
            return ['success' => true, 'message' => 'Subscription not found'];
        }

        $updateData = [
            'current_period_start' => date('Y-m-d H:i:s', $subscription['current_period_start']),
            'current_period_end' => date('Y-m-d H:i:s', $subscription['current_period_end']),
            'cancel_at_period_end' => $subscription['cancel_at_period_end'] ? 1 : 0,
        ];

        if ($subscription['cancel_at_period_end'] && !$existingSub['canceled_at']) {
            $updateData['canceled_at'] = current_time('mysql');
        }

        // Map Stripe status to our status
        $statusMap = [
            'active' => 'active',
            'past_due' => 'past_due',
            'canceled' => 'canceled',
            'unpaid' => 'past_due',
        ];

        if (isset($statusMap[$subscription['status']])) {
            $updateData['status'] = $statusMap[$subscription['status']];
        }

        $this->subscriptionManager->updateByStripeSubscriptionId($stripeSubId, $updateData);

        error_log("BBJ Billing: Updated subscription {$stripeSubId}");
        return ['success' => true];
    }

    /**
     * Handle Stripe customer.subscription.deleted
     */
    private function handleStripeSubscriptionDeleted(array $subscription): array
    {
        $stripeSubId = $subscription['id'];
        $existingSub = $this->subscriptionManager->findByStripeSubscriptionId($stripeSubId);

        if (!$existingSub) {
            return ['success' => true, 'message' => 'Subscription not found'];
        }

        $this->subscriptionManager->updateSubscriptionStatus($existingSub['id'], 'expired', [
            'canceled_at' => current_time('mysql'),
        ]);

        error_log("BBJ Billing: Subscription {$stripeSubId} deleted/expired");
        return ['success' => true];
    }

    /**
     * Handle Stripe invoice.paid
     */
    private function handleStripeInvoicePaid(array $invoice): array
    {
        $stripeSubId = $invoice['subscription'] ?? null;

        if (!$stripeSubId) {
            return ['success' => true, 'message' => 'Not a subscription invoice'];
        }

        $existingSub = $this->subscriptionManager->findByStripeSubscriptionId($stripeSubId);

        if (!$existingSub) {
            return ['success' => true, 'message' => 'Subscription not found'];
        }

        // Update period dates from the invoice
        $updateData = [
            'status' => 'active',
        ];

        if (isset($invoice['lines']['data'][0]['period'])) {
            $period = $invoice['lines']['data'][0]['period'];
            $updateData['current_period_start'] = date('Y-m-d H:i:s', $period['start']);
            $updateData['current_period_end'] = date('Y-m-d H:i:s', $period['end']);
        }

        $this->subscriptionManager->updateByStripeSubscriptionId($stripeSubId, $updateData);

        error_log("BBJ Billing: Invoice paid for subscription {$stripeSubId}");
        return ['success' => true];
    }

    /**
     * Handle Stripe invoice.payment_failed
     */
    private function handleStripeInvoiceFailed(array $invoice): array
    {
        $stripeSubId = $invoice['subscription'] ?? null;

        if (!$stripeSubId) {
            return ['success' => true, 'message' => 'Not a subscription invoice'];
        }

        $existingSub = $this->subscriptionManager->findByStripeSubscriptionId($stripeSubId);

        if (!$existingSub) {
            return ['success' => true, 'message' => 'Subscription not found'];
        }

        $this->subscriptionManager->updateByStripeSubscriptionId($stripeSubId, [
            'status' => 'past_due',
        ]);

        // TODO: Send email notification about failed payment

        error_log("BBJ Billing: Payment failed for subscription {$stripeSubId}");
        return ['success' => true];
    }

    /**
     * Handle Stripe charge.refunded.
     *
     * Only Lifetime needs this. It is the one purchase that consumes a capped,
     * publicly-counted founders spot, and no other event in this switch can
     * give the spot back - a refunded row would keep `status = 'lifetime'`
     * forever and the "N of 50 left" counter would stay permanently short.
     * Partial refunds are logged and left alone (the member keeps what they
     * mostly paid for). The founding badge is deliberately NOT revoked; it is
     * cosmetic and that call is Steve's. PayPal refunds stay manual.
     */
    private function handleStripeChargeRefunded(array $charge): array
    {
        $chargeId = (string) ($charge['id'] ?? '?');
        $amount = (int) ($charge['amount'] ?? 0);
        $refundedAmount = (int) ($charge['amount_refunded'] ?? 0);
        $isFullRefund = !empty($charge['refunded']) || ($amount > 0 && $refundedAmount >= $amount);

        if (!$isFullRefund) {
            error_log(
                'BBJ Billing: partial refund on charge ' . $chargeId . ' ('
                . $refundedAmount . ' of ' . $amount . ' cents) - entitlement unchanged'
            );
            return ['success' => true, 'message' => 'Partial refund - entitlement unchanged'];
        }

        $row = $this->findLifetimeRowForCharge($charge);

        if (!$row) {
            return ['success' => true, 'message' => 'No lifetime subscription matched this refund'];
        }

        if (!$this->freeRefundedLifetimeRow($row)) {
            error_log('BBJ Billing: lifetime refund - FAILED to update subscription row ' . (int) $row['id']);
            return ['error' => 'Failed to release lifetime spot', 'status' => 500];
        }

        return ['success' => true, 'message' => 'Lifetime refund processed'];
    }

    /**
     * Find the live lifetime row a refunded charge belongs to.
     *
     * The PaymentIntent is the specific handle and is stored on rows created
     * from 2026-09 onward. Rows written before that column existed have only
     * the customer id, so those fall back to a customer match - narrowed to a
     * charge whose amount is the lifetime price, so refunding some other
     * charge on the same customer cannot revoke a lifetime membership.
     */
    private function findLifetimeRowForCharge(array $charge): ?array
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        $paymentIntent = is_string($charge['payment_intent'] ?? null) ? $charge['payment_intent'] : '';
        if ($paymentIntent !== '') {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table}
                  WHERE stripe_payment_intent_id = %s
                    AND plan_type = 'lifetime'
                    AND status = 'lifetime'
                  ORDER BY id DESC LIMIT 1",
                $paymentIntent
            ), ARRAY_A);

            if ($row) {
                return $row;
            }
        }

        $customer = is_string($charge['customer'] ?? null) ? $charge['customer'] : '';
        $lifetimePrice = (int) (StripeService::PLANS['lifetime']['amount'] ?? 0);
        $amount = (int) ($charge['amount'] ?? 0);

        if ($customer !== '' && $lifetimePrice > 0 && $amount === $lifetimePrice) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table}
                  WHERE stripe_customer_id = %s
                    AND processor = 'stripe'
                    AND plan_type = 'lifetime'
                    AND status = 'lifetime'
                  ORDER BY id DESC LIMIT 1",
                $customer
            ), ARRAY_A);

            if ($row) {
                return $row;
            }
        }

        error_log(
            'BBJ Billing: charge.refunded ' . ((string) ($charge['id'] ?? '?'))
            . ' matched no live lifetime row (payment_intent=' . ($paymentIntent === '' ? '(none)' : $paymentIntent)
            . ', customer=' . ($customer === '' ? '(none)' : $customer) . ')'
        );

        return null;
    }

    /**
     * Take a refunded lifetime row out of the founders count and strip the role.
     *
     * `refunded` is not a value in the bbj_subscriptions.status ENUM
     * ('active','canceled','past_due','expired','lifetime'), so the row lands
     * on 'canceled': it is no longer counted by
     * FoundersWindow::lifetimeSpotsSold(), which only counts status='lifetime'.
     * updateSubscriptionStatus() also runs the existing role-removal path, so
     * the `lifetime` role goes with it unless another paid row still needs it.
     *
     * Public so the local verify script can drive the transition without
     * forging a Stripe webhook payload.
     */
    public function freeRefundedLifetimeRow(array $row): bool
    {
        $subscriptionId = (int) ($row['id'] ?? 0);
        $userId = (int) ($row['user_id'] ?? 0);

        if ($subscriptionId <= 0) {
            return false;
        }

        $updated = $this->subscriptionManager->updateSubscriptionStatus($subscriptionId, 'canceled', [
            'canceled_at' => current_time('mysql'),
        ]);

        if ($updated) {
            error_log('BBJ Billing: lifetime refund - freed a founders spot for user ' . $userId);
        }

        return $updated;
    }

    /**
     * Handle PayPal webhook
     */
    public function handlePayPalWebhook(string $payload, array $headers): array
    {
        $paypal = new PayPalService();

        // Verify signature
        if (!$paypal->verifyWebhookSignature($payload, $headers)) {
            error_log('BBJ Billing: Invalid PayPal webhook signature');
            return ['error' => 'Invalid signature', 'status' => 400];
        }

        $event = json_decode($payload, true);

        if (!$event || !isset($event['event_type'])) {
            return ['error' => 'Invalid payload', 'status' => 400];
        }

        error_log("BBJ Billing: Processing PayPal webhook: {$event['event_type']}");

        switch ($event['event_type']) {
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                return $this->handlePayPalSubscriptionActivated($event['resource']);

            case 'BILLING.SUBSCRIPTION.CANCELLED':
                return $this->handlePayPalSubscriptionCancelled($event['resource']);

            case 'BILLING.SUBSCRIPTION.SUSPENDED':
                return $this->handlePayPalSubscriptionSuspended($event['resource']);

            case 'PAYMENT.SALE.COMPLETED':
                return $this->handlePayPalPaymentCompleted($event['resource']);

            case 'CHECKOUT.ORDER.APPROVED':
                return $this->handlePayPalOrderApproved($event['resource']);

            default:
                return ['success' => true, 'message' => 'Event type not handled'];
        }
    }

    /**
     * Handle PayPal BILLING.SUBSCRIPTION.ACTIVATED
     */
    private function handlePayPalSubscriptionActivated(array $subscription): array
    {
        $customId = $subscription['custom_id'] ?? null;
        $metadata = $customId ? json_decode($customId, true) : [];

        $userId = (int) ($metadata['wp_user_id'] ?? 0);
        $planType = $metadata['plan_type'] ?? null;

        if (!$userId || !$planType) {
            error_log('BBJ Billing: Missing metadata in PayPal subscription');
            return ['error' => 'Missing metadata', 'status' => 400];
        }

        // Check if already exists
        $existing = $this->subscriptionManager->findByPayPalSubscriptionId($subscription['id']);
        if ($existing) {
            $this->subscriptionManager->updateByPayPalSubscriptionId($subscription['id'], [
                'status' => 'active',
            ]);
            return ['success' => true, 'message' => 'Subscription updated'];
        }

        // Check if user already has active subscription
        if ($this->subscriptionManager->hasActiveSubscription($userId)) {
            return ['success' => true, 'message' => 'User already has active subscription'];
        }

        $paypal = new PayPalService();
        $planConfig = $paypal->getPlanConfig($planType);

        $subscriptionData = [
            'user_id' => $userId,
            'paypal_subscription_id' => $subscription['id'],
            'paypal_payer_id' => $subscription['subscriber']['payer_id'] ?? null,
            'plan_type' => $planConfig['plan_type'] ?? $planType, // ENUM-safe
            'tier' => $planConfig['tier'] ?? 'supporter',
            'processor' => 'paypal',
            'status' => 'active',
            'amount_cents' => (int) (floatval($planConfig['amount']) * 100),
            'currency' => 'USD',
        ];

        $periodEnd = SubscriptionManager::paypalPeriodEnd($subscription, (string) $subscriptionData['plan_type']);
        if ($periodEnd) {
            $subscriptionData['current_period_end'] = $periodEnd;
        }

        $subscriptionId = $this->subscriptionManager->createSubscription($subscriptionData);

        error_log("BBJ Billing: Created PayPal subscription {$subscriptionId} for user {$userId}");
        return ['success' => true, 'subscription_id' => $subscriptionId];
    }

    /**
     * Handle PayPal BILLING.SUBSCRIPTION.CANCELLED
     */
    private function handlePayPalSubscriptionCancelled(array $subscription): array
    {
        $existingSub = $this->subscriptionManager->findByPayPalSubscriptionId($subscription['id']);

        if (!$existingSub) {
            return ['success' => true, 'message' => 'Subscription not found'];
        }

        // Fires for our own period-end cancels (moments after the API call)
        // AND when a member cancels from PayPal's own UI. Either way they've
        // paid through the current period — keep access until it lapses;
        // BillingExpiryCron handles the expiry. Trust PayPal's dates over the
        // stored row: renewals didn't advance current_period_end before
        // 2026-09-18, and a missed renewal webhook would leave it stale again.
        $periodEnd = $this->latestPayPalPeriodEnd($existingSub, $subscription);
        if ($periodEnd && strtotime($periodEnd) > time()) {
            $this->subscriptionManager->updateSubscriptionStatus($existingSub['id'], 'active', [
                'cancel_at_period_end' => 1,
                'current_period_end'   => $periodEnd,
                'canceled_at' => !empty($existingSub['canceled_at']) ? $existingSub['canceled_at'] : current_time('mysql'),
            ]);
            error_log("BBJ Billing: PayPal subscription {$subscription['id']} cancelled - access retained until {$periodEnd}");
            return ['success' => true];
        }

        $this->subscriptionManager->updateSubscriptionStatus($existingSub['id'], 'canceled', [
            'canceled_at' => current_time('mysql'),
        ]);

        error_log("BBJ Billing: PayPal subscription {$subscription['id']} cancelled");
        return ['success' => true];
    }

    /**
     * Handle PayPal BILLING.SUBSCRIPTION.SUSPENDED
     */
    private function handlePayPalSubscriptionSuspended(array $subscription): array
    {
        $existingSub = $this->subscriptionManager->findByPayPalSubscriptionId($subscription['id']);

        if (!$existingSub) {
            return ['success' => true, 'message' => 'Subscription not found'];
        }

        $this->subscriptionManager->updateByPayPalSubscriptionId($subscription['id'], [
            'status' => 'past_due',
        ]);

        error_log("BBJ Billing: PayPal subscription {$subscription['id']} suspended");
        return ['success' => true];
    }

    /**
     * Handle PayPal PAYMENT.SALE.COMPLETED. Subscription renewals carry
     * billing_agreement_id (the I-... subscription id): advance the paid
     * period from PayPal's own dates so a later cancel keeps access through
     * what the member actually paid for. One-time sales have no agreement id
     * and are just acknowledged.
     */
    private function handlePayPalPaymentCompleted(array $payment): array
    {
        error_log("BBJ Billing: PayPal payment completed: " . ($payment['id'] ?? 'unknown'));

        $subId = $payment['billing_agreement_id'] ?? null;
        if (!$subId) {
            return ['success' => true];
        }

        $existingSub = $this->subscriptionManager->findByPayPalSubscriptionId($subId);
        if (!$existingSub || !in_array($existingSub['status'], ['active', 'past_due'], true)) {
            return ['success' => true, 'message' => 'Subscription not found or not renewable'];
        }

        $fetched = (new PayPalService())->getSubscription($subId);
        if (!is_array($fetched) || !empty($fetched['error'])) {
            // Non-2xx makes PayPal retry the event, so a transient API blip
            // doesn't leave the period stale for a whole cycle.
            error_log("BBJ Billing: could not fetch PayPal subscription {$subId} after payment");
            return ['error' => 'PayPal lookup failed', 'status' => 503];
        }

        $periodEnd = SubscriptionManager::paypalPeriodEnd($fetched, (string) $existingSub['plan_type']);
        if (!$periodEnd) {
            return ['success' => true, 'message' => 'No period end from PayPal'];
        }

        $update = ['status' => 'active', 'current_period_end' => $periodEnd];
        $lastPaid = $fetched['billing_info']['last_payment']['time'] ?? null;
        if ($lastPaid && ($ts = strtotime($lastPaid)) !== false) {
            $update['current_period_start'] = gmdate('Y-m-d H:i:s', $ts);
        }
        $this->subscriptionManager->updateByPayPalSubscriptionId($subId, $update);

        $was = $existingSub['current_period_end'] ?: 'NULL';
        error_log("BBJ Billing: PayPal subscription {$subId} renewed - period end {$was} -> {$periodEnd}");
        return ['success' => true];
    }

    /**
     * Best available paid-through date for a PayPal row: PayPal's own dates
     * from the webhook resource, else a live lookup, else the stored value.
     * Never earlier than what's already stored.
     */
    private function latestPayPalPeriodEnd(array $row, array $paypalSub): ?string
    {
        $planType = (string) ($row['plan_type'] ?? '');
        $fresh = SubscriptionManager::paypalPeriodEnd($paypalSub, $planType);

        if ($fresh === null && !empty($row['paypal_subscription_id'])) {
            $fetched = (new PayPalService())->getSubscription($row['paypal_subscription_id']);
            if (is_array($fetched) && empty($fetched['error'])) {
                $fresh = SubscriptionManager::paypalPeriodEnd($fetched, $planType);
            }
        }

        $stored = $row['current_period_end'] ?: null;
        if ($fresh === null || $stored === null) {
            return $fresh ?? $stored;
        }
        // Same 'Y-m-d H:i:s' UTC format on both sides, so string max is date max.
        return max($fresh, $stored);
    }

    /**
     * Handle PayPal CHECKOUT.ORDER.APPROVED (for lifetime one-time purchase)
     */
    private function handlePayPalOrderApproved(array $order): array
    {
        // The order needs to be captured separately via the API
        // This webhook just notifies us the user approved
        error_log("BBJ Billing: PayPal order approved: " . ($order['id'] ?? 'unknown'));
        return ['success' => true, 'message' => 'Order approved, awaiting capture'];
    }
}
