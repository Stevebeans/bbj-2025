<?php

namespace BigBrotherJunkies\Data\Tests\Social;

use BigBrotherJunkies\Data\Social\TranscriptLogic;
use PHPUnit\Framework\TestCase;

class TranscriptLogicTest extends TestCase
{
    public function test_parse_valid_lines_with_fences(): void
    {
        $raw = "```json\n" . json_encode(['lines' => [
            ['time' => '8:18 PM', 'speaker' => 'Chuk', 'to' => 'Angela', 'quote' => 'You ARE my TV', 'kind' => 'verbatim', 'source' => 'housewifery.bsky.social'],
            ['time' => '8:20 PM', 'speaker' => 'Dee', 'to' => null, 'quote' => 'she has got to win', 'kind' => 'paraphrase', 'source' => 'iamerika.bsky.social'],
        ]]) . "\n```";
        $out = TranscriptLogic::parseLinesJson($raw);
        $this->assertCount(2, $out['lines']);
        $this->assertSame('Chuk', $out['lines'][0]['speaker']);
        $this->assertSame('Angela', $out['lines'][0]['to']);
        $this->assertSame('verbatim', $out['lines'][0]['kind']);
        $this->assertNull($out['lines'][1]['to']);
        $this->assertSame('paraphrase', $out['lines'][1]['kind']);
    }

    public function test_parse_caps_clamps_and_defaults(): void
    {
        $lines = array_fill(0, 350, [
            'time' => str_repeat('t', 40), 'speaker' => str_repeat('s', 100),
            'to' => '', 'quote' => str_repeat('q', 400), 'kind' => 'shouted', 'source' => 'a.bsky.social',
        ]);
        $out = TranscriptLogic::parseLinesJson(json_encode(['lines' => $lines]));
        $this->assertCount(300, $out['lines']);
        $l = $out['lines'][0];
        $this->assertSame(20, mb_strlen($l['time']));
        $this->assertSame(80, mb_strlen($l['speaker']));
        $this->assertSame(280, mb_strlen($l['quote']));
        $this->assertNull($l['to']); // empty string -> null
        $this->assertSame('verbatim', $l['kind']); // unknown kind -> verbatim
    }

    public function test_parse_drops_lines_missing_quote_or_speaker(): void
    {
        $out = TranscriptLogic::parseLinesJson(json_encode(['lines' => [
            ['time' => '1 PM', 'speaker' => '', 'quote' => 'hi', 'source' => 'x'],
            ['time' => '1 PM', 'speaker' => 'Dee', 'quote' => '  ', 'source' => 'x'],
            ['time' => '1 PM', 'speaker' => 'Dee', 'quote' => 'ok', 'source' => 'x'],
        ]]));
        $this->assertCount(1, $out['lines']);
        $this->assertSame('ok', $out['lines'][0]['quote']);
    }

    public function test_parse_rejects_garbage(): void
    {
        $this->assertArrayHasKey('error', TranscriptLogic::parseLinesJson('not json'));
        $this->assertArrayHasKey('error', TranscriptLogic::parseLinesJson('{"nope":1}'));
    }
}
