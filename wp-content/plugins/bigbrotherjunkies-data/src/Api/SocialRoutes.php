<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Social\SocialStore;
use BigBrotherJunkies\Data\Permissions\PermissionChecker;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST routes for the Bluesky Feed-Intel monitor (admin-only).
 *
 *  - GET  /social/config     current settings (api key masked)
 *  - POST /social/config     save settings + reschedule the poller
 *  - POST /social/poll       run one poll now (manual refresh)
 *  - GET  /social/posts      paged collected posts
 *  - GET  /social/summaries  stored summaries
 *  - POST /social/summarize  Task 3's summarizer (stubbed 501 here)
 *
 * Every route is gated by PermissionChecker::userCan('social_monitor'). The
 * admin page is client-fetched; no Revalidation / cache-tag / CF-purge calls.
 */
class SocialRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        // Poller callback + idempotent init-time scheduling.
        add_action(SocialStore::POLL_HOOK, [SocialStore::class, 'poll']);
        add_action('init', [SocialStore::class, 'ensureScheduled']);
    }

    public function checkAccess(): bool
    {
        return PermissionChecker::userCan('social_monitor');
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/social/config', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getConfig'],
                'permission_callback' => [$this, 'checkAccess'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'saveConfig'],
                'permission_callback' => [$this, 'checkAccess'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/social/poll', [
            'methods'             => 'POST',
            'callback'            => [$this, 'runPoll'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/social/posts', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPosts'],
            'permission_callback' => [$this, 'checkAccess'],
            'args'                => [
                'source'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'search'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'page'     => ['type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint'],
                'per_page' => ['type' => 'integer', 'default' => 50, 'sanitize_callback' => 'absint'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/social/summaries', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getSummaries'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/social/summarize', [
            'methods'             => 'POST',
            'callback'            => [$this, 'runSummarize'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);
    }

    public function getConfig(): WP_REST_Response
    {
        return new WP_REST_Response([
            'success'  => true,
            'settings' => SocialStore::settingsForApi(),
        ], 200);
    }

    public function saveConfig(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: [];
        SocialStore::saveSettings($params);
        SocialStore::reschedule();

        return new WP_REST_Response([
            'success'  => true,
            'settings' => SocialStore::settingsForApi(),
        ], 200);
    }

    public function runPoll(): WP_REST_Response
    {
        $result = SocialStore::poll();
        return new WP_REST_Response(array_merge(['success' => true], $result), 200);
    }

    public function getPosts(WP_REST_Request $request): WP_REST_Response
    {
        $result = SocialStore::posts([
            'source'   => $request->get_param('source'),
            'search'   => $request->get_param('search'),
            'page'     => (int) $request->get_param('page'),
            'per_page' => (int) $request->get_param('per_page'),
        ]);

        return new WP_REST_Response(array_merge(['success' => true], $result), 200);
    }

    public function getSummaries(): WP_REST_Response
    {
        return new WP_REST_Response([
            'success'   => true,
            'summaries' => SocialStore::summaries(20),
        ], 200);
    }

    public function runSummarize(): WP_REST_Response
    {
        // Task 3 replaces this with SummaryService::run().
        return new WP_REST_Response([
            'success' => false,
            'message' => 'summarizer lands in the next commit',
        ], 501);
    }
}
