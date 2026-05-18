<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\LiveThread\LiveThreadState;
use BigBrotherJunkies\Data\Permissions\PermissionChecker;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Live Thread REST API Routes
 *
 * Endpoints:
 *   GET  /bbjd/v1/live-thread/current                        — public, returns active thread or null
 *   POST /bbjd/v1/live-thread/take-over                      — admin only, atomically opens a new thread
 *   POST /bbjd/v1/live-thread/{id}/close                     — admin only, closes a specific thread
 *   GET  /bbjd/v1/live-thread/{id}/updates-since?ts={unix}   — supporter only (Task 5 stub)
 *
 * Supporter check uses WP role: users with the `supporter` or `lifetime` role (assigned
 * by SubscriptionManager::assignSupporterRole() on Stripe/PayPal payment events) are granted
 * access. This mirrors the ad-free detection in AdContext.jsx which checks user_roles against
 * the configurable `supporter_roles` list (defaults: supporter, lifetime, administrator, editor).
 * We use the canonical paid roles only here — admins/editors use the admin endpoints instead.
 */
class LiveThreadRoutes
{
    /** WP roles that grant supporter-tier access to premium polling */
    private const SUPPORTER_ROLES = ['supporter', 'lifetime'];

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $ns = 'bbjd/v1';

        register_rest_route($ns, '/live-thread/current', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getCurrent'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/live-thread/take-over', [
            'methods'             => 'POST',
            'callback'            => [$this, 'takeOver'],
            'permission_callback' => [$this, 'checkAdminPermission'],
            'args' => [
                'new_post_id' => [
                    'required' => true,
                    'type'     => 'integer',
                ],
            ],
        ]);

        register_rest_route($ns, '/live-thread/(?P<id>\d+)/close', [
            'methods'             => 'POST',
            'callback'            => [$this, 'closeThread'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route($ns, '/live-thread/(?P<id>\d+)/updates-since', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getUpdatesSince'],
            'permission_callback' => [$this, 'checkSupporterPermission'],
            'args' => [
                'ts' => [
                    'required' => true,
                    'type'     => 'integer',
                ],
            ],
        ]);
    }

    /**
     * GET /bbjd/v1/live-thread/current
     * Public. Returns the active thread summary or null.
     */
    public function getCurrent(WP_REST_Request $req): WP_REST_Response
    {
        $post = LiveThreadState::getActivePost();
        if (!$post) {
            return new WP_REST_Response(null, 200);
        }

        $start = (int) get_post_meta($post->ID, LiveThreadState::META_LIVE_START, true);
        return new WP_REST_Response([
            'post_id'    => $post->ID,
            'title'      => get_the_title($post),
            'slug'       => $post->post_name,
            'started_at' => $start,
        ], 200);
    }

    /**
     * POST /bbjd/v1/live-thread/take-over
     * Admin only. Atomically closes the previous thread and opens a new one.
     */
    public function takeOver(WP_REST_Request $req)
    {
        $newPostId = (int) $req->get_param('new_post_id');
        if ($newPostId <= 0 || !get_post($newPostId)) {
            return new WP_Error('invalid_post', 'Post not found.', ['status' => 404]);
        }
        $previousId = LiveThreadState::openThread($newPostId);
        return new WP_REST_Response([
            'new_active'      => $newPostId,
            'closed_previous' => $previousId,
        ], 200);
    }

    /**
     * POST /bbjd/v1/live-thread/{id}/close
     * Admin only. Closes a specific thread.
     */
    public function closeThread(WP_REST_Request $req)
    {
        $id = (int) $req->get_param('id');
        if ($id <= 0 || !get_post($id)) {
            return new WP_Error('invalid_post', 'Post not found.', ['status' => 404]);
        }
        LiveThreadState::closeThread($id);
        return new WP_REST_Response(['closed' => $id], 200);
    }

    /**
     * GET /bbjd/v1/live-thread/{id}/updates-since?ts={unix}
     * Supporter only. Stub — full implementation in Task 5.
     */
    public function getUpdatesSince(WP_REST_Request $req)
    {
        // Stub — filled in Task 5
        return new WP_REST_Response(['updates' => []], 200);
    }

    /**
     * Permission: admin-tier features (same gate as feed updater / editor pane).
     * Uses PermissionChecker so the role list is configurable from the admin UI.
     */
    public function checkAdminPermission(): bool
    {
        return PermissionChecker::userCan('feed_updates');
    }

    /**
     * Permission: supporter-tier features (premium polling).
     *
     * Checks for WP roles `supporter` or `lifetime`, which are assigned by
     * SubscriptionManager::assignSupporterRole() on active Stripe / PayPal payment.
     * Admins / editors can also access (they have an admin endpoint anyway).
     *
     * Evidence for role names:
     *   - SubscriptionManager.php line 361: $role = $planType === 'lifetime' ? 'lifetime' : 'supporter';
     *   - AdSettingsRoutes.php default supporter_roles: ['administrator', 'editor', 'supporter', 'lifetime']
     *   - AdContext.jsx: user.user_roles.some(role => supporterRoles.includes(role))
     */
    public function checkSupporterPermission(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return false;
        }
        return !empty(array_intersect((array) $user->roles, self::SUPPORTER_ROLES));
    }
}
