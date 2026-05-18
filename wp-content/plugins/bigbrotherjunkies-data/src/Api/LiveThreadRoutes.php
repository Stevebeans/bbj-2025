<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Comments\AvatarUploader;
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

        register_rest_route($ns, '/live-thread/(?P<id>\d+)/updates', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getAllUpdatesForThread'],
            'permission_callback' => '__return_true',
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
     * Supporter only. Returns feed updates published after `ts` within the
     * live thread's time window, ordered oldest-first so the client can append.
     *
     * Timestamps live in post_date_gmt (standard WP column), not postmeta —
     * FeedUpdateRoutes::createFeedUpdate() uses wp_insert_post() without any
     * custom time meta, so we filter via date_query rather than meta_query.
     */
    public function getUpdatesSince(WP_REST_Request $req)
    {
        $threadId = (int) $req->get_param('id');
        $sinceTs  = (int) $req->get_param('ts');

        $thread = get_post($threadId);
        if (!$thread) {
            return new WP_Error('invalid_thread', 'Thread not found.', ['status' => 404]);
        }

        $start = (int) get_post_meta($threadId, LiveThreadState::META_LIVE_START, true);
        $end   = (int) get_post_meta($threadId, LiveThreadState::META_LIVE_END, true);

        // Bound the lower edge: never return updates before the thread opened.
        $effectiveSince = max($sinceTs, $start);

        // date_query uses post_date_gmt and compares against UTC datetime strings.
        // 'after' is exclusive (>) by default in WP_Date_Query.
        $dateQuery = [
            'after'     => gmdate('Y-m-d H:i:s', $effectiveSince),
            'inclusive' => false,  // strict >  (we want updates strictly after the client's last ts)
        ];
        if ($end > 0) {
            $dateQuery['before']    = gmdate('Y-m-d H:i:s', $end);
            $dateQuery['inclusive'] = true; // include updates at exactly $end
        }

        $updates = get_posts([
            'post_type'      => 'live-feed-updates',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'date_query'     => [$dateQuery],
        ]);

        $items = array_map([$this, 'serializeFeedUpdate'], $updates);

        $response = new WP_REST_Response([
            'updates'      => $items,
            'thread_state' => LiveThreadState::getState($thread),
            'server_time'  => time(),
        ], 200);

        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        return $response;
    }

    /**
     * GET /bbjd/v1/live-thread/{id}/updates
     * Public. Returns ALL feed updates for a thread's time window, ordered
     * oldest-first. Used by the server-rendered <LiveUpdateTimeline /> so it
     * can render the full initial list without a client round-trip.
     *
     * Timestamps live in post_date_gmt (same as getUpdatesSince); we use
     * date_query with 'inclusive' => true so updates at exactly $start are included.
     */
    public function getAllUpdatesForThread(WP_REST_Request $req)
    {
        $threadId = (int) $req->get_param('id');
        $thread = get_post($threadId);
        if (!$thread) {
            return new WP_Error('invalid_thread', 'Thread not found.', ['status' => 404]);
        }

        $start = (int) get_post_meta($threadId, LiveThreadState::META_LIVE_START, true);
        $end   = (int) get_post_meta($threadId, LiveThreadState::META_LIVE_END, true);

        // date_query uses post_date_gmt; 'inclusive' => true gives us >= $start.
        $dateQuery = [
            'after'     => gmdate('Y-m-d H:i:s', max($start - 1, 0)), // subtract 1s so inclusive=true catches $start exactly
            'inclusive' => true,
        ];
        if ($end > 0) {
            $dateQuery['before']    = gmdate('Y-m-d H:i:s', $end);
            $dateQuery['inclusive'] = true;
        }

        $updates = get_posts([
            'post_type'      => 'live-feed-updates',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'date_query'     => [$dateQuery],
        ]);

        return new WP_REST_Response([
            'updates'      => array_map([$this, 'serializeFeedUpdate'], $updates),
            'thread_state' => LiveThreadState::getState($thread),
            'live_start'   => $start,
            'live_end'     => $end,
        ], 200);
    }

    /**
     * Serialize a live-feed-updates post for polling responses.
     *
     * Shape mirrors FeedUpdateRoutes::formatFeedUpdate() so that
     * <LiveUpdateTimeline /> can render both the server-rendered initial list
     * and polled deltas without any field mapping.
     *
     * Votes are included (one extra DB query per post) because the timeline
     * card renders the vote count. recent_comments are omitted — they're an
     * admin-pane feature not displayed in the public timeline.
     */
    private function serializeFeedUpdate(\WP_Post $update): array
    {
        global $wpdb;

        $authorId = (int) $update->post_author;

        // Vote totals for the timeline card.
        $table = $wpdb->prefix . 'bbj_feed_ratings';
        $totalVotes = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(rating), 0) FROM {$table} WHERE update_id = %d",
            $update->ID
        ));

        $userVote = 0;
        if (is_user_logged_in()) {
            $userVote = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT rating FROM {$table} WHERE update_id = %d AND user_id = %d",
                $update->ID,
                get_current_user_id()
            ));
        }

        return [
            'id'             => $update->ID,
            'slug'           => $update->post_name,
            'title'          => html_entity_decode(get_the_title($update), ENT_QUOTES, 'UTF-8'),
            'excerpt'        => html_entity_decode(
                wp_trim_words(wp_strip_all_tags($update->post_content), 30),
                ENT_QUOTES,
                'UTF-8'
            ),
            'raw_content'    => $update->post_content,
            'permalink'      => get_permalink($update),
            'date'           => get_the_date('c', $update),
            'date_formatted' => get_the_date('M j, Y', $update),
            'time'           => get_the_time('g:i a', $update),
            'modified'       => get_the_modified_date('c', $update),
            'time_ago'       => human_time_diff(get_the_modified_time('U', $update), current_time('timestamp')) . ' ago',
            'thumbnail'      => get_the_post_thumbnail_url($update->ID, 'medium') ?: null,
            'comment_count'  => (int) get_comments_number($update),
            'mode'           => get_post_meta($update->ID, '_feed_update_mode', true) ?: 'feed',
            'votes'          => [
                'total'     => $totalVotes,
                'user_vote' => $userVote,
            ],
            'author'         => [
                'id'     => $authorId,
                'name'   => get_the_author_meta('display_name', $authorId),
                'avatar' => AvatarUploader::getAvatarUrl($authorId, 64),
            ],
        ];
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
