<?php

namespace BigBrotherJunkies\Data\Social;

use BigBrotherJunkies\Data\FeedUpdates\FeedUpdateCreator;

/**
 * Bean Bot: an opt-in service that reads new trusted-feed reports, asks Claude
 * to write ONE short public feed update in fan voice, and publishes it through
 * the shared FeedUpdateCreator (same pipeline as the human composer).
 *
 * House rules honored here:
 *  - Disabled by default: a fresh install schedules nothing and posts nothing
 *    until Steve enables it (settings enabled=false, monitor must be on, an
 *    Anthropic key must exist).
 *  - The cursor only advances on a real publish/skip/suppress, never on error,
 *    so a transient Anthropic failure re-tries the same window next cycle.
 *  - Dry-run preview performs the REAL Claude call but persists NOTHING (no
 *    cursor move, no cap increment, no last_was_brief, no post).
 *  - cron() never throws into Action Scheduler (try/catch + error_log).
 *  - Stored post text is wp_encode_emoji'd in the DB; it is html_entity_decode'd
 *    before it goes to Claude (mirror SummaryService).
 */
class BeanBotService
{
    public const OPTION     = 'bbjd_bean_bot';
    public const CYCLE_HOOK = 'bbjd_bean_bot_cycle';
    public const AS_GROUP   = 'bbj-social';

    /** Allowed cycle intervals (minutes). */
    public const INTERVALS = [30, 45, 60];

    /**
     * Option defaults. `enabled=false` so a fresh deploy never auto-posts.
     * The first four keys are user-settable via REST; the rest are
     * service-managed (cursor, cap bookkeeping, run state).
     */
    public const DEFAULTS = [
        'enabled'          => false,
        'interval_minutes' => 30,
        'max_per_day'      => 24,
        'bluesky_enabled'  => true,
        'cursor_post_id'   => 0,
        'posts_today'      => 0,
        'posts_today_date' => '',
        'last_was_brief'   => false,
        'last_error'       => '',
        'last_run'         => '',
        'author_user_id'   => 0,
    ];

    public const FEED_PROMPT = <<<'PROMPT'
You are Bean Bot, a live-feed watcher posting short public feed updates for Big Brother
Junkies (bigbrotherjunkies.com). You receive recent reports from trusted feed-watcher
accounts and write ONE update in the same casual voice a human fan uses, like:
"Dee doing some camtalking venting about Haley leaving her slippers around her room."

Respond with ONLY a JSON object: {"mode": "post" or "skip", "text": "...", "bluesky": true/false}

Rules:
- mode "post" with 1-4 sentences (under 900 characters) covering what is actually happening
  on the feeds right now per the reports. Present tense. Plain text only.
- If the reports are only jokes, opinions, or episode commentary with no real house events,
  use mode "skip" with empty text.
- If the house is awake but nothing notable is happening, a single short sentence is perfect.
- "bluesky": true only when the update covers a genuinely notable event (a fight, a flip,
  a comp result, a showmance development). Routine updates get false.
- Never invent events. Never name the reporting accounts. No hashtags, no handles, no
  em-dashes. Quote at most a short phrase from any single report.
PROMPT;

    // -----------------------------------------------------------------------
    // Settings
    // -----------------------------------------------------------------------

    /** Stored settings merged over defaults (holds no secrets). */
    public static function settings(): array
    {
        $stored = get_option(self::OPTION, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        return array_merge(self::DEFAULTS, $stored);
    }

    /**
     * Whitelist + sanitize a settings patch, persist it, return the merged
     * settings. ONLY enabled / interval_minutes / max_per_day / bluesky_enabled
     * are settable here; service-managed fields are ignored in the patch.
     */
    public static function saveSettings(array $patch): array
    {
        $current = self::settings();

        if (array_key_exists('enabled', $patch)) {
            $current['enabled'] = (bool) $patch['enabled'];
        }
        if (array_key_exists('interval_minutes', $patch)) {
            $interval = (int) $patch['interval_minutes'];
            if (in_array($interval, self::INTERVALS, true)) {
                $current['interval_minutes'] = $interval;
            }
        }
        if (array_key_exists('max_per_day', $patch)) {
            $current['max_per_day'] = max(1, min(96, (int) $patch['max_per_day']));
        }
        if (array_key_exists('bluesky_enabled', $patch)) {
            $current['bluesky_enabled'] = (bool) $patch['bluesky_enabled'];
        }

        update_option(self::OPTION, $current);
        return $current;
    }

    /** Persist the full option (service-managed writes). */
    private static function persist(array $settings): void
    {
        update_option(self::OPTION, $settings);
    }

    // -----------------------------------------------------------------------
    // Author user
    // -----------------------------------------------------------------------

    /**
     * Find-or-create the `bean-bot` WP author and cache its id in the option.
     * Author role, random 64-char password, dedicated email.
     */
    public static function ensureUser(): int
    {
        $settings = self::settings();
        $cached   = (int) $settings['author_user_id'];
        if ($cached > 0 && get_user_by('id', $cached)) {
            return $cached;
        }

        $existing = get_user_by('login', 'bean-bot');
        if ($existing) {
            $id = (int) $existing->ID;
        } else {
            $id = wp_insert_user([
                'user_login'   => 'bean-bot',
                'user_pass'    => wp_generate_password(64, true, true),
                'user_email'   => 'bean-bot@bigbrotherjunkies.com',
                'display_name' => 'Bean Bot',
                'nickname'     => 'Bean Bot',
                'role'         => 'author',
            ]);
            if (is_wp_error($id)) {
                // Email may already belong to an existing account; reuse it.
                $byEmail = get_user_by('email', 'bean-bot@bigbrotherjunkies.com');
                if ($byEmail) {
                    $id = (int) $byEmail->ID;
                } else {
                    error_log('[BBJ Bean Bot] ensureUser failed: ' . $id->get_error_message());
                    return 0;
                }
            }
        }

        $id = (int) $id;
        if ($id > 0) {
            $settings['author_user_id'] = $id;
            self::persist($settings);
        }
        return $id;
    }

    // -----------------------------------------------------------------------
    // Cycle
    // -----------------------------------------------------------------------

    /**
     * Run one Bean Bot cycle. `$dry=true` performs everything including the
     * Claude call but publishes nothing and persists nothing.
     *
     * @return array{action:string,text:?string,bluesky:?bool,post_id:?int,message:?string,window:array{from:?string,count:int}}
     */
    public static function runCycle(bool $dry = false): array
    {
        $settings = self::settings();
        $monitor  = SocialStore::settings();
        $key      = (string) ($monitor['anthropic_api_key'] ?? '');
        $model    = (string) ($monitor['model'] ?? '');

        // 1a. Disabled gate: bot off, monitor off, or no API key.
        if (empty($settings['enabled']) || empty($monitor['enabled']) || $key === '') {
            return self::result('disabled');
        }

        // 1b. Daily cap with date rollover against the site clock.
        $today      = current_time('Y-m-d');
        $postsToday = (int) $settings['posts_today'];
        if ((string) $settings['posts_today_date'] !== $today) {
            $postsToday = 0; // rolled over; only persisted on a real post
        }
        if ($postsToday >= (int) $settings['max_per_day']) {
            return self::result('capped');
        }

        // 2. Fetch trusted rows beyond the cursor.
        $rows = SocialStore::trustedPostsSince((int) $settings['cursor_post_id']);
        if (empty($rows)) {
            return self::result('skipped', ['message' => 'No new trusted posts.']);
        }

        $maxId = 0;
        foreach ($rows as $row) {
            $maxId = max($maxId, (int) $row['id']);
        }
        $from  = (string) $rows[0]['posted_at'];
        $to    = (string) $rows[count($rows) - 1]['posted_at'];
        $count = count($rows);
        $windowExtra = ['window' => ['from' => $from, 'count' => $count]];

        // 3. Build the user message (entities decoded for Claude).
        $userMessage = self::buildUserMessage($rows, $from, $to);
        $system      = apply_filters('bbjd_bean_bot_feed_prompt', self::FEED_PROMPT);

        // 4. Ask Claude.
        $client = new AnthropicClient();
        $resp   = $client->complete((string) $system, $userMessage, $model, $key);
        if (isset($resp['error'])) {
            if (!$dry) {
                $settings['last_error'] = (string) $resp['error'];
                self::persist($settings);
            }
            return self::result('error', array_merge($windowExtra, ['message' => (string) $resp['error']]));
        }

        // 5. Parse the model JSON.
        $parsed = BeanBotLogic::parseModelJson((string) $resp['text']);
        if (isset($parsed['error'])) {
            $message = 'Model returned unusable output: ' . $parsed['error'];
            if (!$dry) {
                $settings['last_error'] = $message;
                self::persist($settings);
            }
            return self::result('error', array_merge($windowExtra, ['message' => $message]));
        }

        $text      = (string) $parsed['text'];
        $blueskyOn = $parsed['bluesky'] && (bool) $settings['bluesky_enabled'];
        $nowUtc    = current_time('mysql', true);

        // 6. Skip: advance the cursor, publish nothing.
        if ($parsed['mode'] === 'skip') {
            if (!$dry) {
                $settings['cursor_post_id'] = $maxId;
                $settings['last_error']     = '';
                $settings['last_run']       = $nowUtc;
                self::persist($settings);
            }
            return self::result('skipped', array_merge($windowExtra, [
                'text'    => $text,
                'bluesky' => false,
            ]));
        }

        // 7. Quiet suppression: two brief updates in a row would be noise.
        $isBrief = mb_strlen($text) < 120;
        if (BeanBotLogic::shouldSuppressQuiet($isBrief, (bool) $settings['last_was_brief'])) {
            if (!$dry) {
                $settings['cursor_post_id'] = $maxId;
                $settings['last_was_brief'] = $isBrief;
                $settings['last_error']     = '';
                $settings['last_run']       = $nowUtc;
                self::persist($settings);
            }
            return self::result('suppressed', array_merge($windowExtra, [
                'text'    => $text,
                'bluesky' => $blueskyOn,
            ]));
        }

        // 9. Dry run: everything above happened (incl. the Claude call) but we
        //    persist nothing and publish nothing. Report what WOULD post.
        if ($dry) {
            return self::result('posted', array_merge($windowExtra, [
                'text'    => $text,
                'bluesky' => $blueskyOn,
                'post_id' => null,
            ]));
        }

        // 8. Publish through the shared creator.
        $create = FeedUpdateCreator::create([
            'content'         => $text,
            'author_id'       => self::ensureUser(),
            'post_to_bluesky' => $blueskyOn,
        ]);
        if (empty($create['success'])) {
            $message = (string) ($create['message'] ?? 'Feed update creation failed.');
            $settings['last_error'] = $message;
            self::persist($settings);
            return self::result('error', array_merge($windowExtra, ['message' => $message]));
        }

        // Success: advance cursor, count the post (with rollover), remember
        // brevity, clear error, stamp the run.
        $settings['cursor_post_id']   = $maxId;
        $settings['posts_today']      = $postsToday + 1;
        $settings['posts_today_date'] = $today;
        $settings['last_was_brief']   = $isBrief;
        $settings['last_error']       = '';
        $settings['last_run']         = $nowUtc;
        self::persist($settings);

        return self::result('posted', array_merge($windowExtra, [
            'text'    => $text,
            'bluesky' => $blueskyOn,
            'post_id' => (int) ($create['post_id'] ?? 0),
        ]));
    }

    /** Action Scheduler callback: run a live cycle, never throw. */
    public static function cron(): void
    {
        try {
            self::runCycle(false);
        } catch (\Throwable $e) {
            error_log('[BBJ Bean Bot] cycle error: ' . $e->getMessage());
        }
    }

    /**
     * Build the Claude user message from trusted rows. Stored text is
     * entity-encoded in the DB, so decode it back to real characters here.
     *
     * @param array<int,array<string,mixed>> $rows
     */
    private static function buildUserMessage(array $rows, string $from, string $to): string
    {
        $lines   = [];
        $lines[] = sprintf(
            "Season: Big Brother 28. New reports from Steve's trusted feed watchers since the last check (%d posts, %s to %s UTC):",
            count($rows),
            $from,
            $to
        );
        $lines[] = '';
        foreach ($rows as $row) {
            $text    = html_entity_decode((string) $row['text'], ENT_QUOTES, 'UTF-8');
            $lines[] = sprintf('[%s] %s', (string) $row['posted_at'], $text);
        }
        return implode("\n", $lines);
    }

    /** Shape a cycle result with all keys present. */
    private static function result(string $action, array $extra = []): array
    {
        return array_merge([
            'action'  => $action,
            'text'    => null,
            'bluesky' => null,
            'post_id' => null,
            'message' => null,
            'window'  => ['from' => null, 'count' => 0],
        ], $extra);
    }

    // -----------------------------------------------------------------------
    // Scheduling (Action Scheduler) — mirrors SocialStore's poller pattern.
    // -----------------------------------------------------------------------

    /** Idempotent init-time guard: schedule the cycle if enabled + not queued. */
    public static function ensureScheduled(): void
    {
        if (!function_exists('as_schedule_recurring_action') || !function_exists('as_next_scheduled_action')) {
            return;
        }
        $settings = self::settings();
        if (empty($settings['enabled'])) {
            return;
        }
        if (as_next_scheduled_action(self::CYCLE_HOOK) === false) {
            self::scheduleRecurring($settings);
        }
    }

    /** Clear any scheduled cycle and re-schedule from current settings. */
    public static function reschedule(): void
    {
        if (!function_exists('as_unschedule_all_actions')) {
            return;
        }
        as_unschedule_all_actions(self::CYCLE_HOOK, [], self::AS_GROUP);

        $settings = self::settings();
        if (empty($settings['enabled']) || !function_exists('as_schedule_recurring_action')) {
            return;
        }
        self::scheduleRecurring($settings);
    }

    private static function scheduleRecurring(array $settings): void
    {
        $interval = self::intervalSeconds($settings);
        as_schedule_recurring_action(time() + $interval, $interval, self::CYCLE_HOOK, [], self::AS_GROUP);
    }

    private static function intervalSeconds(array $settings): int
    {
        $minutes = (int) $settings['interval_minutes'];
        if (!in_array($minutes, self::INTERVALS, true)) {
            $minutes = self::DEFAULTS['interval_minutes'];
        }
        return $minutes * 60;
    }
}
