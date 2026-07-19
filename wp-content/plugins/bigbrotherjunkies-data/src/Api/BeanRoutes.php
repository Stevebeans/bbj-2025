<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Bean\BeanMemory;
use BigBrotherJunkies\Data\Bean\BeanSchema;
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

        register_rest_route(self::NAMESPACE, '/bean/prompts', [
            'methods'             => 'GET',
            'callback'            => [$this, 'promptsList'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route(self::NAMESPACE, '/bean/facts', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'factsGet'],
                'permission_callback' => [$this, 'loggedIn'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'factsSet'],
                'permission_callback' => [$this, 'isAdmin'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/bean/memory', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'memoryGet'],
                'permission_callback' => [$this, 'loggedIn'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'memorySet'],
                'permission_callback' => [$this, 'loggedIn'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'memoryClear'],
                'permission_callback' => [$this, 'loggedIn'],
            ],
        ]);
    }

    public function loggedIn(): bool
    {
        return is_user_logged_in();
    }

    public function isAdmin(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Shared fact sheet: read by the chat route on every turn (all tiers).
     * canEdit tells the chat route whether THIS user may trigger the
     * conversational "save that to memory for others" write path.
     */
    public function factsGet(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'facts'   => BeanMemory::getShared(),
            'canEdit' => current_user_can('manage_options'),
        ], 200);
    }

    public function factsSet(\WP_REST_Request $request): \WP_REST_Response
    {
        $facts = sanitize_textarea_field((string) $request->get_param('facts'));
        BeanMemory::setShared($facts);
        return new \WP_REST_Response(['ok' => true, 'facts' => BeanMemory::getShared()], 200);
    }

    public function check(): \WP_REST_Response
    {
        return new \WP_REST_Response(BeanUsage::snapshot(get_current_user_id()), 200);
    }

    public function consume(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        BeanUsage::increment($userId);

        // Log the question (admin prompt viewer; questions only, never
        // answers). Best-effort: a logging hiccup must never block a chat.
        $question = trim((string) $request->get_param('question'));
        if ($question !== '') {
            global $wpdb;
            $wpdb->insert(BeanSchema::table(BeanSchema::TABLE_PROMPTS), [
                'user_id'  => $userId,
                'question' => mb_substr($question, 0, 2000),
            ]);
        }

        return new \WP_REST_Response(BeanUsage::snapshot($userId), 200);
    }

    /** Admin-only: recent user questions, newest first. */
    public function promptsList(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $page    = max(1, (int) $request->get_param('page'));
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;
        $t       = BeanSchema::table(BeanSchema::TABLE_PROMPTS);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.user_id, p.question, p.created_at, u.display_name
               FROM {$t} p LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
              ORDER BY p.id DESC LIMIT %d OFFSET %d",
            $perPage,
            $offset
        ), ARRAY_A) ?: [];
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}");

        return new \WP_REST_Response(['prompts' => $rows, 'total' => $total], 200);
    }

    public function memoryGet(): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $tier = BeanUsage::tierForUser($userId);
        $summary = $tier['memory'] ? BeanMemory::get($userId) : '';
        return new \WP_REST_Response(['summary' => $summary], 200);
    }

    public function memorySet(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $tier = BeanUsage::tierForUser($userId);
        if (!$tier['memory']) {
            return new \WP_REST_Response(['ok' => false], 403);
        }
        $summary = sanitize_textarea_field((string) $request->get_param('summary'));
        BeanMemory::set($userId, $summary);
        return new \WP_REST_Response(['ok' => true], 200);
    }

    public function memoryClear(): \WP_REST_Response
    {
        BeanMemory::clear(get_current_user_id());
        return new \WP_REST_Response(['ok' => true], 200);
    }
}
