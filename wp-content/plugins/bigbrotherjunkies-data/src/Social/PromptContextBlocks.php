<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Context blocks shared by every model-facing prompt in the Social module
 * (blog recap, FB drafts, Bean Bot). Each block states a fact the model
 * otherwise has to guess: what day it is, where the week stands, and what
 * the site's own feed watchers wrote. The 9/6/2026 recap got all three
 * wrong (Saturday for Sunday, "HOH reveal coming" after the reveal, the
 * wrong houseguest at risk) with nothing in the prompt to correct it.
 *
 * Pure formatters take arrays and return strings so they are unit-testable
 * without WordPress; the thin wrappers below them do the DB/option reads.
 */
class PromptContextBlocks
{
    public const HOUSE_TZ = 'America/Los_Angeles';

    /** "Today is Sunday, September 6, 2026 (Pacific)." for the house's current day. */
    public static function dateHeader(\DateTimeImmutable $nowUtc): string
    {
        $house = $nowUtc->setTimezone(new \DateTimeZone(self::HOUSE_TZ));
        return sprintf('Today is %s (Pacific).', $house->format('l, F j, Y'));
    }

    /**
     * One line of authoritative week state from the weekly editor rows
     * (WeeklyData::getSeasonWeeks shape). Picks the week that brackets
     * $todayYmd (house date); falls back to the newest week and says so.
     * Blank fields are omitted rather than guessed. Returns '' with no rows.
     *
     * A missing 'saved' key is treated as [] so older-shaped week rows still
     * work. When several weeks in $weeks bracket $todayYmd, the LAST one in
     * input order wins — this relies on WeeklyData::getSeasonWeeks ordering
     * its rows by week_num ASC, so "last" means "latest week".
     */
    public static function gameStateLineFromWeeks(array $weeks, string $todayYmd): string
    {
        if ($weeks === []) {
            return '';
        }

        $current = null;
        foreach ($weeks as $w) {
            $start = (string) ($w['start_date'] ?? '');
            $end   = (string) ($w['end_date'] ?? '');
            if ($start !== '' && $end !== '' && $start <= $todayYmd && $todayYmd <= $end) {
                $current = $w;
            }
        }

        $isCurrent = $current !== null;
        if ($current === null) {
            usort($weeks, static fn(array $a, array $b): int => ((int) ($a['week_num'] ?? 0)) <=> ((int) ($b['week_num'] ?? 0)));
            $current = $weeks[count($weeks) - 1];
        }

        $range = self::dateRange((string) ($current['start_date'] ?? ''), (string) ($current['end_date'] ?? ''));
        $label = 'Week ' . (int) ($current['week_num'] ?? 0) . ($range !== '' ? " ({$range})" : '');

        $parts = [];
        $parts[] = $isCurrent
            ? "Game state: {$label}."
            : "Game state: latest recorded week is {$label}; this week has not been entered yet.";

        $names = static fn(array $list): string => implode(', ', array_map('strval', $list));

        if (!empty($current['hoh'])) {
            $parts[] = 'HOH: ' . $names($current['hoh']) . '.';
        }
        if (!empty($current['pov'])) {
            $parts[] = 'Veto holder: ' . $names($current['pov']) . '.';
        }
        $saved     = $current['saved'] ?? [];
        $nominees  = array_values(array_diff($current['noms'] ?? [], $saved));
        if (!empty($nominees)) {
            $parts[] = 'Nominees: ' . $names($nominees) . '.';
        }
        if (!empty($saved)) {
            $parts[] = 'Veto used on: ' . $names($saved) . ' (off the block).';
        }
        if (!empty($current['evicted'])) {
            $parts[] = ($isCurrent ? 'Evicted this week: ' : 'Evicted: ') . $names($current['evicted']) . '.';
        } elseif ($isCurrent) {
            $parts[] = 'Evicted this week: none yet.';
        }

        return implode(' ', $parts);
    }

    /** "Sep 4 to Sep 10" from two Y-m-d strings; '' if either is missing/invalid. */
    private static function dateRange(string $startYmd, string $endYmd): string
    {
        $tz    = new \DateTimeZone(self::HOUSE_TZ);
        $start = $startYmd !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $startYmd, $tz) : false;
        $end   = $endYmd !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $endYmd, $tz) : false;
        if (!$start || !$end) {
            return '';
        }
        return $start->format('M j') . ' to ' . $end->format('M j');
    }

    /**
     * Wrapper: current season from the `bbj_v2_current_season` option
     * (season CPT post id), rows from WeeklyData. Any failure → ''.
     * Not unit-tested (needs WordPress); all logic is in gameStateLineFromWeeks.
     */
    public static function gameStateLine(\DateTimeImmutable $nowUtc): string
    {
        try {
            $seasonId = (int) get_option('bbj_v2_current_season', 0);
            if ($seasonId <= 0) {
                error_log('[bbjd] PromptContextBlocks::gameStateLine: bbj_v2_current_season not set');
                return '';
            }
            $weeks = \BigBrotherJunkies\Data\Weekly\WeeklyData::getSeasonWeeks($seasonId);
            $today = $nowUtc->setTimezone(new \DateTimeZone(self::HOUSE_TZ))->format('Y-m-d');
            return self::gameStateLineFromWeeks(is_array($weeks) ? $weeks : [], $today);
        } catch (\Throwable $e) {
            // Never block a draft; leave a trail in the PHP error log (the spec's
            // "notice" — Bean Bot's last_error is not used because a blog draft
            // must not rewrite Bean Bot's run state).
            error_log('[bbjd] PromptContextBlocks::gameStateLine failed: ' . $e->getMessage());
            return '';
        }
    }

    public const HOUSE_UPDATES_HEADER = '=== HOUSE UPDATES (bigbrotherjunkies.com feed watchers; primary account of the day) ===';
    public const HOUSE_UPDATES_CAP    = 60;
    public const HOUSE_UPDATE_MAX_CHARS = 600;

    /**
     * The day's human-written live-feed-updates as model-facing lines.
     * Rows: ['posted_at' => UTC 'Y-m-d H:i:s', 'author' => string, 'text' => string].
     * Chronological; over $cap keeps the newest and says how many were dropped.
     */
    public static function formatHouseUpdates(array $rows, int $cap = self::HOUSE_UPDATES_CAP): string
    {
        if ($rows === []) {
            return '';
        }
        usort($rows, static fn(array $a, array $b): int => strcmp((string) $a['posted_at'], (string) $b['posted_at']));

        $omitted = 0;
        if (count($rows) > $cap) {
            $omitted = count($rows) - $cap;
            $rows    = array_slice($rows, $omitted);
        }

        $lines   = [self::HOUSE_UPDATES_HEADER];
        if ($omitted > 0) {
            $lines[] = sprintf('(%d earlier updates omitted)', $omitted);
        }
        foreach ($rows as $row) {
            $lines[] = sprintf(
                '[%s] %s: %s',
                BeanBotLogic::toHouseTime((string) $row['posted_at']),
                trim((string) ($row['author'] ?? '')),
                self::squash((string) ($row['text'] ?? ''))
            );
        }
        return implode("\n", $lines);
    }

    /** Collapse whitespace; cap length with an ellipsis. */
    private static function squash(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) > self::HOUSE_UPDATE_MAX_CHARS) {
            $text = rtrim(mb_substr($text, 0, self::HOUSE_UPDATE_MAX_CHARS)) . '…';
        }
        return $text;
    }

    /**
     * Wrapper: published live-feed-updates in the UTC window, excluding
     * Bean Bot's author (0 excludes nobody). Not unit-tested (uses $wpdb);
     * all formatting is in formatHouseUpdates.
     *
     * Limitation: this only excludes $excludeAuthorId (Bean Bot's
     * post_author). If Bean Bot's `post_as_user_id` setting is ever set to a
     * real user, its AI-written updates publish under that user and are
     * indistinguishable from a human watcher's, so they would enter this
     * block. `post_as_user_id` must stay 0 until a bot marker exists on the
     * post; FeedUpdateCreator::isBotAuthor has the same limitation.
     */
    public static function houseUpdatesBlock(string $fromUtc, string $toUtc, int $excludeAuthorId): string
    {
        try {
            global $wpdb;
            $sql = "SELECT p.post_date_gmt AS posted_at, u.display_name AS author, p.post_content AS text
                      FROM {$wpdb->posts} p
                      LEFT JOIN {$wpdb->users} u ON u.ID = p.post_author
                     WHERE p.post_type = 'live-feed-updates'
                       AND p.post_status = 'publish'
                       AND p.post_date_gmt BETWEEN %s AND %s";
            $args = [$fromUtc, $toUtc];
            if ($excludeAuthorId > 0) {
                $sql   .= ' AND p.post_author <> %d';
                $args[] = $excludeAuthorId;
            }
            $sql .= ' ORDER BY p.post_date_gmt DESC LIMIT 500';

            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
            foreach ($rows as &$row) {
                $row['author'] = (string) ($row['author'] ?? '') !== '' ? $row['author'] : 'Feed watcher';
                $row['text']   = html_entity_decode(wp_strip_all_tags((string) $row['text']), ENT_QUOTES, 'UTF-8');
            }
            unset($row);
            return self::formatHouseUpdates($rows);
        } catch (\Throwable $e) {
            error_log('[bbjd] PromptContextBlocks::houseUpdatesBlock failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Reserved for a community source (Reddit daily threads if the Data
     * Access Request is approved, or the site's own live-thread comments
     * with a better selection rule). Empty in v1 by design; see the spec.
     */
    public static function communityBlock(): string
    {
        return '';
    }

    /**
     * Non-empty blocks, blank-line separated, ahead of the existing message.
     * No blocks → the message is returned byte-identical (Bean Bot's prompt
     * must not change when its toggle is off).
     *
     * @param array<int,string> $blocks
     */
    public static function prependBlocks(string $message, array $blocks): string
    {
        $blocks = array_values(array_filter($blocks, static fn($b): bool => trim((string) $b) !== ''));
        if ($blocks === []) {
            return $message;
        }
        return implode("\n\n", $blocks) . "\n\n" . $message;
    }
}
