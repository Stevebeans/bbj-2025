<?php

namespace BigBrotherJunkies\Data\Auth;

/**
 * Admin settings page for authentication configuration
 */
class AdminSettings
{
    /**
     * Initialize admin settings
     */
    public function init(): void
    {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    /**
     * Add settings page to admin menu
     */
    public function addSettingsPage(): void
    {
        add_options_page(
            __('Authentication Settings', 'bigbrotherjunkies-data'),
            __('Auth Settings', 'bigbrotherjunkies-data'),
            'manage_options',
            'bbjd-auth-settings',
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Option keys
     */
    public const OPTION_GIPHY_API_KEY = 'bbj_giphy_api_key';
    public const OPTION_RECAPTCHA_SITE_KEY = 'bbj_recaptcha_site_key';
    public const OPTION_RECAPTCHA_SECRET_KEY = 'bbj_recaptcha_secret_key';

    /**
     * Register settings
     */
    public function registerSettings(): void
    {
        // Register Google settings
        register_setting('bbjd_auth_settings', GoogleOAuth::OPTION_CLIENT_ID, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        // Register Giphy settings
        register_setting('bbjd_auth_settings', self::OPTION_GIPHY_API_KEY, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        // Register reCAPTCHA settings
        register_setting('bbjd_auth_settings', self::OPTION_RECAPTCHA_SITE_KEY, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('bbjd_auth_settings', self::OPTION_RECAPTCHA_SECRET_KEY, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        // Google OAuth section
        add_settings_section(
            'bbjd_google_oauth_section',
            __('Google Sign-In', 'bigbrotherjunkies-data'),
            [$this, 'renderGoogleSection'],
            'bbjd-auth-settings'
        );

        add_settings_field(
            'bbjd_google_client_id',
            __('Google Client ID', 'bigbrotherjunkies-data'),
            [$this, 'renderClientIdField'],
            'bbjd-auth-settings',
            'bbjd_google_oauth_section'
        );

        // reCAPTCHA section
        add_settings_section(
            'bbjd_recaptcha_section',
            __('reCAPTCHA v3', 'bigbrotherjunkies-data'),
            [$this, 'renderRecaptchaSection'],
            'bbjd-auth-settings'
        );

        add_settings_field(
            'bbjd_recaptcha_site_key',
            __('Site Key', 'bigbrotherjunkies-data'),
            [$this, 'renderRecaptchaSiteKeyField'],
            'bbjd-auth-settings',
            'bbjd_recaptcha_section'
        );

        add_settings_field(
            'bbjd_recaptcha_secret_key',
            __('Secret Key', 'bigbrotherjunkies-data'),
            [$this, 'renderRecaptchaSecretKeyField'],
            'bbjd-auth-settings',
            'bbjd_recaptcha_section'
        );

        // Giphy section
        add_settings_section(
            'bbjd_giphy_section',
            __('Giphy API', 'bigbrotherjunkies-data'),
            [$this, 'renderGiphySection'],
            'bbjd-auth-settings'
        );

        add_settings_field(
            'bbjd_giphy_api_key',
            __('Giphy API Key', 'bigbrotherjunkies-data'),
            [$this, 'renderGiphyApiKeyField'],
            'bbjd-auth-settings',
            'bbjd_giphy_section'
        );
    }

    /**
     * Render the settings page
     */
    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if settings were updated
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'bbjd_auth_messages',
                'bbjd_auth_message',
                __('Settings saved.', 'bigbrotherjunkies-data'),
                'updated'
            );
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('bbjd_auth_messages'); ?>

            <form action="options.php" method="post">
                <?php
                settings_fields('bbjd_auth_settings');
                do_settings_sections('bbjd-auth-settings');
                submit_button(__('Save Settings', 'bigbrotherjunkies-data'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the Google OAuth section description
     */
    public function renderGoogleSection(): void
    {
        ?>
        <p><?php esc_html_e('Configure Google Sign-In for your login and registration forms.', 'bigbrotherjunkies-data'); ?></p>
        <p><strong><?php esc_html_e('Setup Instructions:', 'bigbrotherjunkies-data'); ?></strong></p>
        <ol style="margin-left: 20px; list-style: decimal;">
            <li><?php esc_html_e('Go to', 'bigbrotherjunkies-data'); ?> <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a></li>
            <li><?php esc_html_e('Create a new project or select existing one', 'bigbrotherjunkies-data'); ?></li>
            <li><?php esc_html_e('Go to "Credentials" and click "Create Credentials" > "OAuth client ID"', 'bigbrotherjunkies-data'); ?></li>
            <li><?php esc_html_e('Select "Web application" as the application type', 'bigbrotherjunkies-data'); ?></li>
            <li><?php esc_html_e('Add authorized JavaScript origins:', 'bigbrotherjunkies-data'); ?>
                <br><code><?php echo esc_html(home_url()); ?></code>
            </li>
            <li><?php esc_html_e('Copy the Client ID and paste it below', 'bigbrotherjunkies-data'); ?></li>
        </ol>
        <?php
    }

    /**
     * Render the Client ID field
     */
    public function renderClientIdField(): void
    {
        $clientId = get_option(GoogleOAuth::OPTION_CLIENT_ID, '');
        ?>
        <input
            type="text"
            id="bbjd_google_client_id"
            name="<?php echo esc_attr(GoogleOAuth::OPTION_CLIENT_ID); ?>"
            value="<?php echo esc_attr($clientId); ?>"
            class="regular-text"
            placeholder="123456789-abcdefg.apps.googleusercontent.com"
        >
        <p class="description">
            <?php esc_html_e('Enter your Google OAuth Client ID. It should end with ".apps.googleusercontent.com"', 'bigbrotherjunkies-data'); ?>
        </p>
        <?php if (!empty($clientId)): ?>
            <p class="description" style="color: green;">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e('Google Sign-In is configured and active.', 'bigbrotherjunkies-data'); ?>
            </p>
        <?php else: ?>
            <p class="description" style="color: #d63638;">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e('Google Sign-In is not configured. The Google button will be hidden on forms.', 'bigbrotherjunkies-data'); ?>
            </p>
        <?php endif;
    }

    /**
     * Render the reCAPTCHA section description
     */
    public function renderRecaptchaSection(): void
    {
        ?>
        <p><?php esc_html_e('Configure reCAPTCHA v3 to protect registration forms from bots.', 'bigbrotherjunkies-data'); ?></p>
        <p><strong><?php esc_html_e('Setup Instructions:', 'bigbrotherjunkies-data'); ?></strong></p>
        <ol style="margin-left: 20px; list-style: decimal;">
            <li><?php esc_html_e('Go to', 'bigbrotherjunkies-data'); ?> <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin</a></li>
            <li><?php esc_html_e('Register a new site with reCAPTCHA v3', 'bigbrotherjunkies-data'); ?></li>
            <li><?php esc_html_e('Add your domain:', 'bigbrotherjunkies-data'); ?> <code><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></code></li>
            <li><?php esc_html_e('Copy both keys below', 'bigbrotherjunkies-data'); ?></li>
        </ol>
        <?php
    }

    /**
     * Render the reCAPTCHA site key field
     */
    public function renderRecaptchaSiteKeyField(): void
    {
        $siteKey = get_option(self::OPTION_RECAPTCHA_SITE_KEY, '');
        ?>
        <input
            type="text"
            id="bbjd_recaptcha_site_key"
            name="<?php echo esc_attr(self::OPTION_RECAPTCHA_SITE_KEY); ?>"
            value="<?php echo esc_attr($siteKey); ?>"
            class="regular-text"
            placeholder="6Le..."
        >
        <p class="description">
            <?php esc_html_e('The public site key (used in frontend).', 'bigbrotherjunkies-data'); ?>
        </p>
        <?php
    }

    /**
     * Render the reCAPTCHA secret key field
     */
    public function renderRecaptchaSecretKeyField(): void
    {
        $secretKey = get_option(self::OPTION_RECAPTCHA_SECRET_KEY, '');
        ?>
        <input
            type="password"
            id="bbjd_recaptcha_secret_key"
            name="<?php echo esc_attr(self::OPTION_RECAPTCHA_SECRET_KEY); ?>"
            value="<?php echo esc_attr($secretKey); ?>"
            class="regular-text"
            placeholder="6Le..."
        >
        <p class="description">
            <?php esc_html_e('The secret key (used server-side for verification).', 'bigbrotherjunkies-data'); ?>
        </p>
        <?php
        $siteKey = get_option(self::OPTION_RECAPTCHA_SITE_KEY, '');
        if (!empty($siteKey) && !empty($secretKey)): ?>
            <p class="description" style="color: green;">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e('reCAPTCHA is configured and active.', 'bigbrotherjunkies-data'); ?>
            </p>
        <?php elseif (empty($siteKey) && empty($secretKey)): ?>
            <p class="description" style="color: #d63638;">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e('reCAPTCHA is not configured. Registration forms will not have bot protection.', 'bigbrotherjunkies-data'); ?>
            </p>
        <?php else: ?>
            <p class="description" style="color: #dba617;">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e('reCAPTCHA is partially configured. Both keys are required.', 'bigbrotherjunkies-data'); ?>
            </p>
        <?php endif;
    }

    /**
     * Render the Giphy section description
     */
    public function renderGiphySection(): void
    {
        ?>
        <p><?php esc_html_e('Configure Giphy API for GIF picker in comments.', 'bigbrotherjunkies-data'); ?></p>
        <p><strong><?php esc_html_e('Setup Instructions:', 'bigbrotherjunkies-data'); ?></strong></p>
        <ol style="margin-left: 20px; list-style: decimal;">
            <li><?php esc_html_e('Go to', 'bigbrotherjunkies-data'); ?> <a href="https://developers.giphy.com/" target="_blank">Giphy Developers</a></li>
            <li><?php esc_html_e('Create an account and click "Create an App"', 'bigbrotherjunkies-data'); ?></li>
            <li><?php esc_html_e('Select "API" (not SDK) for the app type', 'bigbrotherjunkies-data'); ?></li>
            <li><?php esc_html_e('Copy the API key below', 'bigbrotherjunkies-data'); ?></li>
        </ol>
        <p class="description"><?php esc_html_e('Free tier allows 100 requests/hour which is plenty for comment GIFs.', 'bigbrotherjunkies-data'); ?></p>
        <?php
    }

    /**
     * Render the Giphy API key field
     */
    public function renderGiphyApiKeyField(): void
    {
        $apiKey = get_option(self::OPTION_GIPHY_API_KEY, '');
        ?>
        <input
            type="password"
            id="bbjd_giphy_api_key"
            name="<?php echo esc_attr(self::OPTION_GIPHY_API_KEY); ?>"
            value="<?php echo esc_attr($apiKey); ?>"
            class="regular-text"
            placeholder="Enter your Giphy API key"
        >
        <p class="description">
            <?php esc_html_e('Your Giphy API key. Kept server-side, never exposed to frontend.', 'bigbrotherjunkies-data'); ?>
        </p>
        <?php if (!empty($apiKey)): ?>
            <p class="description" style="color: green;">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e('Giphy API is configured. GIF picker will be available in comments.', 'bigbrotherjunkies-data'); ?>
            </p>
        <?php else: ?>
            <p class="description" style="color: #dba617;">
                <span class="dashicons dashicons-info"></span>
                <?php esc_html_e('Giphy not configured. GIF picker will be hidden in comments.', 'bigbrotherjunkies-data'); ?>
            </p>
        <?php endif;
    }
}
