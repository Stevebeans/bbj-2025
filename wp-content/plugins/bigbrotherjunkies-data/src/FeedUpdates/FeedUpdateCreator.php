<?php

namespace BigBrotherJunkies\Data\FeedUpdates;

use BigBrotherJunkies\Data\LiveThread\LiveThreadState;
use BigBrotherJunkies\Data\Utils\Revalidation;

/**
 * Core "create a live-feed update" pipeline, factored out of
 * FeedUpdateRoutes::createFeedUpdate so both the composer REST route and the
 * Bean Bot cycle share ONE creation path (title generation, emoji-encode,
 * post insert, optional image, optional Bluesky/Facebook cross-post,
 * live-thread attach).
 *
 * House rules preserved verbatim from the original handler:
 *  - wp_posts is utf8mb3: title + content pass through wp_encode_emoji()
 *    before the INSERT (a raw 4-byte emoji fails the INSERT).
 *  - wp_insert_post can fail with 0 or WP_Error; bail BEFORE any side effect
 *    (image upload, Bluesky) so a failed save can never half-publish.
 *  - An image upload failure never fails the post; the reason is captured so
 *    the composer can surface it and Bluesky posts text-only.
 *
 * @see FeedUpdateRoutes::createFeedUpdate (the route now delegates here)
 */
class FeedUpdateCreator
{
    /**
     * Create a live-feed update and run its cross-posting side effects.
     *
     * @param array{
     *   content:string,
     *   author_id:int,
     *   post_to_bluesky:bool,
     *   image_id?:?int,
     *   title?:?string,
     *   mode?:string,
     *   update_type?:mixed,
     *   post_to_facebook?:bool,
     *   image_file?:?array,
     *   show?:string
     * } $args
     * @return array{
     *   success:bool,
     *   post_id:?int,
     *   bluesky:array,
     *   message:?string,
     *   social_results?:array,
     *   image_error?:?string
     * }
     */
    public static function create(array $args): array
    {
        $content       = (string) ($args['content'] ?? '');
        $authorId      = (int) ($args['author_id'] ?? 0);
        $postToBluesky = (bool) ($args['post_to_bluesky'] ?? false);
        $imageId       = isset($args['image_id']) ? (int) $args['image_id'] : null;
        $providedTitle = $args['title'] ?? null;
        $mode          = (string) ($args['mode'] ?? 'feed');
        if (!in_array($mode, ['feed', 'show'], true)) {
            $mode = 'feed';
        }
        $updateType     = $args['update_type'] ?? null;
        $postToFacebook = (bool) ($args['post_to_facebook'] ?? false);
        $imageFile      = $args['image_file'] ?? null;
        $videoFile      = $args['video_file'] ?? null;
        $show           = sanitize_title((string) ($args['show'] ?? ''));

        // Default (nothing posted) social result — also the failure return shape.
        $socialResults = [
            'bluesky'  => ['posted' => false, 'url' => null, 'error' => null],
            'facebook' => ['posted' => false, 'url' => null, 'error' => null],
        ];

        // Resolve title: caller-supplied headline wins, else auto-generate from mode.
        $postTitle = ($providedTitle !== null && $providedTitle !== '')
            ? (string) $providedTitle
            : self::generateTitle($mode);

        $postContent = $content;

        // wp_posts is utf8mb3 (legacy DB): raw 4-byte emoji make the INSERT
        // fail. Encode them as HTML entities — they render identically.
        $postTitle   = wp_encode_emoji($postTitle);
        $postContent = wp_encode_emoji($postContent);

        // Create the post
        $postArgs = [
            'post_title'   => $postTitle,
            'post_content' => $postContent,
            'post_status'  => 'publish',
            'post_type'    => 'live-feed-updates',
            'meta_input'   => [
                '_feed_update_mode' => $mode,
            ],
        ];
        if ($authorId > 0) {
            $postArgs['post_author'] = $authorId;
        }
        $postId = wp_insert_post($postArgs);

        // wp_insert_post can also fail with 0 (e.g. rejected INSERT) — bail
        // BEFORE side effects (image upload, Bluesky) so a failed save can't
        // half-publish.
        if (is_wp_error($postId) || !$postId) {
            return [
                'success'        => false,
                'post_id'        => null,
                'bluesky'        => $socialResults['bluesky'],
                'message'        => is_wp_error($postId)
                    ? $postId->get_error_message()
                    : 'Could not save the update to the database.',
                'social_results' => $socialResults,
                'image_error'    => null,
            ];
        }

        // Handle image: a freshly-uploaded file (composer) or a pre-existing
        // attachment id (caller supplied). Upload happens post-insert because
        // the attachment is parented to the new post.
        $imageError = null;
        if ($imageFile !== null && !empty($imageFile)) {
            $uploaded = self::handleImageUpload($imageFile, $postId);
            if (is_wp_error($uploaded)) {
                // Don't fail the post — the text is still valuable. Capture the
                // reason so the composer can surface it and Bluesky posts text-only.
                error_log('BBJD feed image upload failed: ' . $uploaded->get_error_message());
                $imageError = $uploaded->get_error_message();
                $imageId = null;
            } elseif ($uploaded) {
                $imageId = $uploaded;
                set_post_thumbnail($postId, $imageId);
            }
        } elseif ($imageId) {
            set_post_thumbnail($postId, $imageId);
        }

        // Handle video clip: same never-fail-the-post contract as images.
        $videoError = null;
        if ($videoFile !== null && !empty($videoFile)) {
            $uploadedVideo = self::handleVideoUpload($videoFile, $postId);
            if (is_wp_error($uploadedVideo)) {
                error_log('BBJD feed video upload failed: ' . $uploadedVideo->get_error_message());
                $videoError = $uploadedVideo->get_error_message();
            } elseif ($uploadedVideo) {
                update_post_meta($postId, '_feed_update_video_id', (int) $uploadedVideo);
            }
        }

        // Apply update_type taxonomy term if provided
        if (!empty($updateType)) {
            if (is_numeric($updateType)) {
                wp_set_object_terms($postId, (int) $updateType, 'update_type', false);
            } else {
                wp_set_object_terms($postId, sanitize_title($updateType), 'update_type', false);
            }
        }

        // Tag with a show (Phase 1: Survivor) so it lives outside the Big
        // Brother feed/homepage/live-thread pipeline. Default '' = Big Brother,
        // unchanged for every existing caller (Bean Bot included).
        if ($show !== '') {
            wp_set_object_terms($postId, $show, 'bbj_show', false);
        }

        // Get hashtag for social posts
        $hashtag  = self::getSeasonHashtag();
        $imageUrl = $imageId ? wp_get_attachment_url($imageId) : null;

        // Post to Bluesky
        if ($postToBluesky) {
            try {
                $bluesky = new BlueskyClient();
                $socialContent = self::formatForSocial($postTitle, $postContent, $hashtag, 'bluesky');
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
                $socialContent = self::formatForSocial($postTitle, $postContent, $hashtag, 'facebook');
                $result = $facebook->post($socialContent, $imageUrl);
                $socialResults['facebook'] = $result;
            } catch (\Exception $e) {
                $socialResults['facebook']['error'] = $e->getMessage();
                error_log('Facebook posting failed: ' . $e->getMessage());
            }
        }

        // Store social results in post meta
        update_post_meta($postId, '_social_posting_results', $socialResults);

        // Clear caches
        do_action('breeze_clear_all_cache');

        // Attach to active live thread (if any) — a show-tagged update (e.g.
        // Survivor) must never join a Big Brother eviction-night thread.
        if ($show === '') {
            $updateTime = current_time('timestamp', true); // UTC, matches post_date_gmt
            $threadId = LiveThreadState::findThreadForUpdate($updateTime);
            if ($threadId > 0) {
                update_post_meta($postId, '_bbjd_thread_post_id', $threadId);
                Revalidation::revalidateTag("live-thread-{$threadId}");
                $thread = get_post($threadId);
                if ($thread) {
                    Revalidation::revalidatePost($thread->post_name);
                }
            }
        }

        return [
            'success'        => true,
            'post_id'        => (int) $postId,
            'bluesky'        => $socialResults['bluesky'],
            'message'        => null,
            'social_results' => $socialResults,
            'image_error'    => $imageError,
            'video_error'    => $videoError,
        ];
    }

    /**
     * Shared API-serializer fragments — feed updates are shaped in THREE
     * places (FeedUpdateRoutes, HomeRoutes, LiveThreadRoutes); keep the
     * video and bot-author fields identical everywhere.
     */
    public static function videoField(int $postId): ?array
    {
        $vid = (int) get_post_meta($postId, '_feed_update_video_id', true);
        if (!$vid) {
            return null;
        }
        return [
            'url'  => wp_get_attachment_url($vid) ?: null,
            'mime' => get_post_mime_type($vid) ?: 'video/mp4',
        ];
    }

    /** Bean Bot posts as the dedicated `bean-bot` WP user. */
    public static function isBotAuthor(int $authorId): bool
    {
        if ($authorId <= 0) {
            return false;
        }
        $user = get_userdata($authorId);
        return $user && $user->user_login === 'bean-bot';
    }

    /** Video clip limits: CF proxies cap request bodies around 100MB, so stay under. */
    public const VIDEO_MAX_BYTES = 80 * 1024 * 1024;
    public const VIDEO_MIMES = [
        'mp4'  => 'video/mp4',
        'm4v'  => 'video/x-m4v',
        'mov'  => 'video/quicktime',
        'webm' => 'video/webm',
    ];

    /**
     * Handle a video clip upload: size + REAL mime validation (finfo, not the
     * browser's claim), then a normal WP attachment parented to the post and
     * referenced from _feed_update_video_id meta.
     */
    private static function handleVideoUpload(array $file, int $postId): int|\WP_Error
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        if (!empty($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_error', 'Video upload failed (error ' . (int) $file['error'] . ').');
        }
        if ((int) ($file['size'] ?? 0) > self::VIDEO_MAX_BYTES) {
            return new \WP_Error('too_large', 'Video is over the ' . (self::VIDEO_MAX_BYTES / 1024 / 1024) . 'MB limit.');
        }
        if (!empty($file['tmp_name']) && function_exists('finfo_open')) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $realMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            if ($realMime && !in_array($realMime, self::VIDEO_MIMES, true)) {
                return new \WP_Error('bad_type', 'Only MP4, MOV, or WebM clips are supported (got ' . $realMime . ').');
            }
        }

        $upload = wp_handle_upload($file, ['test_form' => false, 'mimes' => self::VIDEO_MIMES]);
        if (isset($upload['error'])) {
            return new \WP_Error('upload_error', $upload['error']);
        }

        $attachId = wp_insert_attachment([
            'post_mime_type' => $upload['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $upload['file'], $postId);
        if (!$attachId || is_wp_error($attachId)) {
            return new \WP_Error('attach_error', 'Could not create video attachment');
        }
        wp_update_attachment_metadata($attachId, wp_generate_attachment_metadata($attachId, $upload['file']));

        return $attachId;
    }

    /**
     * Handle image upload
     */
    private static function handleImageUpload(array $file, int $postId): int|\WP_Error
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
        if (!$attachId || is_wp_error($attachId)) {
            return new \WP_Error('attach_error', 'Could not create attachment');
        }
        $attachData = wp_generate_attachment_metadata($attachId, $upload['file']);
        wp_update_attachment_metadata($attachId, $attachData);

        return $attachId;
    }

    /**
     * Generate SEO-friendly title for feed update
     * Format: "BB27 Feed Update - Jan 30, 3:45 PM PT" or "BB27 Show Update - Jan 30, 2025"
     */
    private static function generateTitle(string $mode): string
    {
        // Get season abbreviation (e.g., "BB27")
        $seasonAbbr = self::getSeasonAbbreviation();

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
    private static function getSeasonAbbreviation(): string
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
    public static function getSeasonHashtag(): string
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
    private static function formatForSocial(string $title, string $content, string $hashtag, string $platform): string
    {
        $text = $title ?: wp_strip_all_tags($content);
        $time = current_time('g:i A') . ' PT';

        if ($platform === 'facebook') {
            // Facebook: Spoiler warning format
            return "<<< Feed Update - Spoiler Warning >>>\n\n\n\n\n[Update ({$time}) - {$text}]\n\n{$hashtag}";
        }

        if ($platform === 'bluesky') {
            // Native voice: [BBT timestamp] + the update's own words + season
            // tag. No robotic "[Update (time) - Title]" wrapper — reads like a
            // person live-posting. BBT = Big Brother Time (Pacific), computed
            // explicitly so it's correct regardless of the WP site timezone.
            $bbt = (new \DateTime('now', new \DateTimeZone('America/Los_Angeles')))->format('h:i a');
            $body = trim(wp_strip_all_tags($content));
            $body = preg_replace("/\r\n?/", "\n", $body);
            $body = preg_replace("/\n{3,}/", "\n\n", $body); // collapse big gaps
            if ($body === '') {
                $body = $title;
            }
            // Long updates get a condensed rewrite sized so the prefix and
            // season tag fit inside Bluesky's 300 — otherwise BlueskyClient
            // clips the body mid-word.
            $prefix = "[{$bbt} BBT] ";
            $suffix = $hashtag !== '' ? " {$hashtag}" : '';
            $budget = 300 - mb_strlen($prefix) - mb_strlen($suffix);
            $body   = \BigBrotherJunkies\Data\Social\BlueskyCondenser::condense($body, $budget);
            return $prefix . $body . $suffix;
        }

        // Twitter/legacy: Simple format
        return "[Update ({$time}) - {$text}] {$hashtag}";
    }
}
