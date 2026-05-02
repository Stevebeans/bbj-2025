<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

/**
 * Email Settings page for BBJ Mailing
 */
class EmailSettingsPage
{
    public const MENU_SLUG = 'bbjd-mailing-settings';

    public function handleActions(): void
    {
        add_action('admin_post_bbjd_save_email_settings', [$this, 'handleSaveSettings']);
    }

    public function handleSaveSettings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_save_email_settings');

        $settings = [
            'resend_api_key' => sanitize_text_field($_POST['resend_api_key'] ?? ''),
            'email_from_name' => sanitize_text_field($_POST['email_from_name'] ?? 'Big Brother Junkies'),
            'email_from_address' => sanitize_email($_POST['email_from_address'] ?? ''),
            'email_reply_to' => sanitize_email($_POST['email_reply_to'] ?? ''),
            'resend_webhook_secret' => sanitize_text_field($_POST['resend_webhook_secret'] ?? ''),
            'confirmation_subject' => sanitize_text_field($_POST['confirmation_subject'] ?? ''),
            'frontend_url' => esc_url_raw($_POST['frontend_url'] ?? ''),
            'pause_sending' => !empty($_POST['pause_sending']),
            'dormant_threshold_days' => intval($_POST['dormant_threshold_days'] ?? 90),
            'unsubscribe_secret' => sanitize_text_field($_POST['unsubscribe_secret'] ?? ''),
        ];

        // If unsubscribe secret is empty, generate one
        if (empty($settings['unsubscribe_secret'])) {
            $existing = get_option('bbjd_email_settings', []);
            $settings['unsubscribe_secret'] = $existing['unsubscribe_secret'] ?? bin2hex(random_bytes(32));
        }

        update_option('bbjd_email_settings', $settings);

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'saved',
        ], admin_url('admin.php')));
        exit;
    }

    public function render(): void
    {
        $settings = get_option('bbjd_email_settings', []);
        $message = $_GET['message'] ?? '';
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6 bbjd-max-w-4xl">
                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-6">
                    Email Settings
                </h1>

                <?php $this->renderMessages($message); ?>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('bbjd_save_email_settings'); ?>
                    <input type="hidden" name="action" value="bbjd_save_email_settings">

                    <!-- Resend API Configuration -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                            Resend API
                        </h2>

                        <div class="bbjd-space-y-4">
                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    API Key
                                </label>
                                <input type="password"
                                       name="resend_api_key"
                                       value="<?php echo esc_attr($settings['resend_api_key'] ?? ''); ?>"
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono"
                                       placeholder="re_xxxxxxxxxx">
                                <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-1">
                                    Get your API key from <a href="https://resend.com/api-keys" target="_blank" class="bbjd-text-primary500 bbjd-underline">resend.com/api-keys</a>
                                </p>
                            </div>

                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    Webhook Signing Secret
                                </label>
                                <input type="password"
                                       name="resend_webhook_secret"
                                       value="<?php echo esc_attr($settings['resend_webhook_secret'] ?? ''); ?>"
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm bbjd-font-mono"
                                       placeholder="whsec_xxxxxxxxxx">
                                <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-1">
                                    From Resend dashboard → Webhooks. Used to verify incoming webhook signatures.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Sender Configuration -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                            Sender Details
                        </h2>

                        <div class="bbjd-grid bbjd-grid-cols-1 md:bbjd-grid-cols-2 bbjd-gap-4">
                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    From Name
                                </label>
                                <input type="text"
                                       name="email_from_name"
                                       value="<?php echo esc_attr($settings['email_from_name'] ?? 'Big Brother Junkies'); ?>"
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm">
                            </div>

                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    From Email
                                </label>
                                <input type="email"
                                       name="email_from_address"
                                       value="<?php echo esc_attr($settings['email_from_address'] ?? 'noreply@bigbrotherjunkies.com'); ?>"
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm">
                            </div>

                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    Reply-To Email
                                </label>
                                <input type="email"
                                       name="email_reply_to"
                                       value="<?php echo esc_attr($settings['email_reply_to'] ?? ''); ?>"
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm"
                                       placeholder="Optional">
                            </div>

                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    Frontend URL
                                </label>
                                <input type="url"
                                       name="frontend_url"
                                       value="<?php echo esc_attr($settings['frontend_url'] ?? 'https://bigbrotherjunkies.com'); ?>"
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm">
                                <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-1">
                                    Used for confirm/unsubscribe links in emails.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Confirmation Email -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                            Confirmation Email
                        </h2>

                        <div>
                            <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                Subject Line
                            </label>
                            <input type="text"
                                   name="confirmation_subject"
                                   value="<?php echo esc_attr($settings['confirmation_subject'] ?? 'Confirm your subscription to Big Brother Junkies'); ?>"
                                   class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm">
                        </div>
                    </div>

                    <!-- Sending Controls -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                            Sending Controls
                        </h2>

                        <div class="bbjd-space-y-4">
                            <div class="bbjd-flex bbjd-items-center bbjd-justify-between">
                                <div>
                                    <h3 class="bbjd-text-sm bbjd-font-medium bbjd-text-gray-700">Pause All Sending</h3>
                                    <p class="bbjd-text-xs bbjd-text-gray-500">
                                        When enabled, no emails will be sent (post notifications, confirmations, etc.)
                                    </p>
                                </div>
                                <label class="bbjd-relative bbjd-inline-flex bbjd-items-center bbjd-cursor-pointer bbjd-ml-4 bbjd-shrink-0">
                                    <input type="checkbox"
                                           name="pause_sending"
                                           value="1"
                                           <?php checked($settings['pause_sending'] ?? false); ?>
                                           class="bbjd-sr-only bbjd-peer">
                                    <div class="bbjd-w-11 bbjd-h-6 bbjd-bg-gray-300 bbjd-rounded-full peer-checked:bbjd-bg-red-500 bbjd-transition-colors after:bbjd-content-[''] after:bbjd-absolute after:bbjd-top-[2px] after:bbjd-left-[2px] after:bbjd-bg-white after:bbjd-rounded-full after:bbjd-h-5 after:bbjd-w-5 after:bbjd-transition-transform peer-checked:after:bbjd-translate-x-5"></div>
                                </label>
                            </div>

                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    Dormant Threshold (days)
                                </label>
                                <input type="number"
                                       name="dormant_threshold_days"
                                       value="<?php echo esc_attr($settings['dormant_threshold_days'] ?? 90); ?>"
                                       min="30" max="365"
                                       class="bbjd-w-32 bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm">
                                <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-1">
                                    Subscribers with no opens after this many days are flagged as dormant.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Unsubscribe Secret (hidden, auto-generated) -->
                    <input type="hidden" name="unsubscribe_secret" value="<?php echo esc_attr($settings['unsubscribe_secret'] ?? ''); ?>">

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

    private function renderMessages(string $message): void
    {
        if ($message === 'saved') {
            ?>
            <div class="bbjd-bg-green-100 bbjd-border-l-4 bbjd-border-green-500 bbjd-text-green-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
                <p>Settings saved successfully.</p>
            </div>
            <?php
        }
    }
}
