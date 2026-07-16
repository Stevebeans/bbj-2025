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
Write flowing prose: most paragraphs start plain. Only when the story shifts to a genuinely
new chapter (a comp result, a blowup, a new alliance) may you bold a short lead-in phrase
(markdown ** **) — a few times per recap at most, never every paragraph. No headings, no
bullet lists, no title. Casual, knowledgeable fan voice with light snark where it fits.

Rules: trusted reports are reliable; include tag-post claims only when several apparently
independent posts describe them, attributed as "feedsters are reporting" or similar hedge.
When reports conflict, say so. Never invent events, dialogue, or motives. No handles, no
em-dashes. Quote at most a short phrase from any single report. Respond with the recap only.
PROMPT;

    /** Where the last batch's coverage ended (UTC 'Y-m-d H:i:s'). */
    public const BATCH_CURSOR_OPTION = 'bbjd_fb_draft_cursor';

    public const BATCH_PROMPT = <<<'PROMPT'
You write Facebook posts for the Big Brother Junkies Facebook page. Steve pastes each post
into Facebook's scheduler manually.

You receive every feed report since the last batch. Split the period into 1 to 5 posts: one
post per coherent chapter of house events, roughly one per 1-2 hours of real activity. Quiet
stretches fold into a neighboring post. Never pad: if the whole period is one story, return
one post.

Each post uses this exact layout:
1. One dry opening sentence teasing that chapter's biggest development without giving it away.
2. On its own line: (feed spoilers below)
3. Then four blank lines, so Facebook's "See more" fold hides everything that follows.
4. Two to three short paragraphs telling that chapter's story. Weave in a house-time anchor
   like "~2:24 PM" only where a specific moment earns it: one or two per post at most, and
   zero is fine for a vibes-only stretch.
5. One closing question inviting opinions. Vary the phrasing; never a stock line.
6. The final line, on its own line, exactly: - Steve

Voice: dry, deadpan, sarcastic. Understatement over exclamation; let absurd things sit flat
and never announce or explain a joke. No millennial slang. Present tense. No hashtags, no
emojis, no handles, no em-dashes. Never tell readers to like, comment, or share. Never invent
events. Trusted reports are reliable; include a tag-post claim only when several apparently
independent posts describe it, hedged as "feedsters are reporting".

Respond with ONLY this JSON (no other text):
{"posts":[{"covers":"~12:30-2:45 PM","text":"<full post text with real newlines>"}]}
Posts in chronological order. "covers" is the house-time range the post spans.
PROMPT;

    /**
     * Batch mode: everything since the last batch cursor, split by the model
     * into 1-5 queueable FB posts. Each post is stored as its own 'facebook'
     * summary row (so History shows them); the cursor advances only on success.
     *
     * @return array{success:true,posts:list<array<string,mixed>>,window:array{from:string,to:string}}|array{success:false,message:string}
     */
    public static function generateBatch(): array
    {
        $cursor = (string) get_option(self::BATCH_CURSOR_OPTION, '');
        $bounds = DraftBatchLogic::windowFor($cursor, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $posts   = SocialStore::postsInWindow($bounds['from'], $bounds['to']);
        $trusted = $posts['trusted'];
        $tag     = $posts['tag'];

        if (empty($trusted)) {
            return [
                'success' => false,
                'message' => 'No trusted reports since ' . BeanBotLogic::toHouseTime($bounds['from']) . ' PT.',
            ];
        }

        $settings = SocialStore::settings();
        $client   = new AnthropicClient();
        $result   = $client->complete(
            (string) apply_filters('bbjd_fb_batch_prompt', self::BATCH_PROMPT),
            self::buildUserMessage($bounds, $trusted, $tag),
            (string) $settings['model'],
            (string) $settings['anthropic_api_key']
        );

        if (isset($result['error'])) {
            return ['success' => false, 'message' => (string) $result['error']];
        }

        $parsed = DraftBatchLogic::parseModelJson((string) $result['text']);
        if (isset($parsed['error'])) {
            return ['success' => false, 'message' => 'Model returned unusable output: ' . $parsed['error']];
        }

        $out = [];
        foreach ($parsed['posts'] as $post) {
            $id = SocialStore::insertSummary([
                'kind'          => 'facebook',
                'window_from'   => $bounds['from'],
                'window_to'     => $bounds['to'],
                'model'         => (string) $settings['model'],
                'post_count'    => count($trusted) + count($tag),
                'trusted_count' => count($trusted),
                'content'       => $post['text'],
            ]);
            $out[] = [
                'id'      => $id,
                'covers'  => $post['covers'],
                'content' => $post['text'],
            ];
        }

        update_option(self::BATCH_CURSOR_OPTION, $bounds['to'], false);

        return ['success' => true, 'posts' => $out, 'window' => $bounds];
    }

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
