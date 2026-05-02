<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

/**
 * API Settings page for Stripe and PayPal credentials
 */
class ApiSettingsPage
{
    public const MENU_SLUG = 'bbjd-api-settings';
    public const OPTION_NAME = 'bbjd_api_settings';

    /**
     * Handle actions
     */
    public function handleActions(): void
    {
        add_action('admin_post_bbjd_save_api_settings', [$this, 'handleSaveSettings']);
    }

    /**
     * Get API settings
     */
    public static function getSettings(): array
    {
        $defaults = [
            // Mode
            'stripe_test_mode' => true,
            'paypal_sandbox' => true,

            // Stripe Test
            'stripe_test_publishable_key' => '',
            'stripe_test_secret_key' => '',
            'stripe_test_webhook_secret' => '',

            // Stripe Live
            'stripe_live_publishable_key' => '',
            'stripe_live_secret_key' => '',
            'stripe_live_webhook_secret' => '',

            // PayPal Sandbox
            'paypal_sandbox_client_id' => '',
            'paypal_sandbox_client_secret' => '',
            'paypal_sandbox_webhook_id' => '',

            // PayPal Live
            'paypal_live_client_id' => '',
            'paypal_live_client_secret' => '',
            'paypal_live_webhook_id' => '',

            // Stripe Price IDs (for plan changes)
            'stripe_price_monthly' => '',
            'stripe_price_annual' => '',
            'stripe_price_lifetime' => '',

            // PayPal Plan IDs (for recurring subscriptions)
            'paypal_plan_monthly' => '',
            'paypal_plan_annual' => '',

            // Google Analytics
            'ga4_property_id' => '',
            'ga4_service_account_json' => '',
        ];

        $settings = get_option(self::OPTION_NAME, []);
        return array_merge($defaults, $settings);
    }

    /**
     * Handle save settings action
     */
    public function handleSaveSettings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_save_api_settings');

        $settings = [
            // Mode
            'stripe_test_mode' => isset($_POST['stripe_test_mode']),
            'paypal_sandbox' => isset($_POST['paypal_sandbox']),

            // Stripe Test
            'stripe_test_publishable_key' => sanitize_text_field($_POST['stripe_test_publishable_key'] ?? ''),
            'stripe_test_secret_key' => sanitize_text_field($_POST['stripe_test_secret_key'] ?? ''),
            'stripe_test_webhook_secret' => sanitize_text_field($_POST['stripe_test_webhook_secret'] ?? ''),

            // Stripe Live
            'stripe_live_publishable_key' => sanitize_text_field($_POST['stripe_live_publishable_key'] ?? ''),
            'stripe_live_secret_key' => sanitize_text_field($_POST['stripe_live_secret_key'] ?? ''),
            'stripe_live_webhook_secret' => sanitize_text_field($_POST['stripe_live_webhook_secret'] ?? ''),

            // PayPal Sandbox
            'paypal_sandbox_client_id' => sanitize_text_field($_POST['paypal_sandbox_client_id'] ?? ''),
            'paypal_sandbox_client_secret' => sanitize_text_field($_POST['paypal_sandbox_client_secret'] ?? ''),
            'paypal_sandbox_webhook_id' => sanitize_text_field($_POST['paypal_sandbox_webhook_id'] ?? ''),

            // PayPal Live
            'paypal_live_client_id' => sanitize_text_field($_POST['paypal_live_client_id'] ?? ''),
            'paypal_live_client_secret' => sanitize_text_field($_POST['paypal_live_client_secret'] ?? ''),
            'paypal_live_webhook_id' => sanitize_text_field($_POST['paypal_live_webhook_id'] ?? ''),

            // Stripe Price IDs
            'stripe_price_monthly' => sanitize_text_field($_POST['stripe_price_monthly'] ?? ''),
            'stripe_price_annual' => sanitize_text_field($_POST['stripe_price_annual'] ?? ''),
            'stripe_price_lifetime' => sanitize_text_field($_POST['stripe_price_lifetime'] ?? ''),

            // PayPal Plan IDs
            'paypal_plan_monthly' => sanitize_text_field($_POST['paypal_plan_monthly'] ?? ''),
            'paypal_plan_annual' => sanitize_text_field($_POST['paypal_plan_annual'] ?? ''),

            // Google Analytics
            'ga4_property_id' => sanitize_text_field($_POST['ga4_property_id'] ?? ''),
            'ga4_service_account_json' => wp_unslash($_POST['ga4_service_account_json'] ?? ''),
        ];

        update_option(self::OPTION_NAME, $settings);

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'saved',
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Render the page
     */
    public function render(): void
    {
        $settings = self::getSettings();
        $message = $_GET['message'] ?? '';
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6 bbjd-max-w-4xl">
                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-6">
                    API Settings
                </h1>

                <?php $this->renderMessages($message); ?>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('bbjd_save_api_settings'); ?>
                    <input type="hidden" name="action" value="bbjd_save_api_settings">

                    <!-- Stripe Settings -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <div class="bbjd-flex bbjd-items-center bbjd-justify-between bbjd-mb-4">
                            <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800">
                                Stripe
                            </h2>
                            <label class="bbjd-flex bbjd-items-center bbjd-space-x-2">
                                <input type="checkbox"
                                       name="stripe_test_mode"
                                       <?php checked($settings['stripe_test_mode']); ?>
                                       class="bbjd-rounded bbjd-border-gray-300 bbjd-text-yellow-500 focus:bbjd-ring-yellow-500">
                                <span class="bbjd-text-sm bbjd-font-medium bbjd-text-yellow-600">Test Mode</span>
                            </label>
                        </div>

                        <div class="bbjd-grid bbjd-grid-cols-1 lg:bbjd-grid-cols-2 bbjd-gap-6">
                            <!-- Test Keys -->
                            <div class="bbjd-space-y-4 bbjd-p-4 bbjd-bg-yellow-50 bbjd-rounded-lg bbjd-border bbjd-border-yellow-200">
                                <h3 class="bbjd-font-medium bbjd-text-yellow-800">Test Keys</h3>

                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Publishable Key
                                    </label>
                                    <input type="text"
                                           name="stripe_test_publishable_key"
                                           value="<?php echo esc_attr($settings['stripe_test_publishable_key']); ?>"
                                           placeholder="pk_test_..."
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                                </div>

                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Secret Key
                                    </label>
                                    <input type="password"
                                           name="stripe_test_secret_key"
                                           value="<?php echo esc_attr($settings['stripe_test_secret_key']); ?>"
                                           placeholder="sk_test_..."
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                                </div>

                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Webhook Secret
                                    </label>
                                    <input type="password"
                                           name="stripe_test_webhook_secret"
                                           value="<?php echo esc_attr($settings['stripe_test_webhook_secret']); ?>"
                                           placeholder="whsec_..."
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                                </div>
                            </div>

                            <!-- Live Keys -->
                            <div class="bbjd-space-y-4 bbjd-p-4 bbjd-bg-green-50 bbjd-rounded-lg bbjd-border bbjd-border-green-200">
                                <h3 class="bbjd-font-medium bbjd-text-green-800">Live Keys</h3>

                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Publishable Key
                                    </label>
                                    <input type="text"
                                           name="stripe_live_publishable_key"
                                           value="<?php echo esc_attr($settings['stripe_live_publishable_key']); ?>"
                                           placeholder="pk_live_..."
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                                </div>

                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Secret Key
                                    </label>
                                    <input type="password"
                                           name="stripe_live_secret_key"
                                           value="<?php echo esc_attr($settings['stripe_live_secret_key']); ?>"
                                           placeholder="sk_live_..."
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                                </div>

                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Webhook Secret
                                    </label>
                                    <input type="password"
                                           name="stripe_live_webhook_secret"
                                           value="<?php echo esc_attr($settings['stripe_live_webhook_secret']); ?>"
                                           placeholder="whsec_..."
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                                </div>
                            </div>
                        </div>

                        <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-4">
                            Webhook URL: <code class="bbjd-bg-gray-100 bbjd-px-2 bbjd-py-1 bbjd-rounded"><?php echo esc_html(rest_url('bbjd/v1/billing/webhook/stripe')); ?></code>
                        </p>
                    </div>

                    <!-- Stripe Price IDs -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-2">
                            Stripe Price IDs
                        </h2>
                        <p class="bbjd-text-gray-600 bbjd-text-sm bbjd-mb-4">
                            Create products/prices in your Stripe dashboard and enter the Price IDs here.
                            These are required for plan upgrades/downgrades.
                        </p>

                        <div class="bbjd-grid bbjd-grid-cols-1 md:bbjd-grid-cols-3 bbjd-gap-4">
                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    Monthly ($6.95/mo)
                                </label>
                                <input type="text"
                                       name="stripe_price_monthly"
                                       value="<?php echo esc_attr($settings['stripe_price_monthly']); ?>"
                                       placeholder="price_..."
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                            </div>

                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    Annual ($35/yr)
                                </label>
                                <input type="text"
                                       name="stripe_price_annual"
                                       value="<?php echo esc_attr($settings['stripe_price_annual']); ?>"
                                       placeholder="price_..."
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                            </div>

                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    Lifetime ($99)
                                </label>
                                <input type="text"
                                       name="stripe_price_lifetime"
                                       value="<?php echo esc_attr($settings['stripe_price_lifetime']); ?>"
                                       placeholder="price_..."
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                            </div>
                        </div>

                        <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-3">
                            Note: Without these, plan switching won't work. New signups still use inline price_data.
                        </p>
                    </div>

                    <!-- PayPal Settings -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <div class="bbjd-flex bbjd-items-center bbjd-justify-between bbjd-mb-4">
                            <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800">
                                PayPal
                            </h2>
                            <label class="bbjd-flex bbjd-items-center bbjd-space-x-2">
                                <input type="checkbox"
                                       name="paypal_sandbox"
                                       <?php checked($settings['paypal_sandbox']); ?>
                                       class="bbjd-rounded bbjd-border-gray-300 bbjd-text-yellow-500 focus:bbjd-ring-yellow-500">
                                <span class="bbjd-text-sm bbjd-font-medium bbjd-text-yellow-600">Sandbox Mode</span>
                            </label>
                        </div>

                        <div class="bbjd-grid bbjd-grid-cols-1 lg:bbjd-grid-cols-2 bbjd-gap-6">
                            <!-- Sandbox Keys -->
                            <div class="bbjd-space-y-4 bbjd-p-4 bbjd-bg-yellow-50 bbjd-rounded-lg bbjd-border bbjd-border-yellow-200">
                                <h3 class="bbjd-font-medium bbjd-text-yellow-800">Sandbox Keys</h3>

                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Client ID
                                    </label>
                                    <input type="text"
                                           name="paypal_sandbox_client_id"
                                           value="<?php echo esc_attr($settings['paypal_sandbox_client_id']); ?>"
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                                </div>

                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Client Secret
                                    </label>
                                    <input type="password"
                                           name="paypal_sandbox_client_secret"
                                           value="<?php echo esc_attr($settings['paypal_sandbox_client_secret']); ?>"
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                                </div>

                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Webhook ID
                                    </label>
                                    <input type="text"
                                           name="paypal_sandbox_webhook_id"
                                           value="<?php echo esc_attr($settings['paypal_sandbox_webhook_id']); ?>"
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                                </div>
                            </div>

                            <!-- Live Keys -->
                            <div class="bbjd-space-y-4 bbjd-p-4 bbjd-bg-green-50 bbjd-rounded-lg bbjd-border bbjd-border-green-200">
                                <h3 class="bbjd-font-medium bbjd-text-green-800">Live Keys</h3>

                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Client ID
                                    </label>
                                    <input type="text"
                                           name="paypal_live_client_id"
                                           value="<?php echo esc_attr($settings['paypal_live_client_id']); ?>"
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                                </div>

                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Client Secret
                                    </label>
                                    <input type="password"
                                           name="paypal_live_client_secret"
                                           value="<?php echo esc_attr($settings['paypal_live_client_secret']); ?>"
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                                </div>

                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Webhook ID
                                    </label>
                                    <input type="text"
                                           name="paypal_live_webhook_id"
                                           value="<?php echo esc_attr($settings['paypal_live_webhook_id']); ?>"
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                                </div>
                            </div>
                        </div>

                        <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-4">
                            Webhook URL: <code class="bbjd-bg-gray-100 bbjd-px-2 bbjd-py-1 bbjd-rounded"><?php echo esc_html(rest_url('bbjd/v1/billing/webhook/paypal')); ?></code>
                        </p>
                    </div>

                    <!-- PayPal Plan IDs -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-2">
                            PayPal Subscription Plans
                        </h2>
                        <p class="bbjd-text-gray-600 bbjd-text-sm bbjd-mb-4">
                            Create subscription plans in your PayPal dashboard and enter the Plan IDs here.
                            These are required for monthly/annual recurring payments via PayPal.
                        </p>

                        <div class="bbjd-grid bbjd-grid-cols-1 md:bbjd-grid-cols-2 bbjd-gap-4">
                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    Monthly Plan ID ($6.95/mo)
                                </label>
                                <input type="text"
                                       name="paypal_plan_monthly"
                                       value="<?php echo esc_attr($settings['paypal_plan_monthly']); ?>"
                                       placeholder="P-..."
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                            </div>

                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    Annual Plan ID ($35/yr)
                                </label>
                                <input type="text"
                                       name="paypal_plan_annual"
                                       value="<?php echo esc_attr($settings['paypal_plan_annual']); ?>"
                                       placeholder="P-..."
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                            </div>
                        </div>

                        <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-3">
                            Note: Lifetime payments use PayPal Orders (one-time), not subscriptions.
                        </p>
                    </div>

                    <!-- Google Analytics Settings -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-2">
                            Google Analytics (GA4)
                        </h2>
                        <p class="bbjd-text-gray-600 bbjd-text-sm bbjd-mb-4">
                            Configure GA4 Data API access for the admin analytics dashboard.
                            Requires a Google Cloud service account with GA4 Data API access.
                        </p>

                        <div class="bbjd-space-y-4">
                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    GA4 Property ID
                                </label>
                                <input type="text"
                                       name="ga4_property_id"
                                       value="<?php echo esc_attr($settings['ga4_property_id']); ?>"
                                       placeholder="properties/123456789"
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono">
                                <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-1">
                                    Format: <code>properties/XXXXXXXXX</code> — Find this in GA4 Admin → Property Settings (numeric ID, not the G- measurement ID)
                                </p>
                            </div>

                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    Service Account JSON
                                </label>
                                <textarea name="ga4_service_account_json"
                                          rows="6"
                                          placeholder='{"type": "service_account", "project_id": "...", ...}'
                                          class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono"><?php echo esc_textarea($settings['ga4_service_account_json']); ?></textarea>
                                <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-1">
                                    Paste the full JSON key file from Google Cloud Console. The service account email must be added as a viewer on the GA4 property.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bbjd-flex bbjd-justify-end">
                        <button type="submit"
                                class="bbjd-bg-primary500 bbjd-text-white bbjd-px-6 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors">
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render messages
     */
    private function renderMessages(string $message): void
    {
        if ($message === 'saved') {
            ?>
            <div class="bbjd-bg-green-100 bbjd-border-l-4 bbjd-border-green-500 bbjd-text-green-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
                <p>API settings saved successfully.</p>
            </div>
            <?php
        }
    }
}
