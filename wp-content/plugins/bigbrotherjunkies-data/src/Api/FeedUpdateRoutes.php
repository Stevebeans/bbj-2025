<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Comments\AvatarUploader;
use BigBrotherJunkies\Data\FeedUpdates\FeedUpdateCreator;
use BigBrotherJunkies\Data\LiveThread\LiveThreadState;
use BigBrotherJunkies\Data\Permissions\PermissionChecker;

/**
 * Feed Update API Routes
 *
 * Provides endpoints for:
 * - Creating feed updates with social posting
 * - Voting (upvote/downvote)
 * - User mode preferences (Feed Update vs Show Update)
 * - Current season hashtag
 */
class FeedUpdateRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        // Create feed update
        register_rest_route($namespace, '/feed-updates/create', [
            'methods' => 'POST',
            'callback' => [$this, 'createFeedUpdate'],
            'permission_callback' => [$this, 'checkUpdaterPermission'],
        ]);

        // Get single feed update by slug
        register_rest_route($namespace, '/feed-updates/single/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getSingleFeedUpdate'],
            'permission_callback' => '__return_true',
        ]);

        // Vote on feed update
        register_rest_route($namespace, '/feed-updates/(?P<id>\d+)/vote', [
            'methods' => 'POST',
            'callback' => [$this, 'voteFeedUpdate'],
            'permission_callback' => 'is_user_logged_in',
            'args' => [
                'vote' => [
                    'required' => true,
                    'type' => 'integer',
                    'enum' => [1, -1],
                ],
            ],
        ]);

        // Get/set user's mode preference
        register_rest_route($namespace, '/feed-updates/mode', [
            'methods' => 'GET',
            'callback' => [$this, 'getMode'],
            'permission_callback' => [$this, 'checkUpdaterPermission'],
        ]);

        register_rest_route($namespace, '/feed-updates/mode', [
            'methods' => 'POST',
            'callback' => [$this, 'setMode'],
            'permission_callback' => [$this, 'checkUpdaterPermission'],
            'args' => [
                'mode' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['feed', 'show'],
                ],
            ],
        ]);

        // Get current season hashtag
        register_rest_route($namespace, '/feed-updates/hashtag', [
            'methods' => 'GET',
            'callback' => [$this, 'getCurrentHashtag'],
            'permission_callback' => '__return_true',
        ]);

        // Get social API settings (for frontend to know what's configured)
        register_rest_route($namespace, '/feed-updates/social-config', [
            'methods' => 'GET',
            'callback' => [$this, 'getSocialConfig'],
            'permission_callback' => [$this, 'checkUpdaterPermission'],
        ]);

        // Update + delete a feed update. PUT is the admin-pane inline edit
        // (no social re-post); DELETE is a force delete (no trash).
        register_rest_route($namespace, '/feed-updates/(?P<id>\d+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'updateFeedUpdate'],
                'permission_callback' => [$this, 'checkUpdaterPermission'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'deleteFeedUpdate'],
                'permission_callback' => [$this, 'checkUpdaterPermission'],
            ],
        ]);
    }

    /**
     * Check if current user can post feed updates
     */
    public function checkUpdaterPermission(): bool
    {
        return PermissionChecker::userCan('feed_updates');
    }

    /**
     * Create a new feed update
     */
    public function createFeedUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        // New admin-pane params (all optional for backward compat with Next.js)
        $adminTitle   = $request->get_param('title');
        $adminDetails = $request->get_param('details');
        $adminType    = $request->get_param('update_type'); // term_id (int) or slug (string)

        // Legacy param (Next.js sends this as post_content source)
        $legacyContent = $request->get_param('content');

        $mode = in_array($request->get_param('mode'), ['feed', 'show'])
            ? $request->get_param('mode')
            : 'feed';
        $postToBluesky = (bool) $request->get_param('post_to_bluesky');
        $postToFacebook = (bool) $request->get_param('post_to_facebook');

        // At minimum we need a title OR some content/details; otherwise nothing to save
        $hasTitle   = !empty($adminTitle);
        $hasContent = !empty($adminDetails) || !empty($legacyContent);
        if (!$hasTitle && !$hasContent) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Either a headline or content is required',
            ], 400);
        }

        // Resolve title: admin-written headline wins, else the creator
        // auto-generates from mode (title=null triggers generation).
        $postTitle = $hasTitle ? sanitize_text_field($adminTitle) : null;

        // Resolve content: admin's details field wins, else legacy `content` param
        $postContent = !empty($adminDetails)
            ? wp_kses_post($adminDetails)
            : wp_kses_post($legacyContent);

        // Delegate the full create pipeline (emoji-encode, insert, image,
        // social cross-post, live-thread attach) to the shared creator so the
        // Bean Bot cycle and this route travel one identical path.
        $files  = $request->get_file_params();
        $result = FeedUpdateCreator::create([
            'title'            => $postTitle,
            'content'          => $postContent,
            'mode'             => $mode,
            'author_id'        => get_current_user_id(),
            'update_type'      => $adminType,
            'post_to_bluesky'  => $postToBluesky,
            'post_to_facebook' => $postToFacebook,
            'image_file'       => $files['image'] ?? null,
        ]);

        if (empty($result['success'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['message'] ?? 'Could not save the update to the database.',
            ], 500);
        }

        // Save user's mode preference (route-only: cron has no current user)
        update_user_meta(get_current_user_id(), 'feed_update_mode', $mode);

        // Get the created post for response
        $post = get_post($result['post_id']);

        return new \WP_REST_Response([
            'success' => true,
            'update' => $this->formatFeedUpdate($post),
            'social_results' => $result['social_results'],
            'image_error' => $result['image_error'],
        ], 201);
    }

    /**
     * Get a single feed update by slug
     */
    public function getSingleFeedUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        $slug = sanitize_title($request->get_param('slug'));

        $posts = get_posts([
            'name' => $slug,
            'post_type' => 'live-feed-updates',
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);

        if (empty($posts)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Feed update not found',
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'update' => $this->formatFeedUpdate($posts[0], true),
        ]);
    }

    /**
     * Update an existing feed update (admin pane inline edit).
     * Does NOT trigger social cross-posting — typo fixes shouldn't re-post.
     */
    public function updateFeedUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $post = get_post($id);
        if (!$post || $post->post_type !== 'live-feed-updates') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Feed update not found',
            ], 404);
        }

        $title   = $request->get_param('title');
        $details = $request->get_param('details');
        $type    = $request->get_param('update_type');

        $update = ['ID' => $id];
        if ($title !== null) {
            $update['post_title'] = wp_encode_emoji(sanitize_text_field((string) $title));
        }
        if ($details !== null) {
            $update['post_content'] = wp_encode_emoji(wp_kses_post((string) $details));
        }

        if (count($update) > 1) {
            $result = wp_update_post($update, true);
            if (is_wp_error($result)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $result->get_error_message(),
                ], 500);
            }
        }

        if ($type !== null) {
            if (is_numeric($type)) {
                wp_set_object_terms($id, (int) $type, 'update_type', false);
            } elseif ($type === '') {
                wp_set_object_terms($id, [], 'update_type', false);
            } else {
                wp_set_object_terms($id, sanitize_title($type), 'update_type', false);
            }
        }

        // Clear caches so the archive + single-post pages reflect the edit
        do_action('breeze_clear_all_cache');

        $updated = get_post($id);
        return new \WP_REST_Response([
            'success' => true,
            'update' => $this->formatFeedUpdate($updated),
        ]);
    }

    /**
     * Force-delete a feed update (no trash).
     */
    public function deleteFeedUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $post = get_post($id);
        if (!$post || $post->post_type !== 'live-feed-updates') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Feed update not found',
            ], 404);
        }

        $slug = $post->post_name;
        $result = wp_delete_post($id, true);
        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Delete failed',
            ], 500);
        }

        do_action('breeze_clear_all_cache');

        // wp_delete_post never fires transition_post_status, so the publish-time
        // revalidation hook (Plugin.php) doesn't run — without this the deleted
        // update lingers on the cached homepage/hub until the next publish.
        delete_transient(HomeRoutes::RECENT_TRANSIENT);
        \BigBrotherJunkies\Data\Utils\Revalidation::revalidateFeedUpdate($slug);

        return new \WP_REST_Response(null, 204);
    }

    /**
     * Vote on a feed update (upvote/downvote)
     */
    public function voteFeedUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $updateId = (int) $request->get_param('id');
        $vote = (int) $request->get_param('vote');
        $userId = get_current_user_id();

        // Verify the feed update exists
        $post = get_post($updateId);
        if (!$post || $post->post_type !== 'live-feed-updates') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Feed update not found',
            ], 404);
        }

        $table = $wpdb->prefix . 'bbj_feed_ratings';

        // Check for existing vote
        $existingVote = $wpdb->get_row($wpdb->prepare(
            "SELECT id, rating FROM {$table} WHERE update_id = %d AND user_id = %d",
            $updateId,
            $userId
        ));

        if ($existingVote) {
            if ((int) $existingVote->rating === $vote) {
                // Same vote - remove it (toggle off)
                $wpdb->delete($table, [
                    'id' => $existingVote->id,
                ], ['%d']);
                $userVote = 0;
            } else {
                // Different vote - update it
                $wpdb->update(
                    $table,
                    ['rating' => $vote, 'updated_at' => current_time('mysql')],
                    ['id' => $existingVote->id],
                    ['%d', '%s'],
                    ['%d']
                );
                $userVote = $vote;
            }
        } else {
            // New vote
            $wpdb->insert($table, [
                'update_id' => $updateId,
                'user_id' => $userId,
                'rating' => $vote,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ], ['%d', '%d', '%d', '%s']);
            $userVote = $vote;
        }

        // Get updated total
        $totalVotes = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(rating), 0) FROM {$table} WHERE update_id = %d",
            $updateId
        ));

        return new \WP_REST_Response([
            'success' => true,
            'total_votes' => $totalVotes,
            'user_vote' => $userVote,
        ]);
    }

    /**
     * Get user's current mode preference
     */
    public function getMode(\WP_REST_Request $request): \WP_REST_Response
    {
        $mode = get_user_meta(get_current_user_id(), 'feed_update_mode', true);

        return new \WP_REST_Response([
            'mode' => $mode ?: 'feed',
        ]);
    }

    /**
     * Set user's mode preference
     */
    public function setMode(\WP_REST_Request $request): \WP_REST_Response
    {
        $mode = $request->get_param('mode');
        update_user_meta(get_current_user_id(), 'feed_update_mode', $mode);

        return new \WP_REST_Response([
            'success' => true,
            'mode' => $mode,
        ]);
    }

    /**
     * Get current season hashtag
     */
    public function getCurrentHashtag(\WP_REST_Request $request): \WP_REST_Response
    {
        $hashtag = FeedUpdateCreator::getSeasonHashtag();
        $seasonId = (int) get_option('bbj_v2_current_season');
        $seasonName = '';

        if ($seasonId > 0) {
            $season = get_post($seasonId);
            if ($season) {
                $seasonName = $season->post_title;
            }
        }

        return new \WP_REST_Response([
            'hashtag' => $hashtag,
            'season_id' => $seasonId,
            'season_name' => $seasonName,
        ]);
    }

    /**
     * Get social API configuration status
     */
    public function getSocialConfig(\WP_REST_Request $request): \WP_REST_Response
    {
        $options = get_option('bbjd_social_settings', []);

        return new \WP_REST_Response([
            'bluesky' => [
                'configured' => !empty($options['bluesky_handle']) && !empty($options['bluesky_app_password']),
                'handle' => $options['bluesky_handle'] ?? '',
            ],
            'facebook' => [
                'configured' => !empty($options['facebook_page_token']),
                'page_name' => $options['facebook_page_name'] ?? '',
            ],
        ]);
    }

    /**
     * Format a feed update for API response
     */
    private function formatFeedUpdate(\WP_Post $post, bool $includeFullContent = false): array
    {
        global $wpdb;

        $authorId = (int) $post->post_author;

        // Get vote data
        $table = $wpdb->prefix . 'bbj_feed_ratings';
        $totalVotes = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(rating), 0) FROM {$table} WHERE update_id = %d",
            $post->ID
        ));

        $userVote = 0;
        if (is_user_logged_in()) {
            $userVote = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT rating FROM {$table} WHERE update_id = %d AND user_id = %d",
                $post->ID,
                get_current_user_id()
            ));
        }

        // Get recent comments (last 3)
        $recentComments = get_comments([
            'post_id' => $post->ID,
            'status' => 'approve',
            'number' => 3,
            'orderby' => 'comment_date',
            'order' => 'DESC',
        ]);

        $formattedComments = array_map(function ($comment) {
            $content = wp_strip_all_tags($comment->comment_content);
            // Truncate to ~60 chars for single line display
            $truncated = mb_strlen($content) > 60
                ? mb_substr($content, 0, 57) . '...'
                : $content;

            return [
                'author' => $comment->comment_author,
                'content' => $truncated,
            ];
        }, $recentComments);

        $data = [
            'id' => $post->ID,
            'slug' => $post->post_name,
            'title' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
            'excerpt' => html_entity_decode(wp_trim_words(wp_strip_all_tags($post->post_content), 30), ENT_QUOTES, 'UTF-8'),
            'raw_content' => $post->post_content,
            'permalink' => get_permalink($post),
            'date' => get_the_date('c', $post),
            'date_formatted' => get_the_date('M j, Y', $post),
            'time' => get_the_time('g:i a', $post),
            'modified' => get_the_modified_date('c', $post),
            'time_ago' => human_time_diff(get_the_modified_time('U', $post), current_time('timestamp')) . ' ago',
            // medium_large (768w): this site's 'medium' is configured at 200px,
            // which upscales blurry in the ~350-520px feed cards.
            'thumbnail' => get_the_post_thumbnail_url($post, 'medium_large') ?: null,
            'comment_count' => (int) get_comments_number($post),
            'recent_comments' => $formattedComments,
            'thread' => LiveThreadState::threadSummaryForUpdate($post->ID),
            'mode' => get_post_meta($post->ID, '_feed_update_mode', true) ?: 'feed',
            'votes' => [
                'total' => $totalVotes,
                'user_vote' => $userVote,
            ],
            'author' => [
                'id' => $authorId,
                'name' => get_the_author_meta('display_name', $authorId),
                'avatar' => AvatarUploader::getAvatarUrl($authorId, 64),
                // Bean Bot posts as the dedicated `bean-bot` WP user; the
                // frontend badges its updates with the bot disclaimer.
                'is_bot' => (($user = get_userdata($authorId)) ? $user->user_login : '') === 'bean-bot',
            ],
        ];

        if ($includeFullContent) {
            $data['content'] = apply_filters('the_content', $post->post_content);
            $data['social_results'] = get_post_meta($post->ID, '_social_posting_results', true) ?: null;
        }

        return $data;
    }
}
