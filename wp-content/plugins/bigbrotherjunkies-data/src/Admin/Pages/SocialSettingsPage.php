<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

use BigBrotherJunkies\Data\FeedUpdates\BlueskyClient;
use BigBrotherJunkies\Data\FeedUpdates\FacebookClient;

/**
 * Social Media Settings Page
 *
 * Allows admins to configure Bluesky and Facebook credentials
 * for auto-posting feed updates.
 */
class SocialSettingsPage
{
    public const MENU_SLUG = 'bbjd-social-settings';
    private const OPTION_NAME = 'bbjd_social_settings';

    /**
     * Handle form submissions
     */
    public function handleActions(): void
    {
        if (!isset($_POST['bbjd_social_settings_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['bbjd_social_settings_nonce'], 'bbjd_social_settings')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = [
            'bluesky_handle' => sanitize_text_field($_POST['bluesky_handle'] ?? ''),
            'bluesky_app_password' => sanitize_text_field($_POST['bluesky_app_password'] ?? ''),
            'facebook_page_id' => sanitize_text_field($_POST['facebook_page_id'] ?? ''),
            'facebook_page_token' => sanitize_text_field($_POST['facebook_page_token'] ?? ''),
            'facebook_page_name' => sanitize_text_field($_POST['facebook_page_name'] ?? ''),
        ];

        // Keep existing password if not changed (shows as *******)
        $existing = get_option(self::OPTION_NAME, []);
        if ($settings['bluesky_app_password'] === str_repeat('*', 20)) {
            $settings['bluesky_app_password'] = $existing['bluesky_app_password'] ?? '';
        }
        if ($settings['facebook_page_token'] === str_repeat('*', 20)) {
            $settings['facebook_page_token'] = $existing['facebook_page_token'] ?? '';
        }

        update_option(self::OPTION_NAME, $settings);

        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&saved=1'));
        exit;
    }

    /**
     * Render the settings page
     */
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $settings = get_option(self::OPTION_NAME, []);
        $saved = isset($_GET['saved']);

        // Check connection status
        $blueskyStatus = $this->checkBlueskyStatus();
        $facebookStatus = $this->checkFacebookStatus();

        ?>
        <div class="wrap">
            <h1>Social Media Settings</h1>
            <p class="description">Configure credentials for auto-posting feed updates to social media.</p>

            <?php if ($saved): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Settings saved successfully.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('bbjd_social_settings', 'bbjd_social_settings_nonce'); ?>

                <!-- Bluesky Settings -->
                <div class="card" style="max-width: 600px; margin-top: 20px;">
                    <h2 style="margin-top: 0;">
                        <span style="color: #0085ff;">●</span> Bluesky
                        <?php if ($blueskyStatus['configured']): ?>
                            <span style="color: green; font-size: 14px; font-weight: normal;">✓ Configured</span>
                        <?php endif; ?>
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="bluesky_handle">Handle</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="bluesky_handle"
                                       name="bluesky_handle"
                                       value="<?php echo esc_attr($settings['bluesky_handle'] ?? ''); ?>"
                                       class="regular-text"
                                       placeholder="yourname.bsky.social">
                                <p class="description">Your Bluesky handle (e.g., yourname.bsky.social)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="bluesky_app_password">App Password</label>
                            </th>
                            <td>
                                <input type="password"
                                       id="bluesky_app_password"
                                       name="bluesky_app_password"
                                       value="<?php echo !empty($settings['bluesky_app_password']) ? str_repeat('*', 20) : ''; ?>"
                                       class="regular-text"
                                       placeholder="xxxx-xxxx-xxxx-xxxx">
                                <p class="description">
                                    Create an App Password in Bluesky: Settings → App Passwords → Add App Password
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Facebook Settings -->
                <div class="card" style="max-width: 600px; margin-top: 20px;">
                    <h2 style="margin-top: 0;">
                        <span style="color: #1877f2;">●</span> Facebook
                        <?php if ($facebookStatus['configured']): ?>
                            <span style="color: green; font-size: 14px; font-weight: normal;">
                                ✓ Connected to: <?php echo esc_html($facebookStatus['page_name'] ?? 'Page'); ?>
                            </span>
                        <?php endif; ?>
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="facebook_page_id">Page ID</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="facebook_page_id"
                                       name="facebook_page_id"
                                       value="<?php echo esc_attr($settings['facebook_page_id'] ?? ''); ?>"
                                       class="regular-text"
                                       placeholder="123456789">
                                <p class="description">Your Facebook Page ID (found in Page settings → About)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="facebook_page_name">Page Name</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="facebook_page_name"
                                       name="facebook_page_name"
                                       value="<?php echo esc_attr($settings['facebook_page_name'] ?? ''); ?>"
                                       class="regular-text"
                                       placeholder="Big Brother Junkies">
                                <p class="description">Your Facebook Page name (for display purposes)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="facebook_page_token">Page Access Token</label>
                            </th>
                            <td>
                                <input type="password"
                                       id="facebook_page_token"
                                       name="facebook_page_token"
                                       value="<?php echo !empty($settings['facebook_page_token']) ? str_repeat('*', 20) : ''; ?>"
                                       class="regular-text"
                                       placeholder="EAABs...">
                                <p class="description">
                                    Get this from <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a>
                                    with <code>pages_manage_posts</code> and <code>pages_read_engagement</code> permissions.
                                </p>
                            </td>
                        </tr>
                    </table>

                    <div style="background: #f0f6fc; padding: 12px; border-radius: 4px; margin-top: 10px;">
                        <strong>How to get a Facebook Page Access Token:</strong>
                        <ol style="margin: 10px 0 0 20px; padding: 0;">
                            <li>Go to <a href="https://developers.facebook.com/apps/" target="_blank">Facebook Developers</a> and create an app (or use existing)</li>
                            <li>In <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a>, select your app</li>
                            <li>Click "Get Token" → "Get Page Access Token"</li>
                            <li>Select your page and grant permissions</li>
                            <li>Copy the token and paste it above</li>
                            <li><strong>Important:</strong> For a long-lived token, exchange it in the Access Token Tool</li>
                        </ol>
                    </div>
                </div>

                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="Save Settings">
                </p>
            </form>

            <!-- Test Connection -->
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2 style="margin-top: 0;">Connection Status</h2>

                <table class="widefat" style="margin-top: 10px;">
                    <tbody>
                        <tr>
                            <td style="width: 120px;"><strong>Bluesky</strong></td>
                            <td>
                                <?php if ($blueskyStatus['configured']): ?>
                                    <span style="color: green;">✓ Ready</span>
                                    <span style="color: #666;"> — @<?php echo esc_html($settings['bluesky_handle'] ?? ''); ?></span>
                                <?php else: ?>
                                    <span style="color: #999;">Not configured</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Facebook</strong></td>
                            <td>
                                <?php if ($facebookStatus['configured']): ?>
                                    <?php if ($facebookStatus['valid']): ?>
                                        <span style="color: green;">✓ Connected</span>
                                        <span style="color: #666;"> — <?php echo esc_html($facebookStatus['page_name'] ?? 'Page'); ?></span>
                                    <?php else: ?>
                                        <span style="color: red;">✗ Token invalid</span>
                                        <span style="color: #666;"> — <?php echo esc_html($facebookStatus['error'] ?? ''); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #999;">Not configured</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Check Bluesky configuration status
     */
    private function checkBlueskyStatus(): array
    {
        $client = new BlueskyClient();

        return [
            'configured' => $client->isConfigured(),
        ];
    }

    /**
     * Check Facebook configuration and token validity
     */
    private function checkFacebookStatus(): array
    {
        $settings = get_option(self::OPTION_NAME, []);
        $client = new FacebookClient();

        if (!$client->isConfigured()) {
            return [
                'configured' => false,
                'valid' => false,
            ];
        }

        // Verify token (only if configured)
        $result = $client->verifyToken();

        return [
            'configured' => true,
            'valid' => $result['valid'] ?? false,
            'page_name' => $result['page_name'] ?? $settings['facebook_page_name'] ?? null,
            'error' => $result['error'] ?? null,
        ];
    }
}
