<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Fits a feed update into Bluesky's 300-char budget. Under budget the text
 * passes through untouched (no API call). Over budget, one Haiku call rewrites
 * it — always Haiku regardless of the configured digest model: this fires per
 * cross-post and condensing is a mechanical task. Any failure (no key, API
 * error, still-too-long reply) falls back to a sentence-boundary cut, which
 * beats the old mid-word "..." clip.
 */
class BlueskyCondenser
{
    public const MODEL = 'claude-haiku-4-5';

    /**
     * Portion of the budget a sentence-boundary cut must retain to be used;
     * below this a word-boundary cut keeps more of the update.
     */
    private const MIN_SENTENCE_RATIO = 0.4;

    public const PROMPT = <<<'PROMPT'
You condense live-feed updates for the Big Brother Junkies Bluesky account. You get one
update written in a casual fan voice. Rewrite it to AT MOST %d characters while keeping the
same casual voice, present tense, and as many key facts (names, events, results) as fit.
Cut filler and lower-value detail first. Plain text only. No hashtags, no handles, no
emojis, no em-dashes. Never add anything the original does not say.
Respond with the condensed text only.
PROMPT;

    public static function condense(string $body, int $budget): string
    {
        if (mb_strlen($body) <= $budget) {
            return $body;
        }

        $settings = SocialStore::settings();
        $client   = new AnthropicClient();
        $result   = $client->complete(
            // Ask a little under budget — the model's character counting is
            // approximate and a slightly-over reply would force the fallback.
            sprintf(self::PROMPT, max(50, $budget - 10)),
            html_entity_decode($body, ENT_QUOTES, 'UTF-8'),
            self::MODEL,
            (string) $settings['anthropic_api_key']
        );

        $condensed = isset($result['error']) ? '' : trim((string) $result['text']);
        if ($condensed !== '' && mb_strlen($condensed) <= $budget) {
            return $condensed;
        }

        // A too-long condensed reply is still better raw material than the
        // original: sentence-cut whichever we have.
        return self::fallbackTrim($condensed !== '' ? $condensed : $body, $budget);
    }

    /**
     * Deterministic fit: cut at the last sentence end within budget, else at
     * the last word boundary with an ellipsis. Never mid-word.
     */
    public static function fallbackTrim(string $body, int $budget): string
    {
        if (mb_strlen($body) <= $budget) {
            return $body;
        }

        $slice = mb_substr($body, 0, $budget);

        $sentenceEnd = -1;
        foreach (['.', '!', '?'] as $punct) {
            $pos = mb_strrpos($slice, $punct);
            if ($pos !== false && $pos > $sentenceEnd) {
                $sentenceEnd = $pos;
            }
        }
        if ($sentenceEnd >= (int) ($budget * self::MIN_SENTENCE_RATIO)) {
            return rtrim(mb_substr($body, 0, $sentenceEnd + 1));
        }

        // Word cut: reserve one char for the ellipsis.
        $slice    = mb_substr($body, 0, $budget - 1);
        $lastGap  = mb_strrpos($slice, ' ');
        $cut      = $lastGap !== false && $lastGap > 0 ? mb_substr($slice, 0, $lastGap) : $slice;

        return rtrim($cut) . '…';
    }
}
