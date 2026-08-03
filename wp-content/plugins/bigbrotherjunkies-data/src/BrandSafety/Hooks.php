<?php
namespace BigBrotherJunkies\Data\BrandSafety;

/**
 * Editorial save_post censoring + ads_unsafe flag/REST field.
 *
 * Censor-tier hits get masked in place (original preserved in postmeta for
 * admin review). An unmaskable (in-tag) hit flags the post ads_unsafe until
 * either a clean re-save clears it or an editor sets the manual override.
 */
class Hooks
{
    public const META_UNSAFE = '_bbjd_ads_unsafe';
    public const META_OVERRIDE = '_bbjd_ads_safe_override';
    public const META_ORIGINAL = '_bbjd_bs_original';
    public const POST_TYPES = ['post', 'page', 'live-feed-updates'];

    private static bool $saving = false; // recursion guard

    public function register(): void
    {
        add_action('save_post', [$this, 'onSavePost'], 20, 2);
        add_action('rest_api_init', [$this, 'registerRestFields']);
    }

    public function onSavePost(int $postId, \WP_Post $post): void
    {
        if (self::$saving || wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        if (!in_array($post->post_type, self::POST_TYPES, true)) {
            return;
        }
        self::scanAndCensorPost($postId);
    }

    /** Also called by Backfill. Censors content/title in place; sets/clears unsafe flag. */
    public static function scanAndCensorPost(int $postId): void
    {
        $post = get_post($postId);
        if (!$post) {
            return;
        }
        $content = Scanner::censor($post->post_content);
        // Titles are plain text — no HTML-tag semantics, so a literal '<'/'>'
        // in a title must never be parsed as markup (would wrongly leave a
        // hit unmasked and flag ads_unsafe via the unmaskable branch).
        $title = Scanner::censorText($post->post_title);
        $hits = array_merge($content['hits'], $title['hits']);
        $changed = $content['html'] !== $post->post_content || $title['html'] !== $post->post_title;

        if ($changed) {
            if (get_post_meta($postId, self::META_ORIGINAL, true) === '') {
                add_post_meta($postId, self::META_ORIGINAL, [
                    'content' => $post->post_content,
                    'title' => $post->post_title,
                ], true);
            }
            self::$saving = true;
            global $wpdb;
            // Direct update (not wp_update_post) to avoid re-firing save_post chains.
            $wpdb->update($wpdb->posts, [
                'post_content' => $content['html'],
                'post_title' => $title['html'],
            ], ['ID' => $postId], ['%s', '%s'], ['%d']);
            clean_post_cache($postId);
            self::$saving = false;
        }

        $unmaskable = $content['unmaskable'] || $title['unmaskable'];
        if ($unmaskable) {
            update_post_meta($postId, self::META_UNSAFE, '1');
        } else {
            delete_post_meta($postId, self::META_UNSAFE);
        }
        Log::record('post', $postId, $hits);
    }

    public static function isAdsUnsafe(int $postId): bool
    {
        if (get_post_meta($postId, self::META_OVERRIDE, true) === '1') {
            return false;
        }
        return get_post_meta($postId, self::META_UNSAFE, true) === '1';
    }

    /** Mirrors AdSettingsMetaBox::registerRestFields (hide_ads) — same pattern. */
    public function registerRestFields(): void
    {
        foreach (self::POST_TYPES as $postType) {
            register_rest_field($postType, 'ads_unsafe', [
                'get_callback' => fn($obj) => self::isAdsUnsafe((int) $obj['id']),
                'schema' => ['type' => 'boolean'],
            ]);
        }
    }
}
