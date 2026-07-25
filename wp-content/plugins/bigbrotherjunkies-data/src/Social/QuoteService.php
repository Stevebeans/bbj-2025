<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Quote-candidate generation: one structured Claude call over a Pacific day's
 * collected posts. Candidates are ephemeral (returned to the admin UI); the
 * only persistence is the digest piggyback row (kind=self::KIND) so the
 * morning skim has yesterday's batch waiting. Approval writes QuoteStore.
 *
 * Note: the bbj_social_summaries.kind column is VARCHAR(12); the literal
 * string "quote_candidates" (16 chars) does not fit it, and SocialStore's
 * DB schema/version are out of scope for this task (a pinned unit test
 * locks SocialStore::DB_VERSION at 1.2.0). KIND therefore uses the
 * shortened, still-unambiguous "quote_cand" (10 chars) consistently for
 * both the write (storeDaily) and read (storedCandidates) paths, so the
 * round-trip is internally consistent even though it isn't the brief's
 * exact literal spelling. See task-4-report.md for the full note.
 */
class QuoteService
{
    public const KIND = 'quote_cand';

    public const PROMPT = <<<'PROMPT'
You extract memorable houseguest quotes from fan reports about the Big Brother live feeds.
A memorable quote is something a HOUSEGUEST actually said, quoted or closely paraphrased in
the reports: funny, outrageous, strategic, or era-defining. Return STRICT JSON only:
{"candidates":[{"quote":"...","speaker":"FirstName","to":"FirstName or null","echo_count":N,"source_handles":["handle",...]}]}
Rules: at most 5 candidates, best first. echo_count = number of APPARENTLY INDEPENDENT accounts
reporting the same line. Never invent or embellish a quote; if reports differ slightly, use the
most common wording. speaker is the houseguest's first name only; use "" if unclear. "to" = the
named listener when a report clearly states who was being spoken to, else null; never guess.
If nothing is quotable, return {"candidates":[]}. No prose, no markdown fences.
PROMPT;

    /** Dry generation for a Pacific day. Persists nothing. */
    public static function candidates(string $ymd): array
    {
        $settings = SocialStore::settings();
        $key      = (string) ($settings['anthropic_api_key'] ?? '');
        if ($key === '') {
            return ['success' => false, 'message' => 'No Anthropic API key configured'];
        }

        $bounds = QuoteLogic::houseDayBounds($ymd);
        // Curated: all trusted + top-500 tag by engagement (good quotes
        // travel); the old chronological read capped at 1000 by early morning.
        $rows   = SocialStore::postsForDayCurated($bounds['from'], $bounds['to']);
        if (empty($rows)) {
            return ['success' => false, 'message' => 'No collected posts for that day.'];
        }

        $lines = [];
        foreach ($rows as $post) {
            $lines[] = sprintf(
                '[%s] @%s: %s',
                BeanBotLogic::toHouseTime((string) $post['posted_at']),
                (string) $post['handle'],
                html_entity_decode((string) $post['text'], ENT_QUOTES, 'UTF-8')
            );
        }

        $client = new AnthropicClient();
        $resp   = $client->complete(
            (string) apply_filters('bbjd_quote_candidates_prompt', self::PROMPT),
            implode("\n", $lines),
            (string) $settings['model'],
            $key
        );
        if (isset($resp['error'])) {
            return ['success' => false, 'message' => (string) $resp['error']];
        }

        $parsed = QuoteLogic::parseCandidatesJson((string) ($resp['text'] ?? ''));
        if (isset($parsed['error'])) {
            return ['success' => false, 'message' => 'Model returned unparseable candidates.'];
        }

        return ['success' => true, 'date' => $ymd, 'candidates' => $parsed['candidates']];
    }

    /** Digest piggyback: generate + persist today's (Pacific) candidates. Never throws. */
    public static function storeDaily(): void
    {
        try {
            $ymd = (new \DateTimeImmutable('now', new \DateTimeZone('America/Los_Angeles')))->format('Y-m-d');
            $out = self::candidates($ymd);
            if (empty($out['success'])) {
                return;
            }

            $bounds   = QuoteLogic::houseDayBounds($ymd);
            $settings = SocialStore::settings();
            // Same curated read as candidates() so post_count metadata
            // reflects what generation actually scanned.
            $rows     = SocialStore::postsForDayCurated($bounds['from'], $bounds['to']);

            // Mirrors DraftService::generate()'s insertSummary() call, with
            // kind = self::KIND, content = the JSON-encoded candidates, and
            // window = the Pacific day's UTC bounds.
            SocialStore::insertSummary([
                'kind'        => self::KIND,
                'window_from' => $bounds['from'],
                'window_to'   => $bounds['to'],
                'model'       => (string) $settings['model'],
                'post_count'  => count($rows),
                'content'     => wp_json_encode($out['candidates']),
            ]);
        } catch (\Throwable $e) {
            error_log('[bbjd quotes] storeDaily failed: ' . $e->getMessage());
        }
    }

    /** Latest stored candidate batch covering the given Pacific day, else null. */
    public static function storedCandidates(string $ymd): ?array
    {
        $bounds = QuoteLogic::houseDayBounds($ymd);

        // Mirrors SocialRoutes::getSummaries()'s kind-filtered read + the
        // html_entity_decode('content') step it applies before returning,
        // since insertSummary() wp_encode_emoji's the content column.
        $rows = SocialStore::summaries(20, self::KIND);
        foreach ($rows as $row) {
            if ((string) ($row['window_from'] ?? '') !== $bounds['from']) {
                continue;
            }
            $content = html_entity_decode((string) ($row['content'] ?? ''), ENT_QUOTES, 'UTF-8');
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                return null;
            }
            return ['date' => $ymd, 'candidates' => $decoded];
        }

        return null;
    }
}
