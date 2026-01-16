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
     * Register settings
     */
    public function registerSettings(): void
    {
        // Register settings
        register_setting('bbjd_auth_settings', GoogleOAuth::OPTION_CLIENT_ID, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        // Add settings section
        add_settings_section(
            'bbjd_google_oauth_section',
            __('Google Sign-In', 'bigbrotherjunkies-data'),
            [$this, 'renderGoogleSection'],
            'bbjd-auth-settings'
        );

        // Add Client ID field
        add_settings_field(
            'bbjd_google_client_id',
            __('Google Client ID', 'bigbrotherjunkies-data'),
            [$this, 'renderClientIdField'],
            'bbjd-auth-settings',
            'bbjd_google_oauth_section'
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
}
