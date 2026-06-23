<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Bean\BeanUsage;

/**
 * Ask the Bean — tier + usage metering endpoints.
 *   GET  /bbjd/v1/bean/check    — current tier, cap, used, remaining, allowed
 *   POST /bbjd/v1/bean/consume  — record one message, return updated snapshot
 */
class BeanRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/bean/check', [
            'methods'             => 'GET',
            'callback'            => [$this, 'check'],
            'permission_callback' => [$this, 'loggedIn'],
        ]);

        register_rest_route(self::NAMESPACE, '/bean/consume', [
            'methods'             => 'POST',
            'callback'            => [$this, 'consume'],
            'permission_callback' => [$this, 'loggedIn'],
        ]);
    }

    public function loggedIn(): bool
    {
        return is_user_logged_in();
    }

    public function check(): \WP_REST_Response
    {
        return new \WP_REST_Response(BeanUsage::snapshot(get_current_user_id()), 200);
    }

    public function consume(): \WP_REST_Response
    {
        $userId = get_current_user_id();
        BeanUsage::increment($userId);
        return new \WP_REST_Response(BeanUsage::snapshot($userId), 200);
    }
}
