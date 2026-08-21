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
- Order lines chronologically. Return AT MOST 120 lines per response — when there is
  more, keep the clearest, most game-relevant or entertaining lines spread across the
  whole window. If nothing qualifies, return {"lines":[]}.
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
        // Curated: all trusted + top-500 tag by engagement. The old
        // first-2000-chronological read filled up by ~4 AM at peak volume.
        $rows   = SocialStore::postsForDayCurated($bounds['from'], $bounds['to']);
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

        $client   = new AnthropicClient();
        $lines    = [];
        $started  = time();
        $chunks   = array_chunk($rows, self::CHUNK_POSTS);
        foreach ($chunks as $i => $chunk) {
            // Rapid sequential 60K-token calls trip the API's per-minute token
            // throttle; space the chunks and retry a failed one once.
            if ($i > 0) {
                sleep(5);
            }
            if (time() - $started > 240) {
                error_log('[bbjd house_tx] wall-clock guard hit at chunk ' . $i . '/' . count($chunks));
                break;
            }

            $input = [];
            foreach ($chunk as $post) {
                $input[] = sprintf(
                    '[%s] @%s: %s',
                    BeanBotLogic::toHouseTime((string) $post['posted_at']),
                    (string) $post['handle'],
                    html_entity_decode((string) $post['text'], ENT_QUOTES, 'UTF-8')
                );
            }
            $userMessage = implode("\n", $input);

            $chunkLines = null;
            for ($attempt = 0; $attempt < 2 && $chunkLines === null; $attempt++) {
                if ($attempt > 0) {
                    sleep(20); // give the rate-limit window room before the retry
                }
                $resp = $client->complete($system, $userMessage, (string) $settings['model'], $key);
                if (isset($resp['error'])) {
                    error_log('[bbjd house_tx] chunk ' . $i . ' attempt ' . $attempt . ': ' . $resp['error']);
                    continue;
                }
                $parsed = TranscriptLogic::parseLinesJson((string) ($resp['text'] ?? ''));
                if (isset($parsed['error'])) {
                    error_log('[bbjd house_tx] chunk ' . $i . ' attempt ' . $attempt . ': unparseable');
                    continue;
                }
                $chunkLines = $parsed['lines'];
            }

            if ($chunkLines !== null) {
                $lines = array_merge($lines, TranscriptLogic::filterToRoster($chunkLines, $roster));
            }
            // A twice-failed chunk is skipped; later chunks still run.
        }

        if (empty($lines)) {
            return ['success' => false, 'message' => 'Transcript generation failed for every chunk — try again.'];
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

    /**
     * Roster formatted for prompt display: preferred call name first (official
     * nickname when set, else first name), full name in parens for identity.
     * e.g. "Lala (LaTrice Royale)", "Kamu (Kamu Kirk)". Drafts should sound
     * like the house, and the house never says "Lyric Mederios".
     */
    public static function rosterCallNames(): array
    {
        if (!function_exists('bbj_v2_get_season_players')) {
            return ['active' => [], 'evicted' => []];
        }
        $seasonId = (int) get_option('bbj_v2_current_season', 0);
        if ($seasonId <= 0) {
            return ['active' => [], 'evicted' => []];
        }
        $out = ['active' => [], 'evicted' => []];
        foreach ((array) bbj_v2_get_season_players($seasonId, 'thumbnail') as $p) {
            $first = trim((string) ($p['first_name'] ?? ''));
            $last  = trim((string) ($p['last_name'] ?? ''));
            $nick  = trim((string) ($p['official_nickname'] ?? ''));
            $call  = $nick !== '' ? $nick : $first;
            if ($call === '') {
                continue;
            }
            $full = trim($first . ' ' . $last);
            // Same evicted test as the homepage houseboard (jury = out of the
            // house). Without this split the prompts listed evictees as live
            // houseguests and Bean Bot kept "reporting" them in the house
            // (Kamu, 2026-08-22 member reports).
            $evicted = ((int) ($p['current_jury'] ?? 0) === 1) || ((int) ($p['current_evicted'] ?? 0) === 1);
            $out[$evicted ? 'evicted' : 'active'][] = ($full !== '' && $full !== $call) ? "{$call} ({$full})" : $call;
        }
        return $out;
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
