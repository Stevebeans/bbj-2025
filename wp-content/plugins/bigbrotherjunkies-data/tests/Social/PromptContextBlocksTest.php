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

    private function week(int $num, ?string $start, ?string $end, array $over = []): array
    {
        return array_merge([
            'week_num'   => $num,
            'start_date' => $start,
            'end_date'   => $end,
            'summary'    => null,
            'hoh'        => [],
            'pov'        => [],
            'noms'       => [],
            'evicted'    => [],
            'faces'      => ['hoh' => [], 'pov' => [], 'noms' => [], 'evicted' => []],
        ], $over);
    }

    public function test_game_state_uses_the_week_that_brackets_today(): void
    {
        $weeks = [
            $this->week(8, '2026-08-28', '2026-09-03', ['hoh' => ['Yash'], 'noms' => ['Chuk', 'Lyric'], 'evicted' => ['Chuk']]),
            $this->week(9, '2026-09-04', '2026-09-10', ['hoh' => ['Barrett'], 'pov' => ['Yash'], 'noms' => ['Dee', 'Devens']]),
        ];
        $this->assertSame(
            'Game state: Week 9 (Sep 4 to Sep 10). HOH: Barrett. Veto holder: Yash. Nominees: Dee, Devens. Evicted this week: none yet.',
            PromptContextBlocks::gameStateLineFromWeeks($weeks, '2026-09-06')
        );
    }

    public function test_game_state_omits_blank_fields_instead_of_guessing(): void
    {
        $weeks = [$this->week(9, '2026-09-04', '2026-09-10', ['hoh' => ['Barrett']])];
        $this->assertSame(
            'Game state: Week 9 (Sep 4 to Sep 10). HOH: Barrett. Evicted this week: none yet.',
            PromptContextBlocks::gameStateLineFromWeeks($weeks, '2026-09-06')
        );
    }

    public function test_game_state_falls_back_to_newest_week_and_says_it_is_stale(): void
    {
        $weeks = [
            $this->week(8, '2026-08-28', '2026-09-03', ['hoh' => ['Yash'], 'evicted' => ['Chuk']]),
        ];
        $this->assertSame(
            'Game state: latest recorded week is Week 8 (Aug 28 to Sep 3); this week has not been entered yet. HOH: Yash. Evicted: Chuk.',
            PromptContextBlocks::gameStateLineFromWeeks($weeks, '2026-09-06')
        );
    }

    public function test_game_state_handles_weeks_without_dates(): void
    {
        $weeks = [$this->week(2, null, null, ['hoh' => ['Melody'], 'noms' => ['Taylor', 'Rome']])];
        $this->assertSame(
            'Game state: latest recorded week is Week 2; this week has not been entered yet. HOH: Melody. Nominees: Taylor, Rome.',
            PromptContextBlocks::gameStateLineFromWeeks($weeks, '2026-09-06')
        );
    }

    public function test_game_state_is_empty_when_there_are_no_weeks(): void
    {
        $this->assertSame('', PromptContextBlocks::gameStateLineFromWeeks([], '2026-09-06'));
    }

    public function test_house_updates_are_chronological_with_weekday_stamps(): void
    {
        $rows = [
            ['posted_at' => '2026-09-06 21:14:00', 'author' => 'LynnD', 'text' => 'Dee crying to Angela in the bathroom, worried she is the one going home.'],
            ['posted_at' => '2026-09-06 19:02:00', 'author' => 'Steve Beans', 'text' => 'Barrett wins HOH.'],
        ];
        $expected = implode("\n", [
            '=== HOUSE UPDATES (bigbrotherjunkies.com feed watchers; primary account of the day) ===',
            '[Sun Sep 6 12:02 PM] Steve Beans: Barrett wins HOH.',
            '[Sun Sep 6 2:14 PM] LynnD: Dee crying to Angela in the bathroom, worried she is the one going home.',
        ]);
        $this->assertSame($expected, PromptContextBlocks::formatHouseUpdates($rows));
    }

    public function test_house_updates_keep_the_newest_when_over_cap(): void
    {
        $rows = [];
        for ($i = 0; $i < 5; $i++) {
            $rows[] = ['posted_at' => sprintf('2026-09-06 %02d:00:00', 15 + $i), 'author' => 'Steve Beans', 'text' => "update {$i}"];
        }
        $out = PromptContextBlocks::formatHouseUpdates($rows, 3);
        $lines = explode("\n", $out);
        $this->assertSame('(2 earlier updates omitted)', $lines[1]);
        $this->assertStringEndsWith('update 2', $lines[2]);
        $this->assertStringEndsWith('update 4', $lines[4]);
        $this->assertCount(5, $lines); // header + omitted line + 3 rows
    }

    public function test_house_updates_collapse_whitespace_and_trim_long_text(): void
    {
        $long = str_repeat('word ', 200); // 1000 chars
        $rows = [['posted_at' => '2026-09-06 19:02:00', 'author' => 'LynnD', 'text' => "line one\n\n   line   two " . $long]];
        $out  = PromptContextBlocks::formatHouseUpdates($rows);
        $line = explode("\n", $out)[1];
        $this->assertStringContainsString('line one line two word', $line);
        // 600 chars of text plus the 3-byte UTF-8 ellipsis.
        $this->assertLessThanOrEqual(strlen('[Sun Sep 6 12:02 PM] LynnD: ') + 600 + 3, strlen($line));
        $this->assertStringEndsWith('…', $line);
    }

    public function test_house_updates_block_is_empty_with_no_rows(): void
    {
        $this->assertSame('', PromptContextBlocks::formatHouseUpdates([]));
    }

    public function test_community_block_is_reserved_and_empty_in_v1(): void
    {
        $this->assertSame('', PromptContextBlocks::communityBlock());
    }
}
