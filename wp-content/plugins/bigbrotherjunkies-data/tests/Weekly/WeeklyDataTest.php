<?php

namespace BigBrotherJunkies\Data\Tests\Weekly;

use BigBrotherJunkies\Data\Weekly\WeeklyData;
use PHPUnit\Framework\TestCase;

class WeeklyDataTest extends TestCase
{
    public function test_build_timeline_cells_maps_comps_noms_and_finale(): void
    {
        $weekRows = [
            ['week_num' => 1, 'nom' => 0],
            ['week_num' => 2, 'nom' => 1],
            ['week_num' => 3, 'nom' => 0],
        ];
        $compRows = [
            ['week_num' => 1, 'slug' => 'hoh'],
            ['week_num' => 3, 'slug' => 'pov'],
        ];

        $result = WeeklyData::buildTimelineCells($weekRows, $compRows, 1, 0, 999, 3);

        $this->assertSame([1], $result['cells']['hoh']);
        $this->assertSame([3], $result['cells']['pov']);
        $this->assertSame([2], $result['cells']['nom']);
        $this->assertSame(3, $result['max_week']);
        $this->assertTrue($result['tracked']);
        $this->assertTrue($result['has_activity']);
        $this->assertSame(['label' => '★ WIN', 'class' => 'winner'], $result['finale']);
    }

    public function test_runner_up_and_afp_finale(): void
    {
        $ru = WeeklyData::buildTimelineCells([], [], 2, 0, 5, 0);
        $this->assertSame(['label' => 'RU', 'class' => 'runnerup'], $ru['finale']);

        $afp = WeeklyData::buildTimelineCells([], [], 0, 777, 777, 0);
        $this->assertSame(['label' => '★ AFP', 'class' => 'afp'], $afp['finale']);
    }

    public function test_untracked_season_has_no_activity(): void
    {
        $result = WeeklyData::buildTimelineCells([], [], null, 0, 999, 0);
        $this->assertFalse($result['tracked']);
        $this->assertFalse($result['has_activity']);
        $this->assertNull($result['finale']);
    }
}
