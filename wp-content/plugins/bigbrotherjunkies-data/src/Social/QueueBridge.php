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
    /** Default minutes between queued posts; admin-adjustable via settings. */
    public const SLOT_MINUTES = 20;
    private const ENUM_FLAG   = 'bbjd_content_queue_source_v2';

    /**
     * Minutes between queued posts: the queue_spacing_minutes setting
     * (bbjd_social_monitor), clamped 5-180, falling back to SLOT_MINUTES.
     */
    public static function spacingMinutes(): int
    {
        $mins = (int) (SocialStore::settings()['queue_spacing_minutes'] ?? 0);
        return $mins > 0 ? max(5, min(180, $mins)) : self::SLOT_MINUTES;
    }

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


    /** Random shift applied to :00/:30 grid picks (seconds). */
    private const JITTER_MIN_S = 120;
    private const JITTER_MAX_S = 840;
    /** No two scheduled queue posts fire within this many seconds. */
    private const MIN_GAP_S = 1500;

    /**
     * De-clockwork a slot: grid picks (:00/:30 exactly) get a random 2-14
     * minute shift, then any result is pushed later until it sits >= 25
     * minutes from every other scheduled row. Exact-interval API posting is
     * a classic automation signature; whether or not Meta actually penalizes
     * it (bonus-program lore, 2026-08-22 - unverified), the insurance is
     * free and off-the-hour posting dodges the :00 scheduling rush anyway.
     */
    public static function humanizeSlot(string $at): string
    {
        $tz = new \DateTimeZone('UTC');
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $at, $tz);
        if ($dt === false) {
            return $at;
        }
        if ((int) $dt->format('i') % 30 === 0 && (int) $dt->format('s') === 0) {
            $dt = $dt->modify('+' . random_int(self::JITTER_MIN_S, self::JITTER_MAX_S) . ' seconds');
        }

        global $wpdb;
        $table  = Schema::table(Schema::TABLE_CONTENT_QUEUE);
        $others = [];
        foreach ($wpdb->get_col("SELECT scheduled_at FROM {$table} WHERE status = 'scheduled' AND scheduled_at IS NOT NULL") as $row) {
            $ts = strtotime($row . ' UTC');
            if ($ts !== false && $ts > 0) {
                $others[] = $ts;
            }
        }
        // Push strictly later past each conflict; finite conflicts => settles.
        for ($i = 0; $i < 25; $i++) {
            $ts = $dt->getTimestamp();
            $conflict = null;
            foreach ($others as $o) {
                if (abs($o - $ts) < self::MIN_GAP_S) {
                    $conflict = $o;
                    break;
                }
            }
            if ($conflict === null) {
                break;
            }
            $dt = $dt->setTimestamp($conflict + self::MIN_GAP_S + random_int(0, 240));
        }
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * Resolve the target slot for a queued item. An explicit, well-formed,
     * FUTURE UTC 'Y-m-d H:i:s' request is honored verbatim; anything empty,
     * malformed, or already in the past falls back to the next auto-slot.
     */
    public static function resolveSlot(?string $requested): string
    {
        return self::parseFutureUtc((string) ($requested ?? '')) ?? self::nextFreeSlot();
    }

    /**
     * Strict UTC 'Y-m-d H:i:s' parse that also requires a future time; returns
     * the canonical string on success, null on garbage / past. Mirrors
     * ContentEngineRoutes::parseUtc's round-trip strictness.
     */
    private static function parseFutureUtc(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $tz = new \DateTimeZone('UTC');
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $tz);
        if ($dt === false || $dt->format('Y-m-d H:i:s') !== $value) {
            return null;
        }
        if ($dt <= new \DateTimeImmutable('now', $tz)) {
            return null;
        }
        return $dt->format('Y-m-d H:i:s');
    }

    /** Next open slot: spacingMinutes() after the last scheduled item, floor NOW()+5min. */
    public static function nextFreeSlot(): string
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_CONTENT_QUEUE);
        return (string) $wpdb->get_var(
            "SELECT DATE_FORMAT(GREATEST(
                COALESCE(
                    (SELECT MAX(scheduled_at) FROM {$table} WHERE status = 'scheduled') + INTERVAL " . self::spacingMinutes() . " MINUTE,
                    NOW()
                ),
                NOW() + INTERVAL 5 MINUTE
             ), '%Y-%m-%d %H:%i:%s')"
        );
    }

    /**
     * Queue a quickie card (base64 PNG + caption). Uses the explicit
     * 'scheduled_at' slot when it's a valid future UTC time, else the next
     * auto-slot.
     *
     * @param array{page_id:string,page_name?:string,message:string,image_data:string,scheduled_at?:string} $args
     * @return array{success:bool,id?:int,scheduled_at?:string,message?:string}
     */
    public static function enqueueQuickie(array $args): array
    {
        self::ensureSourceEnum();
        global $wpdb;
        $table  = Schema::table(Schema::TABLE_CONTENT_QUEUE);
        $slot   = self::humanizeSlot(self::resolveSlot($args['scheduled_at'] ?? null));
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
     * spacingMinutes() apart from the next free slot. Already-queued/posted
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
        $out     = [];
        $errors  = [];
        $slot    = self::nextFreeSlot();
        $spacing = self::spacingMinutes();
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
            // Stamp the FB message with the update's original publish time
            // (site tz) — the drip can post these an hour+ later, and readers
            // should know the events aren't from right now.
            $published = get_post_datetime($post);
            $stamp     = $published ? '[Feed Update ' . $published->format('n/j g:ia') . '] ' : '';
            $store  = self::humanizeSlot($slot);
            $result = $wpdb->insert($table, [
                'status'           => 'scheduled',
                'source'           => 'feed_update_share',
                'content_type'     => 'facebook_post',
                'title'            => $title,
                'body'             => $stamp . $title,
                'source_url'       => FrontendUrl::forPost($post),
                'wp_post_id'       => $post->ID,
                'target_page'      => $pageId,
                'target_page_name' => $pageName,
                'scheduled_at'     => $store,
                'author_id'        => get_current_user_id(),
            ]);
            if ($result === false) {
                $errors[] = "Post {$post->ID}: queue insert failed" . ($wpdb->last_error ? ': ' . $wpdb->last_error : '');
                continue;
            }
            $out[] = ['id' => (int) $wpdb->insert_id, 'post_id' => (int) $post->ID, 'scheduled_at' => $store];
            // Advance the chain from the jittered stored time so spacing stays real.
            $slot  = gmdate('Y-m-d H:i:s', strtotime($store) + $spacing * MINUTE_IN_SECONDS);
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
