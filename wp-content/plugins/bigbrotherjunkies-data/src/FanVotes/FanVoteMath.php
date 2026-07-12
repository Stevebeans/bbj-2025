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
}
