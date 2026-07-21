<?php

namespace BigBrotherJunkies\Data\Tests\Social;

use BigBrotherJunkies\Data\Social\PromptContext;
use PHPUnit\Framework\TestCase;

class PromptContextTest extends TestCase
{
    /** Thursday 8:00 PM ET, 60 min (the live eviction slot). */
    private const SLOTS = [['day' => 4, 'time_et' => '20:00', 'duration_min' => 60]];

    private function utc(string $s): \DateTimeImmutable
    {
        return new \DateTimeImmutable($s, new \DateTimeZone('UTC'));
    }

    public function test_inside_et_airing(): void
    {
        // Thu 2026-07-16 8:30 PM EDT = Fri 00:30 UTC
        $this->assertTrue(PromptContext::inCautionWindow(self::SLOTS, $this->utc('2026-07-17 00:30:00')));
    }

    public function test_tail_after_airing_counts(): void
    {
        // 9:20 PM ET: airing ended 9:00, 30-min tail still cautioned
        $this->assertTrue(PromptContext::inCautionWindow(self::SLOTS, $this->utc('2026-07-17 01:20:00')));
    }

    public function test_pt_reair_window_counts(): void
    {
        // PT re-air modeled as ET start +3h: 11:00 PM ET-1:00... 8:30 PM PT = Fri 03:30 UTC
        $this->assertTrue(PromptContext::inCautionWindow(self::SLOTS, $this->utc('2026-07-17 03:30:00')));
    }

    public function test_outside_windows_false(): void
    {
        $this->assertFalse(PromptContext::inCautionWindow(self::SLOTS, $this->utc('2026-07-16 18:00:00'))); // Thu 2 PM ET
        $this->assertFalse(PromptContext::inCautionWindow(self::SLOTS, $this->utc('2026-07-18 00:30:00'))); // Fri 8:30 PM ET
    }

    public function test_malformed_slots_ignored(): void
    {
        $bad = [
            ['day' => 9, 'time_et' => '20:00', 'duration_min' => 60],
            ['day' => 4, 'time_et' => 'garbage', 'duration_min' => 60],
            ['day' => 4, 'time_et' => '20:00', 'duration_min' => 0],
        ];
        $this->assertFalse(PromptContext::inCautionWindow($bad, $this->utc('2026-07-17 00:30:00')));
    }

    public function test_empty_slots_false(): void
    {
        $this->assertFalse(PromptContext::inCautionWindow([], $this->utc('2026-07-17 00:30:00')));
    }

    // --- rosterBlock ---

    public function test_roster_block_lists_roster_glossary_and_rule(): void
    {
        $block = PromptContext::rosterBlock(['Haley', 'Kamu', 'Lala'], ['Thog = Haley']);
        $this->assertStringContainsString("This season's houseguests: Haley, Kamu, Lala.", $block);
        $this->assertStringContainsString('Thog = Haley', $block);
        $this->assertStringContainsString('SAME person', $block);
    }

    public function test_roster_block_glossary_only_when_roster_excluded(): void
    {
        $block = PromptContext::rosterBlock(['Haley'], ['Thog = Haley'], false);
        $this->assertStringNotContainsString("This season's houseguests", $block);
        $this->assertStringContainsString('Thog = Haley', $block);
    }

    public function test_roster_block_empty_when_nothing_to_say(): void
    {
        $this->assertSame('', PromptContext::rosterBlock([], []));
        $this->assertSame('', PromptContext::rosterBlock(['Haley'], [], false));
    }

    public function test_roster_block_drops_malformed_glossary_lines(): void
    {
        $block = PromptContext::rosterBlock([], ['Thog = Haley', 'garbage line', '   ']);
        $this->assertStringContainsString('Thog = Haley', $block);
        $this->assertStringNotContainsString('garbage', $block);
    }

    public function test_roster_block_decodes_entities(): void
    {
        // Stored lines are wp_encode_emoji'd; the block must decode for Claude.
        $block = PromptContext::rosterBlock([], ['Thog&#039;s = Haley']);
        $this->assertStringContainsString("Thog's = Haley", $block);
    }
}
