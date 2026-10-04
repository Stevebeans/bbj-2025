<?php
namespace BigBrotherJunkies\Data\Tests\Stories;

use BigBrotherJunkies\Data\Stories\SettingsLogic;
use PHPUnit\Framework\TestCase;

class SettingsLogicTest extends TestCase
{
    public function test_slug_lowercases_and_strips(): void
    {
        $this->assertSame('big-brother', SettingsLogic::slug(' Big Brother '));
        $this->assertSame('survivor', SettingsLogic::slug('Survivor!'));
        $this->assertSame('', SettingsLogic::slug('###'));
    }

    public function test_normalize_shows_applies_defaults_and_clamps(): void
    {
        $out = SettingsLogic::normalizeShows([
            'Big Brother' => ['enabled' => 'yes', 'max_per_day' => 999, 'publish_mode' => 'bogus', 'fb_push' => 'nope'],
            '' => ['enabled' => true],
        ]);
        $this->assertArrayHasKey('big-brother', $out);
        $this->assertArrayNotHasKey('', $out);
        $bb = $out['big-brother'];
        $this->assertTrue($bb['enabled']);
        $this->assertSame('inbox_only', $bb['publish_mode']);
        $this->assertSame(48, $bb['max_per_day'], 'clamped to 48');
        $this->assertSame('confirmed_only', $bb['fb_push']);
        $this->assertFalse($bb['auto_enabled'], 'auto lane defaults OFF');
        $this->assertSame('Big Brother', $bb['label']);
    }

    public function test_normalize_sources_validates_type_trust_and_key(): void
    {
        $out = SettingsLogic::normalizeSources([
            ['show' => 'big-brother', 'type' => 'reddit', 'key' => 'r/BigBrother', 'trust' => 'standard'],
            ['show' => 'big-brother', 'type' => 'instagram', 'key' => '@janellepierzina', 'trust' => 'bogus'],
            ['show' => 'big-brother', 'type' => 'rss', 'key' => 'not a url'],
            ['show' => 'big-brother', 'type' => 'carrier-pigeon', 'key' => 'x'],
            ['show' => '', 'type' => 'rss', 'key' => 'https://example.com/feed'],
        ]);
        $this->assertCount(2, $out);
        $this->assertSame('BigBrother', $out[0]['key'], 'r/ prefix stripped');
        $this->assertSame(12, strlen($out[0]['id']));
        $this->assertSame('janellepierzina', $out[1]['key'], '@ stripped');
        $this->assertSame('standard', $out[1]['trust'], 'bad trust falls back to standard');
        $this->assertTrue($out[1]['enabled']);
    }

    public function test_normalize_sources_keeps_existing_id(): void
    {
        $out = SettingsLogic::normalizeSources([
            ['id' => 'abcdef012345', 'show' => 'survivor', 'type' => 'rss', 'key' => 'https://example.com/feed.xml'],
        ]);
        $this->assertSame('abcdef012345', $out[0]['id']);
    }
}
