<?php

namespace BigBrotherJunkies\Data\Tests\Social;

use BigBrotherJunkies\Data\Social\QuoteLogic;
use PHPUnit\Framework\TestCase;

class QuoteLogicTest extends TestCase
{
    public function test_parse_valid_candidates_with_fences(): void
    {
        $raw = "```json\n" . json_encode(['candidates' => [
            ['quote' => 'exactly what I had on my bingo card', 'speaker' => 'Barrett', 'echo_count' => 3, 'source_handles' => ['a.bsky.social', 'b.bsky.social']],
        ]]) . "\n```";
        $out = QuoteLogic::parseCandidatesJson($raw);
        $this->assertCount(1, $out['candidates']);
        $this->assertSame('Barrett', $out['candidates'][0]['speaker']);
        $this->assertSame(3, $out['candidates'][0]['echo_count']);
    }

    public function test_parse_caps_at_five_and_clamps_lengths(): void
    {
        $cands = array_fill(0, 8, ['quote' => str_repeat('q', 400), 'speaker' => str_repeat('s', 100), 'echo_count' => 0, 'source_handles' => []]);
        $out = QuoteLogic::parseCandidatesJson(json_encode(['candidates' => $cands]));
        $this->assertCount(5, $out['candidates']);
        $this->assertSame(300, mb_strlen($out['candidates'][0]['quote']));
        $this->assertSame(80, mb_strlen($out['candidates'][0]['speaker']));
        $this->assertSame(1, $out['candidates'][0]['echo_count']); // floor 1
    }

    public function test_parse_rejects_garbage_and_drops_empty_quotes(): void
    {
        $this->assertArrayHasKey('error', QuoteLogic::parseCandidatesJson('not json'));
        $this->assertArrayHasKey('error', QuoteLogic::parseCandidatesJson('{"nope":1}'));
        $out = QuoteLogic::parseCandidatesJson(json_encode(['candidates' => [['quote' => '  ', 'speaker' => 'X']]]));
        $this->assertSame([], $out['candidates']);
    }

    public function test_house_day_bounds_pdt(): void
    {
        // July = PDT (UTC-7): Pacific Jul 14 = 07:00 UTC Jul 14 → 07:00 UTC Jul 15
        $b = QuoteLogic::houseDayBounds('2026-07-14');
        $this->assertSame('2026-07-14 07:00:00', $b['from']);
        $this->assertSame('2026-07-15 07:00:00', $b['to']);
    }
}
