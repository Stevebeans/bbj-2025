<?php

namespace BigBrotherJunkies\Data\Tests\Social;

use BigBrotherJunkies\Data\Social\BlueskyParser;
use PHPUnit\Framework\TestCase;

class BlueskyParserTest extends TestCase
{
    private function post(array $over = []): array
    {
        return array_replace_recursive([
            'uri' => 'at://did:plc:abc/app.bsky.feed.post/xyz1',
            'cid' => 'bafy123',
            'author' => ['handle' => 'watcher.bsky.social', 'displayName' => 'Feed Watcher'],
            'record' => ['text' => 'Dee and Mallory whispering on cam 3', 'createdAt' => '2026-07-12T21:03:05.123Z'],
            'indexedAt' => '2026-07-12T21:03:07Z',
        ], $over);
    }

    public function test_from_search_normalizes_posts(): void
    {
        $rows = BlueskyParser::fromSearch(['posts' => [$this->post()]]);
        $this->assertCount(1, $rows);
        $this->assertSame('at://did:plc:abc/app.bsky.feed.post/xyz1', $rows[0]['uri']);
        $this->assertSame('watcher.bsky.social', $rows[0]['handle']);
        $this->assertSame('Feed Watcher', $rows[0]['display_name']);
        $this->assertSame('Dee and Mallory whispering on cam 3', $rows[0]['text']);
        $this->assertSame('2026-07-12 21:03:05', $rows[0]['posted_at']);
    }

    public function test_from_search_skips_malformed_entries(): void
    {
        $rows = BlueskyParser::fromSearch(['posts' => [
            ['uri' => 'at://only-uri'],
            $this->post(),
        ]]);
        $this->assertCount(1, $rows);
    }

    public function test_from_author_feed_skips_reposts(): void
    {
        $rows = BlueskyParser::fromAuthorFeed(['feed' => [
            ['post' => $this->post(), 'reason' => ['$type' => 'app.bsky.feed.defs#reasonRepost']],
            ['post' => $this->post(['uri' => 'at://did:plc:abc/app.bsky.feed.post/xyz2'])],
        ]]);
        $this->assertCount(1, $rows);
        $this->assertSame('at://did:plc:abc/app.bsky.feed.post/xyz2', $rows[0]['uri']);
    }

    public function test_to_utc_handles_offsets_and_garbage(): void
    {
        $this->assertSame('2026-07-13 04:03:05', BlueskyParser::toUtc('2026-07-12T21:03:05-07:00'));
        $this->assertSame('', BlueskyParser::toUtc('not-a-date'));
        $this->assertSame('', BlueskyParser::toUtc(''));
        $this->assertSame('', BlueskyParser::toUtc('   '));
    }

    public function test_normalize_captures_engagement_counts(): void
    {
        $rows = BlueskyParser::fromSearch(['posts' => [$this->post([
            'likeCount' => 12, 'repostCount' => 3, 'replyCount' => 5, 'quoteCount' => 1,
        ])]]);
        $this->assertSame(12, $rows[0]['likes']);
        $this->assertSame(3, $rows[0]['reposts']);
        $this->assertSame(5, $rows[0]['replies']);
        $this->assertSame(1, $rows[0]['quotes']);
    }

    public function test_missing_counts_default_to_zero(): void
    {
        $rows = BlueskyParser::fromSearch(['posts' => [$this->post()]]);
        $this->assertSame(0, $rows[0]['likes']);
        $this->assertSame(0, $rows[0]['reposts']);
        $this->assertSame(0, $rows[0]['replies']);
        $this->assertSame(0, $rows[0]['quotes']);
    }
}
