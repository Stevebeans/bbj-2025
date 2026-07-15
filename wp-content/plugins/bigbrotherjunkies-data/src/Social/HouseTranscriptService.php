<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * House transcript: one structured Claude call turns a Pacific day's collected
 * posts into a screenplay of the house — dialogue lines feedsters explicitly
 * quoted, with speaker/listener/time/source. Admin-only surface; stored as a
 * summaries row (kind=house_tx, latest-wins) so each day generates once.
 * Accuracy contract lives in the PROMPT: verbatim-only, skip-when-unsure,
 * paraphrase clearly marked. Mirrors QuoteService throughout.
 */
class HouseTranscriptService
{
    public const KIND = 'house_tx';

    public const PROMPT = <<<'PROMPT'
You compile a transcript of what Big Brother houseguests SAID, from fan reports about the
live feeds. Extract only speech a report explicitly quotes or clearly attributes. Return
STRICT JSON only:
{"lines":[{"time":"8:18 PM","speaker":"Chuk","to":"Angela","quote":"You ARE my TV","kind":"verbatim","source":"handle"}]}
Rules:
- kind "verbatim" ONLY when the report puts the words in quotation marks; clearly reported
  but unquoted speech is kind "paraphrase". Never invent, merge, or embellish wording.
- SKIP a line entirely when the speaker is unnamed or ambiguous, when quote marks are
  sarcasm/nicknames/show references rather than houseguest speech, or when you are unsure.
  Missing lines are fine; invented lines are not.
- "to" = the named listener, or null. "time" = the report's house time (as given).
  "source" = the reporting account's handle. speaker/to are first names only.
- Order lines chronologically. If the day is long, keep the ~200 clearest, most
  game-relevant or entertaining lines. If nothing qualifies, return {"lines":[]}.
No prose, no markdown fences.
PROMPT;

    /**
     * Generate for a Pacific day, persist (latest wins), return the lines.
     * $roster (houseguest names, optional) pins speaker/listener attribution
     * to the actual cast — without it, #bb28 tag noise (other shows, feedsters
     * quoting themselves) leaks in as phantom speakers.
     */
    public static function generate(string $ymd, array $roster = []): array
    {
        $settings = SocialStore::settings();
        $key      = (string) ($settings['anthropic_api_key'] ?? '');
        if ($key === '') {
            return ['success' => false, 'message' => 'No Anthropic API key configured'];
        }

        $bounds = QuoteLogic::houseDayBounds($ymd);
        $rows   = SocialStore::postsForDay($bounds['from'], $bounds['to']);
        if (empty($rows)) {
            return ['success' => false, 'message' => 'No collected posts for that day.'];
        }

        $input = [];
        foreach ($rows as $post) {
            $input[] = sprintf(
                '[%s] @%s: %s',
                BeanBotLogic::toHouseTime((string) $post['posted_at']),
                (string) $post['handle'],
                html_entity_decode((string) $post['text'], ENT_QUOTES, 'UTF-8')
            );
        }

        $system = (string) apply_filters('bbjd_house_transcript_prompt', self::PROMPT);
        $roster = array_values(array_filter(array_map(static function ($n) {
            return trim((string) $n);
        }, $roster)));
        if (!empty($roster)) {
            $system .= "\nThis season's houseguests: " . implode(', ', array_slice($roster, 0, 30))
                . ". \"speaker\" MUST be one of them (first name); \"to\" must be one of them or"
                . ' null. Skip lines spoken by anyone else (fans, production, other shows).';
        }

        $client = new AnthropicClient();
        $resp   = $client->complete(
            $system,
            implode("\n", $input),
            (string) $settings['model'],
            $key
        );
        if (isset($resp['error'])) {
            return ['success' => false, 'message' => (string) $resp['error']];
        }

        $parsed = TranscriptLogic::parseLinesJson((string) ($resp['text'] ?? ''));
        if (isset($parsed['error'])) {
            return ['success' => false, 'message' => 'Model returned an unparseable transcript.'];
        }

        SocialStore::insertSummary([
            'kind'        => self::KIND,
            'window_from' => $bounds['from'],
            'window_to'   => $bounds['to'],
            'model'       => (string) $settings['model'],
            'post_count'  => count($rows),
            'content'     => wp_json_encode($parsed['lines']),
        ]);

        return ['success' => true, 'date' => $ymd, 'lines' => $parsed['lines']];
    }

    /** Latest stored transcript covering the given Pacific day, else null. */
    public static function storedForDay(string $ymd): ?array
    {
        $bounds = QuoteLogic::houseDayBounds($ymd);
        $rows   = SocialStore::summaries(20, self::KIND);
        foreach ($rows as $row) {
            if ((string) ($row['window_from'] ?? '') !== $bounds['from']) {
                continue;
            }
            $content = html_entity_decode((string) ($row['content'] ?? ''), ENT_QUOTES, 'UTF-8');
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                return null;
            }
            return ['date' => $ymd, 'lines' => $decoded];
        }

        return null;
    }
}
