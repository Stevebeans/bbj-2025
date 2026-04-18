<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Email\EmailSchema;
use BigBrotherJunkies\Data\Email\EmailService;
use BigBrotherJunkies\Data\Email\EmailSender;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API routes for the BBJ Email System
 *
 * Handles subscribe/confirm/unsubscribe flows, preference management,
 * Resend webhook processing, admin stats, and subscriber management.
 */
class EmailRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        // Public: subscribe
        register_rest_route(self::NAMESPACE, '/email/subscribe', [
            'methods' => 'POST',
            'callback' => [$this, 'subscribe'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'lists' => [
                    'required' => false,
                    'type' => 'array',
                    'default' => ['post-notifications'],
                ],
            ],
        ]);

        // Public: confirm subscription via token
        register_rest_route(self::NAMESPACE, '/email/confirm/(?P<token>[a-f0-9]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'confirm'],
            'permission_callback' => '__return_true',
            'args' => [
                'token' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Public: unsubscribe via email + HMAC token
        register_rest_route(self::NAMESPACE, '/email/unsubscribe', [
            'methods' => 'GET',
            'callback' => [$this, 'unsubscribe'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'token' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Authenticated: get/update email preferences
        register_rest_route(self::NAMESPACE, '/email/preferences', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getPreferences'],
                'permission_callback' => [$this, 'requireAuth'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updatePreferences'],
                'permission_callback' => [$this, 'requireAuth'],
                'args' => [
                    'lists' => [
                        'required' => true,
                        'type' => 'array',
                    ],
                ],
            ],
        ]);

        // Webhook: Resend event processing
        register_rest_route(self::NAMESPACE, '/email/webhook/resend', [
            'methods' => 'POST',
            'callback' => [$this, 'handleWebhook'],
            'permission_callback' => '__return_true',
        ]);

        // Admin: email system stats
        register_rest_route(self::NAMESPACE, '/email/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getStats'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);

        // Admin: list subscribers
        register_rest_route(self::NAMESPACE, '/email/subscribers', [
            'methods' => 'GET',
            'callback' => [$this, 'getSubscribers'],
            'permission_callback' => [$this, 'requireAdmin'],
            'args' => [
                'list' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'post-notifications',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'page' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                ],
                'per_page' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 20,
                ],
                'status' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'search' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Admin: import subscribers
        register_rest_route(self::NAMESPACE, '/email/subscribers/import', [
            'methods' => 'POST',
            'callback' => [$this, 'importSubscribers'],
            'permission_callback' => [$this, 'requireAdmin'],
            'args' => [
                'emails' => [
                    'required' => true,
                    'type' => 'array',
                ],
                'list' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'post-notifications',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Admin: send re-confirmation emails
        register_rest_route(self::NAMESPACE, '/email/reconfirm', [
            'methods' => 'POST',
            'callback' => [$this, 'sendReconfirmation'],
            'permission_callback' => [$this, 'requireAdmin'],
            'args' => [
                'subscriber_ids' => [
                    'required' => true,
                    'type' => 'array',
                ],
            ],
        ]);

        // Admin: send test email
        register_rest_route(self::NAMESPACE, '/email/test', [
            'methods' => 'POST',
            'callback' => [$this, 'sendTestEmail'],
            'permission_callback' => [$this, 'requireAdmin'],
            'args' => [
                'email' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);

        // Admin: get all lists with subscriber counts
        register_rest_route(self::NAMESPACE, '/email/lists', [
            'methods' => 'GET',
            'callback' => [$this, 'getLists'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);
    }

    /**
     * Subscribe an email address
     */
    public function subscribe(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        $email = sanitize_email($params['email'] ?? '');
        $lists = $params['lists'] ?? ['post-notifications'];

        if (!is_email($email)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Please provide a valid email address.',
            ], 400);
        }

        $userId = is_user_logged_in() ? get_current_user_id() : null;

        $service = new EmailService();
        $result = $service->subscribe($email, 'widget', $lists, $userId);

        $messages = [
            'subscribed' => 'You\'re subscribed! You\'ll receive notifications for new posts.',
            'already_subscribed' => 'You\'re already subscribed.',
            'pending' => 'Almost there! Check your email to confirm your subscription.',
            'resubscribed_pending' => 'Welcome back! Check your email to re-confirm your subscription.',
        ];

        return new WP_REST_Response([
            'success' => true,
            'status' => $result['status'],
            'message' => $messages[$result['status']] ?? 'Subscription processed.',
            'subscriber_id' => $result['subscriber_id'],
        ], 200);
    }

    public function confirm(WP_REST_Request $request): WP_REST_Response
    {
        $token = $request->get_param('token');

        $service = new EmailService();
        $confirmed = $service->confirm($token);

        if (!$confirmed) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid or expired confirmation link.',
            ], 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Your subscription is confirmed! You\'ll now receive email updates.',
        ], 200);
    }

    public function unsubscribe(WP_REST_Request $request): WP_REST_Response
    {
        $email = $request->get_param('email');
        $token = $request->get_param('token');

        $service = new EmailService();
        $result = $service->unsubscribe($email, $token);

        if (!$result) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid unsubscribe link.',
            ], 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'You\'ve been unsubscribed. Sorry to see you go!',
        ], 200);
    }

    /**
     * Get current user's email preferences (list memberships)
     */
    public function getPreferences(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $userId = get_current_user_id();
        $user = wp_get_current_user();

        $subTable = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);
        $lsTable = EmailSchema::table(EmailSchema::TABLE_LIST_SUBSCRIBERS);
        $listTable = EmailSchema::table(EmailSchema::TABLE_LISTS);

        // Find subscriber by user_id or email
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, status FROM {$subTable} WHERE user_id = %d OR email = %s LIMIT 1",
            $userId,
            $user->user_email
        ));

        if (!$subscriber) {
            // Not subscribed yet — return all lists with subscribed=false
            $allLists = $wpdb->get_results(
                "SELECT slug, name, description FROM {$listTable} WHERE is_active = 1 ORDER BY id ASC",
                ARRAY_A
            );

            return new WP_REST_Response([
                'success' => true,
                'subscribed' => false,
                'lists' => array_map(function ($list) {
                    return array_merge($list, ['subscribed' => false]);
                }, $allLists ?: []),
            ], 200);
        }

        // Get all active lists and mark which ones this subscriber is on
        $allLists = $wpdb->get_results(
            "SELECT l.id, l.slug, l.name, l.description
             FROM {$listTable} l
             WHERE l.is_active = 1
             ORDER BY l.id ASC",
            ARRAY_A
        );

        $subscribedListIds = $wpdb->get_col($wpdb->prepare(
            "SELECT list_id FROM {$lsTable} WHERE subscriber_id = %d",
            $subscriber->id
        ));

        $subscribedListIds = array_map('intval', $subscribedListIds);

        $lists = array_map(function ($list) use ($subscribedListIds) {
            return [
                'slug' => $list['slug'],
                'name' => $list['name'],
                'description' => $list['description'],
                'subscribed' => in_array((int) $list['id'], $subscribedListIds, true),
            ];
        }, $allLists ?: []);

        return new WP_REST_Response([
            'success' => true,
            'subscribed' => $subscriber->status === 'subscribed',
            'email' => $subscriber->email,
            'lists' => $lists,
        ], 200);
    }

    public function updatePreferences(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $userId = get_current_user_id();
        $user = wp_get_current_user();
        $params = $request->get_json_params();
        $selectedSlugs = $params['lists'] ?? [];

        $subTable = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);
        $lsTable = EmailSchema::table(EmailSchema::TABLE_LIST_SUBSCRIBERS);
        $listTable = EmailSchema::table(EmailSchema::TABLE_LISTS);

        // Find or create subscriber
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$subTable} WHERE user_id = %d OR email = %s LIMIT 1",
            $userId,
            $user->user_email
        ));

        if (!$subscriber) {
            // Create subscriber (auto-confirmed since logged in)
            $now = current_time('mysql');
            $wpdb->insert($subTable, [
                'user_id' => $userId,
                'email' => $user->user_email,
                'status' => 'subscribed',
                'source' => 'preferences',
                'subscribed_at' => $now,
                'confirmed_at' => $now,
                'created_at' => $now,
            ]);
            $subscriberId = (int) $wpdb->insert_id;
        } else {
            $subscriberId = (int) $subscriber->id;
        }

        // Remove all existing list memberships
        $wpdb->delete($lsTable, ['subscriber_id' => $subscriberId]);

        // Add selected lists
        if (!empty($selectedSlugs)) {
            $now = current_time('mysql');
            foreach ($selectedSlugs as $slug) {
                $slug = sanitize_text_field($slug);
                $listId = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$listTable} WHERE slug = %s AND is_active = 1",
                    $slug
                ));

                if ($listId) {
                    $wpdb->insert($lsTable, [
                        'subscriber_id' => $subscriberId,
                        'list_id' => (int) $listId,
                        'subscribed_at' => $now,
                    ]);
                }
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Email preferences updated.',
        ], 200);
    }

    /**
     * Handle Resend webhook events (Svix signature verification)
     */
    public function handleWebhook(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_body();
        $svixId = $request->get_header('svix-id');
        $svixTimestamp = $request->get_header('svix-timestamp');
        $svixSignature = $request->get_header('svix-signature');

        // Verify required headers
        if (!$svixId || !$svixTimestamp || !$svixSignature) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Missing webhook signature headers.',
            ], 400);
        }

        // Verify Svix signature
        $settings = get_option('bbjd_email_settings', []);
        $webhookSecret = $settings['resend_webhook_secret'] ?? '';

        if (empty($webhookSecret)) {
            error_log('[BBJ Email] Webhook secret not configured');
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Webhook not configured.',
            ], 500);
        }

        // Strip "whsec_" prefix if present
        $secretKey = $webhookSecret;
        if (str_starts_with($secretKey, 'whsec_')) {
            $secretKey = substr($secretKey, 6);
        }

        $signedContent = "{$svixId}.{$svixTimestamp}.{$body}";
        $expectedSignature = base64_encode(
            hash_hmac('sha256', $signedContent, base64_decode($secretKey), true)
        );

        // Svix sends multiple signatures separated by spaces (v1,signature)
        $signatures = explode(' ', $svixSignature);
        $verified = false;

        foreach ($signatures as $sig) {
            // Each signature is in format "v1,base64signature"
            $parts = explode(',', $sig, 2);
            if (count($parts) === 2 && hash_equals($expectedSignature, $parts[1])) {
                $verified = true;
                break;
            }
        }

        if (!$verified) {
            error_log('[BBJ Email] Webhook signature verification failed');
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid signature.',
            ], 401);
        }

        // Parse payload
        $payload = json_decode($body, true);

        if (!$payload) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid JSON payload.',
            ], 400);
        }

        $eventType = $payload['type'] ?? '';
        $data = $payload['data'] ?? [];
        $emailId = $data['email_id'] ?? '';

        if (empty($emailId)) {
            // Nothing to update, but acknowledge the webhook
            return new WP_REST_Response(['success' => true], 200);
        }

        $this->processWebhookEvent($eventType, $emailId, $data);

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Get email system stats (subscriber counts, send metrics, engagement)
     */
    public function getStats(WP_REST_Request $request): WP_REST_Response
    {
        $service = new EmailService();

        return new WP_REST_Response([
            'success' => true,
            'stats' => $service->getStats(),
            'engagement' => $service->getEngagementScoring(),
            'recent_sends' => $service->getRecentSends(),
        ], 200);
    }

    public function getSubscribers(WP_REST_Request $request): WP_REST_Response
    {
        $listSlug = $request->get_param('list');
        $filters = [
            'page' => $request->get_param('page'),
            'per_page' => $request->get_param('per_page'),
            'status' => $request->get_param('status'),
            'search' => $request->get_param('search'),
        ];

        $filters = array_filter($filters, fn($v) => $v !== null);

        $service = new EmailService();
        $result = $service->getSubscribers($listSlug, $filters);

        return new WP_REST_Response([
            'success' => true,
            ...$result,
        ], 200);
    }

    public function importSubscribers(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        $emails = $params['emails'] ?? [];
        $listSlug = $params['list'] ?? 'post-notifications';

        if (empty($emails) || !is_array($emails)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No emails provided.',
            ], 400);
        }

        $service = new EmailService();
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($emails as $email) {
            $email = sanitize_email($email);

            if (!is_email($email)) {
                $skipped++;
                $errors[] = $email . ' (invalid)';
                continue;
            }

            $result = $service->subscribe($email, 'import', [$listSlug]);

            if ($result['status'] === 'already_subscribed') {
                $skipped++;
            } else {
                $imported++;
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => "Imported {$imported} subscribers, skipped {$skipped}.",
        ], 200);
    }

    public function sendReconfirmation(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        $subscriberIds = $params['subscriber_ids'] ?? [];

        if (empty($subscriberIds) || !is_array($subscriberIds)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No subscriber IDs provided.',
            ], 400);
        }

        $subscriberIds = array_map('intval', $subscriberIds);

        $sender = new EmailSender();
        $sent = $sender->sendReconfirmation($subscriberIds);

        return new WP_REST_Response([
            'success' => true,
            'sent' => $sent,
            'message' => "Sent {$sent} re-confirmation emails.",
        ], 200);
    }

    public function sendTestEmail(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        $email = sanitize_email($params['email'] ?? '');

        // Default to current user's email
        if (empty($email) || !is_email($email)) {
            $user = wp_get_current_user();
            $email = $user->user_email;
        }

        $sender = new EmailSender();
        $sent = $sender->sendTestEmail($email);

        if (!$sent) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to send test email. Check Resend API configuration.',
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => "Test email sent to {$email}.",
        ], 200);
    }

    public function getLists(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $listTable = EmailSchema::table(EmailSchema::TABLE_LISTS);
        $lsTable = EmailSchema::table(EmailSchema::TABLE_LIST_SUBSCRIBERS);
        $subTable = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);

        $lists = $wpdb->get_results(
            "SELECT
                l.id,
                l.slug,
                l.name,
                l.description,
                l.is_active,
                l.created_at,
                COUNT(CASE WHEN s.status = 'subscribed' THEN 1 END) AS subscriber_count
             FROM {$listTable} l
             LEFT JOIN {$lsTable} ls ON ls.list_id = l.id
             LEFT JOIN {$subTable} s ON s.id = ls.subscriber_id
             GROUP BY l.id
             ORDER BY l.id ASC",
            ARRAY_A
        );

        $lists = array_map(fn($list) => [
            ...$list,
            'id' => (int) $list['id'],
            'is_active' => (bool) $list['is_active'],
            'subscriber_count' => (int) $list['subscriber_count'],
        ], $lists ?: []);

        return new WP_REST_Response([
            'success' => true,
            'lists' => $lists,
        ], 200);
    }

    public function requireAuth(): bool
    {
        return is_user_logged_in();
    }

    public function requireAdmin(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Process a Resend webhook event and update the sends table
     */
    private function processWebhookEvent(string $eventType, string $emailId, array $data): void
    {
        global $wpdb;

        $sendsTable = EmailSchema::table(EmailSchema::TABLE_SENDS);
        $subTable = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);
        $now = current_time('mysql');

        // Find the send record by resend_id
        $send = $wpdb->get_row($wpdb->prepare(
            "SELECT id, subscriber_id FROM {$sendsTable} WHERE resend_id = %s",
            $emailId
        ));

        if (!$send) {
            // No matching send record — nothing to update
            return;
        }

        // Simple timestamp events: delivered, opened, clicked
        $timestampFields = [
            'email.delivered' => 'delivered_at',
            'email.opened'   => 'opened_at',
            'email.clicked'  => 'clicked_at',
        ];

        if (isset($timestampFields[$eventType])) {
            $wpdb->update($sendsTable, [
                $timestampFields[$eventType] => $now,
            ], ['id' => $send->id]);
            return;
        }

        if ($eventType === 'email.bounced') {
            $bounceType = ($data['bounce_type'] ?? '') === 'Permanent' ? 'hard' : 'soft';

            $wpdb->update($sendsTable, [
                'bounced_at' => $now,
                'bounce_type' => $bounceType,
            ], ['id' => $send->id]);

            if ($bounceType === 'hard') {
                $wpdb->update($subTable, [
                    'status' => 'unsubscribed',
                    'unsubscribed_at' => $now,
                ], ['id' => $send->subscriber_id]);
            }
            return;
        }

        if ($eventType === 'email.complained') {
            $wpdb->update($subTable, [
                'status' => 'unsubscribed',
                'unsubscribed_at' => $now,
            ], ['id' => $send->subscriber_id]);
        }
    }
}
