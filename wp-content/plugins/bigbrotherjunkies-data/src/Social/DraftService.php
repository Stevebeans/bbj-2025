<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Generates a one-off Facebook or blog draft from the collected Bluesky posts
 * in a given time window, for Steve to review and paste/post manually.
 *
 * House rules honored here:
 *  - Stored post text is entity-encoded (wp_encode_emoji) in the DB; it is
 *    html_entity_decode'd back to real characters before it goes to Claude,
 *    mirroring SummaryService.
 *  - postsInWindow() is read-only: it never touches the `summarized` flag or
 *    the Bean Bot cursor, so drafts never interfere with the digest's or Bean
 *    Bot's own bookkeeping.
 *  - Drafts are stored via SocialStore::insertSummary() with an explicit
 *    'kind' ('facebook' | 'blog'); insertSummary() already wp_encode_emoji's
 *    the content column, so the raw model text is passed through untouched
 *    here to avoid a double encode.
 *  - Plain text response from Claude: unlike Bean Bot, there is no JSON
 *    envelope to parse for a draft.
 */
class DraftService
{
    private const ALLOWED_KINDS = ['facebook', 'blog'];

    public const FB_PROMPT = <<<'PROMPT'
You write Facebook posts for the Big Brother Junkies Facebook page, summarizing what happened
on the Big Brother live feeds in a window. Steve will review and post it manually.

Produce the post in this exact layout:
1. One eye-popping opening sentence that teases the biggest development without giving it away.
2. On its own line: (feed spoilers below)
3. Then four blank lines, so Facebook's "See more" fold hides everything that follows.
4. The payoff: 2 to 3 short paragraphs telling the story of the window in a casual fan voice.
   Catchy and funny when the material allows it, never forced.
5. A closing question inviting opinions on the key moment. Vary the phrasing (never a stock
   line): "What are your thoughts on...", "Are you buying...", "Smart move or disaster?",
   "Whose side are you on?" are the flavor, not a script. One question only.

Rules: base the post on trusted reports; you may include a tag-post claim only when several
apparently independent posts describe it, phrased as "feedsters are reporting". Spoilers are
fine after the fold, never in the opening sentence. Present tense. No hashtags, no emojis,
no handles, no em-dashes. Never tell readers to like, comment, or share. Never invent events.
Respond with the post text only.
PROMPT;

    public const BLOG_PROMPT = <<<'PROMPT'
You write live-feed recap copy for bigbrotherjunkies.com. Steve pastes your output into his
blog post editor and edits it, so produce clean publishable paragraphs, not notes.

Tell the FULL story of the window in chronological order: what happened, who was involved,
what led to it, and what it means for the game. Use as many paragraphs as the material needs.
Bold a short lead-in phrase at the start of each major beat (markdown ** **). No headings,
no bullet lists, no title. Casual, knowledgeable fan voice with light snark where it fits.

Rules: trusted reports are reliable; include tag-post claims only when several apparently
independent posts describe them, attributed as "feedsters are reporting" or similar hedge.
When reports conflict, say so. Never invent events, dialogue, or motives. No handles, no
em-dashes. Quote at most a short phrase from any single report. Respond with the recap only.
PROMPT;

    /**
     * @return array{success:true,draft:?array<string,mixed>}|array{success:false,message:string}
     */
    public static function generate(string $kind, string $window): array
    {
        if (!in_array($kind, self::ALLOWED_KINDS, true)) {
            return ['success' => false, 'message' => 'Invalid draft kind.'];
        }

        $bounds  = BeanBotLogic::windowBounds($window, new \DateTimeImmutable('now', new \DateTimeZone('UTC')), wp_timezone());
        $posts   = SocialStore::postsInWindow((string) $bounds['from'], (string) $bounds['to']);
        $trusted = $posts['trusted'];
        $tag     = $posts['tag'];

        if (empty($trusted) && empty($tag)) {
            return ['success' => false, 'message' => 'No collected posts in that window.'];
        }

        $userMessage = self::buildUserMessage($bounds, $trusted, $tag);
        $system      = $kind === 'blog'
            ? apply_filters('bbjd_bean_bot_blog_prompt', self::BLOG_PROMPT)
            : apply_filters('bbjd_bean_bot_fb_prompt', self::FB_PROMPT);

        $settings = SocialStore::settings();
        $client   = new AnthropicClient();
        $result   = $client->complete(
            (string) $system,
            $userMessage,
            (string) $settings['model'],
            (string) $settings['anthropic_api_key']
        );

        if (isset($result['error'])) {
            return ['success' => false, 'message' => (string) $result['error']];
        }

        $summaryId = SocialStore::insertSummary([
            'kind'          => $kind,
            'window_from'   => $bounds['from'],
            'window_to'     => $bounds['to'],
            'model'         => (string) $settings['model'],
            'post_count'    => count($trusted) + count($tag),
            'trusted_count' => count($trusted),
            'content'       => $result['text'],
        ]);

        $drafts   = SocialStore::summaries(1, $kind);
        $draftRow = $drafts[0] ?? null;
        if ($draftRow !== null && (int) ($draftRow['id'] ?? 0) === $summaryId) {
            $draftRow['content'] = html_entity_decode((string) $draftRow['content'], ENT_QUOTES, 'UTF-8');
        }

        return ['success' => true, 'draft' => $draftRow];
    }

    /**
     * Shared user-message builder for both draft kinds (entities decoded for
     * Claude; stored text is entity-encoded in the DB).
     *
     * @param array{from:string,to:string} $bounds
     * @param array<int,array<string,mixed>> $trusted
     * @param array<int,array<string,mixed>> $tag
     */
    private static function buildUserMessage(array $bounds, array $trusted, array $tag): string
    {
        $lines   = [];
        $lines[] = sprintf(
            'Season: Big Brother 28. Window: %s to %s PT. %d trusted reports, %d public tag posts. Times on each report line are Pacific house time.',
            BeanBotLogic::toHouseTime((string) $bounds['from']),
            BeanBotLogic::toHouseTime((string) $bounds['to']),
            count($trusted),
            count($tag)
        );
        $lines[] = '';
        $lines[] = '=== TRUSTED REPORTS (reliable) ===';
        foreach (self::chronological($trusted) as $post) {
            $lines[] = sprintf(
                '[%s] %s',
                BeanBotLogic::toHouseTime((string) $post['posted_at']),
                html_entity_decode((string) $post['text'], ENT_QUOTES, 'UTF-8')
            );
        }
        $lines[] = '';
        $lines[] = '=== TAG POSTS (unvetted; only trust claims echoed by several independent posts) ===';
        foreach (self::chronological($tag) as $post) {
            $lines[] = sprintf(
                '[%s] @%s: %s',
                BeanBotLogic::toHouseTime((string) $post['posted_at']),
                (string) $post['handle'],
                html_entity_decode((string) $post['text'], ENT_QUOTES, 'UTF-8')
            );
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
}
