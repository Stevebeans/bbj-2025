<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Push\PushSubscriptionRepository;
use BigBrotherJunkies\Data\Push\VapidKeys;

/**
 * Web push subscription endpoints.
 *   GET  /bbjd/v1/push/key
 *   POST /bbjd/v1/push/subscribe
 *   POST /bbjd/v1/push/unsubscribe
 */
class PushRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/push/key', [
            'methods' => 'GET', 'callback' => [$this, 'key'], 'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NAMESPACE, '/push/subscribe', [
            'methods' => 'POST', 'callback' => [$this, 'subscribe'], 'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NAMESPACE, '/push/unsubscribe', [
            'methods' => 'POST', 'callback' => [$this, 'unsubscribe'], 'permission_callback' => '__return_true',
        ]);
    }

    public function key(): \WP_REST_Response
    {
        return new \WP_REST_Response(['public_key' => VapidKeys::publicKey()], 200);
    }

    public function subscribe(\WP_REST_Request $req): \WP_REST_Response
    {
        $sub = $req->get_json_params();
        $endpoint = $sub['endpoint'] ?? '';
        $p256dh = $sub['keys']['p256dh'] ?? '';
        $auth = $sub['keys']['auth'] ?? '';
        if (!$endpoint || !$p256dh || !$auth) {
            return new \WP_REST_Response(['error' => 'invalid_subscription'], 400);
        }
        $userId = get_current_user_id() ?: null;
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 255) : null;
        $id = PushSubscriptionRepository::upsert($endpoint, $p256dh, $auth, $userId, $ua);
        return new \WP_REST_Response(['ok' => true, 'id' => $id], 200);
    }

    public function unsubscribe(\WP_REST_Request $req): \WP_REST_Response
    {
        $endpoint = $req->get_json_params()['endpoint'] ?? '';
        if ($endpoint) {
            PushSubscriptionRepository::deleteByEndpoint($endpoint);
        }
        return new \WP_REST_Response(['ok' => true], 200);
    }
}
