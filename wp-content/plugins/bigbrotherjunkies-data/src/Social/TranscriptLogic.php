<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Pure house-transcript logic: model-JSON parsing for extracted dialogue
 * lines. No WordPress dependencies (mirrors QuoteLogic / BeanBotLogic).
 */
class TranscriptLogic
{
    public const MAX_LINES       = 300;
    public const MAX_QUOTE_LEN   = 280;
    public const MAX_NAME_LEN    = 80;
    public const MAX_TIME_LEN    = 20;
    public const KINDS           = ['verbatim', 'paraphrase'];

    /**
     * Deterministic roster enforcement: the prompt asks the model to stick to
     * the cast, but tag noise (podcasters, other shows) still leaks. Drop any
     * line whose speaker isn't a roster name; clear (null) non-roster
     * listeners. Names match on any name token or nickname — houseguests go
     * by first OR last names on the feeds ("Devens", "Thog[martin]").
     * Empty roster = passthrough.
     *
     * @param array<int,array<string,mixed>> $lines
     * @param string[] $rosterNames full names and/or nicknames
     */
    public static function filterToRoster(array $lines, array $rosterNames): array
    {
        if (empty($rosterNames)) {
            return $lines;
        }
        $allowed = [];
        foreach ($rosterNames as $name) {
            foreach (preg_split('/\s+/', trim((string) $name)) ?: [] as $token) {
                if ($token !== '') {
                    $allowed[mb_strtolower($token)] = true;
                }
            }
        }

        $out = [];
        foreach ($lines as $line) {
            if (!isset($allowed[mb_strtolower(trim((string) $line['speaker']))])) {
                continue;
            }
            if ($line['to'] !== null && !isset($allowed[mb_strtolower(trim((string) $line['to']))])) {
                $line['to'] = null;
            }
            $out[] = $line;
        }
        return $out;
    }

    public static function parseLinesJson(string $raw): array
    {
        $trimmed = trim((string) preg_replace('/^```[a-z]*\s*|\s*```$/i', '', trim($raw)));
        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded) || !isset($decoded['lines']) || !is_array($decoded['lines'])) {
            return ['error' => 'invalid_json'];
        }

        $out = [];
        foreach ($decoded['lines'] as $line) {
            if (!is_array($line)) {
                continue;
            }
            $speaker = trim((string) ($line['speaker'] ?? ''));
            $quote   = trim((string) ($line['quote'] ?? ''));
            if ($speaker === '' || $quote === '') {
                continue; // skip-when-unsure contract: unattributed lines never render
            }
            $to   = trim((string) ($line['to'] ?? ''));
            $kind = (string) ($line['kind'] ?? 'verbatim');
            $out[] = [
                'time'    => mb_substr(trim((string) ($line['time'] ?? '')), 0, self::MAX_TIME_LEN),
                'speaker' => mb_substr($speaker, 0, self::MAX_NAME_LEN),
                'to'      => $to === '' ? null : mb_substr($to, 0, self::MAX_NAME_LEN),
                'quote'   => mb_substr($quote, 0, self::MAX_QUOTE_LEN),
                'kind'    => in_array($kind, self::KINDS, true) ? $kind : 'verbatim',
                'source'  => mb_substr(trim((string) ($line['source'] ?? '')), 0, 190),
            ];
            if (count($out) >= self::MAX_LINES) {
                break;
            }
        }

        return ['lines' => $out];
    }
}
