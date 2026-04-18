<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Comments\AvatarUploader;
use BigBrotherJunkies\Data\FeedUpdates\BlueskyClient;
use BigBrotherJunkies\Data\FeedUpdates\FacebookClient;

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
    private const ALLOWED_ROLES = ['administrator', 'editor', 'updater', 'second_in_command'];

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
    }

    /**
     * Check if current user can post feed updates
     */
    public function checkUpdaterPermission(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        return !empty(array_intersect(self::ALLOWED_ROLES, $user->roles));
    }

    /**
     * Create a new feed update
     */
    public function createFeedUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        $content = wp_kses_post($request->get_param('content'));
        $mode = in_array($request->get_param('mode'), ['feed', 'show'])
            ? $request->get_param('mode')
            : 'feed';
        $postToBluesky = (bool) $request->get_param('post_to_bluesky');
        $postToFacebook = (bool) $request->get_param('post_to_facebook');

        // Validate required fields
        if (empty($content)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Content is required',
            ], 400);
        }

        // Generate SEO-friendly title: "BB27 Feed Update - Jan 30, 3:45 PM PT"
        $title = $this->generateTitle($mode);

        // Create the post
        $postId = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'live-feed-updates',
            'meta_input' => [
                '_feed_update_mode' => $mode,
            ],
        ]);

        if (is_wp_error($postId)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $postId->get_error_message(),
            ], 500);
        }

        // Handle image upload if provided
        $imageId = null;
        $files = $request->get_file_params();
        if (!empty($files['image'])) {
            $imageId = $this->handleImageUpload($files['image'], $postId);
            if ($imageId && !is_wp_error($imageId)) {
                set_post_thumbnail($postId, $imageId);
            }
        }

        // Get hashtag for social posts
        $hashtag = $this->getSeasonHashtag();
        $imageUrl = $imageId ? wp_get_attachment_url($imageId) : null;

        // Social posting results
        $socialResults = [
            'bluesky' => ['posted' => false, 'url' => null, 'error' => null],
            'facebook' => ['posted' => false, 'url' => null, 'error' => null],
        ];

        // Post to Bluesky
        if ($postToBluesky) {
            try {
                $bluesky = new BlueskyClient();
                $socialContent = $this->formatForSocial($title, $content, $hashtag, 'bluesky');
                $result = $bluesky->post($socialContent, $imageUrl);
                $socialResults['bluesky'] = $result;
            } catch (\Exception $e) {
                $socialResults['bluesky']['error'] = $e->getMessage();
                error_log('Bluesky posting failed: ' . $e->getMessage());
            }
        }

        // Post to Facebook
        if ($postToFacebook) {
            try {
                $facebook = new FacebookClient();
                $socialContent = $this->formatForSocial($title, $content, $hashtag, 'facebook');
                $result = $facebook->post($socialContent, $imageUrl);
                $socialResults['facebook'] = $result;
            } catch (\Exception $e) {
                $socialResults['facebook']['error'] = $e->getMessage();
                error_log('Facebook posting failed: ' . $e->getMessage());
            }
        }

        // Store social results in post meta
        update_post_meta($postId, '_social_posting_results', $socialResults);

        // Save user's mode preference
        update_user_meta(get_current_user_id(), 'feed_update_mode', $mode);

        // Clear caches
        do_action('breeze_clear_all_cache');

        // Get the created post for response
        $post = get_post($postId);

        return new \WP_REST_Response([
            'success' => true,
            'update' => $this->formatFeedUpdate($post),
            'social_results' => $socialResults,
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
        $hashtag = $this->getSeasonHashtag();
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
     * Handle image upload
     */
    private function handleImageUpload(array $file, int $postId): int|\WP_Error
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            return new \WP_Error('upload_error', $upload['error']);
        }

        $filetype = wp_check_filetype(basename($upload['file']), null);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachId = wp_insert_attachment($attachment, $upload['file'], $postId);
        $attachData = wp_generate_attachment_metadata($attachId, $upload['file']);
        wp_update_attachment_metadata($attachId, $attachData);

        return $attachId;
    }

    /**
     * Generate SEO-friendly title for feed update
     * Format: "BB27 Feed Update - Jan 30, 3:45 PM PT" or "BB27 Show Update - Jan 30, 2025"
     */
    private function generateTitle(string $mode): string
    {
        // Get season abbreviation (e.g., "BB27")
        $seasonAbbr = $this->getSeasonAbbreviation();

        // Get current BB Time (Pacific)
        $timezone = new \DateTimeZone('America/Los_Angeles');
        $now = new \DateTime('now', $timezone);

        // Format based on mode
        $typeLabel = $mode === 'show' ? 'Show Update' : 'Feed Update';

        if ($mode === 'show') {
            // Show updates: "BB27 Show Update - Jan 30, 2025"
            $dateStr = $now->format('M j, Y');
        } else {
            // Feed updates: "BB27 Feed Update - Jan 30, 3:45 PM PT"
            $dateStr = $now->format('M j, g:i A') . ' PT';
        }

        return "{$seasonAbbr} {$typeLabel} - {$dateStr}";
    }

    /**
     * Get season abbreviation (e.g., "BB27")
     */
    private function getSeasonAbbreviation(): string
    {
        $seasonId = (int) get_option('bbj_v2_current_season');

        if ($seasonId <= 0) {
            return 'BB';
        }

        // Try to get abbreviation from season meta
        $abbreviation = get_post_meta($seasonId, 'abbreviation', true);

        if (empty($abbreviation) && function_exists('get_field')) {
            $abbreviation = get_field('abbreviation', $seasonId);
        }

        if (empty($abbreviation)) {
            $season = get_post($seasonId);
            if ($season) {
                if (preg_match('/Big Brother (\d+)/i', $season->post_title, $matches)) {
                    $abbreviation = 'BB' . $matches[1];
                } elseif (preg_match('/Celebrity Big Brother (\d+)/i', $season->post_title, $matches)) {
                    $abbreviation = 'CBB' . $matches[1];
                }
            }
        }

        return $abbreviation ?: 'BB';
    }

    /**
     * Get current season hashtag (e.g., #BB27)
     */
    private function getSeasonHashtag(): string
    {
        $seasonId = (int) get_option('bbj_v2_current_season');

        if ($seasonId <= 0) {
            return '#BigBrother';
        }

        // Try to get abbreviation from season meta
        $abbreviation = get_post_meta($seasonId, 'abbreviation', true);

        if (empty($abbreviation)) {
            // Fallback: try ACF field
            if (function_exists('get_field')) {
                $abbreviation = get_field('abbreviation', $seasonId);
            }
        }

        if (empty($abbreviation)) {
            // Fallback: use post title
            $season = get_post($seasonId);
            if ($season) {
                // Extract abbreviation from title like "Big Brother 27" -> "BB27"
                if (preg_match('/Big Brother (\d+)/i', $season->post_title, $matches)) {
                    $abbreviation = 'BB' . $matches[1];
                } elseif (preg_match('/Celebrity Big Brother (\d+)/i', $season->post_title, $matches)) {
                    $abbreviation = 'CBB' . $matches[1];
                }
            }
        }

        return $abbreviation ? '#' . preg_replace('/\s+/', '', $abbreviation) : '#BigBrother';
    }

    /**
     * Format content for social media posting
     */
    private function formatForSocial(string $title, string $content, string $hashtag, string $platform): string
    {
        $text = $title ?: wp_strip_all_tags($content);
        $time = current_time('g:i A') . ' PT';

        if ($platform === 'facebook') {
            // Facebook: Spoiler warning format
            return "<<< Feed Update - Spoiler Warning >>>\n\n\n\n\n[Update ({$time}) - {$text}]\n\n{$hashtag}";
        }

        // Bluesky/Twitter: Simple format
        return "[Update ({$time}) - {$text}] {$hashtag}";
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
            'permalink' => get_permalink($post),
            'date' => get_the_date('c', $post),
            'date_formatted' => get_the_date('M j, Y', $post),
            'time' => get_the_time('g:i a', $post),
            'modified' => get_the_modified_date('c', $post),
            'time_ago' => human_time_diff(get_the_modified_time('U', $post), current_time('timestamp')) . ' ago',
            'thumbnail' => get_the_post_thumbnail_url($post, 'medium') ?: null,
            'comment_count' => (int) get_comments_number($post),
            'recent_comments' => $formattedComments,
            'mode' => get_post_meta($post->ID, '_feed_update_mode', true) ?: 'feed',
            'votes' => [
                'total' => $totalVotes,
                'user_vote' => $userVote,
            ],
            'author' => [
                'id' => $authorId,
                'name' => get_the_author_meta('display_name', $authorId),
                'avatar' => AvatarUploader::getAvatarUrl($authorId, 64),
            ],
        ];

        if ($includeFullContent) {
            $data['content'] = apply_filters('the_content', $post->post_content);
            $data['social_results'] = get_post_meta($post->ID, '_social_posting_results', true) ?: null;
        }

        return $data;
    }
}
