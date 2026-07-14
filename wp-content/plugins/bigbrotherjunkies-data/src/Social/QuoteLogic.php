<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Pure quote-candidate logic: model-JSON parsing and Pacific-day window math.
 * No WordPress dependencies (mirrors BeanBotLogic).
 */
class QuoteLogic
{
    public const MAX_CANDIDATES  = 5;
    public const MAX_QUOTE_LEN   = 300;
    public const MAX_SPEAKER_LEN = 80;

    public static function parseCandidatesJson(string $raw): array
    {
        $trimmed = trim((string) preg_replace('/^```[a-z]*\s*|\s*```$/i', '', trim($raw)));
        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded) || !isset($decoded['candidates']) || !is_array($decoded['candidates'])) {
            return ['error' => 'invalid_json'];
        }

        $out = [];
        foreach ($decoded['candidates'] as $cand) {
            if (!is_array($cand)) {
                continue;
            }
            $quote = trim((string) ($cand['quote'] ?? ''));
            if ($quote === '') {
                continue;
            }
            $out[] = [
                'quote'          => mb_substr($quote, 0, self::MAX_QUOTE_LEN),
                'speaker'        => mb_substr(trim((string) ($cand['speaker'] ?? '')), 0, self::MAX_SPEAKER_LEN),
                'echo_count'     => max(1, (int) ($cand['echo_count'] ?? 1)),
                'source_handles' => array_values(array_filter(array_map('strval', (array) ($cand['source_handles'] ?? [])))),
            ];
            if (count($out) >= self::MAX_CANDIDATES) {
                break;
            }
        }

        return ['candidates' => $out];
    }

    /** UTC bounds of a Pacific calendar day; 'to' is exclusive. */
    public static function houseDayBounds(string $ymd): array
    {
        $tz   = new \DateTimeZone('America/Los_Angeles');
        $utc  = new \DateTimeZone('UTC');
        $from = new \DateTimeImmutable($ymd . ' 00:00:00', $tz);
        return [
            'from' => $from->setTimezone($utc)->format('Y-m-d H:i:s'),
            'to'   => $from->add(new \DateInterval('P1D'))->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
    }
}
