<?php

namespace BigBrotherJunkies\Data\FanVotes;

/**
 * Pure share/delta/mover math for fan-favorite voting. No DB, unit-tested.
 */
class FanVoteMath
{
    /** @return array<int,array{votes:int,share:float}> */
    public static function shares(array $countsByPlayer, int $totalVoters): array
    {
        $out = [];
        foreach ($countsByPlayer as $pid => $votes) {
            $votes = (int) $votes;
            $out[(int) $pid] = [
                'votes' => $votes,
                'share' => $totalVoters > 0 ? round(($votes / $totalVoters) * 100, 1) : 0.0,
            ];
        }
        return $out;
    }

    /** @return array<int,float> today share minus previous share (missing prev = 0) */
    public static function deltas(array $sharesToday, array $sharesPrev): array
    {
        $out = [];
        foreach ($sharesToday as $pid => $s) {
            $prev = $sharesPrev[$pid]['share'] ?? 0.0;
            $out[$pid] = round($s['share'] - $prev, 1);
        }
        return $out;
    }

    /** @return array<int,array{player_id:int,delta:float}> biggest |delta| first, zeros dropped */
    public static function movers(array $deltas, int $limit = 5): array
    {
        $rows = [];
        foreach ($deltas as $pid => $d) {
            if (abs($d) < 0.05) continue;
            $rows[] = ['player_id' => (int) $pid, 'delta' => (float) $d];
        }
        usort($rows, static function ($a, $b) {
            $cmp = abs($b['delta']) <=> abs($a['delta']);
            return $cmp !== 0 ? $cmp : ($a['player_id'] <=> $b['player_id']);
        });
        return array_slice($rows, 0, $limit);
    }

    /** Rank-slot point values (Steve-approved top-heavy decay). */
    public const SLOT_POINTS = [1 => 15.0, 2 => 10.0, 3 => 7.0, 4 => 5.0, 5 => 3.0];
    public const BASE_SLOT_POINTS = 1.0;
    public const HEART_POINTS = 15.0;

    /** Points a ballot slot is worth. Rank < 1 scores nothing. */
    public static function slotPoints(int $rank): float
    {
        if ($rank < 1) {
            return 0.0;
        }
        return self::SLOT_POINTS[$rank] ?? self::BASE_SLOT_POINTS;
    }

    /**
     * Weighted points per player from ranked ballots + heart bonus votes.
     *
     * @param array<int,int[]> $ballots user_id => player_ids in rank order (rank 1 first)
     * @param array<int,int>   $hearts  user_id => player_id (bonus #1-value vote)
     * @param array<int,float> $weights user_id => multiplier (missing = 1.0)
     * @return array<int,float> player_id => points (1dp)
     */
    public static function tally(array $ballots, array $hearts, array $weights): array
    {
        $points = [];
        foreach ($ballots as $userId => $order) {
            $w = (float) ($weights[$userId] ?? 1.0);
            $rank = 1;
            foreach ($order as $pid) {
                $pid = (int) $pid;
                $points[$pid] = ($points[$pid] ?? 0.0) + self::slotPoints($rank) * $w;
                $rank++;
            }
        }
        foreach ($hearts as $userId => $pid) {
            $pid = (int) $pid;
            $w = (float) ($weights[$userId] ?? 1.0);
            $points[$pid] = ($points[$pid] ?? 0.0) + self::HEART_POINTS * $w;
        }
        foreach ($points as $pid => $p) {
            $points[$pid] = round($p, 1);
        }
        return $points;
    }

    /**
     * Share of total points per player. Output is deltas()-compatible.
     *
     * @return array<int,array{points:float,share:float}>
     */
    public static function pointShares(array $pointsByPlayer, float $totalPoints): array
    {
        $out = [];
        foreach ($pointsByPlayer as $pid => $pts) {
            $pts = (float) $pts;
            $out[(int) $pid] = [
                'points' => round($pts, 1),
                'share'  => $totalPoints > 0 ? round(($pts / $totalPoints) * 100, 1) : 0.0,
            ];
        }
        return $out;
    }
}
