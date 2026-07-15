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

    public function test_filter_to_roster_drops_phantom_speakers_and_clears_bad_listeners(): void
    {
        $lines = [
            ['time' => '1', 'speaker' => 'Barrett', 'to' => 'Angela', 'quote' => 'a', 'kind' => 'verbatim', 'source' => 'x'],
            ['time' => '2', 'speaker' => 'Devens', 'to' => 'Taran', 'quote' => 'b', 'kind' => 'verbatim', 'source' => 'x'],
            ['time' => '3', 'speaker' => 'Maggie', 'to' => null, 'quote' => 'c', 'kind' => 'verbatim', 'source' => 'x'],
            ['time' => '4', 'speaker' => 'haley', 'to' => 'Thogmartin', 'quote' => 'd', 'kind' => 'verbatim', 'source' => 'x'],
        ];
        $roster = ['Barrett Pfeiffer', 'Angela Devens', 'Haley Thogmartin'];
        $out = TranscriptLogic::filterToRoster($lines, $roster);
        $this->assertCount(3, $out); // Maggie dropped
        $this->assertSame('Barrett', $out[0]['speaker']);
        $this->assertSame('Angela', $out[0]['to']);
        $this->assertNull($out[1]['to']); // Taran (podcaster) cleared, Devens (last name) kept
        $this->assertSame('Thogmartin', $out[2]['to']); // last names count as roster names
    }

    public function test_filter_to_roster_passthrough_when_roster_empty(): void
    {
        $lines = [['time' => '1', 'speaker' => 'Anyone', 'to' => null, 'quote' => 'a', 'kind' => 'verbatim', 'source' => 'x']];
        $this->assertSame($lines, TranscriptLogic::filterToRoster($lines, []));
    }

    public function test_parse_rejects_garbage(): void
    {
        $this->assertArrayHasKey('error', TranscriptLogic::parseLinesJson('not json'));
        $this->assertArrayHasKey('error', TranscriptLogic::parseLinesJson('{"nope":1}'));
    }
}
