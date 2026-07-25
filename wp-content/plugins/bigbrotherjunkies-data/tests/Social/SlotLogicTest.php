<?php

namespace BigBrotherJunkies\Tests\Social;

use BigBrotherJunkies\Data\Social\SlotLogic;
use PHPUnit\Framework\TestCase;

class SlotLogicTest extends TestCase
{
    private function utc(string $s): \DateTimeImmutable
    {
        return new \DateTimeImmutable($s, new \DateTimeZone('UTC'));
    }

    public function test_first_slot_is_next_half_hour_after_5min_buffer(): void
    {
        $slots = SlotLogic::grid($this->utc('2026-07-25 18:04:00'), []);
        $this->assertSame('2026-07-25 18:30:00', $slots[0]['at']);
        // 18:26 + 5min buffer passes 18:30 -> first slot 19:00
        $slots = SlotLogic::grid($this->utc('2026-07-25 18:26:00'), []);
        $this->assertSame('2026-07-25 19:00:00', $slots[0]['at']);
    }

    public function test_grid_length_and_spacing(): void
    {
        $slots = SlotLogic::grid($this->utc('2026-07-25 18:00:00'), []);
        $this->assertCount(48, $slots);
        $this->assertSame('2026-07-25 18:30:00', $slots[0]['at']);
        $this->assertSame('2026-07-25 19:00:00', $slots[1]['at']);
    }

    public function test_taken_within_radius_with_label(): void
    {
        $slots = SlotLogic::grid($this->utc('2026-07-25 18:00:00'), [
            ['at' => '2026-07-25 19:10:00', 'source' => 'social_quickie'],
        ]);
        // 19:00 slot: 19:10 is within 15min -> taken
        $this->assertTrue($slots[1]['taken']);
        $this->assertSame('quickie card', $slots[1]['label']);
        // 19:30 slot: 19:10 is 20min away -> free
        $this->assertFalse($slots[2]['taken']);
        $this->assertNull($slots[2]['label']);
    }

    public function test_exact_slot_hit_is_taken(): void
    {
        $slots = SlotLogic::grid($this->utc('2026-07-25 18:00:00'), [
            ['at' => '2026-07-25 20:00:00', 'source' => 'feed_update_share'],
        ]);
        $this->assertTrue($slots[3]['taken']);
        $this->assertSame('feed share', $slots[3]['label']);
    }

    public function test_label_mapping(): void
    {
        $this->assertSame('quickie card', SlotLogic::labelFor('social_quickie'));
        $this->assertSame('feed share', SlotLogic::labelFor('feed_update_share'));
        $this->assertSame('batch post', SlotLogic::labelFor('batch'));
        $this->assertSame('manual post', SlotLogic::labelFor('manual'));
        $this->assertSame('manual post', SlotLogic::labelFor('image_paste'));
    }

    public function test_malformed_scheduled_rows_ignored(): void
    {
        $slots = SlotLogic::grid($this->utc('2026-07-25 18:00:00'), [
            ['at' => 'garbage', 'source' => 'manual'],
            ['source' => 'manual'],
        ]);
        $this->assertFalse($slots[0]['taken']);
    }
}
