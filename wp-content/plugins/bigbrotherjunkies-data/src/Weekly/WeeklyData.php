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
        int $maxWeek,
        array $voteRows = []
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

        // Eviction votes received per week (omit weeks with 0 votes against this player).
        $votes = [];
        foreach ($voteRows as $vr) {
            $count = (int) ($vr['votes'] ?? 0);
            if ($count > 0) {
                $votes[(int) $vr['week_num']] = $count;
            }
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
            'votes'        => $votes,
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

        // Eviction votes received per week: count of houseguests who voted to evict this player.
        $voteRows = $wpdb->get_results($wpdb->prepare(
            "SELECT w.week_num, COUNT(*) AS votes
               FROM {$wplayers} vp
               INNER JOIN {$weeks} w ON w.id = vp.week_id
              WHERE w.season_id = %d AND vp.voted_for = %d
              GROUP BY w.week_num",
            $seasonId, $playerId
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

        return self::buildTimelineCells($weekRows, $compRows, $finishPlace, $afpId, $playerId, $maxWeek, $voteRows);
    }

    /**
     * Pure: tally per-player totals from comp + nom rows.
     * @return array<int,array{hoh:int,pov:int,misc:int,nom:int}>
     */
    public static function countTotalsFromRows(array $compRows, array $nomRows): array
    {
        $totals = [];
        $ensure = function (&$totals, $pid) {
            if (!isset($totals[$pid])) {
                $totals[$pid] = ['hoh' => 0, 'pov' => 0, 'misc' => 0, 'nom' => 0];
            }
        };
        foreach ($compRows as $c) {
            $pid = (int) $c['player_id'];
            $ensure($totals, $pid);
            $slug = $c['slug'];
            if (isset($totals[$pid][$slug])) {
                $totals[$pid][$slug]++;
            } else {
                $totals[$pid]['misc']++; // any non-hoh/pov comp counts as misc
            }
        }
        foreach ($nomRows as $n) {
            $pid = (int) $n['player_id'];
            $ensure($totals, $pid);
            $totals[$pid]['nom'] = (int) $n['noms'];
        }
        return $totals;
    }

    /**
     * Recompute and write bbj_total_hoh/pov/misc/nom for every player in a season,
     * from the weekly tables. RUN MANUALLY only for fully-tracked seasons.
     * @return int number of player rows updated
     */
    public static function recomputeSeasonTotals(int $seasonId): int
    {
        global $wpdb;
        $weeks    = $wpdb->prefix . WeeklySchema::TABLE_WEEKS;
        $wplayers = $wpdb->prefix . WeeklySchema::TABLE_WEEKS_PLAYERS;
        $wcomps   = $wpdb->prefix . WeeklySchema::TABLE_WEEK_COMPS;
        $ctypes   = $wpdb->prefix . WeeklySchema::TABLE_COMP_TYPES;
        $pseason  = $wpdb->prefix . 'bbj_v2_player_season';

        $compRows = $wpdb->get_results($wpdb->prepare(
            "SELECT wc.player_id, ct.slug
               FROM {$wcomps} wc
               INNER JOIN {$weeks} w   ON w.id  = wc.week_id
               INNER JOIN {$ctypes} ct ON ct.id = wc.comp_type_id
              WHERE w.season_id = %d",
            $seasonId
        ), ARRAY_A) ?: [];

        $nomRows = $wpdb->get_results($wpdb->prepare(
            "SELECT wp.player_id,
                    SUM(wp.nom) AS noms,
                    SUM(wp.evicted) AS evicted
               FROM {$wplayers} wp
              WHERE wp.season_id = %d
              GROUP BY wp.player_id",
            $seasonId
        ), ARRAY_A) ?: [];

        $totals = self::countTotalsFromRows($compRows, $nomRows);

        $updated = 0;
        foreach ($totals as $playerId => $t) {
            $updated += (int) $wpdb->update(
                $pseason,
                [
                    'bbj_total_hoh'  => $t['hoh'],
                    'bbj_total_pov'  => $t['pov'],
                    'bbj_total_misc' => $t['misc'],
                    'bbj_total_nom'  => $t['nom'],
                ],
                ['bbj_player' => $playerId, 'bbj_season' => $seasonId]
            );
        }
        return $updated;
    }

    /**
     * Per-season list of weeks for the season-page breakdown.
     * Returns: [{ week_num, start_date, end_date, summary,
     *            hoh:[names], pov:[names], noms:[names], evicted:[names],
     *            faces:{ hoh:[face], pov:[face], noms:[face], evicted:[face] } }]
     * where face = { id, name, slug, photo }. (ordered by week_num)
     *
     * `faces` is sourced from the SAME junction rows as getPlayerTimeline()
     * (bbj_week_comps for hoh/pov, bbj_weeks_players for nom/evicted), so the
     * season power-map and player timelines can never disagree.
     */
    public static function getSeasonWeeks(int $seasonId): array
    {
        global $wpdb;
        $weeks    = $wpdb->prefix . WeeklySchema::TABLE_WEEKS;
        $wplayers = $wpdb->prefix . WeeklySchema::TABLE_WEEKS_PLAYERS;
        $wcomps   = $wpdb->prefix . WeeklySchema::TABLE_WEEK_COMPS;
        $ctypes   = $wpdb->prefix . WeeklySchema::TABLE_COMP_TYPES;
        $players  = $wpdb->prefix . 'bbj_players';
        $posts    = $wpdb->posts;

        $weekRows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, week_num, start_date, end_date, summary
               FROM {$weeks} WHERE season_id = %d ORDER BY week_num ASC",
            $seasonId
        ), ARRAY_A) ?: [];

        if (empty($weekRows)) {
            return [];
        }

        // Build a player "face" {id,name,slug,photo} from a query row.
        $toFace = static function (array $r): array {
            $pic   = (int) ($r['profile_picture'] ?? 0);
            $photo = $pic ? (wp_get_attachment_image_url($pic, 'thumbnail') ?: '') : '';
            return [
                'id'    => (int) $r['player_id'],
                'name'  => (string) $r['name'],
                'slug'  => (string) ($r['player_slug'] ?? ''),
                'photo' => $photo,
            ];
        };

        $out = [];
        foreach ($weekRows as $w) {
            $weekId = (int) $w['id'];

            // HoH / Veto winners — comp junction (same source as player timelines).
            $comps = $wpdb->get_results($wpdb->prepare(
                "SELECT ct.slug AS comp_slug, p.post_title AS name,
                        wc.player_id, p.post_name AS player_slug, bp.profile_picture
                   FROM {$wcomps} wc
                   INNER JOIN {$ctypes} ct ON ct.id = wc.comp_type_id
                   INNER JOIN {$posts}  p  ON p.ID  = wc.player_id
                   LEFT JOIN  {$players} bp ON (bp.post_id = wc.player_id
                                                OR (bp.id = wc.player_id AND bp.post_id = 0))
                  WHERE wc.week_id = %d",
                $weekId
            ), ARRAY_A) ?: [];

            $hoh = $pov = [];
            $hohFaces = $povFaces = [];
            foreach ($comps as $c) {
                if ($c['comp_slug'] === 'hoh') {
                    $hoh[]      = $c['name'];
                    $hohFaces[] = $toFace($c);
                } elseif ($c['comp_slug'] === 'pov') {
                    $pov[]      = $c['name'];
                    $povFaces[] = $toFace($c);
                }
                // other slugs (e.g. 'blockbuster', 'misc') intentionally ignored — Phase 1
            }

            // Noms / Evicted — per-player status rows.
            $statusRows = $wpdb->get_results($wpdb->prepare(
                "SELECT p.post_title AS name, wp.nom, wp.evicted,
                        wp.player_id, p.post_name AS player_slug, bp.profile_picture
                   FROM {$wplayers} wp
                   INNER JOIN {$posts}  p  ON p.ID = wp.player_id
                   LEFT JOIN  {$players} bp ON (bp.post_id = wp.player_id
                                                OR (bp.id = wp.player_id AND bp.post_id = 0))
                  WHERE wp.week_id = %d",
                $weekId
            ), ARRAY_A) ?: [];

            $noms = $evicted = [];
            $nomFaces = $evictedFaces = [];
            foreach ($statusRows as $s) {
                if ((int) $s['nom'] === 1) {
                    $noms[]     = $s['name'];
                    $nomFaces[] = $toFace($s);
                }
                if ((int) $s['evicted'] === 1) {
                    $evicted[]      = $s['name'];
                    $evictedFaces[] = $toFace($s);
                }
            }

            $out[] = [
                'week_num'   => (int) $w['week_num'],
                'start_date' => $w['start_date'],
                'end_date'   => $w['end_date'],
                'summary'    => $w['summary'],
                'hoh'        => $hoh,
                'pov'        => $pov,
                'noms'       => $noms,
                'evicted'    => $evicted,
                'faces'      => [
                    'hoh'     => $hohFaces,
                    'pov'     => $povFaces,
                    'noms'    => $nomFaces,
                    'evicted' => $evictedFaces,
                ],
            ];
        }

        return $out;
    }
}
