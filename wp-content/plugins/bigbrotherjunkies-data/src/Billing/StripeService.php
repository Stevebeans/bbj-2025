<?php

namespace BigBrotherJunkies\Data\Billing;

/**
 * Stripe payment service
 * Handles checkout sessions, subscriptions, and webhooks
 */
class StripeService
{
    private string $secretKey;
    private string $publishableKey;
    private string $webhookSecret;
    private bool $testMode;
    private array $priceIds;

    /** Shown to the member when a stored Stripe Price cannot be trusted. */
    public const PRICE_UNAVAILABLE_MESSAGE = 'This plan is temporarily unavailable. Please contact support.';

    /**
     * Plan configuration
     */
    public const PLANS = [
        'monthly' => [
            'name' => 'BBJ Supporter Monthly',
            'amount' => 695, // $6.95 in cents
            'interval' => 'month',
            'interval_count' => 1,
            'role' => 'supporter',
            'tier' => 'supporter',
            'plan_type' => 'monthly', // ENUM-safe value stored in DB
        ],
        'annual' => [
            'name' => 'BBJ All-Access Pass',
            'amount' => 3500, // $35.00 in cents
            'interval' => 'year',
            'interval_count' => 1,
            'role' => 'supporter',
            'tier' => 'supporter',
            'plan_type' => 'annual',
        ],
        'lifetime' => [
            'name' => 'BBJ Lifetime',
            'amount' => 9900, // $99.00 in cents
            'interval' => null, // One-time
            'role' => 'lifetime',
            'tier' => 'supporter',
            'plan_type' => 'lifetime',
        ],
        'full_bean_monthly' => [
            'name' => 'Full Bean Monthly',
            'amount' => 1499, // $14.99 in cents
            'interval' => 'month',
            'interval_count' => 1,
            'role' => 'full_bean',
            'tier' => 'full_bean',
            'plan_type' => 'monthly',
        ],
        'full_bean_annual' => [
            'name' => 'Full Bean Annual',
            'amount' => 11900, // $119.00 in cents
            'interval' => 'year',
            'interval_count' => 1,
            'role' => 'full_bean',
            'tier' => 'full_bean',
            'plan_type' => 'annual',
        ],
    ];

    public function __construct()
    {
        $settings = \BigBrotherJunkies\Data\Admin\Pages\ApiSettingsPage::getSettings();

        $this->testMode = $settings['stripe_test_mode'];
        $this->secretKey = $this->testMode
            ? $settings['stripe_test_secret_key']
            : $settings['stripe_live_secret_key'];
        $this->publishableKey = $this->testMode
            ? $settings['stripe_test_publishable_key']
            : $settings['stripe_live_publishable_key'];
        $this->webhookSecret = $this->testMode
            ? $settings['stripe_test_webhook_secret']
            : $settings['stripe_live_webhook_secret'];
        $this->priceIds = [
            'monthly' => $settings['stripe_price_monthly'] ?? '',
            'annual' => $settings['stripe_price_annual'] ?? '',
            'lifetime' => $settings['stripe_price_lifetime'] ?? '',
            'full_bean_monthly' => $settings['stripe_price_full_bean_monthly'] ?? '',
            'full_bean_annual' => $settings['stripe_price_full_bean_annual'] ?? '',
        ];
    }

    /**
     * Check if Stripe is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->secretKey);
    }

    /**
     * Get publishable key for frontend
     */
    public function getPublishableKey(): string
    {
        return $this->publishableKey;
    }

    /**
     * Get available plans
     */
    public function getPlans(): array
    {
        return array_map(function ($key, $plan) {
            return [
                'id' => $key,
                'name' => $plan['name'],
                'amount' => $plan['amount'],
                'amount_display' => '$' . number_format($plan['amount'] / 100, 2),
                'interval' => $plan['interval'],
                'is_recurring' => $plan['interval'] !== null,
                'tier' => $plan['tier'] ?? 'supporter',
            ];
        }, array_keys(self::PLANS), self::PLANS);
    }

    /**
     * Get or create a Stripe customer for a WordPress user
     */
    public function getOrCreateCustomer(int $userId): ?string
    {
        $user = get_user_by('ID', $userId);
        if (!$user) {
            return null;
        }

        // Check if user already has a Stripe customer ID
        $customerId = get_user_meta($userId, 'bbj_stripe_customer_id', true);
        if ($customerId) {
            // Verify the customer still exists (handles test/live mode mismatch)
            $existing = $this->apiRequest("customers/{$customerId}", [], 'GET');
            if ($existing && !isset($existing['error'])) {
                return $customerId;
            }
            // Customer doesn't exist in current mode — clear and recreate
            delete_user_meta($userId, 'bbj_stripe_customer_id');
        }

        // Create new customer
        $response = $this->apiRequest('customers', [
            'email' => $user->user_email,
            'name' => $user->display_name,
            'metadata' => [
                'wp_user_id' => $userId,
                'username' => $user->user_login,
            ],
        ]);

        if ($response && isset($response['id'])) {
            update_user_meta($userId, 'bbj_stripe_customer_id', $response['id']);
            return $response['id'];
        }

        return null;
    }

    /**
     * Create a Stripe Checkout session
     */
    public function createCheckoutSession(int $userId, string $planType, string $successUrl, string $cancelUrl): ?array
    {
        if (!isset(self::PLANS[$planType])) {
            return ['error' => 'Invalid plan type'];
        }

        $plan = self::PLANS[$planType];
        $customerId = $this->getOrCreateCustomer($userId);

        if (!$customerId) {
            return ['error' => 'Failed to create customer'];
        }

        $params = [
            'customer' => $customerId,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'payment_method_types' => ['card'],
            'metadata' => [
                'wp_user_id' => $userId,
                'plan_type' => $planType,
            ],
            'allow_promotion_codes' => true,
        ];

        // Different handling for subscription vs one-time payment
        if ($plan['interval']) {
            // Subscription
            $params['mode'] = 'subscription';
            $params['line_items'] = [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $plan['amount'],
                    'recurring' => [
                        'interval' => $plan['interval'],
                        'interval_count' => $plan['interval_count'],
                    ],
                    'product_data' => [
                        'name' => $plan['name'],
                        'description' => $this->getPlanDescription($planType),
                    ],
                ],
                'quantity' => 1,
            ]];
        } else {
            // One-time payment (lifetime)
            $params['mode'] = 'payment';
            $params['line_items'] = [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $plan['amount'],
                    'product_data' => [
                        'name' => $plan['name'],
                        'description' => $this->getPlanDescription($planType),
                    ],
                ],
                'quantity' => 1,
            ]];
        }

        $response = $this->apiRequest('checkout/sessions', $params);

        if ($response && isset($response['id'])) {
            return [
                'session_id' => $response['id'],
                'url' => $response['url'],
            ];
        }

        return ['error' => $response['error'] ?? 'Failed to create checkout session'];
    }

    /**
     * Get plan description for display
     */
    private function getPlanDescription(string $planType): string
    {
        $descriptions = [
            'monthly' => 'Ad-free browsing, supporter badge, and premium features. Cancel anytime.',
            'annual' => 'Ad-free everything on every show we cover - about 58% off monthly.',
            'lifetime' => 'Premium access forever. One payment, never expires.',
            'full_bean_monthly' => 'Everything in Supporter, plus the smartest Bean (unlimited chats, longer memory). Cancel anytime.',
            'full_bean_annual' => 'A full year of the smartest Bean and ad-free browsing. Best value.',
        ];

        return $descriptions[$planType] ?? '';
    }

    /**
     * Get Stripe Customer Portal URL
     */
    public function createPortalSession(int $userId, string $returnUrl): ?array
    {
        $customerId = get_user_meta($userId, 'bbj_stripe_customer_id', true);

        if (!$customerId) {
            return ['error' => 'No subscription found'];
        }

        $response = $this->apiRequest('billing_portal/sessions', [
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);

        if ($response && isset($response['url'])) {
            return ['url' => $response['url']];
        }

        return ['error' => $response['error'] ?? 'Failed to create portal session'];
    }

    /**
     * Cancel a subscription (at period end)
     */
    public function cancelSubscription(string $subscriptionId): ?array
    {
        $response = $this->apiRequest("subscriptions/{$subscriptionId}", [
            'cancel_at_period_end' => true,
        ], 'POST');

        if ($response && isset($response['id'])) {
            return [
                'success' => true,
                'cancel_at' => $response['cancel_at'] ?? null,
            ];
        }

        return ['error' => $response['error'] ?? 'Failed to cancel subscription'];
    }

    /**
     * Cancel a subscription immediately (delete it)
     */
    public function cancelSubscriptionImmediately(string $subscriptionId): ?array
    {
        $response = $this->apiRequest("subscriptions/{$subscriptionId}", [], 'DELETE');

        if ($response && isset($response['id'])) {
            return ['success' => true];
        }

        return ['error' => $response['error'] ?? 'Failed to cancel subscription immediately'];
    }

    /**
     * Get subscription details
     */
    public function getSubscription(string $subscriptionId): ?array
    {
        return $this->apiRequest("subscriptions/{$subscriptionId}", [], 'GET');
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            return false;
        }

        $signatureParts = [];
        foreach (explode(',', $signature) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2) {
                $signatureParts[$pair[0]] = $pair[1];
            }
        }

        if (!isset($signatureParts['t']) || !isset($signatureParts['v1'])) {
            return false;
        }

        $timestamp = $signatureParts['t'];
        $expectedSignature = $signatureParts['v1'];

        // Check timestamp tolerance (5 minutes)
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        // Compute expected signature
        $signedPayload = $timestamp . '.' . $payload;
        $computedSignature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        return hash_equals($computedSignature, $expectedSignature);
    }

    /**
     * Get the default payment method for a user
     */
    public function getPaymentMethod(int $userId): ?array
    {
        $customerId = get_user_meta($userId, 'bbj_stripe_customer_id', true);
        if (!$customerId) {
            return null;
        }

        // Fetch customer to get default payment method
        $customer = $this->apiRequest("customers/{$customerId}", [
            'expand' => ['default_source', 'invoice_settings.default_payment_method'],
        ], 'GET');

        if (!$customer || isset($customer['error'])) {
            return null;
        }

        // Check invoice_settings.default_payment_method first (newer Stripe approach)
        $pm = $customer['invoice_settings']['default_payment_method'] ?? null;
        if ($pm && is_array($pm) && isset($pm['card'])) {
            return [
                'type' => 'card',
                'brand' => $pm['card']['brand'] ?? 'unknown',
                'last4' => $pm['card']['last4'] ?? '****',
                'exp_month' => $pm['card']['exp_month'] ?? null,
                'exp_year' => $pm['card']['exp_year'] ?? null,
            ];
        }

        // Fallback: list payment methods attached to customer
        $methods = $this->apiRequest("payment_methods", [
            'customer' => $customerId,
            'type' => 'card',
            'limit' => 1,
        ], 'GET');

        if ($methods && !empty($methods['data'][0]['card'])) {
            $card = $methods['data'][0]['card'];
            return [
                'type' => 'card',
                'brand' => $card['brand'] ?? 'unknown',
                'last4' => $card['last4'] ?? '****',
                'exp_month' => $card['exp_month'] ?? null,
                'exp_year' => $card['exp_year'] ?? null,
            ];
        }

        return null;
    }

    /**
     * Get a stored Stripe Price ID for a plan type
     */
    public function getStripePriceId(string $planType): ?string
    {
        return !empty($this->priceIds[$planType]) ? $this->priceIds[$planType] : null;
    }

    /**
     * Verify that the stored Stripe Price ID for a plan still bills what
     * self::PLANS says it should. Only the stored-Price paths need this:
     * createCheckoutSession() builds inline price_data from PLANS and is
     * therefore always correct, but updateSubscription() (plan switches)
     * hands Stripe a Price object out of bbjd_api_settings, which can hold a
     * retired price. A stale ID there silently bills the old amount while the
     * local row records the new one.
     *
     * One Stripe GET per price id per hour (transient-cached). Fails CLOSED:
     * a mismatch or an unreadable price refuses the switch rather than
     * risking a wrong charge.
     *
     * @return string|null Error message to hand back to the caller, or null when the price is good.
     */
    private function assertConfiguredPriceMatches(string $planType): ?string
    {
        if (!isset(self::PLANS[$planType])) {
            return 'Invalid plan type';
        }

        // Escape hatch for tests/offline environments (no network in the suite).
        if (apply_filters('bbjd_billing_skip_price_verification', false, $planType)) {
            return null;
        }

        $priceId = $this->getStripePriceId($planType);
        if (!$priceId) {
            // Nothing stored: the caller's own "not configured" branch reports it.
            return null;
        }

        $expected = (int) self::PLANS[$planType]['amount'];
        $cacheKey = 'bbjd_stripe_price_' . md5($priceId);
        $cached = get_transient($cacheKey);

        if (is_array($cached) && isset($cached['unit_amount'])) {
            $price = $cached;
        } else {
            $response = $this->apiRequest('prices/' . rawurlencode($priceId), [], 'GET');

            if (!is_array($response) || isset($response['error']) || !isset($response['unit_amount'])) {
                error_log(
                    'BBJ Billing: could not read configured Stripe price for ' . $planType
                    . ' (' . $priceId . ') - refusing checkout: '
                    . (is_array($response) ? ($response['error'] ?? 'malformed price response') : 'no response')
                );
                return self::PRICE_UNAVAILABLE_MESSAGE;
            }

            $price = [
                'unit_amount' => (int) $response['unit_amount'],
                'interval'    => $response['recurring']['interval'] ?? null,
                'currency'    => strtolower((string) ($response['currency'] ?? '')),
            ];
            set_transient($cacheKey, $price, HOUR_IN_SECONDS);
        }

        if ((int) $price['unit_amount'] !== $expected) {
            error_log(
                'BBJ Billing: configured Stripe price for ' . $planType . ' is '
                . (int) $price['unit_amount'] . '¢, expected ' . $expected . '¢ - refusing checkout'
            );
            return self::PRICE_UNAVAILABLE_MESSAGE;
        }

        $wantInterval = self::PLANS[$planType]['interval'];
        if ($wantInterval !== null && ($price['interval'] ?? null) !== $wantInterval) {
            error_log(
                'BBJ Billing: configured Stripe price for ' . $planType . ' bills per '
                . var_export($price['interval'] ?? null, true) . ', expected ' . $wantInterval
                . ' - refusing checkout'
            );
            return self::PRICE_UNAVAILABLE_MESSAGE;
        }

        if ($price['currency'] !== '' && $price['currency'] !== 'usd') {
            error_log(
                'BBJ Billing: configured Stripe price for ' . $planType . ' is in '
                . $price['currency'] . ', expected usd - refusing checkout'
            );
            return self::PRICE_UNAVAILABLE_MESSAGE;
        }

        return null;
    }

    /**
     * Update a subscription to a new plan (swap the price)
     */
    public function updateSubscription(string $subscriptionId, string $newPlanType): array
    {
        $newPriceId = $this->getStripePriceId($newPlanType);
        if (!$newPriceId) {
            return ['error' => 'Price ID not configured for this plan. Contact support.'];
        }

        // The stored Price ID is the only place a wrong amount can enter the
        // system unnoticed. Verify it against PLANS before Stripe bills anyone.
        $priceError = $this->assertConfiguredPriceMatches($newPlanType);
        if ($priceError !== null) {
            return ['error' => $priceError];
        }

        // Get current subscription to find the subscription item
        $sub = $this->getSubscription($subscriptionId);
        if (!$sub || isset($sub['error'])) {
            return ['error' => $sub['error'] ?? 'Failed to retrieve subscription'];
        }

        $itemId = $sub['items']['data'][0]['id'] ?? null;
        if (!$itemId) {
            return ['error' => 'Could not find subscription item'];
        }

        // Update the subscription item with the new price, with proration
        $response = $this->apiRequest("subscriptions/{$subscriptionId}", [
            'items' => [
                [
                    'id' => $itemId,
                    'price' => $newPriceId,
                ],
            ],
            'proration_behavior' => 'create_prorations',
        ], 'POST');

        if ($response && isset($response['id']) && !isset($response['error'])) {
            return [
                'success' => true,
                'subscription_id' => $response['id'],
                'new_plan' => $newPlanType,
            ];
        }

        return ['error' => $response['error'] ?? 'Failed to update subscription'];
    }

    /**
     * Get plan config by type
     */
    public function getPlanConfig(string $planType): ?array
    {
        return self::PLANS[$planType] ?? null;
    }

    /**
     * Make API request to Stripe
     */
    private function apiRequest(string $endpoint, array $data = [], string $method = 'POST'): ?array
    {
        $url = 'https://api.stripe.com/v1/' . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'timeout' => 30,
        ];

        if ($method !== 'GET' && !empty($data)) {
            $args['body'] = $this->buildFormData($data);
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('Stripe API error: ' . $response->get_error_message());
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            error_log('Stripe API error: ' . json_encode($data['error']));
            return ['error' => $data['error']['message'] ?? 'Unknown error'];
        }

        return $data;
    }

    /**
     * Build form data for Stripe API (handles nested arrays)
     */
    private function buildFormData(array $data, string $prefix = ''): string
    {
        $params = [];
        $this->flattenArray($data, $params, $prefix);
        return http_build_query($params);
    }

    /**
     * Flatten nested array for form data
     */
    private function flattenArray(array $data, array &$result, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            $newKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                $this->flattenArray($value, $result, $newKey);
            } elseif (is_bool($value)) {
                $result[$newKey] = $value ? 'true' : 'false';
            } else {
                $result[$newKey] = $value;
            }
        }
    }
}
