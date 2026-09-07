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
}
