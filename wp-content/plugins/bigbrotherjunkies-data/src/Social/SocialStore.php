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
    public const DB_VERSION        = '1.0.0';

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

        update_option(self::OPTION, $current);
        return $current;
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
     * @return int number of rows actually inserted (updates not counted)
     */
    public static function insertPosts(array $rows, string $source): int
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
                    (source, uri, cid, handle, display_name, text, posted_at, fetched_at, summarized)
                 VALUES (%s, %s, %s, %s, %s, %s, %s, %s, 0)
                 ON DUPLICATE KEY UPDATE source = IF(VALUES(source) = 'trusted', 'trusted', source)",
                $source,
                (string) $row['uri'],
                (string) ($row['cid'] ?? ''),
                (string) $row['handle'],
                wp_encode_emoji((string) ($row['display_name'] ?? '')),
                wp_encode_emoji((string) $row['text']),
                (string) $row['posted_at'],
                $now
            ));

            // wpdb->query returns affected rows: 1 = fresh insert, 2 = row
            // updated (source upgraded), 0 = duplicate no-op. Only 1 is new.
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
     * @param array{source?:?string,search?:?string,page?:int,per_page?:int} $args
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public static function posts(array $args): array
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_POSTS);

        $source  = $args['source'] ?? null;
        $search  = $args['search'] ?? null;
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
     * Store a generated summary. Content is emoji-encoded (utf8mb3-safe).
     *
     * @param array{window_from?:?string,window_to?:?string,model?:string,post_count?:int,trusted_count?:int,content?:string} $row
     * @return int inserted summary id
     */
    public static function insertSummary(array $row): int
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_SUMMARIES);
        $now   = current_time('mysql', true);

        $wpdb->insert($table, [
            'created_at'    => $now,
            'window_from'   => $row['window_from'] ?? $now,
            'window_to'     => $row['window_to'] ?? $now,
            'model'         => (string) ($row['model'] ?? ''),
            'post_count'    => (int) ($row['post_count'] ?? 0),
            'trusted_count' => (int) ($row['trusted_count'] ?? 0),
            'content'       => wp_encode_emoji((string) ($row['content'] ?? '')),
        ], ['%s', '%s', '%s', '%s', '%d', '%d', '%s']);

        return (int) $wpdb->insert_id;
    }

    /** Most recent summaries, newest first. */
    public static function summaries(int $limit = 20): array
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_SUMMARIES);
        $limit = max(1, min(100, $limit));
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d",
            $limit
        ), ARRAY_A) ?: [];
    }
}
