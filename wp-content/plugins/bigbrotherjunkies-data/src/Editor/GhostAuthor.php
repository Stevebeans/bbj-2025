<?php

namespace BigBrotherJunkies\Data\Editor;

/**
 * "Bean Bot" byline for AI-drafted posts Steve reviews but didn't write, so
 * readers can see a bot wrote them. Reuses the `bean-bot` author Bean Bot
 * already posts as. Admin-only: the flag is saved and honored only for users
 * with manage_options. While drafting the post stays owned by the real editor
 * (autosave/ownership untouched); the author swaps to Bean Bot at
 * publish/schedule time, and swaps back if the flag is cleared.
 */
class GhostAuthor
{
    public const META_KEY = '_bbjd_ghost_author';

    /** Pass $createIfMissing = false on read-only paths so they never create users. */
    public static function id(bool $createIfMissing = true): int
    {
        if ($createIfMissing) {
            return \BigBrotherJunkies\Data\Social\BeanBotService::ensureUser();
        }
        $user = get_user_by('login', 'bean-bot');
        return $user ? (int) $user->ID : 0;
    }

    public static function canUse(): bool
    {
        return current_user_can('manage_options');
    }

    /** Save the flag from a request payload; silently ignored for non-admins. */
    public static function saveFlag(int $postId, $value): void
    {
        if (!self::canUse()) {
            return;
        }
        update_post_meta($postId, self::META_KEY, !empty($value) ? 1 : 0);
    }

    public static function isFlagged(int $postId): bool
    {
        return (int) get_post_meta($postId, self::META_KEY, true) === 1;
    }

    /**
     * Make post_author agree with the flag. Only ever called for
     * published/scheduled posts; the flag itself is admin-gated, so honoring
     * it here needs no extra capability check.
     */
    public static function syncAuthor(int $postId): void
    {
        $post = get_post($postId);
        if (!$post) {
            return;
        }
        if (self::isFlagged($postId)) {
            $ghost = self::id();
            if ($ghost > 0 && (int) $post->post_author !== $ghost) {
                wp_update_post(['ID' => $postId, 'post_author' => $ghost]);
            }
            return;
        }
        $ghost = self::id(false);
        if ($ghost > 0 && (int) $post->post_author === $ghost) {
            wp_update_post(['ID' => $postId, 'post_author' => get_current_user_id()]);
        }
    }
}
