<?php

namespace BigBrotherJunkies\Data\Billing;

/**
 * PayPal payment service
 * Handles subscriptions and webhooks
 */
class PayPalService
{
    private string $clientId;
    private string $clientSecret;
    private string $webhookId;
    private bool $sandbox;
    private ?string $accessToken = null;

    /**
     * Plan configuration (mirrors Stripe plans)
     */
    private const PLANS = [
        'monthly' => [
            'name' => 'BBJ Supporter Monthly',
            'amount' => '6.95',
            'interval' => 'MONTH',
            'interval_count' => 1,
            'role' => 'supporter',
        ],
        'annual' => [
            'name' => 'BBJ Season Pass',
            'amount' => '35.00',
            'interval' => 'YEAR',
            'interval_count' => 1,
            'role' => 'supporter',
        ],
        'lifetime' => [
            'name' => 'BBJ Lifetime',
            'amount' => '99.00',
            'interval' => null, // One-time
            'role' => 'lifetime',
        ],
    ];

    public function __construct()
    {
        $settings = \BigBrotherJunkies\Data\Admin\Pages\ApiSettingsPage::getSettings();

        $this->sandbox = $settings['paypal_sandbox'];
        $this->clientId = $this->sandbox
            ? $settings['paypal_sandbox_client_id']
            : $settings['paypal_live_client_id'];
        $this->clientSecret = $this->sandbox
            ? $settings['paypal_sandbox_client_secret']
            : $settings['paypal_live_client_secret'];
        $this->webhookId = $this->sandbox
            ? $settings['paypal_sandbox_webhook_id']
            : $settings['paypal_live_webhook_id'];
    }

    /**
     * Check if PayPal is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /**
     * Get client ID for frontend
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * Get API base URL
     */
    private function getBaseUrl(): string
    {
        return $this->sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * Get access token
     */
    private function getAccessToken(): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $response = wp_remote_post($this->getBaseUrl() . '/v1/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('PayPal auth error: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            $this->accessToken = $body['access_token'];
            return $this->accessToken;
        }

        error_log('PayPal auth failed: ' . wp_remote_retrieve_body($response));
        return null;
    }

    /**
     * Create a PayPal order for one-time payment (lifetime)
     */
    public function createOrder(int $userId, string $planType, string $returnUrl, string $cancelUrl): ?array
    {
        if (!isset(self::PLANS[$planType])) {
            return ['error' => 'Invalid plan type'];
        }

        $plan = self::PLANS[$planType];

        // For lifetime, create a one-time order
        if ($planType !== 'lifetime') {
            return ['error' => 'Use createSubscription for recurring plans'];
        }

        $response = $this->apiRequest('v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => "user_{$userId}_lifetime",
                'description' => $plan['name'],
                'custom_id' => json_encode([
                    'wp_user_id' => $userId,
                    'plan_type' => $planType,
                ]),
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $plan['amount'],
                ],
            ]],
            'application_context' => [
                'brand_name' => 'Big Brother Junkies',
                'landing_page' => 'BILLING',
                'user_action' => 'PAY_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ]);

        if ($response && isset($response['id'])) {
            $approveLink = null;
            foreach ($response['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    $approveLink = $link['href'];
                    break;
                }
            }

            return [
                'order_id' => $response['id'],
                'approve_url' => $approveLink,
            ];
        }

        return ['error' => $response['error'] ?? 'Failed to create order'];
    }

    /**
     * Capture a PayPal order (after user approval)
     */
    public function captureOrder(string $orderId): ?array
    {
        $response = $this->apiRequest("v2/checkout/orders/{$orderId}/capture", []);

        if ($response && $response['status'] === 'COMPLETED') {
            $customId = $response['purchase_units'][0]['payments']['captures'][0]['custom_id'] ?? null;
            $metadata = $customId ? json_decode($customId, true) : [];

            return [
                'success' => true,
                'order_id' => $orderId,
                'payer_id' => $response['payer']['payer_id'] ?? null,
                'payer_email' => $response['payer']['email_address'] ?? null,
                'wp_user_id' => $metadata['wp_user_id'] ?? null,
                'plan_type' => $metadata['plan_type'] ?? null,
            ];
        }

        return ['error' => $response['error'] ?? 'Failed to capture order'];
    }

    /**
     * Create a PayPal subscription
     * Note: Requires pre-created plans in PayPal dashboard
     */
    public function createSubscription(int $userId, string $planType, string $returnUrl, string $cancelUrl): ?array
    {
        if (!isset(self::PLANS[$planType]) || $planType === 'lifetime') {
            return ['error' => 'Invalid subscription plan type'];
        }

        // Get the PayPal plan ID from settings
        $planId = $this->getPayPalPlanId($planType);
        if (!$planId) {
            return ['error' => 'PayPal plan not configured. Please use Stripe or contact support.'];
        }

        $user = get_user_by('ID', $userId);

        $response = $this->apiRequest('v1/billing/subscriptions', [
            'plan_id' => $planId,
            'subscriber' => [
                'name' => [
                    'given_name' => $user->first_name ?: $user->display_name,
                    'surname' => $user->last_name ?: '',
                ],
                'email_address' => $user->user_email,
            ],
            'custom_id' => json_encode([
                'wp_user_id' => $userId,
                'plan_type' => $planType,
            ]),
            'application_context' => [
                'brand_name' => 'Big Brother Junkies',
                'locale' => 'en-US',
                'user_action' => 'SUBSCRIBE_NOW',
                'payment_method' => [
                    'payer_selected' => 'PAYPAL',
                    'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
                ],
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ]);

        if ($response && isset($response['id'])) {
            $approveLink = null;
            foreach ($response['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    $approveLink = $link['href'];
                    break;
                }
            }

            return [
                'subscription_id' => $response['id'],
                'approve_url' => $approveLink,
            ];
        }

        return ['error' => $response['error'] ?? 'Failed to create subscription'];
    }

    /**
     * Get subscription details
     */
    public function getSubscription(string $subscriptionId): ?array
    {
        return $this->apiRequest("v1/billing/subscriptions/{$subscriptionId}", [], 'GET');
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(string $subscriptionId, string $reason = 'User requested cancellation'): ?array
    {
        $response = $this->apiRequest("v1/billing/subscriptions/{$subscriptionId}/cancel", [
            'reason' => $reason,
        ]);

        // PayPal returns 204 No Content on success
        if ($response === [] || (isset($response['success']) && $response['success'])) {
            return ['success' => true];
        }

        return ['error' => $response['error'] ?? 'Failed to cancel subscription'];
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, array $headers): bool
    {
        if (empty($this->webhookId)) {
            return false;
        }

        $response = $this->apiRequest('v1/notifications/verify-webhook-signature', [
            'auth_algo' => $headers['paypal-auth-algo'] ?? '',
            'cert_url' => $headers['paypal-cert-url'] ?? '',
            'transmission_id' => $headers['paypal-transmission-id'] ?? '',
            'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
            'transmission_time' => $headers['paypal-transmission-time'] ?? '',
            'webhook_id' => $this->webhookId,
            'webhook_event' => json_decode($payload, true),
        ]);

        return isset($response['verification_status']) && $response['verification_status'] === 'SUCCESS';
    }

    /**
     * Get PayPal plan ID from settings
     */
    private function getPayPalPlanId(string $planType): ?string
    {
        $settings = \BigBrotherJunkies\Data\Admin\Pages\ApiSettingsPage::getSettings();

        $planKey = 'paypal_plan_' . $planType;
        return !empty($settings[$planKey]) ? $settings[$planKey] : null;
    }

    /**
     * Get plan config by type
     */
    public function getPlanConfig(string $planType): ?array
    {
        return self::PLANS[$planType] ?? null;
    }

    /**
     * Make API request to PayPal
     */
    private function apiRequest(string $endpoint, array $data = [], string $method = 'POST'): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['error' => 'Failed to authenticate with PayPal'];
        }

        $url = $this->getBaseUrl() . '/' . ltrim($endpoint, '/');

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ($method !== 'GET' && !empty($data)) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('PayPal API error: ' . $response->get_error_message());
            return ['error' => $response->get_error_message()];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // 204 No Content is success for some endpoints
        if ($statusCode === 204) {
            return ['success' => true];
        }

        $data = json_decode($body, true);

        if ($statusCode >= 400) {
            $errorMsg = $data['message'] ?? $data['error_description'] ?? 'Unknown error';
            error_log("PayPal API error ({$statusCode}): " . json_encode($data));
            return ['error' => $errorMsg];
        }

        return $data ?? [];
    }
}
