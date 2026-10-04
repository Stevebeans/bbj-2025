<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Data layer + poller for the Bluesky Feed-Intel monitor: settings, post
 * ingest, recurring poll (trusted author feeds + a hashtag search), pruning,
 * and the summary window / storage read by Task 3's summarizer.
 *
 * House rules honored here:
 *  - Stored post text and display_name pass through wp_encode_emoji()
 *    (prod/staging wp_* is utf8mb3; a raw 4-byte emoji fails the INSERT).
 *  - Bluesky timestamps are stored as UTC DATETIME; "now" bookkeeping uses
 *    current_time('mysql', true) / gmdate() so pruning stays in one timezone.
 *  - No Revalidation / cache-tag / CF-purge calls; the admin page is
 *    client-fetched (cost guardrail).
 */
class SocialStore
{
    public const OPTION            = 'bbjd_social_monitor';
    public const DB_VERSION_OPTION = 'bbj_social_db_version';
    public const DB_VERSION        = '1.4.0';

    /** Action Scheduler recurring hook + group for the poller. */
    public const POLL_HOOK = 'bbjd_social_poll';
    public const AS_GROUP  = 'bbj-social';

    /** Minimum poll cadence (minutes) to stay polite to the public AppView. */
    public const MIN_POLL_MINUTES = 5;

    /** Retention: posts older than this (days) are pruned each poll. */
    public const PRUNE_DAYS = 21;

    /** Prompt-size guards for the unsummarized window. */
    public const CAP_TRUSTED = 400;
    public const CAP_TAG     = 600;

    /** Admin-selectable Claude models (exact API ids). */
    public const MODELS = ['claude-sonnet-5', 'claude-haiku-4-5', 'claude-opus-4-8'];

    /** Settings defaults; enabled=false so a fresh deploy never auto-polls. */
    public const DEFAULTS = [
        'enabled'           => false,
        'tag'               => '#BB28',
        'handles'           => [],
        'poll_minutes'      => 10,
        'model'             => 'claude-sonnet-5',
        'anthropic_api_key' => '',
        'cbs_slots'         => [],
        // Minutes between queued FB posts (quickies + feed-update shares).
        'queue_spacing_minutes' => 20,
        // "Nick = Real Name" lines mapping community nicknames to houseguests.
        'fan_nicknames'         => [],
        // Per-show Bluesky sources beyond the default BB tag/handles above
        // (Survivor first). Each row: {show, tag, handles[]}. Polled by the
        // same global schedule; see poll().
        'show_sources'          => [],
    ];

    // -----------------------------------------------------------------------
    // Migration
    // -----------------------------------------------------------------------

    public static function maybeMigrate(): void
    {
        if (version_compare(get_option(self::DB_VERSION_OPTION, '0'), self::DB_VERSION, '>=')) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach (SocialSchema::getAllSchemas() as $schema) {
            dbDelta($schema);
        }
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    // -----------------------------------------------------------------------
    // Settings
    // -----------------------------------------------------------------------

    /** Stored settings merged over defaults. Includes the raw api key. */
    public static function settings(): array
    {
        $stored = get_option(self::OPTION, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        return array_merge(self::DEFAULTS, $stored);
    }

    /**
     * Settings shaped for the REST layer: the raw key is replaced by a boolean
     * `anthropic_api_key_set` and NEVER returned.
     */
    public static function settingsForApi(): array
    {
        $s   = self::settings();
        $key = (string) ($s['anthropic_api_key'] ?? '');
        unset($s['anthropic_api_key']);
        $s['anthropic_api_key_set'] = ($key !== '');
        return $s;
    }

    /**
     * Whitelist + sanitize a settings patch, persist it, return the merged
     * settings. The api key is only overwritten when a non-empty value is sent.
     */
    public static function saveSettings(array $patch): array
    {
        $current = self::settings();

        if (array_key_exists('enabled', $patch)) {
            $current['enabled'] = (bool) $patch['enabled'];
        }
        if (array_key_exists('tag', $patch)) {
            $current['tag'] = self::normalizeTag((string) $patch['tag']);
        }
        if (array_key_exists('handles', $patch)) {
            $current['handles'] = self::normalizeHandles($patch['handles']);
        }
        if (array_key_exists('poll_minutes', $patch)) {
            $current['poll_minutes'] = max(self::MIN_POLL_MINUTES, (int) $patch['poll_minutes']);
        }
        if (array_key_exists('model', $patch)) {
            $model = (string) $patch['model'];
            $current['model'] = in_array($model, self::MODELS, true) ? $model : self::DEFAULTS['model'];
        }
        if (array_key_exists('anthropic_api_key', $patch)) {
            $key = trim((string) $patch['anthropic_api_key']);
            if ($key !== '') {
                $current['anthropic_api_key'] = $key;
            }
        }
        if (array_key_exists('cbs_slots', $patch)) {
            $current['cbs_slots'] = self::normalizeCbsSlots($patch['cbs_slots']);
        }
        if (array_key_exists('queue_spacing_minutes', $patch)) {
            $mins = (int) $patch['queue_spacing_minutes'];
            $current['queue_spacing_minutes'] = $mins > 0 ? max(5, min(180, $mins)) : 20;
        }
        if (array_key_exists('fan_nicknames', $patch)) {
            $current['fan_nicknames'] = self::normalizeFanNicknames($patch['fan_nicknames']);
        }
        if (array_key_exists('show_sources', $patch)) {
            $current['show_sources'] = self::normalizeShowSources($patch['show_sources']);
        }

        update_option(self::OPTION, $current);
        return $current;
    }

    /**
     * Whitelist + sanitize per-show Bluesky sources (Survivor, etc). Each row
     * needs a non-empty `show` slug (sanitize_title); `tag`/`handles` reuse
     * the same normalizers as the default BB fields. Max 5 sources.
     *
     * @param mixed $rows
     * @return array<int,array{show:string,tag:string,handles:string[]}>
     */
    private static function normalizeShowSources($rows): array
    {
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $show = sanitize_title((string) ($row['show'] ?? ''));
            if ($show === '') {
                continue;
            }
            $out[] = [
                'show'    => $show,
                'tag'     => self::normalizeTag((string) ($row['tag'] ?? '')),
                'handles' => self::normalizeHandles($row['handles'] ?? []),
            ];
            if (count($out) >= 5) {
                break;
            }
        }
        return $out;
    }

    /**
     * Normalize fan-nickname entries to "Nick = Real Name" lines. Accepts an
     * array of lines or one newline-separated string; invalid lines drop.
     * wp_encode_emoji so an emoji nickname can't fail the whole option write
     * on utf8mb3 (decoded again at prompt-injection time).
     */
    private static function normalizeFanNicknames($raw): array
    {
        $lines = is_array($raw) ? $raw : explode("\n", (string) $raw);
        $out = [];
        foreach ($lines as $line) {
            $line = sanitize_text_field((string) $line);
            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }
            [$nick, $name] = array_map('trim', explode('=', $line, 2));
            if ($nick === '' || $name === '' || mb_strlen($nick) > 40 || mb_strlen($name) > 60) {
                continue;
            }
            $out[] = wp_encode_emoji("{$nick} = {$name}");
            if (count($out) >= 100) {
                break;
            }
        }
        return array_values(array_unique($out));
    }

    /** Normalize a tag to a single leading '#'. Empty stays empty. */
    private static function normalizeTag(string $tag): string
    {
        $tag = trim($tag);
        $tag = trim(ltrim($tag, '#'));
        return $tag === '' ? '' : '#' . $tag;
    }

    /**
     * Normalize handles: trim, strip a leading '@', lowercase, drop blanks,
     * dedupe (order preserved).
     *
     * @param mixed $handles
     * @return string[]
     */
    private static function normalizeHandles($handles): array
    {
        if (!is_array($handles)) {
            return [];
        }
        $out = [];
        foreach ($handles as $h) {
            if (!is_string($h)) {
                continue;
            }
            $h = strtolower(ltrim(trim($h), '@'));
            if ($h !== '' && !in_array($h, $out, true)) {
                $out[] = $h;
            }
        }
        return $out;
    }

    /**
     * Whitelist CBS slots: day 0-6 (0=Sunday), time_et HH:MM 24h, duration
     * clamped 30-180 min (default 60). Max 7 slots.
     *
     * @param mixed $slots
     */
    private static function normalizeCbsSlots($slots): array
    {
        if (!is_array($slots)) {
            return [];
        }
        $out = [];
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $day  = (int) ($slot['day'] ?? -1);
            $time = trim((string) ($slot['time_et'] ?? ''));
            $dur  = (int) ($slot['duration_min'] ?? 0);
            if ($day < 0 || $day > 6 || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
                continue;
            }
            // $dur ?: 60 — a 0/unset duration falls back to the 60-min default.
            $out[] = ['day' => $day, 'time_et' => $time, 'duration_min' => max(30, min(180, $dur ?: 60))];
        }
        return array_slice($out, 0, 7);
    }

    // -----------------------------------------------------------------------
    // Scheduling (Action Scheduler)
    // -----------------------------------------------------------------------

    /**
     * Idempotent init-time guard: if enabled and nothing is scheduled, schedule
     * the recurring poll. Interval changes are handled by reschedule() on save.
     */
    public static function ensureScheduled(): void
    {
        if (!function_exists('as_schedule_recurring_action') || !function_exists('as_next_scheduled_action')) {
            return;
        }
        $settings = self::settings();
        if (empty($settings['enabled'])) {
            return;
        }
        if (as_next_scheduled_action(self::POLL_HOOK) === false) {
            self::scheduleRecurring($settings);
        }
    }

    /**
     * Clear any scheduled poll and re-schedule from the current settings.
     * Called after a config save so a changed interval or disabled flag takes
     * effect immediately.
     */
    public static function reschedule(): void
    {
        if (!function_exists('as_unschedule_all_actions')) {
            return;
        }
        as_unschedule_all_actions(self::POLL_HOOK, [], self::AS_GROUP);

        $settings = self::settings();
        if (empty($settings['enabled']) || !function_exists('as_schedule_recurring_action')) {
            return;
        }
        self::scheduleRecurring($settings);
    }

    /** Schedule the recurring poll action from the given settings' poll_minutes. */
    private static function scheduleRecurring(array $settings): void
    {
        $interval = max(self::MIN_POLL_MINUTES, (int) $settings['poll_minutes']) * 60;
        as_schedule_recurring_action(time() + $interval, $interval, self::POLL_HOOK, [], self::AS_GROUP);
    }

    // -----------------------------------------------------------------------
    // Ingest
    // -----------------------------------------------------------------------

    /**
     * Insert normalized BlueskyParser rows. utf8mb3-safe (emoji encoded).
     * INSERT ... ON DUPLICATE KEY on uniq_uri: a duplicate uri arriving as
     * 'trusted' upgrades an existing 'tag' row (trusted wins); otherwise the
     * existing row is left untouched.
     *
     * $show tags the row's show_slug ('' = BB, the default for every existing
     * caller). Note: uniq_uri means a post seen by both the BB config and a
     * show config (e.g. a shared tag/handle) keeps its FIRST attribution on a
     * duplicate uri - the ON DUPLICATE KEY branch below never touches
     * show_slug, so a later poll can't reclassify an already-stored post.
     *
     * @return int number of rows actually inserted (updates not counted)
     */
    public static function insertPosts(array $rows, string $source, string $show = ''): int
    {
        global $wpdb;

        $source = $source === 'trusted' ? 'trusted' : 'tag';
        $table  = SocialSchema::table(SocialSchema::TABLE_POSTS);
        $now    = current_time('mysql', true); // UTC
        $inserted = 0;

        foreach ($rows as $row) {
            if (empty($row['uri']) || empty($row['handle']) || empty($row['text']) || empty($row['posted_at'])) {
                continue;
            }

            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$table}
                    (source, uri, cid, handle, display_name, text, posted_at, fetched_at, summarized,
                     likes, reposts, replies, quotes, show_slug)
                 VALUES (%s, %s, %s, %s, %s, %s, %s, %s, 0, %d, %d, %d, %d, %s)
                 ON DUPLICATE KEY UPDATE
                    source  = IF(VALUES(source) = 'trusted', 'trusted', source),
                    likes   = VALUES(likes),
                    reposts = VALUES(reposts),
                    replies = VALUES(replies),
                    quotes  = VALUES(quotes)",
                $source,
                (string) $row['uri'],
                (string) ($row['cid'] ?? ''),
                (string) $row['handle'],
                wp_encode_emoji((string) ($row['display_name'] ?? '')),
                wp_encode_emoji((string) $row['text']),
                (string) $row['posted_at'],
                $now,
                (int) ($row['likes'] ?? 0),
                (int) ($row['reposts'] ?? 0),
                (int) ($row['replies'] ?? 0),
                (int) ($row['quotes'] ?? 0),
                $show
            ));

            // 1 = fresh insert, 2 = existing row updated (source upgrade or count refresh), 0 = identical duplicate. Only 1 is new.
            if ($result === 1) {
                $inserted++;
            }
        }

        return $inserted;
    }

    // -----------------------------------------------------------------------
    // Poll
    // -----------------------------------------------------------------------

    /**
     * Run one poll: each trusted handle's author feed, then the hashtag search,
     * then prune. Each source is wrapped so one failing handle cannot abort the
     * poll; failures are error_log'd and surfaced in the result.
     *
     * @return array{skipped:bool,trusted?:int,tag?:int,pruned?:int,errors?:string[]}
     */
    public static function poll(): array
    {
        $settings = self::settings();
        if (empty($settings['enabled'])) {
            return ['skipped' => true];
        }

        $result = [
            'skipped' => false,
            'trusted' => 0,
            'tag'     => 0,
            'pruned'  => 0,
            'errors'  => [],
        ];

        foreach ((array) $settings['handles'] as $handle) {
            try {
                $feed = BlueskyReader::authorFeed((string) $handle);
                $result['trusted'] += self::insertPosts($feed['rows'], 'trusted');
                if ($feed['error'] !== null) {
                    $result['errors'][] = $feed['error'];
                }
            } catch (\Throwable $e) {
                $msg = 'author feed ' . $handle . ': ' . $e->getMessage();
                error_log('[BBJ Social] poll ' . $msg);
                $result['errors'][] = $msg;
            }
        }

        try {
            $tag = (string) $settings['tag'];
            if ($tag !== '') {
                $feed = BlueskyReader::search($tag);
                $result['tag'] += self::insertPosts($feed['rows'], 'tag');
                if ($feed['error'] !== null) {
                    $result['errors'][] = $feed['error'];
                }
            }
        } catch (\Throwable $e) {
            $msg = 'search ' . (string) $settings['tag'] . ': ' . $e->getMessage();
            error_log('[BBJ Social] poll ' . $msg);
            $result['errors'][] = $msg;
        }

        // Per-show sources (Survivor, etc): same trusted-handles + tag-search
        // shape as the BB loop above, tagged with the show's slug. Polling
        // cadence stays global - this just grows the one poll() run.
        foreach ((array) $settings['show_sources'] as $src) {
            $show = (string) $src['show'];
            foreach ((array) $src['handles'] as $handle) {
                try {
                    $feed = BlueskyReader::authorFeed((string) $handle);
                    $result['trusted'] += self::insertPosts($feed['rows'], 'trusted', $show);
                    if ($feed['error'] !== null) {
                        $result['errors'][] = $show . '/' . $handle . ': ' . $feed['error'];
                    }
                } catch (\Throwable $e) {
                    $msg = $show . '/' . $handle . ': ' . $e->getMessage();
                    error_log('[BBJ Social] poll ' . $msg);
                    $result['errors'][] = $msg;
                }
            }

            $tag = (string) $src['tag'];
            if ($tag !== '') {
                try {
                    $feed = BlueskyReader::search($tag);
                    $result['tag'] += self::insertPosts($feed['rows'], 'tag', $show);
                    if ($feed['error'] !== null) {
                        $result['errors'][] = $show . '/tag: ' . $feed['error'];
                    }
                } catch (\Throwable $e) {
                    $msg = $show . '/tag: ' . $e->getMessage();
                    error_log('[BBJ Social] poll ' . $msg);
                    $result['errors'][] = $msg;
                }
            }
        }

        $result['pruned'] = self::prune();

        return $result;
    }

    /** Delete posts older than PRUNE_DAYS (UTC). Returns rows removed. */
    private static function prune(): int
    {
        global $wpdb;
        $table  = SocialSchema::table(SocialSchema::TABLE_POSTS);
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::PRUNE_DAYS * DAY_IN_SECONDS);
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE posted_at < %s",
            $cutoff
        ));
        return is_int($deleted) ? $deleted : 0;
    }

    // -----------------------------------------------------------------------
    // Reads
    // -----------------------------------------------------------------------

    /**
     * Paged post list for the admin UI, newest first.
     *
     * @param array{source?:?string,search?:?string,show?:?string,page?:int,per_page?:int} $args
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public static function posts(array $args): array
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_POSTS);

        $source  = $args['source'] ?? null;
        $search  = $args['search'] ?? null;
        $show    = $args['show'] ?? null;
        $page    = max(1, (int) ($args['page'] ?? 1));
        $perPage = (int) ($args['per_page'] ?? 50);
        if ($perPage <= 0) {
            $perPage = 50;
        }
        $perPage = min(200, $perPage);
        $offset  = ($page - 1) * $perPage;

        $where  = [];
        $params = [];
        if ($source === 'trusted' || $source === 'tag') {
            $where[]  = 'source = %s';
            $params[] = $source;
        }
        if (is_string($search) && trim($search) !== '') {
            $where[]  = 'text LIKE %s';
            $params[] = '%' . $wpdb->esc_like(trim($search)) . '%';
        }
        if (is_string($show)) {
            $where[]  = 'show_slug = %s';
            $params[] = $show;
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countSql = "SELECT COUNT(*) FROM {$table} {$whereSql}";
        $total = (int) ($params
            ? $wpdb->get_var($wpdb->prepare($countSql, $params))
            : $wpdb->get_var($countSql));

        $listSql    = "SELECT * FROM {$table} {$whereSql} ORDER BY posted_at DESC, id DESC LIMIT %d OFFSET %d";
        $listParams = array_merge($params, [$perPage, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($listSql, $listParams), ARRAY_A) ?: [];

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Unsummarized posts split by source for the summarizer. Trusted capped at
     * the newest CAP_TRUSTED, tag at CAP_TAG (caps guard prompt size; a cap hit
     * is logged). `from` = oldest included post or null; `to` = newest included
     * post or now.
     *
     * @return array{from:?string,to:string,trusted:array<int,array<string,mixed>>,tag:array<int,array<string,mixed>>}
     */
    public static function unsummarizedWindow(): array
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_POSTS);

        $trusted = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE summarized = 0 AND source = 'trusted'
              ORDER BY posted_at DESC, id DESC LIMIT %d",
            self::CAP_TRUSTED
        ), ARRAY_A) ?: [];

        $tag = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE summarized = 0 AND source = 'tag'
              ORDER BY posted_at DESC, id DESC LIMIT %d",
            self::CAP_TAG
        ), ARRAY_A) ?: [];

        if (count($trusted) >= self::CAP_TRUSTED) {
            $totalTrusted = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table} WHERE summarized = 0 AND source = 'trusted'"
            );
            if ($totalTrusted > self::CAP_TRUSTED) {
                error_log("[BBJ Social] unsummarizedWindow: trusted capped at " . self::CAP_TRUSTED . " of {$totalTrusted}");
            }
        }
        if (count($tag) >= self::CAP_TAG) {
            $totalTag = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table} WHERE summarized = 0 AND source = 'tag'"
            );
            if ($totalTag > self::CAP_TAG) {
                error_log("[BBJ Social] unsummarizedWindow: tag capped at " . self::CAP_TAG . " of {$totalTag}");
            }
        }

        $times = array_merge(
            array_column($trusted, 'posted_at'),
            array_column($tag, 'posted_at')
        );
        // Timestamps are zero-padded 'Y-m-d H:i:s', so string min/max = chronological.
        $from = $times ? min($times) : null;
        $to   = $times ? max($times) : current_time('mysql', true);

        return [
            'from'    => $from,
            'to'      => $to,
            'trusted' => $trusted,
            'tag'     => $tag,
        ];
    }

    /**
     * Posts with posted_at BETWEEN fromUtc and toUtc, split by source, for the
     * Task 3 draft generators. Trusted capped at the newest CAP_TRUSTED, tag at
     * CAP_TAG (same caps + capped-logging as unsummarizedWindow). READ-ONLY:
     * unlike unsummarizedWindow(), this never filters or updates the
     * `summarized` flag, so it cannot disturb the digest's or Bean Bot's cursor.
     * Matches show_slug exactly ('' = BB), so a BB draft never reads Survivor
     * rows and vice versa.
     *
     * @return array{trusted:array<int,array<string,mixed>>,tag:array<int,array<string,mixed>>}
     */
    public static function postsInWindow(string $fromUtc, string $toUtc, string $show = ''): array
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_POSTS);

        $trusted = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE source = 'trusted' AND show_slug = %s AND posted_at BETWEEN %s AND %s
              ORDER BY posted_at DESC, id DESC LIMIT %d",
            $show,
            $fromUtc,
            $toUtc,
            self::CAP_TRUSTED
        ), ARRAY_A) ?: [];

        $tag = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE source = 'tag' AND show_slug = %s AND posted_at BETWEEN %s AND %s
              ORDER BY posted_at DESC, id DESC LIMIT %d",
            $show,
            $fromUtc,
            $toUtc,
            self::CAP_TAG
        ), ARRAY_A) ?: [];

        if (count($trusted) >= self::CAP_TRUSTED) {
            $totalTrusted = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE source = 'trusted' AND show_slug = %s AND posted_at BETWEEN %s AND %s",
                $show,
                $fromUtc,
                $toUtc
            ));
            if ($totalTrusted > self::CAP_TRUSTED) {
                error_log("[BBJ Social] postsInWindow: trusted capped at " . self::CAP_TRUSTED . " of {$totalTrusted}");
            }
        }
        if (count($tag) >= self::CAP_TAG) {
            $totalTag = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE source = 'tag' AND show_slug = %s AND posted_at BETWEEN %s AND %s",
                $show,
                $fromUtc,
                $toUtc
            ));
            if ($totalTag > self::CAP_TAG) {
                error_log("[BBJ Social] postsInWindow: tag capped at " . self::CAP_TAG . " of {$totalTag}");
            }
        }

        return [
            'trusted' => $trusted,
            'tag'     => $tag,
        ];
    }

    /** All posts (both sources) inside a UTC window, chronological. Transcript read. */
    public static function postsForDay(string $fromUtc, string $toUtc, int $limit = 1000): array
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_POSTS);
        $limit = max(1, min(2000, $limit));
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, source, handle, display_name, text, posted_at FROM {$table}
              WHERE posted_at >= %s AND posted_at < %s
              ORDER BY posted_at ASC, id ASC LIMIT %d",
            $fromUtc,
            $toUtc,
            $limit
        ), ARRAY_A) ?: [];
    }

    /**
     * Curated day read for the transcript/quote generators: EVERY trusted
     * post in the window plus the top $tagTop tag posts by engagement
     * (likes + 2*reposts), merged back into chronological order.
     *
     * Replaces postsForDay() for generation: at BB28-peak volume
     * (~7.7K posts/day) the old first-N-chronological read filled its cap
     * by ~4 AM and silently truncated the rest of the day (2026-07-18).
     * Good quotes travel — high-engagement tag posts carry them without
     * scanning thousands of dead posts.
     *
     * @return array<int,array<string,mixed>> same row shape as postsForDay()
     */
    public static function postsForDayCurated(string $fromUtc, string $toUtc, int $tagTop = 500, int $trustedCap = 1500): array
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_POSTS);

        $trusted = $wpdb->get_results($wpdb->prepare(
            "SELECT id, source, handle, display_name, text, posted_at FROM {$table}
              WHERE source = 'trusted' AND posted_at >= %s AND posted_at < %s
              ORDER BY posted_at ASC, id ASC LIMIT %d",
            $fromUtc,
            $toUtc,
            max(1, min(3000, $trustedCap))
        ), ARRAY_A) ?: [];

        $tag = $wpdb->get_results($wpdb->prepare(
            "SELECT id, source, handle, display_name, text, posted_at FROM {$table}
              WHERE source = 'tag' AND posted_at >= %s AND posted_at < %s
              ORDER BY (likes + 2 * reposts) DESC, posted_at ASC, id ASC LIMIT %d",
            $fromUtc,
            $toUtc,
            max(1, min(2000, $tagTop))
        ), ARRAY_A) ?: [];

        $rows = array_merge($trusted, $tag);
        usort($rows, static function (array $a, array $b): int {
            return [$a['posted_at'], (int) $a['id']] <=> [$b['posted_at'], (int) $b['id']];
        });
        return $rows;
    }

    /**
     * Trending: posts from the last 2 hours ranked by engagement VELOCITY
     * (score per minute of age, 10-minute floor so brand-new posts with a
     * couple of likes don't dominate; >=5 interactions to clear noise).
     * Surfaces young-but-climbing posts before they win the day board.
     * Airing-window posts are flagged and downweighted like topPosts().
     *
     * $show filters to one show's posts ('' = BB, the default; matches
     * show_slug exactly, so a Survivor board never mixes in BB rows).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function trendingPosts(int $limit, string $show = ''): array
    {
        global $wpdb;
        $table  = SocialSchema::table(SocialSchema::TABLE_POSTS);
        $limit  = max(1, min(50, $limit));
        $cutoff = gmdate('Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *, (likes + 2 * reposts) AS score,
                    (likes + 2 * reposts) / GREATEST(TIMESTAMPDIFF(MINUTE, posted_at, UTC_TIMESTAMP()), 10) AS velocity
               FROM {$table}
              WHERE posted_at >= %s AND (likes + 2 * reposts) >= 5 AND show_slug = %s
              ORDER BY velocity DESC, posted_at DESC LIMIT %d",
            $cutoff,
            $show,
            $limit * 3
        ), ARRAY_A) ?: [];

        $utc = new \DateTimeZone('UTC');
        foreach ($rows as &$row) {
            $in = PromptContext::isCautionWindow(new \DateTimeImmutable((string) $row['posted_at'], $utc));
            $row['score']            = (int) $row['score'];
            $row['velocity']         = round((float) $row['velocity'], 2);
            $row['in_airing_window'] = $in;
            $row['effective_score']  = (float) $row['velocity'] * ($in ? 0.3 : 1.0);
        }
        unset($row);
        usort($rows, static function (array $a, array $b): int {
            return $b['effective_score'] <=> $a['effective_score'];
        });
        return array_slice($rows, 0, $limit);
    }

    /**
     * Engagement-ranked posts for the Top Posts board. Raw score =
     * likes + 2*reposts. Posts inside a CBS caution window are flagged and
     * downweighted to 30% so episode-reaction spikes don't bury feed intel;
     * we fetch 3x the limit by raw score, re-rank, then slice.
     *
     * $show filters to one show's posts ('' = BB, the default; matches
     * show_slug exactly, so a Survivor board never mixes in BB rows).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function topPosts(int $hours, int $limit, string $show = ''): array
    {
        global $wpdb;
        $table  = SocialSchema::table(SocialSchema::TABLE_POSTS);
        $hours  = in_array($hours, [6, 12, 24], true) ? $hours : 24;
        $limit  = max(1, min(50, $limit));
        $cutoff = gmdate('Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *, (likes + 2 * reposts) AS score FROM {$table}
              WHERE posted_at >= %s AND show_slug = %s
              ORDER BY score DESC, posted_at DESC LIMIT %d",
            $cutoff,
            $show,
            $limit * 3
        ), ARRAY_A) ?: [];

        $utc = new \DateTimeZone('UTC');
        foreach ($rows as &$row) {
            $in = PromptContext::isCautionWindow(new \DateTimeImmutable((string) $row['posted_at'], $utc));
            $row['score']             = (int) $row['score'];
            $row['in_airing_window']  = $in;
            $row['effective_score']   = $row['score'] * ($in ? 0.3 : 1.0);
        }
        unset($row);
        usort($rows, static function (array $a, array $b): int {
            return $b['effective_score'] <=> $a['effective_score'];
        });
        return array_slice($rows, 0, $limit);
    }

    /**
     * Trusted-source rows past a cursor id, oldest first, for the Bean Bot
     * cycle. Only id/handle/text/posted_at (text stays entity-encoded; the
     * caller decodes before sending to Claude).
     *
     * @return array<int,array{id:string,handle:string,text:string,posted_at:string}>
     */
    public static function trustedPostsSince(int $afterId, ?string $sincePostedAt = null, int $limit = 200): array
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_POSTS);
        $limit = max(1, min(500, $limit));
        $where = "source = 'trusted' AND id > %d";
        $args  = [$afterId];
        if ($sincePostedAt !== null) {
            $where .= ' AND posted_at >= %s';
            $args[] = $sincePostedAt;
        }
        $args[] = $limit;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, handle, text, posted_at FROM {$table}
              WHERE {$where}
              ORDER BY id ASC LIMIT %d",
            ...$args
        ), ARRAY_A) ?: [];
    }

    /** Flag the given post ids as summarized. */
    public static function markSummarized(array $ids): void
    {
        global $wpdb;
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return;
        }
        $table = SocialSchema::table(SocialSchema::TABLE_POSTS);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET summarized = 1 WHERE id IN ({$placeholders})",
            $ids
        ));
    }

    /**
     * Store a generated summary or draft. Content is emoji-encoded
     * (utf8mb3-safe). `kind` defaults to 'digest' (the summarizer's row shape,
     * kept byte-identical) but Task 3's DraftService passes 'facebook'/'blog',
     * and QuoteService passes 'quote_cand' (shortened from "quote_candidates"
     * to fit the kind column's VARCHAR(12); see QuoteService::KIND).
     *
     * @param array{kind?:string,window_from?:?string,window_to?:?string,model?:string,post_count?:int,trusted_count?:int,content?:string} $row
     * @return int inserted summary id
     */
    public static function insertSummary(array $row): int
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_SUMMARIES);
        $now   = current_time('mysql', true);

        $kind = (string) ($row['kind'] ?? 'digest');
        if (!in_array($kind, ['digest', 'facebook', 'blog', 'blog_surv', 'quote_cand', 'house_tx'], true)) {
            $kind = 'digest';
        }

        $wpdb->insert($table, [
            'created_at'    => $now,
            'kind'          => $kind,
            'window_from'   => $row['window_from'] ?? $now,
            'window_to'     => $row['window_to'] ?? $now,
            'model'         => (string) ($row['model'] ?? ''),
            'post_count'    => (int) ($row['post_count'] ?? 0),
            'trusted_count' => (int) ($row['trusted_count'] ?? 0),
            'content'       => wp_encode_emoji((string) ($row['content'] ?? '')),
        ], ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']);

        return (int) $wpdb->insert_id;
    }

    /**
     * Most recent summaries/drafts, newest first. When $kind is given
     * ('digest'|'facebook'|'blog'), only rows of that kind are returned.
     */
    public static function summaries(int $limit = 20, ?string $kind = null): array
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_SUMMARIES);
        $limit = max(1, min(100, $limit));

        if ($kind !== null) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE kind = %s ORDER BY created_at DESC, id DESC LIMIT %d",
                $kind,
                $limit
            ), ARRAY_A) ?: [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d",
            $limit
        ), ARRAY_A) ?: [];
    }

    /**
     * Summaries whose window OVERLAPS [$fromUtc, $toUtc), oldest first, so a
     * caller can replay a date range (e.g. the week-summary generator feeding
     * a week's digests/recaps to Claude) in chronological order.
     *
     * @param list<string> $kinds subset of the kind enum; empty = all kinds
     */
    public static function summariesInWindow(string $fromUtc, string $toUtc, array $kinds = [], int $limit = 20): array
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_SUMMARIES);
        $limit = max(1, min(100, $limit));

        $kindSql = '';
        $params  = [$toUtc, $fromUtc];
        if ($kinds !== []) {
            $kindSql  = ' AND kind IN (' . implode(',', array_fill(0, count($kinds), '%s')) . ')';
            $params   = array_merge($params, array_map('strval', $kinds));
        }
        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
              WHERE window_from < %s AND window_to > %s{$kindSql}
              ORDER BY window_from ASC, id ASC LIMIT %d",
            $params
        ), ARRAY_A) ?: [];
    }
}
