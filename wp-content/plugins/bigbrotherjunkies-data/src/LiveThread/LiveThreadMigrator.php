<?php

namespace BigBrotherJunkies\Data\LiveThread;

/**
 * One-time migration of posts that used the legacy `liveFeedThread` flag
 * (calendar-day feed-update embed). Converts each to a closed live-thread
 * matching its publish day.
 *
 * Idempotent — safe to run multiple times. Records completion in an option.
 *
 * Legacy meta key confirmed as `_bbjd_live_feed_thread` (PostSettingsMetaBox::META_KEY).
 * Note: HomeRoutes.php incorrectly queries `_live_feed_thread` (missing _bbjd_ prefix)
 * which means that field never returned data. The canonical write path uses
 * `_bbjd_live_feed_thread`, so that is what we migrate here.
 */
class LiveThreadMigrator
{
    private const FLAG_OPTION = 'bbjd_live_thread_migration_v1';
    private const LEGACY_META = '_bbjd_live_feed_thread';

    public static function maybeRun(): void
    {
        if ((int) get_option(self::FLAG_OPTION, 0) === 1) {
            return;
        }

        $posts = get_posts([
            'post_type'      => 'post',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => self::LEGACY_META,
                    'value'   => '1',
                    'compare' => '=',
                ],
            ],
            'fields' => 'ids',
        ]);

        $now = time();
        foreach ($posts as $postId) {
            $postDate = get_the_date('Y-m-d', $postId);
            $start = (int) strtotime($postDate . ' 00:00:00');
            $end   = (int) strtotime($postDate . ' 23:59:59');

            update_post_meta($postId, LiveThreadState::META_LIVE_UPDATES, 1);
            update_post_meta($postId, LiveThreadState::META_LIVE_START, $start);
            update_post_meta($postId, LiveThreadState::META_LIVE_END, $end);
            update_post_meta($postId, LiveThreadState::META_CLOSED_AT, $now);
        }

        update_option(self::FLAG_OPTION, 1, false);
    }
}
