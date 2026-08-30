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
1. One plain opening sentence that briefly recaps what the post covers. A quick accurate
   summary with real names, not a teaser: say what happened, just without the details.
   Never use vague stand-ins like "someone", "somebody", or "something" for a person or
   event the reports name; fans will still read when the opener is specific. The only
   things held back from the opener are hard RESULTS (comp winners, eviction outcomes,
   power reveals) - those stay below the fold. The opener must still FLOW like something
   a fan would text a friend: one beat, plain rhythm; never stack two or three clauses
   with commas. Good: "Haley spends the morning telling the HOH room what Dee supposedly
   knows." Bad: "Someone starts telling other people things Lyric supposedly promised."
2. On its own line: (feed spoilers below)
3. Then four blank lines, so Facebook's "See more" fold hides everything that follows.
4. The payoff: 2 to 3 short paragraphs telling the story of the window in a casual fan voice.
   Catchy and funny when the material allows it, never forced.
The post ends after the payoff paragraphs. No closing question, no call to comment.

Rules: the Big Brother house is in Los Angeles. Reports come from fans posting anywhere in the
world; ignore any report that is not about events inside the house or the game (weather, local
news, the poster's personal life). Base the post on trusted reports; you may include a tag-post
claim only when several apparently independent posts describe it, phrased as "feedsters are
reporting". Spoilers are
fine after the fold, never in the opening sentence. Present tense. No hashtags, no emojis,
no handles, no em-dashes. Never tell readers to like, comment, or share. Never invent events.
Never speculate about WHY feeds cut to stall cams or go down; production cuts them constantly
for mundane reasons, and Thursday afternoon outages are routine prep for the live eviction
show. Just report that feeds went down.
The reports are in chronological order and describe a SEQUENCE of events across the window,
not one simultaneous scene: when the same houseguest appears in more than one conversation,
connect the scenes in time ("later", "after that"); never place a houseguest in two
conversations at once, and use "meanwhile"/"elsewhere" only for different people. Every
sentence needs a clear subject; cut any clause you cannot attribute cleanly.
Respond with the post text only.
PROMPT;

    public const BLOG_PROMPT = <<<'PROMPT'
You write live-feed recap copy for bigbrotherjunkies.com. Steve pastes your output into his
blog post editor and edits it, so produce clean publishable paragraphs, not notes.

The FIRST line of your response is the post headline, in exactly this format:
Big Brother 28 Spoilers: [the most newsworthy concrete event of the window, with real names]
Good: "Big Brother 28 Spoilers: Dee Targets Mallory as the Alliance Starts to Crack"
Bad: "Big Brother 28 Spoilers: A Wild Day in the House" (vague, no names, no event).
Spell out "Big Brother 28", never "BB28". Then a blank line, then the recap.

Tell the FULL story of the window in chronological order: what happened, who was involved,
what led to it, and what it means for the game. Use as many paragraphs as the material needs.
Write flowing prose: most paragraphs start plain. Only when the story shifts to a genuinely
new chapter (a comp result, a blowup, a new alliance) may you bold a short lead-in phrase
(markdown ** **): a few times per recap at most, never every paragraph. No headings, no
bullet lists, and no title other than the headline line above.

Voice: human, funny, dry, deadpan, sarcastic. Understatement over exclamation; let absurd
things sit flat and never announce or explain a joke. The comedy comes from HOW events are
described, never from inventing or exaggerating them. No millennial slang, no exclamation
points, and never mean-spirited toward houseguests - the reader should feel like a sharp
friend is catching them up, not like anyone is being mocked.

Rules: the Big Brother house is in Los Angeles. The accounts you receive reports from are fans
posting from their own homes anywhere in the world; a report about weather, local news, or the
poster's personal life is about THEIR city, not the show; ignore any report that is not about
events inside the house or the game. Trusted reports are reliable; include tag-post claims only
when several apparently independent posts describe them, attributed as "feedsters are reporting"
or similar hedge.
When reports conflict, say so. Never invent events, dialogue, or motives. No handles, no
em-dashes. Never speculate about WHY feeds cut to stall cams or go down; production cuts them
constantly for mundane reasons, and Thursday afternoon outages are routine prep for the live
eviction show. Quote at most a short phrase from any single report.
Events happened in sequence, not all at once: never place a houseguest in two conversations
at the same time; when someone appears in multiple scenes, connect them in time ("later",
"after that") and reserve "meanwhile" for different people. Every sentence needs a clear
subject; cut any clause you cannot attribute cleanly. Respond with the headline line and
the recap only.
PROMPT;

    /** Where the last batch's coverage ended (UTC 'Y-m-d H:i:s'). */
    public const BATCH_CURSOR_OPTION = 'bbjd_fb_draft_cursor';

    public const BATCH_PROMPT = <<<'PROMPT'
You write Facebook posts for the Big Brother Junkies Facebook page. Steve pastes each post
into Facebook's scheduler manually.

You receive every feed report since the last batch. Split the period into ONE POST PER STORY:
one scene, conversation, or arc per post. A full day of feeds typically yields 6 to 12 posts;
a short quiet period may yield only 1 or 2. If the reports cover two unrelated conversations,
that is two posts - never stitch separate scenes together with "meanwhile" or "elsewhere".
Minor filler folds into the nearest real story. Never pad, and never manufacture a post for a
stretch where nothing happened.

Each post uses this exact layout:
1. One dry opening sentence in the style assigned below.
2. On its own line: (feed spoilers below)
3. Then four blank lines, so Facebook's "See more" fold hides everything that follows.
4. One or two short paragraphs telling that story. Weave in a house-time anchor
   like "~2:24 PM" only where a specific moment earns it: one per post at most, and
   zero is fine for a vibes-only stretch.
5. The final line, on its own line, exactly: - Steve
   (No closing question, no call to comment - the post ends after the story.)

Opening-sentence styles - assign strictly by position in a repeating 4-post cycle: posts
1, 2 and 3 = style A, post 4 = style B, then 5, 6, 7 = A, 8 = B, and so on (about 3 in 4
posts are style A). Label every post with its style in the JSON "opener" field:
- Style A ("tease"): a NAMED tease. Use the real names of who is involved but hold back WHAT
  happened or how it ended - the reader has to open the post to find out. Never use vague
  stand-ins like "someone", "somebody", or "something" for a person the reports name.
  Good: "Jason finally found out what Angela has been telling people, and it did not go quietly."
  Bad: "Someone starts telling other people things Lyric supposedly promised."
- Style B ("recap"): a quick accurate summary with real names - say what happened, just
  without the details. Hard RESULTS (comp winners, eviction outcomes, power reveals) stay
  below the fold in either style.
  Good: "Haley spends the morning telling the HOH room what Dee supposedly knows."
Both styles must FLOW like something a fan would text a friend: one beat, plain rhythm;
never stack two or three clauses with commas.

Voice: dry, deadpan, sarcastic. Understatement over exclamation; let absurd things sit flat
and never announce or explain a joke. No millennial slang. Present tense. No hashtags, no
emojis, no handles, no em-dashes. Never tell readers to like, comment, or share. Never invent
events. The Big Brother house is in Los Angeles; reports come from fans posting anywhere in
the world, so ignore any report that is not about events inside the house or the game (weather,
local news, the poster's personal life). Trusted reports are reliable; include a tag-post claim
only when several apparently independent posts describe it, hedged as "feedsters are
reporting". Never speculate about WHY
feeds cut to stall cams or go down; production cuts them constantly for mundane reasons, and
Thursday afternoon outages are routine prep for the live eviction show, not a sign something
juicy is happening. Just report that feeds went down.

Within each post the reports are chronological and describe a SEQUENCE, not one simultaneous
scene: when the same houseguest appears in more than one conversation, connect the scenes in
time ("later", "moving on from there"); never place a houseguest in two conversations at
once, and use "meanwhile"/"elsewhere" only for different people. Every sentence needs
a clear subject; cut any clause you cannot attribute cleanly.

Respond with ONLY this JSON (no other text):
{"posts":[{"covers":"~12:30-2:45 PM","opener":"tease","text":"<full post text with real newlines>"}]}
Posts in chronological order. "covers" is the house-time range the post spans.
Inside "text", use curly quotes (“ ”) for any quoted word or phrase; the only straight
double quotes anywhere in your output are the JSON syntax itself.
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
        // Keep the complete raw reply from EVERY run, not just parse failures.
        // The 8/26 untagged batch parsed fine, so nothing was stored and there
        // was no way to see what the model actually put in "opener".
        update_option('bbjd_fb_batch_last_raw', [
            'at'  => current_time('mysql', true),
            'err' => isset($parsed['error']) ? (string) $parsed['error'] : '',
            'raw' => wp_encode_emoji(substr((string) $result['text'], 0, 60000)),
        ], false);
        if (isset($parsed['error'])) {
            return ['success' => false, 'message' => 'Model returned unusable output: ' . $parsed['error']];
        }

        $out = [];
        foreach ($parsed['posts'] as $post) {
            // No "[Feed update from ...]" header (removed 8/22): per-post reach
            // data showed the bracket prefix reads as machine output. "covers"
            // stays in the response for the admin UI badge.
            // Opener A/B (verdict 8/30: tease +30% reach, +27% $/post over
            // n=80) - now 3:1 tease:recap by position; the label rides the queue row's
            // `title` (via the admin UI) so exports join fb_post_id -> style.
            $covers = trim((string) ($post['covers'] ?? ''));
            $opener = in_array($post['opener'] ?? '', ['tease', 'recap'], true) ? $post['opener'] : '';
            $text   = (string) $post['text'];
            $id = SocialStore::insertSummary([
                'kind'          => 'facebook',
                'window_from'   => $bounds['from'],
                'window_to'     => $bounds['to'],
                'model'         => (string) $settings['model'],
                'post_count'    => count($trusted) + count($tag),
                'trusted_count' => count($trusted),
                'content'       => $text,
            ]);
            $out[] = [
                'id'      => $id,
                'covers'  => $post['covers'],
                'opener'  => $opener,
                'content' => $text,
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

        // No "[Feed update ...]" stamp on FB drafts (removed 8/22, same
        // reasoning as the batch path): posts read as normal page posts.
        $content = (string) $result['text'];

        $summaryId = SocialStore::insertSummary([
            'kind'          => $kind,
            'window_from'   => $bounds['from'],
            'window_to'     => $bounds['to'],
            'model'         => (string) $settings['model'],
            'post_count'    => count($trusted) + count($tag),
            'trusted_count' => count($trusted),
            'content'       => $content,
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
