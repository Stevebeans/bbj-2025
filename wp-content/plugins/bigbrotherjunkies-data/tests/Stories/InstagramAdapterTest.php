<?php
namespace BigBrotherJunkies\Data\Tests\Stories;

use BigBrotherJunkies\Data\Stories\Sources\InstagramAdapter;
use PHPUnit\Framework\TestCase;

class InstagramAdapterTest extends TestCase
{
    private array $source = ['show' => 'big-brother', 'type' => 'instagram', 'key' => 'janellepierzina', 'trust' => 'trusted'];

    public function test_parses_media_with_credit_author_and_engagement_score(): void
    {
        $json  = json_decode(file_get_contents(__DIR__ . '/../fixtures/stories/ig-business-discovery.json'), true);
        $items = InstagramAdapter::parse($json, $this->source);
        $this->assertCount(3, $items);
        $img = $items[0];
        $this->assertSame('https://www.instagram.com/p/AAA111/', $img['url']);
        $this->assertSame('instagram', $img['source']);
        $this->assertSame('janellepierzina', $img['source_key']);
        $this->assertSame('@janellepierzina', $img['author']);
        $this->assertSame('Podcast drops Thursday. Who should be my first guest? #BB28', $img['excerpt']);
        $this->assertSame('Podcast drops Thursday. Who should be my first guest?', $img['title'], 'title = first sentence-ish, hashtags dropped');
        $this->assertSame('image', $img['media_type']);
        $this->assertSame('https://scontent.cdninstagram.com/m1.jpg', $img['media_url']);
        $this->assertSame('https://scontent.cdninstagram.com/m1.jpg', $img['thumbnail_url'], 'images use media_url as thumb');
        $this->assertSame('2026-09-01 18:20:00', $img['published_at']);
        $this->assertSame(5400 + 312 * 3, $img['score'], 'likes + 3x comments');
        $this->assertSame('trusted', $img['trust']);

        $vid = $items[1];
        $this->assertSame('video', $vid['media_type']);
        $this->assertSame('https://scontent.cdninstagram.com/m2_thumb.jpg', $vid['thumbnail_url']);

        $car = $items[2];
        $this->assertSame('carousel', $car['media_type']);
        $this->assertSame('', $car['excerpt'], 'no caption ok');
        $this->assertSame('Instagram post by @janellepierzina', $car['title']);
    }

    public function test_error_payload_returns_empty(): void
    {
        $this->assertSame([], InstagramAdapter::parse(['error' => ['message' => 'nope']], $this->source));
    }
}
