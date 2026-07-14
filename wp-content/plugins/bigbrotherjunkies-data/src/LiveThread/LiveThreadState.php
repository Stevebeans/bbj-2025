<?php

namespace BigBrotherJunkies\Data\LiveThread;

use BigBrotherJunkies\Data\Utils\Revalidation;
use WP_Post;

/**
 * Owns the live-update state machine for posts.
 *
 * States (derived, never stored):
 *   - 'none'   : not a live thread
 *   - 'live'   : currently active (matches global option, in window, not closed)
 *   - 'closed' : was a live thread, no longer active
 */
class LiveThreadState
{
    public const OPTION_ACTIVE = 'bbjd_active_live_thread';

    public const META_LIVE_UPDATES = '_bbjd_live_updates';
    public const META_LIVE_START   = '_bbjd_live_start';
    public const META_LIVE_END     = '_bbjd_live_end';
    public const META_CLOSED_AT    = '_bbjd_closed_at';
    public const META_CLOSING_SUMMARY = '_bbjd_closing_summary';

    /**
     * Derive the live-thread state for a post.
     */
    public static function getState(WP_Post $post): string
    {
        $enabled = (int) get_post_meta($post->ID, self::META_LIVE_UPDATES, true);
        if ($enabled !== 1) {
            return 'none';
        }

        $closedAt = (int) get_post_meta($post->ID, self::META_CLOSED_AT, true);
        if ($closedAt > 0) {
            return 'closed';
        }

        $start = (int) get_post_meta($post->ID, self::META_LIVE_START, true);
        $end   = (int) get_post_meta($post->ID, self::META_LIVE_END, true);
        $now   = time();

        if ($start > 0 && $now < $start) {
            return 'none'; // window not yet open — treat as not-yet-live (closed implies "was live")
        }

        if ($end > 0 && $now > $end) {
            return 'closed';
        }

        $activeId = (int) get_option(self::OPTION_ACTIVE, 0);
        if ($activeId !== $post->ID) {
            return 'closed';
        }

        return 'live';
    }

    /**
     * Get the currently active live thread post, or null.
     */
    public static function getActivePost(): ?WP_Post
    {
        $id = (int) get_option(self::OPTION_ACTIVE, 0);
        if ($id <= 0) {
            return null;
        }
        $post = get_post($id);
        if (!$post || $post->post_status !== 'publish') {
            return null;
        }
        if (self::getState($post) !== 'live') {
            return null;
        }
        return $post;
    }

    /**
     * Atomically open a new thread.
     *
     * If a thread is already active, it is closed first (its _bbjd_closed_at
     * is stamped). The global option is then set to the new post ID.
     *
     * Returns the previously-active post ID (or 0 if none).
     */
    public static function openThread(int $newPostId): int
    {
        global $wpdb;

        $previousId = (int) get_option(self::OPTION_ACTIVE, 0);
        $now = time();

        $wpdb->query('START TRANSACTION');
        try {
            if ($previousId > 0 && $previousId !== $newPostId) {
                update_post_meta($previousId, self::META_CLOSED_AT, $now);
            }

            // Ensure the new post is marked as a live-updates post
            update_post_meta($newPostId, self::META_LIVE_UPDATES, 1);
            delete_post_meta($newPostId, self::META_CLOSED_AT);

            // Stamp the window start if the editor didn't set one. Without this
            // the updates query falls back to 1970 and returns the full archive.
            // 2h lookback captures updates posted shortly before go-live.
            $existingStart = (int) get_post_meta($newPostId, self::META_LIVE_START, true);
            if ($existingStart <= 0) {
                update_post_meta($newPostId, self::META_LIVE_START, $now - 2 * HOUR_IN_SECONDS);
            }

            update_option(self::OPTION_ACTIVE, $newPostId, false);

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        // Fire revalidation tags (outside the transaction)
        Revalidation::revalidateTag('live-thread-active');
        Revalidation::revalidateTag("live-thread-{$newPostId}");
        if ($previousId > 0 && $previousId !== $newPostId) {
            Revalidation::revalidateTag("live-thread-{$previousId}");
        }

        return $previousId;
    }

    /**
     * Close the currently-active thread (or a specific post).
     */
    public static function closeThread(?int $postId = null): void
    {
        global $wpdb;

        $activeId = (int) get_option(self::OPTION_ACTIVE, 0);
        $targetId = $postId ?? $activeId;
        if ($targetId <= 0) {
            return;
        }

        $wpdb->query('START TRANSACTION');
        try {
            update_post_meta($targetId, self::META_CLOSED_AT, time());

            if ($activeId === $targetId) {
                update_option(self::OPTION_ACTIVE, 0, false);
            }

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        Revalidation::revalidateTag('live-thread-active');
        Revalidation::revalidateTag("live-thread-{$targetId}");
    }

    /** Request-lifetime cache: threadId => summary array|null. Not persisted (no transients). */
    private static $threadSummaryCache = [];

    /**
     * Build the `thread` payload field for a feed-update post: the daily live
     * thread it's attached to (via `_bbjd_thread_post_id` meta), or null when
     * there is no meta, or the referenced post is missing/unpublished.
     *
     * Shared by FeedUpdateRoutes::formatFeedUpdate() and the HomeRoutes feed
     * loop so both payload builders stay byte-identical for this field.
     * Cached per-thread-id for the lifetime of the request only.
     *
     * @return array{id:int,slug:string,title:string,comment_count:int}|null
     */
    public static function threadSummaryForUpdate(int $updatePostId): ?array
    {
        $threadId = (int) get_post_meta($updatePostId, '_bbjd_thread_post_id', true);
        if (!$threadId) {
            return null;
        }
        if (!array_key_exists($threadId, self::$threadSummaryCache)) {
            $thread = get_post($threadId);
            self::$threadSummaryCache[$threadId] = ($thread && $thread->post_status === 'publish')
                ? [
                    'id'            => $threadId,
                    'slug'          => $thread->post_name,
                    'title'         => html_entity_decode(get_the_title($thread), ENT_QUOTES, 'UTF-8'),
                    'comment_count' => (int) $thread->comment_count,
                  ]
                : null;
        }
        return self::$threadSummaryCache[$threadId];
    }

    /**
     * Find which currently-live thread (if any) a feed-update at $updateTime
     * belongs to. Returns post ID or 0.
     */
    public static function findThreadForUpdate(int $updateTime): int
    {
        $activePost = self::getActivePost();
        if (!$activePost) {
            return 0;
        }

        $start = (int) get_post_meta($activePost->ID, self::META_LIVE_START, true);
        $end   = (int) get_post_meta($activePost->ID, self::META_LIVE_END, true);

        if ($start > 0 && $updateTime < $start) {
            return 0;
        }
        if ($end > 0 && $updateTime > $end) {
            return 0;
        }

        return $activePost->ID;
    }
}
