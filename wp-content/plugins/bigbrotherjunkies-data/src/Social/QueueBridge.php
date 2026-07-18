<?php

namespace BigBrotherJunkies\Data\Social;

use BigBrotherJunkies\Data\Database\Schema;
use BigBrotherJunkies\Data\Utils\FrontendUrl;

/**
 * Bridges Social Intel into the Content Engine queue (bbj_content_queue +
 * the every-minute WP-Cron poster). Two new sources: 'social_quickie'
 * (card PNG + caption) and 'feed_update_share' (text+link drip of Steve's
 * own feed updates). Spacing targets ~3 posts/hour.
 *
 * Slot arithmetic runs on the DATABASE clock (NOW()): ContentEngineCron
 * fires rows where scheduled_at <= NOW(), so mixing in PHP/site timezones
 * here would skew fire times.
 */
class QueueBridge
{
    public const SLOT_MINUTES = 20;
    private const ENUM_FLAG   = 'bbjd_content_queue_source_v2';

    /**
     * One-time enum widening for installs created before this feature.
     * Self-healing: creates the table first (dbDelta) if it doesn't exist
     * yet, then ALTERs the enum, then VERIFIES both new values landed
     * before setting the flag — so a failed ALTER never gets silently
     * treated as done.
     */
    public static function ensureSourceEnum(): void
    {
        if (get_option(self::ENUM_FLAG)) {
            return;
        }
        global $wpdb;
        $table = Schema::table(Schema::TABLE_CONTENT_QUEUE);

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta(Schema::getContentQueueTableSchema());

        $wpdb->query("ALTER TABLE {$table} MODIFY source
            ENUM('manual','image_paste','news_scan','template','on_this_day','social_quickie','feed_update_share')
            DEFAULT 'manual'");

        $enumRow = $wpdb->get_row("SHOW COLUMNS FROM {$table} LIKE 'source'", ARRAY_A);
        $type    = (string) ($enumRow['Type'] ?? '');
        if (strpos($type, 'social_quickie') === false || strpos($type, 'feed_update_share') === false) {
            return;
        }
        update_option(self::ENUM_FLAG, 1, false);
    }

    /** Next open slot: SLOT_MINUTES after the last scheduled item, floor NOW()+5min. */
    public static function nextFreeSlot(): string
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_CONTENT_QUEUE);
        return (string) $wpdb->get_var(
            "SELECT DATE_FORMAT(GREATEST(
                COALESCE(
                    (SELECT MAX(scheduled_at) FROM {$table} WHERE status = 'scheduled') + INTERVAL " . self::SLOT_MINUTES . " MINUTE,
                    NOW()
                ),
                NOW() + INTERVAL 5 MINUTE
             ), '%Y-%m-%d %H:%i:%s')"
        );
    }

    /**
     * Queue a quickie card (base64 PNG + caption) for the next free slot.
     *
     * @param array{page_id:string,page_name?:string,message:string,image_data:string} $args
     * @return array{success:bool,id?:int,scheduled_at?:string,message?:string}
     */
    public static function enqueueQuickie(array $args): array
    {
        self::ensureSourceEnum();
        global $wpdb;
        $table  = Schema::table(Schema::TABLE_CONTENT_QUEUE);
        $slot   = self::nextFreeSlot();
        $result = $wpdb->insert($table, [
            'status'           => 'scheduled',
            'source'           => 'social_quickie',
            'content_type'     => 'facebook_post',
            'title'            => 'Quickie card',
            'body'             => (string) $args['message'],
            'image_data'       => (string) $args['image_data'],
            'target_page'      => (string) $args['page_id'],
            'target_page_name' => (string) ($args['page_name'] ?? ''),
            'scheduled_at'     => $slot,
            'author_id'        => get_current_user_id(),
        ]);
        if ($result === false) {
            return ['success' => false, 'message' => 'Queue insert failed' . ($wpdb->last_error ? ': ' . $wpdb->last_error : '')];
        }
        return ['success' => true, 'id' => (int) $wpdb->insert_id, 'scheduled_at' => $slot];
    }

    /**
     * Queue text+link FB shares of published feed updates, chained
     * SLOT_MINUTES apart from the next free slot. Already-queued/posted
     * updates (by wp_post_id) are skipped.
     *
     * Failed inserts are skipped (not counted toward the returned rows, slot
     * not advanced) and noted in the 'errors' key so the route can surface
     * a partial-failure response.
     *
     * @param int[] $postIds
     * @return array{queued:array<int,array{id:int,post_id:int,scheduled_at:string}>,errors:array<int,string>}
     */
    public static function enqueueFeedShares(array $postIds, string $pageId, string $pageName = ''): array
    {
        self::ensureSourceEnum();
        global $wpdb;
        $table  = Schema::table(Schema::TABLE_CONTENT_QUEUE);
        $out    = [];
        $errors = [];
        $slot   = self::nextFreeSlot();
        foreach ($postIds as $postId) {
            $post = get_post((int) $postId);
            if (!$post || $post->post_status !== 'publish' || $post->post_type !== 'live-feed-updates') {
                continue;
            }
            if (self::isShared($post->ID)) {
                continue;
            }
            // Feed-update titles are stored wp_encode_emoji'd (utf8mb3-safe
            // wp_posts); decode before the queue row so an emoji-heavy title
            // doesn't post to Facebook as a literal &#x1f5a4; entity string.
            $title  = html_entity_decode((string) $post->post_title, ENT_QUOTES, 'UTF-8');
            $result = $wpdb->insert($table, [
                'status'           => 'scheduled',
                'source'           => 'feed_update_share',
                'content_type'     => 'facebook_post',
                'title'            => $title,
                'body'             => $title,
                'source_url'       => FrontendUrl::forPost($post),
                'wp_post_id'       => $post->ID,
                'target_page'      => $pageId,
                'target_page_name' => $pageName,
                'scheduled_at'     => $slot,
                'author_id'        => get_current_user_id(),
            ]);
            if ($result === false) {
                $errors[] = "Post {$post->ID}: queue insert failed" . ($wpdb->last_error ? ': ' . $wpdb->last_error : '');
                continue;
            }
            $out[] = ['id' => (int) $wpdb->insert_id, 'post_id' => (int) $post->ID, 'scheduled_at' => $slot];
            $slot  = gmdate('Y-m-d H:i:s', strtotime($slot) + self::SLOT_MINUTES * MINUTE_IN_SECONDS);
        }
        return ['queued' => $out, 'errors' => $errors];
    }

    /**
     * Latest published feed updates with a shared/queued flag for the picker.
     *
     * @return array<int,array{id:int,title:string,published_gmt:string,url:string,shared:bool}>
     */
    public static function recentFeedUpdates(int $limit = 20): array
    {
        $posts = get_posts([
            'post_type'      => 'live-feed-updates',
            'post_status'    => 'publish',
            'posts_per_page' => max(1, min(50, $limit)),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        $out = [];
        foreach ($posts as $post) {
            $out[] = [
                'id'            => (int) $post->ID,
                'title'         => html_entity_decode((string) $post->post_title, ENT_QUOTES, 'UTF-8'),
                'published_gmt' => (string) $post->post_date_gmt,
                'url'           => FrontendUrl::forPost($post),
                'shared'        => self::isShared((int) $post->ID),
            ];
        }
        return $out;
    }

    /** Is this WP post already queued or posted as a feed_update_share? */
    private static function isShared(int $wpPostId): bool
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_CONTENT_QUEUE);
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
              WHERE source = 'feed_update_share' AND wp_post_id = %d
                AND status IN ('scheduled','posted')",
            $wpPostId
        )) > 0;
    }
}
