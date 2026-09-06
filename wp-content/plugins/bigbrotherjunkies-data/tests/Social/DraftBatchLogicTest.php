<?php

namespace BigBrotherJunkies\Data\Tests\Social;

use BigBrotherJunkies\Data\Social\DraftBatchLogic;
use PHPUnit\Framework\TestCase;

class DraftBatchLogicTest extends TestCase
{
    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-07-15 20:00:00', new \DateTimeZone('UTC'));
    }

    public function test_window_defaults_to_six_hours_without_cursor(): void
    {
        foreach ([null, '', 'not-a-date'] as $cursor) {
            $w = DraftBatchLogic::windowFor($cursor, $this->now());
            $this->assertSame('2026-07-15 14:00:00', $w['from'], var_export($cursor, true));
            $this->assertSame('2026-07-15 20:00:00', $w['to']);
        }
    }

    public function test_window_uses_fresh_cursor_verbatim(): void
    {
        $w = DraftBatchLogic::windowFor('2026-07-15 17:30:00', $this->now());
        $this->assertSame('2026-07-15 17:30:00', $w['from']);
        $this->assertSame('2026-07-15 20:00:00', $w['to']);
    }

    public function test_window_clamps_stale_cursor_to_24h(): void
    {
        $w = DraftBatchLogic::windowFor('2026-07-10 09:00:00', $this->now());
        $this->assertSame('2026-07-14 20:00:00', $w['from']);
    }

    public function test_parse_valid_batch_with_fences(): void
    {
        $raw = "```json\n{\"posts\":[{\"covers\":\"~12:30-1:45 PM\",\"text\":\"Post one.\"},{\"covers\":\"~2 PM\",\"text\":\"Post two.\"}]}\n```";
        $out = DraftBatchLogic::parseModelJson($raw);
        $this->assertCount(2, $out['posts']);
        $this->assertSame('~12:30-1:45 PM', $out['posts'][0]['covers']);
        $this->assertSame('Post two.', $out['posts'][1]['text']);
    }

    public function test_parse_drops_blank_posts_and_caps_at_max(): void
    {
        // Cap raised 5 -> 15 on 8/22 (single-story batches); test tracks the constant.
        $posts = [['covers' => '', 'text' => '   ']];
        for ($i = 1; $i <= DraftBatchLogic::MAX_POSTS + 2; $i++) {
            $posts[] = ['covers' => "h$i", 'text' => "Post $i"];
        }
        $out = DraftBatchLogic::parseModelJson(json_encode(['posts' => $posts]));
        $this->assertCount(DraftBatchLogic::MAX_POSTS, $out['posts']);
        $this->assertSame('Post 1', $out['posts'][0]['text']);
        $this->assertSame('Post ' . DraftBatchLogic::MAX_POSTS, $out['posts'][DraftBatchLogic::MAX_POSTS - 1]['text']);
    }

    public function test_normalize_opener_accepts_variants(): void
    {
        // The model occasionally capitalizes, pads, or answers with the style
        // letter; anything recognizable maps to a clean label, garbage to ''.
        $this->assertSame('tease', DraftBatchLogic::normalizeOpener('tease'));
        $this->assertSame('tease', DraftBatchLogic::normalizeOpener(' Tease '));
        $this->assertSame('tease', DraftBatchLogic::normalizeOpener('Style A ("tease")'));
        $this->assertSame('tease', DraftBatchLogic::normalizeOpener('A'));
        $this->assertSame('recap', DraftBatchLogic::normalizeOpener('recap'));
        $this->assertSame('recap', DraftBatchLogic::normalizeOpener('RECAP'));
        $this->assertSame('recap', DraftBatchLogic::normalizeOpener('style b'));
        $this->assertSame('recap', DraftBatchLogic::normalizeOpener('b'));
        $this->assertSame('', DraftBatchLogic::normalizeOpener(''));
        $this->assertSame('', DraftBatchLogic::normalizeOpener('funny'));
    }

    public function test_parse_normalizes_openers(): void
    {
        $out = DraftBatchLogic::parseModelJson(json_encode(['posts' => [
            ['covers' => 'h1', 'opener' => 'Tease', 'text' => 'One'],
            ['covers' => 'h2', 'opener' => 'STYLE B', 'text' => 'Two'],
            ['covers' => 'h3', 'opener' => 'whatever', 'text' => 'Three'],
        ]]));
        $this->assertSame('tease', $out['posts'][0]['opener']);
        $this->assertSame('recap', $out['posts'][1]['opener']);
        $this->assertSame('', $out['posts'][2]['opener']);
    }

    public function test_parse_rejects_garbage_and_empty(): void
    {
        $this->assertArrayHasKey('error', DraftBatchLogic::parseModelJson('not json'));
        $this->assertArrayHasKey('error', DraftBatchLogic::parseModelJson('{"posts":"nope"}'));
        $this->assertArrayHasKey('error', DraftBatchLogic::parseModelJson('{"posts":[]}'));
        $this->assertArrayHasKey('error', DraftBatchLogic::parseModelJson('{"posts":[{"text":""}]}'));
    }

    public function test_parse_repairs_literal_newlines_in_text(): void
    {
        // The real failure: the model emits a raw newline (the FB fold) inside
        // the JSON string, which strict json_decode rejects. It must be salvaged.
        $raw = "{\"posts\":[{\"covers\":\"~2 PM\",\"text\":\"Opening line.\n\n\n\nThe payoff paragraph.\"}]}";
        $out = DraftBatchLogic::parseModelJson($raw);
        $this->assertArrayNotHasKey('error', $out, 'literal newlines should be repaired, not rejected');
        $this->assertCount(1, $out['posts']);
        $this->assertStringContainsString('The payoff paragraph.', $out['posts'][0]['text']);
        $this->assertStringContainsString("\n", $out['posts'][0]['text'], 'newlines preserved in the decoded text');
    }

    public function test_parse_strips_prose_around_json(): void
    {
        $raw = "Sure! Here are the posts:\n{\"posts\":[{\"covers\":\"h\",\"text\":\"Body.\"}]}\nHope that helps.";
        $out = DraftBatchLogic::parseModelJson($raw);
        $this->assertArrayNotHasKey('error', $out);
        $this->assertSame('Body.', $out['posts'][0]['text']);
    }

    public function test_parse_accepts_bare_array_envelope(): void
    {
        $raw = "[{\"covers\":\"h1\",\"text\":\"One.\"},{\"covers\":\"h2\",\"text\":\"Two.\"}]";
        $out = DraftBatchLogic::parseModelJson($raw);
        $this->assertArrayNotHasKey('error', $out);
        $this->assertCount(2, $out['posts']);
        $this->assertSame('Two.', $out['posts'][1]['text']);
    }

    public function test_parse_repairs_trailing_comma(): void
    {
        $raw = "{\"posts\":[{\"covers\":\"h\",\"text\":\"Body.\"},]}";
        $out = DraftBatchLogic::parseModelJson($raw);
        $this->assertArrayNotHasKey('error', $out);
        $this->assertSame('Body.', $out['posts'][0]['text']);
    }

    public function test_parse_repairs_invalid_escape_sequence(): void
    {
        // \' is not a legal JSON escape; models slip these in occasionally.
        $raw = "{\"posts\":[{\"covers\":\"h\",\"text\":\"It\\'s a mess in there.\"}]}";
        $out = DraftBatchLogic::parseModelJson($raw);
        $this->assertArrayNotHasKey('error', $out);
        $this->assertStringContainsString('s a mess', $out['posts'][0]['text']);
    }

    public function test_parse_salvages_unescaped_inner_quotes(): void
    {
        // The CONFIRMED 7/17 prod failure: the model escaped quotes in post 1
        // but emitted raw ones later ("lines drawn", "dicey"), killing decode.
        $raw = '{"posts":[' .
            '{"covers":"~5-6 PM","text":"Ashley\'s reaction to hearing \\"14-0\\" out loud.\\n- Steve"},' .
            '{"covers":"~9:45 PM","text":"The ordeal left "lines drawn" between camps, things are about to get "dicey" in there.\\n- Steve"}' .
            ']}';
        $out = DraftBatchLogic::parseModelJson($raw);
        $this->assertArrayNotHasKey('error', $out, 'unescaped inner quotes must be salvaged');
        $this->assertCount(2, $out['posts']);
        $this->assertStringContainsString('"14-0"', $out['posts'][0]['text']);
        $this->assertStringContainsString('"lines drawn"', $out['posts'][1]['text']);
        $this->assertStringContainsString("\n- Steve", $out['posts'][1]['text']);
        $this->assertSame('~9:45 PM', $out['posts'][1]['covers']);
    }

    public function test_parse_reports_error_on_truncated_json(): void
    {
        // Cut off mid-string (no closing quote/brace) => genuinely unrecoverable.
        $raw = "{\"posts\":[{\"covers\":\"h\",\"text\":\"This got cut off mid sen";
        $this->assertArrayHasKey('error', DraftBatchLogic::parseModelJson($raw));
    }

    public function test_salvage_survives_opener_field_and_literal_newlines(): void
    {
        // Prod 9/5: the model kept the documented envelope (covers, opener, text)
        // but used straight quotes inside one post and raw newlines in later
        // ones. Decoders reject that; the salvage regex must still see every
        // post (it was written before "opener" existed and never matched since).
        $raw = "{\"posts\":[{\"covers\":\"~6:58-7:20 PM\",\"opener\":\"recap\",\"text\":\"Taylor promises a shower that will be \"a little hood.\"\n- Steve\"},"
            . "{\"covers\":\"~8:08-8:23 PM\",\"opener\":\"tease\",\"text\":\"Angela has a rough night.\n(feed spoilers below)\n\nDee comforts her.\n- Steve\"}]}";
        $out = DraftBatchLogic::parseModelJson($raw);
        $this->assertArrayNotHasKey('error', $out, 'salvage must rescue the batch');
        $this->assertCount(2, $out['posts']);
        $this->assertSame('recap', $out['posts'][0]['opener']);
        $this->assertSame('tease', $out['posts'][1]['opener']);
        $this->assertStringContainsString('"a little hood."', $out['posts'][0]['text']);
        $this->assertStringContainsString("Angela has a rough night.\n(feed spoilers below)", $out['posts'][1]['text']);
    }
}
