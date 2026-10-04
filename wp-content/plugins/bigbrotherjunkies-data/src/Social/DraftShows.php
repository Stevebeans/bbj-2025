<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Which show a blog draft is about. '' = Big Brother (every pre-Shows row and
 * the default everywhere), matching the show_slug / bbj_show convention.
 *
 * Survivor drafts get their own summary kind so they never reach the BB week
 * summary (WeekSummaryService reads kinds 'digest' + 'blog') and History can
 * list them separately. The kind column is VARCHAR(12), hence 'blog_surv'.
 *
 * Pure: no WordPress calls, unit-tested in DraftShowsTest.
 */
class DraftShows
{
    private const SHOWS = [
        ''         => ['label' => 'Big Brother 28', 'blog_kind' => 'blog'],
        'survivor' => ['label' => 'Survivor 49', 'blog_kind' => 'blog_surv'],
    ];

    public static function isKnown(string $show): bool
    {
        return isset(self::SHOWS[$show]);
    }

    /** Season label for the user message ("Big Brother 28"); unknown → BB. */
    public static function label(string $show): string
    {
        return (self::SHOWS[$show] ?? self::SHOWS[''])['label'];
    }

    /** Summary-row kind a blog draft for $show is stored under; unknown → 'blog'. */
    public static function blogKind(string $show): string
    {
        return (self::SHOWS[$show] ?? self::SHOWS[''])['blog_kind'];
    }

    /** Every blog-draft kind, for the summaries whitelist. */
    public static function blogKinds(): array
    {
        return array_values(array_column(self::SHOWS, 'blog_kind'));
    }

    public static function blogPrompt(string $show): string
    {
        return $show === 'survivor' ? DraftService::SURVIVOR_BLOG_PROMPT : DraftService::BLOG_PROMPT;
    }
}
