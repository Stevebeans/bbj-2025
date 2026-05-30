<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Email\WelcomeEmailService;
use BigBrotherJunkies\Data\Permissions\PermissionChecker;

class WelcomeEmailRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('bbjd/v1', '/welcome-emails', [
            'methods' => 'GET',
            'callback' => [$this, 'get'],
            'permission_callback' => [$this, 'canManage'],
        ]);
        register_rest_route('bbjd/v1', '/welcome-emails', [
            'methods' => 'POST',
            'callback' => [$this, 'save'],
            'permission_callback' => [$this, 'canManage'],
        ]);
        register_rest_route('bbjd/v1', '/welcome-emails/test', [
            'methods' => 'POST',
            'callback' => [$this, 'sendTest'],
            'permission_callback' => [$this, 'canManage'],
        ]);
        register_rest_route('bbjd/v1', '/account/confirm', [
            'methods' => 'POST',
            'callback' => [$this, 'confirmAccount'],
            'permission_callback' => '__return_true',
            'args' => ['token' => ['required' => true, 'type' => 'string']],
        ]);
    }

    /**
     * Permission: same gate the other admin-settings routes use.
     * Mirrors AdminRoutes::checkAdminSettingsAccess() — the Mailing admin
     * tab is gated by the 'admin_settings' feature.
     */
    public function canManage(): bool
    {
        return PermissionChecker::userCan('admin_settings');
    }

    public function get(): \WP_REST_Response
    {
        return new \WP_REST_Response(WelcomeEmailService::getTemplates(), 200);
    }

    public function save(\WP_REST_Request $req): \WP_REST_Response
    {
        $body = $req->get_json_params();
        $conf = $body['confirmation']['body_html'] ?? '';
        if (strpos($conf, '{{confirm_url}}') === false) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Confirmation email must include {{confirm_url}}.'], 400);
        }
        $clean = WelcomeEmailService::defaults();
        foreach (['confirmation', 'welcome', 'premium'] as $k) {
            if (isset($body[$k]['subject'])) {
                $clean[$k]['subject'] = sanitize_text_field($body[$k]['subject']);
            }
            if (isset($body[$k]['body_html'])) {
                $clean[$k]['body_html'] = wp_kses_post($body[$k]['body_html']);
            }
        }
        if (isset($body['welcome_newsletter_fragment'])) {
            $clean['welcome_newsletter_fragment'] = wp_kses_post($body['welcome_newsletter_fragment']);
        }
        WelcomeEmailService::saveTemplates($clean);
        return new \WP_REST_Response(['success' => true, 'templates' => WelcomeEmailService::getTemplates()], 200);
    }

    public function sendTest(\WP_REST_Request $req): \WP_REST_Response
    {
        $which = sanitize_text_field($req->get_json_params()['which'] ?? 'welcome');
        if (!in_array($which, ['confirmation', 'welcome', 'premium'], true)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Unknown template'], 400);
        }
        $u = wp_get_current_user();
        $sub = $which === 'premium'
            ? ['id' => 0, 'plan_type' => 'monthly', 'amount_cents' => 695, 'current_period_end' => null, 'processor' => 'stripe']
            : null;
        $rendered = WelcomeEmailService::renderFor($which, $u, $sub);
        $client = new \BigBrotherJunkies\Data\Email\ResendClient();
        if (!$client->isConfigured()) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Resend not configured'], 500);
        }
        $id = $client->send($u->user_email, '[TEST] ' . $rendered['subject'], $rendered['html'],
            [['name' => 'category', 'value' => 'welcome-test']]);
        return new \WP_REST_Response(['success' => (bool) $id,
            'message' => $id ? 'Test sent to ' . $u->user_email : 'Send failed'], $id ? 200 : 500);
    }

    /**
     * Confirm a user account from the emailed token, then double-opt-in + welcome.
     * Token scheme mirrors AuthRoutes::handleRegister (set in a later task).
     */
    public function confirmAccount(\WP_REST_Request $req): \WP_REST_Response
    {
        $token = sanitize_text_field($req->get_param('token'));
        $users = get_users([
            'meta_key' => 'bbjd_verification_token',
            'meta_value' => $token,
            'number' => 1,
        ]);
        if (empty($users)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Invalid or expired link.'], 400);
        }
        $user = $users[0];
        $expires = (int) get_user_meta($user->ID, 'bbjd_verification_expires', true);
        if ($expires && $expires < time()) {
            return new \WP_REST_Response(['success' => false, 'message' => 'This link has expired.'], 400);
        }

        update_user_meta($user->ID, 'bbjd_email_verified', true);
        delete_user_meta($user->ID, 'bbjd_verification_token');
        delete_user_meta($user->ID, 'bbjd_verification_expires');

        $wantsNewsletter = (bool) get_user_meta($user->ID, 'bbjd_pending_newsletter_optin', true);
        if ($wantsNewsletter) {
            try {
                (new \BigBrotherJunkies\Data\Email\EmailService())
                    ->subscribe($user->user_email, 'registration', ['post-notifications'], $user->ID);
            } catch (\Throwable $e) {
                error_log('[BBJ WelcomeEmail] newsletter subscribe failed: ' . $e->getMessage());
            }
        }
        delete_user_meta($user->ID, 'bbjd_pending_newsletter_optin');

        WelcomeEmailService::sendWelcome($user->ID, $wantsNewsletter);

        return new \WP_REST_Response(['success' => true, 'message' => 'Your email is confirmed — welcome!'], 200);
    }
}
