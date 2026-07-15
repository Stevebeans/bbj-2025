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

    /** Posts per Claude call: keeps each call's output well under max_tokens. */
    private const CHUNK_POSTS = 600;

    /**
     * Generate for a Pacific day, persist (latest wins), return the lines.
     * Busy days exceed one call's budget in BOTH directions — 1000+ input
     * posts and more dialogue than one 8K-token response holds — so the day
     * is processed in chronological chunks and the lines concatenated.
     * The roster is derived server-side (names + official nicknames) and
     * enforced deterministically after parsing.
     */
    public static function generate(string $ymd): array
    {
        $settings = SocialStore::settings();
        $key      = (string) ($settings['anthropic_api_key'] ?? '');
        if ($key === '') {
            return ['success' => false, 'message' => 'No Anthropic API key configured'];
        }

        $bounds = QuoteLogic::houseDayBounds($ymd);
        $rows   = SocialStore::postsForDay($bounds['from'], $bounds['to'], 2000);
        if (empty($rows)) {
            return ['success' => false, 'message' => 'No collected posts for that day.'];
        }

        $system = (string) apply_filters('bbjd_house_transcript_prompt', self::PROMPT);
        $roster = self::currentSeasonRoster();
        if (!empty($roster)) {
            $system .= "\nThis season's houseguests: " . implode(', ', array_slice($roster, 0, 40))
                . ". \"speaker\" MUST be one of them; \"to\" must be one of them or"
                . ' null. Skip lines spoken by anyone else (fans, production, other shows).';
        }

        $client = new AnthropicClient();
        $lines  = [];
        foreach (array_chunk($rows, self::CHUNK_POSTS) as $chunk) {
            $input = [];
            foreach ($chunk as $post) {
                $input[] = sprintf(
                    '[%s] @%s: %s',
                    BeanBotLogic::toHouseTime((string) $post['posted_at']),
                    (string) $post['handle'],
                    html_entity_decode((string) $post['text'], ENT_QUOTES, 'UTF-8')
                );
            }

            $resp = $client->complete($system, implode("\n", $input), (string) $settings['model'], $key);
            if (isset($resp['error'])) {
                // Keep earlier chunks if a later one fails; fail only when empty-handed.
                if (empty($lines)) {
                    return ['success' => false, 'message' => (string) $resp['error']];
                }
                break;
            }
            $parsed = TranscriptLogic::parseLinesJson((string) ($resp['text'] ?? ''));
            if (isset($parsed['error'])) {
                if (empty($lines)) {
                    return ['success' => false, 'message' => 'Model returned an unparseable transcript.'];
                }
                break;
            }
            $lines = array_merge($lines, TranscriptLogic::filterToRoster($parsed['lines'], $roster));
        }

        SocialStore::insertSummary([
            'kind'        => self::KIND,
            'window_from' => $bounds['from'],
            'window_to'   => $bounds['to'],
            'model'       => (string) $settings['model'],
            'post_count'  => count($rows),
            'content'     => wp_json_encode($lines),
        ]);

        return ['success' => true, 'date' => $ymd, 'lines' => $lines];
    }

    /**
     * Current season's houseguest names + official nicknames, server-side.
     * Nicknames matter: the house runs on them ("Lala" = LaTrice), and the
     * roster filter would otherwise drop real dialogue as phantom speakers.
     */
    public static function currentSeasonRoster(): array
    {
        if (!function_exists('bbj_v2_get_season_players')) {
            return [];
        }
        $seasonId = (int) get_option('bbj_v2_current_season', 0);
        if ($seasonId <= 0) {
            return [];
        }
        $names = [];
        foreach ((array) bbj_v2_get_season_players($seasonId, 'thumbnail') as $p) {
            // Rows come from wp_bbj_players: first_name / last_name / official_nickname.
            $name = trim(trim((string) ($p['first_name'] ?? '')) . ' ' . trim((string) ($p['last_name'] ?? '')));
            if ($name !== '') {
                $names[] = $name;
            }
            $nick = trim((string) ($p['official_nickname'] ?? ''));
            if ($nick !== '' && mb_strlen($nick) >= 2) {
                $names[] = $nick;
            }
        }
        return $names;
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
