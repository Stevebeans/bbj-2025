<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Billing\StripeService;
use BigBrotherJunkies\Data\Billing\PayPalService;
use BigBrotherJunkies\Data\Billing\SubscriptionManager;
use BigBrotherJunkies\Data\Billing\WebhookHandler;

/**
 * Billing REST API Routes
 */
class BillingRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        // Get available plans
        register_rest_route($namespace, '/billing/plans', [
            'methods' => 'GET',
            'callback' => [$this, 'getPlans'],
            'permission_callback' => '__return_true',
        ]);

        // Get current subscription status
        register_rest_route($namespace, '/billing/subscription', [
            'methods' => 'GET',
            'callback' => [$this, 'getSubscription'],
            'permission_callback' => [$this, 'requireAuth'],
        ]);

        // Create Stripe checkout session
        register_rest_route($namespace, '/billing/create-checkout', [
            'methods' => 'POST',
            'callback' => [$this, 'createCheckout'],
            'permission_callback' => [$this, 'requireAuth'],
        ]);

        // Create PayPal order (for lifetime)
        register_rest_route($namespace, '/billing/create-paypal-order', [
            'methods' => 'POST',
            'callback' => [$this, 'createPayPalOrder'],
            'permission_callback' => [$this, 'requireAuth'],
        ]);

        // Capture PayPal order
        register_rest_route($namespace, '/billing/capture-paypal-order', [
            'methods' => 'POST',
            'callback' => [$this, 'capturePayPalOrder'],
            'permission_callback' => [$this, 'requireAuth'],
        ]);

        // Create PayPal subscription
        register_rest_route($namespace, '/billing/create-paypal-subscription', [
            'methods' => 'POST',
            'callback' => [$this, 'createPayPalSubscription'],
            'permission_callback' => [$this, 'requireAuth'],
        ]);

        // Get Stripe portal URL
        register_rest_route($namespace, '/billing/portal', [
            'methods' => 'GET',
            'callback' => [$this, 'getPortalUrl'],
            'permission_callback' => [$this, 'requireAuth'],
        ]);

        // Change subscription plan
        register_rest_route($namespace, '/billing/change-plan', [
            'methods' => 'POST',
            'callback' => [$this, 'changePlan'],
            'permission_callback' => [$this, 'requireAuth'],
        ]);

        // Get payment history / invoices
        register_rest_route($namespace, '/billing/invoices', [
            'methods' => 'GET',
            'callback' => [$this, 'getInvoices'],
            'permission_callback' => [$this, 'requireAuth'],
        ]);

        // Cancel subscription
        register_rest_route($namespace, '/billing/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'cancelSubscription'],
            'permission_callback' => [$this, 'requireAuth'],
        ]);

        // Stripe webhook
        register_rest_route($namespace, '/billing/webhook/stripe', [
            'methods' => 'POST',
            'callback' => [$this, 'handleStripeWebhook'],
            'permission_callback' => '__return_true',
        ]);

        // PayPal webhook
        register_rest_route($namespace, '/billing/webhook/paypal', [
            'methods' => 'POST',
            'callback' => [$this, 'handlePayPalWebhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get available plans
     */
    public function getPlans(\WP_REST_Request $request): \WP_REST_Response
    {
        $stripe = new StripeService();
        $paypal = new PayPalService();

        return new \WP_REST_Response([
            'success' => true,
            'plans' => [
                [
                    'id' => 'monthly',
                    'name' => 'Monthly',
                    'price' => '$6.95',
                    'price_cents' => 695,
                    'interval' => 'month',
                    'description' => 'Cancel anytime',
                    'badge' => 'Supporter',
                    'popular' => false,
                ],
                [
                    'id' => 'annual',
                    'name' => 'Season Pass',
                    'price' => '$35',
                    'price_cents' => 3500,
                    'interval' => 'year',
                    'description' => 'Save over 58%',
                    'badge' => 'Supporter',
                    'popular' => true,
                ],
                [
                    'id' => 'lifetime',
                    'name' => 'Lifetime',
                    'price' => '$99',
                    'price_cents' => 9900,
                    'interval' => null,
                    'description' => 'One-time, never expires',
                    'badge' => 'Lifetime',
                    'popular' => false,
                ],
            ],
            'stripe_configured' => $stripe->isConfigured(),
            'stripe_publishable_key' => $stripe->getPublishableKey(),
            'paypal_configured' => $paypal->isConfigured(),
            'paypal_client_id' => $paypal->getClientId(),
        ], 200);
    }

    /**
     * Get current user's subscription
     */
    public function getSubscription(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $manager = SubscriptionManager::getInstance();

        $subscription = $manager->getActiveSubscription($userId);

        if (!$subscription) {
            return new \WP_REST_Response([
                'success' => true,
                'has_subscription' => false,
                'subscription' => null,
            ], 200);
        }

        // Get payment method info
        $paymentMethod = null;
        if ($subscription['processor'] === 'stripe') {
            $stripe = new StripeService();
            $paymentMethod = $stripe->getPaymentMethod($userId);
        } elseif ($subscription['processor'] === 'paypal') {
            $paymentMethod = ['type' => 'paypal'];
        }

        return new \WP_REST_Response([
            'success' => true,
            'has_subscription' => true,
            'subscription' => [
                'id' => (int) $subscription['id'],
                'plan_type' => $subscription['plan_type'],
                'plan_name' => $this->getPlanDisplayName($subscription['plan_type']),
                'status' => $subscription['status'],
                'processor' => $subscription['processor'],
                'amount_display' => '$' . number_format($subscription['amount_cents'] / 100, 2),
                'current_period_end' => $subscription['current_period_end'],
                'cancel_at_period_end' => (bool) $subscription['cancel_at_period_end'],
                'canceled_at' => $subscription['canceled_at'],
                'created_at' => $subscription['created_at'],
                'payment_method' => $paymentMethod,
            ],
        ], 200);
    }

    /**
     * Create Stripe checkout session
     */
    public function createCheckout(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $params = $request->get_json_params();

        $planType = $params['plan_type'] ?? null;
        $successUrl = $params['success_url'] ?? home_url('/checkout/success');
        $cancelUrl = $params['cancel_url'] ?? home_url('/checkout/cancel');

        if (!$planType || !in_array($planType, ['monthly', 'annual', 'lifetime'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid plan type',
            ], 400);
        }

        // Check if user already has active subscription
        $manager = SubscriptionManager::getInstance();
        if ($manager->hasActiveSubscription($userId)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'You already have an active subscription',
            ], 400);
        }

        $stripe = new StripeService();

        if (!$stripe->isConfigured()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Payment processing is not configured',
            ], 500);
        }

        $result = $stripe->createCheckoutSession($userId, $planType, $successUrl, $cancelUrl);

        if (isset($result['error'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'session_id' => $result['session_id'],
            'checkout_url' => $result['url'],
        ], 200);
    }

    /**
     * Create PayPal order (for lifetime one-time payment)
     */
    public function createPayPalOrder(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $params = $request->get_json_params();

        $planType = $params['plan_type'] ?? 'lifetime';
        $returnUrl = $params['return_url'] ?? home_url('/checkout/success?processor=paypal');
        $cancelUrl = $params['cancel_url'] ?? home_url('/checkout/cancel');

        if ($planType !== 'lifetime') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Use create-paypal-subscription for recurring plans',
            ], 400);
        }

        $manager = SubscriptionManager::getInstance();
        if ($manager->hasActiveSubscription($userId)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'You already have an active subscription',
            ], 400);
        }

        $paypal = new PayPalService();

        if (!$paypal->isConfigured()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'PayPal is not configured',
            ], 500);
        }

        $result = $paypal->createOrder($userId, $planType, $returnUrl, $cancelUrl);

        if (isset($result['error'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'order_id' => $result['order_id'],
            'approve_url' => $result['approve_url'],
        ], 200);
    }

    /**
     * Capture PayPal order after approval
     */
    public function capturePayPalOrder(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $orderId = $params['order_id'] ?? null;

        if (!$orderId) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Order ID is required',
            ], 400);
        }

        $paypal = new PayPalService();
        $result = $paypal->captureOrder($orderId);

        if (isset($result['error'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        // Create subscription record for lifetime
        $manager = SubscriptionManager::getInstance();

        if ($result['wp_user_id'] && $result['plan_type'] === 'lifetime') {
            $subscriptionId = $manager->createSubscription([
                'user_id' => $result['wp_user_id'],
                'paypal_payer_id' => $result['payer_id'],
                'plan_type' => 'lifetime',
                'processor' => 'paypal',
                'status' => 'lifetime',
                'amount_cents' => 9900,
                'currency' => 'USD',
            ]);

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Payment successful! Welcome to lifetime membership.',
                'subscription_id' => $subscriptionId,
            ], 200);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Payment captured',
        ], 200);
    }

    /**
     * Create PayPal subscription (for monthly/annual)
     */
    public function createPayPalSubscription(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $params = $request->get_json_params();

        $planType = $params['plan_type'] ?? null;
        $returnUrl = $params['return_url'] ?? home_url('/checkout/success?processor=paypal');
        $cancelUrl = $params['cancel_url'] ?? home_url('/checkout/cancel');

        if (!$planType || !in_array($planType, ['monthly', 'annual'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid plan type for subscription',
            ], 400);
        }

        $manager = SubscriptionManager::getInstance();
        if ($manager->hasActiveSubscription($userId)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'You already have an active subscription',
            ], 400);
        }

        $paypal = new PayPalService();

        if (!$paypal->isConfigured()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'PayPal is not configured',
            ], 500);
        }

        $result = $paypal->createSubscription($userId, $planType, $returnUrl, $cancelUrl);

        if (isset($result['error'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'subscription_id' => $result['subscription_id'],
            'approve_url' => $result['approve_url'],
        ], 200);
    }

    /**
     * Get Stripe Customer Portal URL
     */
    public function getPortalUrl(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $returnUrl = $request->get_param('return_url') ?? home_url('/settings?tab=premium');

        $stripe = new StripeService();
        $result = $stripe->createPortalSession($userId, $returnUrl);

        if (isset($result['error'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'url' => $result['url'],
        ], 200);
    }

    /**
     * Change subscription plan
     */
    public function changePlan(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $params = $request->get_json_params();
        $planType = $params['plan_type'] ?? null;

        if (!$planType || !in_array($planType, ['monthly', 'annual', 'lifetime'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid plan type',
            ], 400);
        }

        $manager = SubscriptionManager::getInstance();
        $result = $manager->changePlan($userId, $planType);

        if (isset($result['error'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => $result['message'],
        ], 200);
    }

    /**
     * Get payment history
     */
    public function getInvoices(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $manager = SubscriptionManager::getInstance();
        $history = $manager->getSubscriptionHistory($userId, 20);

        $invoices = array_map(function ($record) {
            return [
                'id' => (int) $record['id'],
                'date' => $record['created_at'],
                'plan_name' => $this->getPlanDisplayName($record['plan_type']),
                'plan_type' => $record['plan_type'],
                'amount_display' => '$' . number_format(($record['amount_cents'] ?? 0) / 100, 2),
                'status' => $record['status'],
                'processor' => $record['processor'],
            ];
        }, $history);

        return new \WP_REST_Response([
            'success' => true,
            'invoices' => $invoices,
        ], 200);
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $manager = SubscriptionManager::getInstance();

        $result = $manager->cancelSubscription($userId);

        if (isset($result['error'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => $result['message'],
            'cancel_at' => $result['cancel_at'] ?? null,
        ], 200);
    }

    /**
     * Handle Stripe webhook
     */
    public function handleStripeWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $request->get_body();
        $signature = $request->get_header('stripe-signature') ?? '';

        $handler = new WebhookHandler();
        $result = $handler->handleStripeWebhook($payload, $signature);

        $status = $result['status'] ?? 200;
        unset($result['status']);

        return new \WP_REST_Response($result, $status);
    }

    /**
     * Handle PayPal webhook
     */
    public function handlePayPalWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $request->get_body();
        $headers = [
            'paypal-auth-algo' => $request->get_header('paypal-auth-algo'),
            'paypal-cert-url' => $request->get_header('paypal-cert-url'),
            'paypal-transmission-id' => $request->get_header('paypal-transmission-id'),
            'paypal-transmission-sig' => $request->get_header('paypal-transmission-sig'),
            'paypal-transmission-time' => $request->get_header('paypal-transmission-time'),
        ];

        $handler = new WebhookHandler();
        $result = $handler->handlePayPalWebhook($payload, $headers);

        $status = $result['status'] ?? 200;
        unset($result['status']);

        return new \WP_REST_Response($result, $status);
    }

    /**
     * Get plan display name
     */
    private function getPlanDisplayName(string $planType): string
    {
        $names = [
            'monthly' => 'Monthly Supporter',
            'annual' => 'Season Pass',
            'lifetime' => 'Lifetime Member',
        ];

        return $names[$planType] ?? $planType;
    }

    /**
     * Permission callback: require authentication
     */
    public function requireAuth(): bool
    {
        return is_user_logged_in();
    }
}
