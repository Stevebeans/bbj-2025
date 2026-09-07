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
     * v1 limitation: the weekly rows do not expose who a veto was used on,
     * so only HOH, veto holder, nominees and evictions are stated.
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
            usort($weeks, static fn(array $a, array $b): int => ((int) $a['week_num']) <=> ((int) $b['week_num']));
            $current = $weeks[count($weeks) - 1];
        }

        $range = self::dateRange((string) ($current['start_date'] ?? ''), (string) ($current['end_date'] ?? ''));
        $label = 'Week ' . (int) $current['week_num'] . ($range !== '' ? " ({$range})" : '');

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
        if (!empty($current['noms'])) {
            $parts[] = 'Nominees: ' . $names($current['noms']) . '.';
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
}
