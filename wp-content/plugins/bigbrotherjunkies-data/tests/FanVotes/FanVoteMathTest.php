<?php

namespace BigBrotherJunkies\Data\Tests\FanVotes;

use BigBrotherJunkies\Data\FanVotes\FanVoteMath;
use PHPUnit\Framework\TestCase;

class FanVoteMathTest extends TestCase
{
    public function test_shares_computes_percentages(): void
    {
        $shares = FanVoteMath::shares([101 => 6, 102 => 3, 103 => 1], 10);
        $this->assertSame(['votes' => 6, 'share' => 60.0], $shares[101]);
        $this->assertSame(['votes' => 1, 'share' => 10.0], $shares[103]);
    }

    public function test_shares_zero_voters_all_zero(): void
    {
        $shares = FanVoteMath::shares([101 => 0], 0);
        $this->assertSame(['votes' => 0, 'share' => 0.0], $shares[101]);
    }

    public function test_deltas_vs_previous_day(): void
    {
        $today = [101 => ['votes' => 6, 'share' => 60.0], 102 => ['votes' => 4, 'share' => 40.0]];
        $prev  = [101 => ['votes' => 5, 'share' => 50.0]]; // 102 new since yesterday
        $d = FanVoteMath::deltas($today, $prev);
        $this->assertSame(10.0, $d[101]);
        $this->assertSame(40.0, $d[102]);
    }

    public function test_movers_sorted_by_magnitude_excluding_zero(): void
    {
        $m = FanVoteMath::movers([101 => 10.0, 102 => -25.0, 103 => 0.0, 104 => 5.0], 2);
        $this->assertSame([102, 101], array_column($m, 'player_id'));
        $this->assertSame(-25.0, $m[0]['delta']);
    }
}
