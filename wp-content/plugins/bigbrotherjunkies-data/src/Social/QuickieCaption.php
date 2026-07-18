<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * One dry Steve line to caption a quickie card. Always Haiku regardless of
 * the configured digest model: the card carries the content, the caption is
 * a single beat, and this fires per-quickie so it should stay cheap.
 */
class QuickieCaption
{
    public const MODEL = 'claude-haiku-4-5';

    public const PROMPT = <<<'PROMPT'
You write one short Facebook caption for the Big Brother Junkies page. The image being posted
(which you cannot see) is a styled quote card of a fan's Bluesky post about the Big Brother
live feeds. The caption is Steve's single dry comment reacting to that post.

Voice: dry, deadpan, understated; let absurd things sit flat; never announce or explain the
joke. One or two short sentences maximum. A closing question is allowed but optional. Present
tense. No hashtags, no emojis, no handles, no em-dashes. Never tell readers to like, comment,
or share. Never invent events beyond what the post says. The Big Brother house is in Los
Angeles. Never speculate about why feeds cut to stall cams or go down.
Respond with the caption text only.
PROMPT;

    /**
     * @return array{success:true,caption:string}|array{success:false,message:string}
     */
    public static function generate(string $postText, string $handle): array
    {
        $settings = SocialStore::settings();
        $client   = new AnthropicClient();
        $result   = $client->complete(
            (string) apply_filters('bbjd_quickie_caption_prompt', self::PROMPT),
            "Fan post by @{$handle}:\n\n" . html_entity_decode($postText, ENT_QUOTES, 'UTF-8'),
            self::MODEL,
            (string) $settings['anthropic_api_key']
        );
        if (isset($result['error'])) {
            return ['success' => false, 'message' => (string) $result['error']];
        }
        return ['success' => true, 'caption' => trim((string) $result['text'])];
    }
}
