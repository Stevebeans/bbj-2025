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

    public function test_parse_drops_blank_posts_and_caps_at_five(): void
    {
        $posts = [['covers' => '', 'text' => '   ']];
        for ($i = 1; $i <= 7; $i++) {
            $posts[] = ['covers' => "h$i", 'text' => "Post $i"];
        }
        $out = DraftBatchLogic::parseModelJson(json_encode(['posts' => $posts]));
        $this->assertCount(5, $out['posts']);
        $this->assertSame('Post 1', $out['posts'][0]['text']);
        $this->assertSame('Post 5', $out['posts'][4]['text']);
    }

    public function test_parse_rejects_garbage_and_empty(): void
    {
        $this->assertArrayHasKey('error', DraftBatchLogic::parseModelJson('not json'));
        $this->assertArrayHasKey('error', DraftBatchLogic::parseModelJson('{"posts":"nope"}'));
        $this->assertArrayHasKey('error', DraftBatchLogic::parseModelJson('{"posts":[]}'));
        $this->assertArrayHasKey('error', DraftBatchLogic::parseModelJson('{"posts":[{"text":""}]}'));
    }
}
