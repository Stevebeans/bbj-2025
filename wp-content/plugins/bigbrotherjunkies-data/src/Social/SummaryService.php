<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Builds the Claude-generated live-feed intel digest: pulls the unsummarized
 * post window from SocialStore, prompts Claude with a source-weighted digest
 * (trusted feed-watchers vs. the public hashtag), stores the result, and
 * marks every included post summarized.
 *
 * House rules honored here:
 *  - Stored post text is entity-encoded (wp_encode_emoji) in the DB; it is
 *    html_entity_decode'd back to real characters before it goes to Claude.
 *  - On any Anthropic error, posts are left unsummarized so the next run
 *    retries the same window (no silently dropped coverage).
 *  - SocialStore::insertSummary() already wp_encode_emoji()'s the content
 *    column, so the raw model text is passed through untouched here to
 *    avoid a double encode.
 */
class SummaryService
{
    public const PROMPT = <<<'PROMPT'
You are the live-feed intelligence assistant for Big Brother Junkies (bigbrotherjunkies.com),
working for Steve, who runs the site. He watches the Big Brother live feeds but can only
watch one camera at a time, so he misses conversations, whispers, and events on other cameras.

You receive two sets of Bluesky posts about the current Big Brother season:
1. TRUSTED POSTS from feed-watcher accounts Steve vetted. Treat these as reliable reporting.
2. TAG POSTS from the public hashtag. Unvetted: jokes, spam, and hot takes mixed with
   genuinely valuable reports from cameras nobody else covered.

Produce exactly three sections, in this order, using these exact markdown headings:

## Confirmed timeline
Chronological bullets of events reported by trusted accounts. Each bullet: approximate time,
what happened, who was involved, crediting handles inline like (@handle). When tag posts
independently describe the same event, append a corroboration note like
"(echoed by 6 tag posts)". Merge duplicate reports of the same event into one bullet.

## You might have missed
Events that appear ONLY in tag posts. Include an item only when it is specific (names, a
conversation, a camera or time) and plausibly a real feed event. Each bullet: the claim, how
many apparently independent accounts describe it, any camera/time details given, and a
confidence label: strong / moderate / weak. This section is Steve's tip sheet for what to
scrub back and verify on the feeds. Exclude pure opinions, jokes, memes, and TV-episode
commentary. If nothing qualifies, write "Nothing credible surfaced outside your trusted
sources this window."

## Facebook draft
A discussion-bait post for the Big Brother Junkies Facebook page, ready to paste, in this
exact layout:
1. One eye-popping opening sentence that teases the biggest confirmed development without
   giving it away.
2. On its own line: (feed spoilers below)
3. Then four blank lines, so Facebook's "See more" fold hides everything that follows.
4. The payoff: two to four sentences on the biggest confirmed developments in a casual
   fan voice. Catchy and funny when the material allows it, never forced.
5. A closing question inviting opinions on the key moment. Vary the phrasing every post
   instead of repeating a stock line: "What are your thoughts on...", "Are you buying...",
   "Smart move or disaster?", "Whose side are you on?" are the flavor, not a script. One
   question only.
Spoilers are fine after the fold, never in the opening sentence. Present tense. No hashtags,
no emojis, no handles or attributions. Never tell readers to like, comment, or share
(Facebook demotes that); the question is the call to action. Never include anything that
only appeared in the "You might have missed" section.

Rules: never invent events. When trusted accounts conflict, say so. Quote at most a short
phrase from any single post. Ignore posts about the broadcast episode edit unless they
report live-feed events. Keep the whole output under 700 words.
PROMPT;

    /**
     * @return array{success:true,summary:?array<string,mixed>}|array{success:false,message:string}
     */
    public static function run(): array
    {
        $window  = SocialStore::unsummarizedWindow();
        $trusted = $window['trusted'];
        $tag     = $window['tag'];

        if (empty($trusted) && empty($tag)) {
            return ['success' => false, 'message' => 'No new posts since the last summary.'];
        }

        $userMessage = self::buildUserMessage($window, $trusted, $tag);
        $system      = apply_filters('bbjd_social_summary_prompt', self::PROMPT);

        $settings = SocialStore::settings();
        $client   = new AnthropicClient();
        $result   = $client->complete(
            (string) $system,
            $userMessage,
            (string) $settings['model'],
            (string) $settings['anthropic_api_key']
        );

        if (isset($result['error'])) {
            // Client error: leave every post unsummarized so the next run retries this window.
            return ['success' => false, 'message' => (string) $result['error']];
        }

        $allIds = array_merge(array_column($trusted, 'id'), array_column($tag, 'id'));

        $summaryId = SocialStore::insertSummary([
            'window_from'   => $window['from'],
            'window_to'     => $window['to'],
            'model'         => (string) $settings['model'],
            'post_count'    => count($trusted) + count($tag),
            'trusted_count' => count($trusted),
            'content'       => $result['text'],
        ]);

        SocialStore::markSummarized($allIds);

        $summaries  = SocialStore::summaries(1);
        $summaryRow = $summaries[0] ?? null;
        if ($summaryRow !== null && (int) ($summaryRow['id'] ?? 0) === $summaryId) {
            $summaryRow['content'] = html_entity_decode((string) $summaryRow['content'], ENT_QUOTES, 'UTF-8');
        }

        return ['success' => true, 'summary' => $summaryRow];
    }

    /**
     * @param array{from:?string,to:string} $window
     * @param array<int,array<string,mixed>> $trusted
     * @param array<int,array<string,mixed>> $tag
     */
    private static function buildUserMessage(array $window, array $trusted, array $tag): string
    {
        $handles = array_unique(array_column($trusted, 'handle'));

        $lines   = [];
        $lines[] = sprintf(
            'Season: Big Brother 28. Window: %s to %s (UTC). %d trusted posts from %d accounts, %d tag posts.',
            (string) $window['from'],
            (string) $window['to'],
            count($trusted),
            count($handles),
            count($tag)
        );
        $lines[] = '';
        $lines[] = '=== TRUSTED POSTS ===';
        foreach (self::chronological($trusted) as $post) {
            $lines[] = self::formatPostLine($post);
        }
        $lines[] = '';
        $lines[] = '=== TAG POSTS ===';
        foreach (self::chronological($tag) as $post) {
            $lines[] = self::formatPostLine($post);
        }

        return implode("\n", $lines);
    }

    /**
     * Oldest first, so the model reads posts in chronological order.
     *
     * @param array<int,array<string,mixed>> $posts
     * @return array<int,array<string,mixed>>
     */
    private static function chronological(array $posts): array
    {
        usort($posts, static function (array $a, array $b): int {
            return strcmp((string) $a['posted_at'], (string) $b['posted_at']);
        });
        return $posts;
    }

    private static function formatPostLine(array $post): string
    {
        $text = html_entity_decode((string) $post['text'], ENT_QUOTES, 'UTF-8');
        return sprintf('[%s] @%s: %s', (string) $post['posted_at'], (string) $post['handle'], $text);
    }
}
