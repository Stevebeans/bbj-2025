<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * CBS air-schedule awareness. Steve live-posts televised episodes himself,
 * so while an episode airs (ET) or re-airs (PT, ~3h later) every AI
 * summarizer must treat episode-reaction chatter as noise, and the bots stay
 * out of the episode lane entirely. Slots live in bbjd_social_monitor
 * (cbs_slots). Each slot yields TWO caution windows - the ET airing and the
 * PT re-air - each padded with a 30-minute tail.
 */
class PromptContext
{
    public const NOTICE = 'IMPORTANT: A CBS Big Brother episode is airing right now (or just aired) in a US time zone. Episode-reaction posts are NOT feed intel - ignore any post reacting to the televised episode unless it explicitly describes current live-feed activity.';

    public const TAIL_MINUTES = 30;
    public const PT_REAIR_OFFSET_HOURS = 3;

    /**
     * Pure window math (no WordPress) so it unit-tests cleanly: is $now (UTC)
     * inside any caution window derived from $slots?
     *
     * @param array<int,array{day:int,time_et:string,duration_min:int}> $slots
     */
    public static function inCautionWindow(array $slots, \DateTimeImmutable $now): bool
    {
        $et = new \DateTimeZone('America/New_York');
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $day  = (int) ($slot['day'] ?? -1);
            $time = (string) ($slot['time_et'] ?? '');
            $dur  = (int) ($slot['duration_min'] ?? 0);
            if ($day < 0 || $day > 6 || $dur <= 0 || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
                continue;
            }
            // Only the slot occurrence on today-in-ET or yesterday-in-ET can
            // still contain $now (windows span at most ~7 hours).
            foreach ([0, 1] as $daysBack) {
                $ref = $now->setTimezone($et)->modify("-{$daysBack} day");
                if ((int) $ref->format('w') !== $day) {
                    continue;
                }
                $start   = new \DateTimeImmutable($ref->format('Y-m-d') . ' ' . $time, $et);
                $spanMin = $dur + self::TAIL_MINUTES;
                foreach ([0, self::PT_REAIR_OFFSET_HOURS * 60] as $offsetMin) {
                    $winStart = $start->modify("+{$offsetMin} minutes");
                    $winEnd   = $winStart->modify("+{$spanMin} minutes");
                    if ($now >= $winStart && $now <= $winEnd) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /** WP-facing wrapper: slots from settings, $now defaulting to real now. */
    public static function isCautionWindow(?\DateTimeImmutable $now = null): bool
    {
        $slots = (array) (SocialStore::settings()['cbs_slots'] ?? []);
        if ($slots === []) {
            return false;
        }
        return self::inCautionWindow($slots, $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    /** Filter callback attached to every AI prompt hook (SocialRoutes::init). */
    public static function appendNotice($prompt)
    {
        if (!is_string($prompt) || !self::isCautionWindow()) {
            return $prompt;
        }
        return $prompt . "\n\n" . self::NOTICE;
    }

    /**
     * Filter callback: season roster + fan-nickname glossary on every
     * feed-intel prompt. Fan reports run on community nicknames ("Thog" =
     * Haley) the models can't know, which produced phantom houseguests in
     * drafts (2026-07-21). The transcript prompt appends its own roster line
     * after its filters run, so that hook gets the glossary only.
     */
    public static function appendRoster($prompt)
    {
        if (!is_string($prompt)) {
            return $prompt;
        }
        $block = self::rosterBlock(
            HouseTranscriptService::currentSeasonRoster(),
            (array) (SocialStore::settings()['fan_nicknames'] ?? []),
            current_filter() !== 'bbjd_house_transcript_prompt'
        );
        return $block === '' ? $prompt : $prompt . "\n\n" . $block;
    }

    /**
     * Pure block builder (no WordPress) so it unit-tests cleanly.
     *
     * @param string[] $roster    names (+ official nicknames) for the season
     * @param string[] $nicknames normalized "Nick = Real Name" lines
     */
    public static function rosterBlock(array $roster, array $nicknames, bool $includeRoster = true): string
    {
        $lines = [];
        if ($includeRoster && $roster) {
            $lines[] = "This season's houseguests: " . implode(', ', array_slice($roster, 0, 40)) . '.';
        }

        $pairs = [];
        foreach ($nicknames as $line) {
            $line = trim(html_entity_decode((string) $line, ENT_QUOTES, 'UTF-8'));
            if ($line !== '' && str_contains($line, '=')) {
                $pairs[] = $line;
            }
        }
        if ($pairs) {
            $lines[] = 'Fan nickname glossary (community nicknames fans use in reports): '
                . implode('; ', $pairs) . '.';
        }

        if ($lines) {
            $lines[] = 'A nickname and its real name are the SAME person - never treat them as two '
                . 'different people. Always use real names in your output.';
        }

        return implode("\n", $lines);
    }
}
