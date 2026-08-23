<?php

namespace BigBrotherJunkies\Data\Editor;

/**
 * "BennyBronx" ghost byline for AI-drafted posts Steve reviews but didn't
 * write. Admin-only: the flag is saved and honored only for users with
 * manage_options. While drafting the post stays owned by the real editor
 * (autosave/ownership untouched); the author swaps to the ghost account at
 * publish/schedule time, and swaps back if the flag is cleared.
 */
class GhostAuthor
{
    public const OPTION       = 'bbjd_ghost_author_id';
    public const LOGIN        = 'bbj-ghostwriter';
    public const DISPLAY_NAME = 'BennyBronx';
    public const META_KEY     = '_bbjd_ghost_author';

    /** Pass $createIfMissing = false on read-only paths so they never create users. */
    public static function id(bool $createIfMissing = true): int
    {
        $id = (int) get_option(self::OPTION, 0);
        if ($id > 0 && get_userdata($id)) {
            return $id;
        }
        if (!$createIfMissing) {
            return 0;
        }
        $existing = get_user_by('login', self::LOGIN);
        if ($existing) {
            update_option(self::OPTION, (int) $existing->ID);
            return (int) $existing->ID;
        }
        $id = wp_insert_user([
            'user_login'   => self::LOGIN,
            'user_email'   => self::LOGIN . '@bigbrotherjunkies.com',
            'user_pass'    => wp_generate_password(32, true, true),
            'display_name' => self::DISPLAY_NAME,
            'role'         => 'subscriber',
        ]);
        if (is_wp_error($id)) {
            return 0;
        }
        update_option(self::OPTION, (int) $id);
        return (int) $id;
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
