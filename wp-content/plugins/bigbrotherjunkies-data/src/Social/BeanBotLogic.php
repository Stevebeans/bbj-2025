<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Pure Bean Bot logic: model-JSON parsing, window math, quiet suppression.
 * No WordPress dependencies.
 */
class BeanBotLogic
{
    private const ALLOWED_MODES = ['post', 'skip'];
    private const MAX_TEXT_LENGTH = 950;

    public static function parseModelJson(string $raw): array
    {
        $trimmed = trim($raw);
        $trimmed = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $trimmed);
        $trimmed = trim((string) $trimmed);

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return ['error' => 'invalid_json'];
        }

        $mode = $decoded['mode'] ?? null;
        if (!is_string($mode) || !in_array($mode, self::ALLOWED_MODES, true)) {
            return ['error' => 'invalid_mode'];
        }

        $text = $decoded['text'] ?? null;
        if (!is_string($text)) {
            return ['error' => 'invalid_text'];
        }
        $text = trim($text);

        if ($mode === 'post' && $text === '') {
            return ['error' => 'empty_text'];
        }

        $text = mb_substr($text, 0, self::MAX_TEXT_LENGTH);

        $bluesky = $decoded['bluesky'] ?? false;
        // Strict: only a real JSON boolean true enables Bluesky. An LLM that
        // emits the STRING "false" must not be coerced truthy into true.
        $bluesky = $bluesky === true;
        if ($mode === 'skip') {
            $bluesky = false;
        }

        return [
            'mode' => $mode,
            'text' => $text,
            'bluesky' => $bluesky,
        ];
    }

    public static function windowBounds(string $window, \DateTimeImmutable $now, \DateTimeZone $siteTz): array
    {
        $utc = new \DateTimeZone('UTC');
        $to = $now->setTimezone($utc);

        switch ($window) {
            case '12h':
                $from = $now->sub(new \DateInterval('PT12H'))->setTimezone($utc);
                break;
            case '24h':
                $from = $now->sub(new \DateInterval('P1D'))->setTimezone($utc);
                break;
            case 'today':
            default:
                $from = $now->setTimezone($siteTz)->setTime(0, 0, 0)->setTimezone($utc);
                break;
        }

        return [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ];
    }

    public static function shouldSuppressQuiet(bool $currentIsBrief, bool $lastWasBrief): bool
    {
        return $currentIsBrief && $lastWasBrief;
    }

    /**
     * Auto feed updates narrate the live feeds, so source reports older than
     * this are stale no matter where the cursor sits (fresh install, cursor
     * reset, bot re-enabled after a gap). Digests/drafts keep their own wider
     * windows — this only bounds the every-cycle bot.
     */
    public const FRESH_HOURS = 1;

    /** UTC 'Y-m-d H:i:s' cutoff: source posts older than this are ignored. */
    public static function freshnessCutoff(\DateTimeImmutable $now): string
    {
        return $now->setTimezone(new \DateTimeZone('UTC'))
            ->sub(new \DateInterval('PT' . self::FRESH_HOURS . 'H'))
            ->format('Y-m-d H:i:s');
    }

    /**
     * Format a stored UTC timestamp ('Y-m-d H:i:s') as Pacific house time
     * ('M j g:i A', e.g. "Jul 13 7:23 PM"). Posts are stored in UTC but the
     * house (and Steve) run on America/Los_Angeles, so model-facing report
     * lines read in house time. Invalid input is returned unchanged.
     */
    public static function toHouseTime(string $utcDateTime): string
    {
        $utc = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $utcDateTime, new \DateTimeZone('UTC'));
        if ($utc === false || $utc->format('Y-m-d H:i:s') !== $utcDateTime) {
            return $utcDateTime;
        }
        return $utc->setTimezone(new \DateTimeZone('America/Los_Angeles'))->format('M j g:i A');
    }
}
