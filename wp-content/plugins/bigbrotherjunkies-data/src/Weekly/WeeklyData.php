<?php

namespace BigBrotherJunkies\Data\Weekly;

class WeeklyData
{
    public static function buildTimelineCells(
        array $weekRows,
        array $compRows,
        ?int $finishPlace,
        int $afpId,
        int $playerId,
        int $maxWeek
    ): array {
        $cells = ['hoh' => [], 'pov' => [], 'nom' => []];

        $compsByWeek = [];
        foreach ($compRows as $cr) {
            $compsByWeek[(int) $cr['week_num']][] = $cr['slug'];
        }

        foreach ($weekRows as $w) {
            $wn = (int) $w['week_num'];
            $slugs = $compsByWeek[$wn] ?? [];
            if (in_array('hoh', $slugs, true)) $cells['hoh'][] = $wn;
            if (in_array('pov', $slugs, true)) $cells['pov'][] = $wn;
            if ((int) ($w['nom'] ?? 0) === 1)  $cells['nom'][] = $wn;
        }

        $finale = null;
        if ($finishPlace === 1) {
            $finale = ['label' => '★ WIN', 'class' => 'winner'];
        } elseif ($finishPlace === 2) {
            $finale = ['label' => 'RU', 'class' => 'runnerup'];
        } elseif ($afpId > 0 && $afpId === $playerId) {
            $finale = ['label' => '★ AFP', 'class' => 'afp'];
        }

        $tracked = $maxWeek > 0;
        $hasActivity = !empty($cells['hoh']) || !empty($cells['pov'])
            || !empty($cells['nom']) || $finale !== null;

        return [
            'tracked'      => $tracked,
            'max_week'     => $maxWeek,
            'cells'        => $cells,
            'finale'       => $finale,
            'has_activity' => $hasActivity,
        ];
    }

    /**
     * Fetch + assemble a player's game-timeline for a season.
     * $playerId and $seasonId are WP post IDs.
     */
    public static function getPlayerTimeline(int $playerId, int $seasonId): array
    {
        global $wpdb;
        $weeks    = $wpdb->prefix . WeeklySchema::TABLE_WEEKS;
        $wplayers = $wpdb->prefix . WeeklySchema::TABLE_WEEKS_PLAYERS;
        $wcomps   = $wpdb->prefix . WeeklySchema::TABLE_WEEK_COMPS;
        $ctypes   = $wpdb->prefix . WeeklySchema::TABLE_COMP_TYPES;
        $pseason  = $wpdb->prefix . 'bbj_v2_player_season';
        $seasons  = $wpdb->prefix . 'bbj_seasons';

        $maxWeek = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(week_num), 0) FROM {$weeks} WHERE season_id = %d",
            $seasonId
        ));

        if ($maxWeek === 0) {
            return self::buildTimelineCells([], [], null, 0, $playerId, 0);
        }

        $weekRows = $wpdb->get_results($wpdb->prepare(
            "SELECT w.week_num, COALESCE(wp.nom, 0) AS nom
               FROM {$weeks} w
               LEFT JOIN {$wplayers} wp ON wp.week_id = w.id AND wp.player_id = %d
              WHERE w.season_id = %d
              ORDER BY w.week_num ASC",
            $playerId, $seasonId
        ), ARRAY_A) ?: [];

        $compRows = $wpdb->get_results($wpdb->prepare(
            "SELECT w.week_num, ct.slug
               FROM {$wcomps} wc
               INNER JOIN {$weeks} w  ON w.id  = wc.week_id
               INNER JOIN {$ctypes} ct ON ct.id = wc.comp_type_id
              WHERE wc.player_id = %d AND w.season_id = %d",
            $playerId, $seasonId
        ), ARRAY_A) ?: [];

        $finishPlace = $wpdb->get_var($wpdb->prepare(
            "SELECT finish_place FROM {$pseason} WHERE bbj_player = %d AND bbj_season = %d",
            $playerId, $seasonId
        ));
        $finishPlace = ($finishPlace === null) ? null : (int) $finishPlace;

        $afpId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT afp FROM {$seasons} WHERE id = %d LIMIT 1",
            $seasonId
        ));

        return self::buildTimelineCells($weekRows, $compRows, $finishPlace, $afpId, $playerId, $maxWeek);
    }
}
