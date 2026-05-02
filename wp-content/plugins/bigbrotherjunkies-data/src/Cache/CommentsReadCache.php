<?php

namespace BigBrotherJunkies\Data\Cache;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Object cache wrapper for GET /bbjd/v1/comments/{post_id}.
 *
 * Keys are versioned per-post so we can bust all paged/sorted
 * variants with a single wp_cache_set on the version counter,
 * without needing pattern-delete support from the cache backend.
 *
 * Only anonymous (logged-out) reads are cached.  Per-user state
 * (user_vote, user_reaction, can_edit, can_delete, can_pin,
 * is_online) is deeply embedded in formatComment, so caching
 * logged-in responses would leak one user's permissions to another.
 */
class CommentsReadCache
{
    private const GROUP = 'bbj_v2';
    private const TTL   = 60;

    // ---------------------------------------------------------------
    // Key helpers
    // ---------------------------------------------------------------

    public static function key(int $postId, int $page, string $sort): string
    {
        return sprintf('bbj_comments_%d_p%d_s%s', $postId, $page, $sort);
    }

    /**
     * Return a versioned cache key for this post/page/sort combination.
     * Bumping the version counter (bust()) invalidates all derived keys.
     */
    public static function versionedKey(int $postId, int $page, string $sort): string
    {
        $vKey = "bbj_comments_v_{$postId}";
        $v    = (int) wp_cache_get($vKey, self::GROUP) ?: 1;
        return sprintf('bbj_comments_%d_v%d_p%d_s%s', $postId, $v, $page, $sort);
    }

    // ---------------------------------------------------------------
    // Read / write
    // ---------------------------------------------------------------

    public static function get(int $postId, int $page, string $sort)
    {
        return wp_cache_get(self::key($postId, $page, $sort), self::GROUP);
    }

    public static function set(int $postId, int $page, string $sort, $payload): bool
    {
        return wp_cache_set(self::key($postId, $page, $sort), $payload, self::GROUP, self::TTL);
    }

    // ---------------------------------------------------------------
    // Invalidation
    // ---------------------------------------------------------------

    /**
     * Bust all cached pages/sort variants for a post by bumping its
     * version counter.  Old versioned keys become unreachable and will
     * eventually be evicted by the cache backend's LRU/TTL logic.
     */
    public static function bust(int $postId): void
    {
        $vKey = "bbj_comments_v_{$postId}";
        $cur  = (int) wp_cache_get($vKey, self::GROUP) ?: 1;
        wp_cache_set($vKey, $cur + 1, self::GROUP, 0);
    }

    // ---------------------------------------------------------------
    // Hook registration
    // ---------------------------------------------------------------

    /**
     * Register WordPress and bbjd invalidation hooks.
     *
     * Called once from Plugin::init() via add_action('init', …).
     */
    public static function registerInvalidationHooks(): void
    {
        // WordPress core comment lifecycle hooks.
        // wp_insert_comment passes ($comment_id, $comment_object) — use the object.
        add_action('wp_insert_comment', function ($commentId, $commentObj) {
            $postId = (int) ($commentObj->comment_post_ID ?? 0);
            if ($postId > 0) {
                self::bust($postId);
            }
        }, 10, 2);

        // edit_comment passes ($comment_id, $data) — look up the comment.
        add_action('edit_comment', function ($commentId) {
            $comment = get_comment($commentId);
            $postId  = $comment ? (int) $comment->comment_post_ID : 0;
            if ($postId > 0) {
                self::bust($postId);
            }
        }, 10, 1);

        // deleted_comment passes $comment_id (comment already removed from DB) —
        // we may not be able to look it up, so accept possible miss on bust.
        add_action('deleted_comment', function ($commentId) {
            $comment = get_comment($commentId);
            $postId  = $comment ? (int) $comment->comment_post_ID : 0;
            if ($postId > 0) {
                self::bust($postId);
            }
        }, 10, 1);

        // wp_set_comment_status passes ($comment_id, $new_status).
        add_action('wp_set_comment_status', function ($commentId) {
            $comment = get_comment($commentId);
            $postId  = $comment ? (int) $comment->comment_post_ID : 0;
            if ($postId > 0) {
                self::bust($postId);
            }
        }, 10, 1);

        // Custom bbjd write actions — no-ops until these are fired by future tasks.
        // Action names verified against grep output: none exist yet in the plugin src.
        add_action('bbjd_comment_voted',    fn($postId) => self::bust((int) $postId), 10, 1);
        add_action('bbjd_comment_pinned',   fn($postId) => self::bust((int) $postId), 10, 1);
        add_action('bbjd_comment_reacted',  fn($postId) => self::bust((int) $postId), 10, 1);
        add_action('bbjd_comment_reported', fn($postId) => self::bust((int) $postId), 10, 1);
    }
}
