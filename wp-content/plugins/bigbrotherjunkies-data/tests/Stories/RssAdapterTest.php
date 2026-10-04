<?php
namespace BigBrotherJunkies\Data\Tests\Stories;

use BigBrotherJunkies\Data\Stories\Sources\RssAdapter;
use PHPUnit\Framework\TestCase;

class RssAdapterTest extends TestCase
{
    private array $source = ['show' => 'big-brother', 'type' => 'rss', 'key' => 'https://example.com/feed', 'trust' => 'standard'];

    public function test_parses_rss2_items_and_skips_linkless(): void
    {
        $items = RssAdapter::parse(file_get_contents(__DIR__ . '/../fixtures/stories/rss2.xml'), $this->source);
        $this->assertCount(1, $items);
        $it = $items[0];
        $this->assertSame('big-brother', $it['show']);
        $this->assertSame('rss', $it['source']);
        $this->assertSame('https://example.com/feed', $it['source_key']);
        $this->assertSame('https://example.com/janelle-podcast', $it['url']);
        $this->assertSame('Janelle announces new podcast', $it['title']);
        $this->assertSame('Big Brother legend Janelle is launching a weekly show.', $it['excerpt'], 'html stripped');
        $this->assertSame('Staff Writer', $it['author']);
        $this->assertSame('2026-09-01 14:05:00', $it['published_at']);
        $this->assertSame('image', $it['media_type']);
        $this->assertSame('https://example.com/janelle.jpg', $it['thumbnail_url']);
        $this->assertSame('standard', $it['trust']);
    }

    public function test_parses_youtube_atom(): void
    {
        $items = RssAdapter::parse(file_get_contents(__DIR__ . '/../fixtures/stories/atom-youtube.xml'), $this->source);
        $this->assertCount(1, $items);
        $it = $items[0];
        $this->assertSame('https://www.youtube.com/watch?v=abc123', $it['url']);
        $this->assertSame('My BB28 finale predictions', $it['title']);
        $this->assertSame('Breaking down who I think wins.', $it['excerpt']);
        $this->assertSame('Taylor Hale', $it['author']);
        $this->assertSame('2026-09-01 10:00:00', $it['published_at']);
        $this->assertSame('video', $it['media_type']);
        $this->assertSame('https://i.ytimg.com/vi/abc123/hqdefault.jpg', $it['thumbnail_url']);
    }

    public function test_garbage_returns_empty(): void
    {
        $this->assertSame([], RssAdapter::parse('<html>nope</html>', $this->source));
        $this->assertSame([], RssAdapter::parse('', $this->source));
    }
}
