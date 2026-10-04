<?php
namespace BigBrotherJunkies\Data\Tests\Stories;

use BigBrotherJunkies\Data\Stories\Sources\RedditAdapter;
use PHPUnit\Framework\TestCase;

class RedditAdapterTest extends TestCase
{
    private function json(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/../fixtures/stories/reddit-new.json'), true);
    }

    public function test_parses_posts_with_permalink_url_and_score(): void
    {
        $items = RedditAdapter::parse($this->json(), ['show' => 'big-brother', 'type' => 'reddit', 'key' => 'BigBrother', 'trust' => 'standard', 'flair' => '']);
        $this->assertCount(3, $items);
        $this->assertSame('https://www.reddit.com/r/BigBrother/comments/a1/x/', $items[0]['url']);
        $this->assertSame('Spoiler: Vince wins HOH', $items[0]['title']);
        $this->assertSame('Per the feeds just now.', $items[0]['excerpt']);
        $this->assertSame('u/feedwatcher', $items[0]['author']);
        $this->assertSame(412, $items[0]['score']);
        $this->assertSame('reddit', $items[0]['source']);
        $this->assertSame('BigBrother', $items[0]['source_key']);
        $this->assertSame(gmdate('Y-m-d H:i:s', 1788610000), $items[0]['published_at']);
        // Link post: excerpt carries the outbound URL, thumbnail kept, media image.
        $this->assertSame('https://i.redd.it/abc.jpg', $items[1]['excerpt']);
        $this->assertSame('image', $items[1]['media_type']);
        $this->assertSame('https://b.thumbs.redditmedia.com/t.jpg', $items[1]['thumbnail_url']);
        $this->assertSame('', $items[2]['thumbnail_url'], '"self" is not a URL');
    }

    public function test_flair_filter_is_case_insensitive_and_comma_separated(): void
    {
        $items = RedditAdapter::parse($this->json(), ['show' => 'big-brother', 'type' => 'reddit', 'key' => 'BigBrother', 'trust' => 'standard', 'flair' => 'spoilers, former houseguests']);
        $this->assertCount(2, $items);
        $this->assertSame('Spoiler: Vince wins HOH', $items[0]['title']);
    }

    public function test_malformed_returns_empty(): void
    {
        $this->assertSame([], RedditAdapter::parse(['nope' => 1], ['show' => 'x', 'type' => 'reddit', 'key' => 'y', 'trust' => 'low']));
    }
}
