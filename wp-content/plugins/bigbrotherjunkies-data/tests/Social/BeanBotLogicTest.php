<?php

namespace BigBrotherJunkies\Data\Tests\Social;

use BigBrotherJunkies\Data\Social\BeanBotLogic;
use PHPUnit\Framework\TestCase;

class BeanBotLogicTest extends TestCase
{
    public function test_parse_valid_post_json(): void
    {
        $out = BeanBotLogic::parseModelJson('{"mode":"post","text":"Dee camtalking about Haley again","bluesky":true}');
        $this->assertSame('post', $out['mode']);
        $this->assertSame('Dee camtalking about Haley again', $out['text']);
        $this->assertTrue($out['bluesky']);
        // Strict boolean: the string "false" must not coerce truthy into true.
        $this->assertFalse(BeanBotLogic::parseModelJson('{"mode":"post","text":"x","bluesky":"false"}')['bluesky']);
    }

    public function test_parse_strips_markdown_fences_and_defaults_bluesky(): void
    {
        $raw = "```json\n{\"mode\":\"post\",\"text\":\"Barrett with new hair\"}\n```";
        $out = BeanBotLogic::parseModelJson($raw);
        $this->assertSame('post', $out['mode']);
        $this->assertFalse($out['bluesky']);
    }

    public function test_parse_skip_forces_bluesky_false_and_allows_empty_text(): void
    {
        $out = BeanBotLogic::parseModelJson('{"mode":"skip","text":"","bluesky":true}');
        $this->assertSame('skip', $out['mode']);
        $this->assertFalse($out['bluesky']);
    }

    public function test_parse_rejects_garbage_bad_mode_and_empty_post_text(): void
    {
        $this->assertArrayHasKey('error', BeanBotLogic::parseModelJson('not json'));
        $this->assertArrayHasKey('error', BeanBotLogic::parseModelJson('{"mode":"shout","text":"x"}'));
        $this->assertArrayHasKey('error', BeanBotLogic::parseModelJson('{"mode":"post","text":"  "}'));
    }

    public function test_parse_clamps_text_to_950_chars(): void
    {
        $long = str_repeat('a', 1200);
        $out = BeanBotLogic::parseModelJson('{"mode":"post","text":"' . $long . '"}');
        $this->assertSame(950, mb_strlen($out['text']));
    }

    public function test_window_bounds_today_uses_site_midnight(): void
    {
        $now = new \DateTimeImmutable('2026-07-13 20:30:00', new \DateTimeZone('UTC'));
        $b = BeanBotLogic::windowBounds('today', $now, new \DateTimeZone('America/Los_Angeles'));
        // Site-local (PDT, UTC-7) midnight Jul 13 = 07:00 UTC
        $this->assertSame('2026-07-13 07:00:00', $b['from']);
        $this->assertSame('2026-07-13 20:30:00', $b['to']);
    }

    public function test_window_bounds_12h_24h_and_unknown_fallback(): void
    {
        $now = new \DateTimeImmutable('2026-07-13 20:00:00', new \DateTimeZone('UTC'));
        $tz = new \DateTimeZone('America/Los_Angeles');
        $this->assertSame('2026-07-13 08:00:00', BeanBotLogic::windowBounds('12h', $now, $tz)['from']);
        $this->assertSame('2026-07-12 20:00:00', BeanBotLogic::windowBounds('24h', $now, $tz)['from']);
        $this->assertSame(
            BeanBotLogic::windowBounds('today', $now, $tz)['from'],
            BeanBotLogic::windowBounds('bogus', $now, $tz)['from']
        );
    }

    public function test_freshness_cutoff_is_one_hour_back_in_utc(): void
    {
        $now = new \DateTimeImmutable('2026-07-14 20:00:00', new \DateTimeZone('UTC'));
        $this->assertSame('2026-07-14 19:00:00', BeanBotLogic::freshnessCutoff($now));
        // Non-UTC input converts: 1pm PDT = 20:00 UTC → cutoff 19:00 UTC
        $pt = new \DateTimeImmutable('2026-07-14 13:00:00', new \DateTimeZone('America/Los_Angeles'));
        $this->assertSame('2026-07-14 19:00:00', BeanBotLogic::freshnessCutoff($pt));
    }

    public function test_quiet_suppression_only_when_both_brief(): void
    {
        $this->assertTrue(BeanBotLogic::shouldSuppressQuiet(true, true));
        $this->assertFalse(BeanBotLogic::shouldSuppressQuiet(true, false));
        $this->assertFalse(BeanBotLogic::shouldSuppressQuiet(false, true));
    }

    public function test_to_house_time_converts_utc_to_pacific_across_midnight(): void
    {
        // 02:23 UTC on Jul 14 is 19:23 PDT (UTC-7) on Jul 13 the day before:
        // proves both the timezone shift and the date rollover across midnight.
        $this->assertSame('Mon Jul 13 7:23 PM', BeanBotLogic::toHouseTime('2026-07-14 02:23:00'));
    }

    public function test_to_house_time_weekday_follows_pacific_not_utc(): void
    {
        // 06:59 UTC on Sep 7 is still 11:59 PM Sunday in Los Angeles (PDT).
        $this->assertSame('Sun Sep 6 11:59 PM', BeanBotLogic::toHouseTime('2026-09-07 06:59:00'));
        // One minute later the house has rolled to Monday.
        $this->assertSame('Mon Sep 7 12:00 AM', BeanBotLogic::toHouseTime('2026-09-07 07:00:00'));
    }

    public function test_to_house_time_passes_garbage_through_unchanged(): void
    {
        $this->assertSame('not a date', BeanBotLogic::toHouseTime('not a date'));
        $this->assertSame('', BeanBotLogic::toHouseTime(''));
    }
}
