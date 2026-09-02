<?php
namespace BigBrotherJunkies\Data\Tests\Stories;

use BigBrotherJunkies\Data\Stories\QuickieHandoffLogic;
use PHPUnit\Framework\TestCase;

class QuickieHandoffLogicTest extends TestCase
{
    private function ig(): array
    {
        return ['source' => 'instagram', 'source_key' => 'janellepierzina', 'author' => '@janellepierzina',
                'url' => 'https://www.instagram.com/p/AAA111/', 'title' => 'Podcast drops Thursday.',
                'excerpt' => "Podcast drops Thursday. Who should be my first guest? #BB28\nLink in bio"];
    }

    public function test_credit_line_names_handle_platform_and_link(): void
    {
        $this->assertSame("📸 @janellepierzina on Instagram\nhttps://www.instagram.com/p/AAA111/", QuickieHandoffLogic::creditLine($this->ig()));
        $rss = ['source' => 'rss', 'source_key' => 'https://example.com/feed', 'author' => 'Staff Writer', 'url' => 'https://example.com/a', 'title' => 'T', 'excerpt' => 'E'];
        $this->assertSame("via Staff Writer\nhttps://example.com/a", QuickieHandoffLogic::creditLine($rss));
        $reddit = ['source' => 'reddit', 'source_key' => 'BigBrother', 'author' => 'u/feedwatcher', 'url' => 'https://www.reddit.com/r/BigBrother/comments/a1/x/', 'title' => 'T', 'excerpt' => 'E'];
        $this->assertSame("via u/feedwatcher on r/BigBrother\nhttps://www.reddit.com/r/BigBrother/comments/a1/x/", QuickieHandoffLogic::creditLine($reddit));
    }

    public function test_default_caption_is_caption_plus_credit_without_hashtags_or_link_in_bio(): void
    {
        $cap = QuickieHandoffLogic::defaultCaption($this->ig());
        $this->assertStringStartsWith("Podcast drops Thursday. Who should be my first guest?", $cap);
        $this->assertStringNotContainsString('#BB28', $cap);
        $this->assertStringNotContainsString('Link in bio', $cap);
        $this->assertStringEndsWith("📸 @janellepierzina on Instagram\nhttps://www.instagram.com/p/AAA111/", $cap);
    }

    public function test_has_credit_requires_the_handle(): void
    {
        $this->assertTrue(QuickieHandoffLogic::hasCredit("Janelle is back!\n\n📸 @janellepierzina on Instagram", $this->ig()));
        $this->assertTrue(QuickieHandoffLogic::hasCredit("photo: @JanellePierzina", $this->ig()), 'case-insensitive');
        $this->assertFalse(QuickieHandoffLogic::hasCredit("Janelle is back!", $this->ig()));
        $this->assertFalse(QuickieHandoffLogic::hasCredit("", $this->ig()));
    }

    public function test_credit_handle_for_non_ig_is_author(): void
    {
        $this->assertSame('u/feedwatcher', QuickieHandoffLogic::creditHandle(['source' => 'reddit', 'author' => 'u/feedwatcher', 'source_key' => 'BigBrother']));
        $this->assertSame('@janellepierzina', QuickieHandoffLogic::creditHandle($this->ig()));
    }
}
