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

    public function test_slot_points_decay(): void
    {
        $this->assertSame(15.0, FanVoteMath::slotPoints(1));
        $this->assertSame(10.0, FanVoteMath::slotPoints(2));
        $this->assertSame(7.0, FanVoteMath::slotPoints(3));
        $this->assertSame(5.0, FanVoteMath::slotPoints(4));
        $this->assertSame(3.0, FanVoteMath::slotPoints(5));
        $this->assertSame(1.0, FanVoteMath::slotPoints(6));
        $this->assertSame(1.0, FanVoteMath::slotPoints(14));
        $this->assertSame(0.0, FanVoteMath::slotPoints(0));
    }

    public function test_tally_ballots_hearts_and_weights(): void
    {
        // user 1 (weight 1): ballot [101,102,103], heart 101
        // user 2 (weight 3): ballot [102,101], no heart
        $points = FanVoteMath::tally(
            [1 => [101, 102, 103], 2 => [102, 101]],
            [1 => 101],
            [2 => 3.0]
        );
        // 101: 15*1 (u1 #1) + 15*1 (u1 heart) + 10*3 (u2 #2) = 60.0
        $this->assertSame(60.0, $points[101]);
        // 102: 10*1 (u1 #2) + 15*3 (u2 #1) = 55.0
        $this->assertSame(55.0, $points[102]);
        // 103: 7*1 = 7.0
        $this->assertSame(7.0, $points[103]);
    }

    public function test_tally_rank_six_plus_scores_base_point(): void
    {
        $points = FanVoteMath::tally([1 => [1, 2, 3, 4, 5, 6, 7]], [], []);
        $this->assertSame(1.0, $points[6]);
        $this->assertSame(1.0, $points[7]);
    }

    public function test_tally_heart_only_user_counts(): void
    {
        $points = FanVoteMath::tally([], [9 => 101], [9 => 2.0]);
        $this->assertSame(30.0, $points[101]);
    }

    public function test_point_shares_and_zero_total(): void
    {
        $shares = FanVoteMath::pointShares([101 => 60.0, 102 => 40.0], 100.0);
        $this->assertSame(['points' => 60.0, 'share' => 60.0], $shares[101]);
        $zero = FanVoteMath::pointShares([101 => 0.0], 0.0);
        $this->assertSame(['points' => 0.0, 'share' => 0.0], $zero[101]);
    }

    public function test_deltas_accepts_point_shares(): void
    {
        $today = FanVoteMath::pointShares([101 => 60.0], 100.0);
        $prev  = FanVoteMath::pointShares([101 => 50.0], 100.0);
        $this->assertSame(10.0, FanVoteMath::deltas($today, $prev)[101]);
    }
}
