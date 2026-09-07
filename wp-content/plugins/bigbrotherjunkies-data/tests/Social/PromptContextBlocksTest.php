<?php

namespace BigBrotherJunkies\Data\Tests\Social;

use BigBrotherJunkies\Data\Social\PromptContextBlocks;
use PHPUnit\Framework\TestCase;

class PromptContextBlocksTest extends TestCase
{
    private function utc(string $ymdHis): \DateTimeImmutable
    {
        return new \DateTimeImmutable($ymdHis, new \DateTimeZone('UTC'));
    }

    public function test_date_header_names_the_pacific_weekday_and_year(): void
    {
        // 3 AM UTC Monday Sep 7 is still 8 PM Sunday Sep 6 in Los Angeles.
        $this->assertSame(
            'Today is Sunday, September 6, 2026 (Pacific).',
            PromptContextBlocks::dateHeader($this->utc('2026-09-07 03:00:00'))
        );
    }

    public function test_date_header_rolls_at_pacific_midnight(): void
    {
        $this->assertSame(
            'Today is Sunday, September 6, 2026 (Pacific).',
            PromptContextBlocks::dateHeader($this->utc('2026-09-07 06:59:59'))
        );
        $this->assertSame(
            'Today is Monday, September 7, 2026 (Pacific).',
            PromptContextBlocks::dateHeader($this->utc('2026-09-07 07:00:00'))
        );
    }

    public function test_date_header_across_the_november_dst_fallback(): void
    {
        // DST ends 2026-11-01 02:00 PDT. 08:30 UTC = 01:30 PDT, 09:30 UTC = 01:30 PST; both Sunday Nov 1.
        $this->assertSame(
            'Today is Sunday, November 1, 2026 (Pacific).',
            PromptContextBlocks::dateHeader($this->utc('2026-11-01 08:30:00'))
        );
        $this->assertSame(
            'Today is Sunday, November 1, 2026 (Pacific).',
            PromptContextBlocks::dateHeader($this->utc('2026-11-01 09:30:00'))
        );
    }
}
