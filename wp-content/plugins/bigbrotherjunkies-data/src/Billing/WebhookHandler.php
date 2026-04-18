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

        $stripe = new StripeService();
        $planConfig = $stripe->getPlanConfig($planType);

        if (!$planConfig) {
            return ['error' => 'Invalid plan type', 'status' => 400];
        }

        // Check if user already has an active subscription
        if ($this->subscriptionManager->hasActiveSubscription($userId)) {
            error_log("BBJ Billing: User {$userId} already has active subscription");
            return ['success' => true, 'message' => 'User already has active subscription'];
        }

        $subscriptionData = [
            'user_id' => $userId,
            'stripe_customer_id' => $session['customer'],
            'plan_type' => $planType,
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
        }

        $subscriptionId = $this->subscriptionManager->createSubscription($subscriptionData);

        if ($subscriptionId) {
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
            'plan_type' => $planType,
            'processor' => 'paypal',
            'status' => 'active',
            'amount_cents' => (int) (floatval($planConfig['amount']) * 100),
            'currency' => 'USD',
        ];

        if (isset($subscription['billing_info']['next_billing_time'])) {
            $subscriptionData['current_period_end'] = date('Y-m-d H:i:s', strtotime($subscription['billing_info']['next_billing_time']));
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
     * Handle PayPal PAYMENT.SALE.COMPLETED (for one-time payments)
     */
    private function handlePayPalPaymentCompleted(array $payment): array
    {
        // This is called for subscription renewals and one-time payments
        // For subscriptions, we just acknowledge it
        error_log("BBJ Billing: PayPal payment completed: " . ($payment['id'] ?? 'unknown'));
        return ['success' => true];
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
