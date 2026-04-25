<?php
/**
 * Archive data helpers — players + seasons directories.
 *
 * Returns one row per player or season for the archive templates.
 * Skeleton-tier: single SQL pass, no caching, no pagination.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * All published players with career-aggregated stats and season participation.
 *
 * Mixed FK quirk handled: wp_bbj_players matches on either post_id OR (id AND post_id=0).
 * Junction stats are SUM-aggregated; the comma-joined season_ids feed the season filter.
 */
function bbj_v2_archive_all_players(): array
{
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT
            p.ID                                              AS post_id,
            p.post_title,
            p.post_name                                       AS slug,
            bp.first_name,
            bp.last_name,
            bp.profile_picture,
            bp.occupation,
            bp.date_of_birth,
            bp.hometown_city,
            bp.hometown_state,
            COUNT(DISTINCT j.bbj_season)                      AS season_count,
            MIN(NULLIF(j.finish_place, 0))                    AS best_finish,
            GROUP_CONCAT(DISTINCT j.bbj_season ORDER BY j.bbj_season) AS season_ids
         FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->prefix}bbj_players bp
                ON (bp.post_id = p.ID OR (bp.id = p.ID AND bp.post_id = 0))
         LEFT JOIN {$wpdb->prefix}bbj_v2_player_season j
                ON j.bbj_player = p.ID
         WHERE p.post_type   = 'bigbrother-players'
           AND p.post_status = 'publish'
         GROUP BY p.ID
         ORDER BY p.post_title ASC",
        ARRAY_A
    );

    foreach ($rows as &$row) {
        $first = $row['first_name'] ?? '';
        $last  = $row['last_name']  ?? '';
        $full  = trim($first . ' ' . $last);
        if ($full === '') {
            $parts = array_values(array_filter(explode(' ', trim((string) $row['post_title']), 2)));
            $first = $parts[0] ?? '';
            $last  = $parts[1] ?? '';
            $full  = $row['post_title'];
        }
        $row['first_name'] = $first;
        $row['last_name']  = $last;
        $row['full_name']  = $full;

        $age = null;
        if (!empty($row['date_of_birth']) && $row['date_of_birth'] !== '0000-00-00') {
            try {
                $dob = new DateTime($row['date_of_birth']);
                $age = (int) $dob->diff(new DateTime('now'))->y;
            } catch (Exception $e) {
                $age = null;
            }
        }
        $row['age'] = $age;

        $location_parts = array_filter([$row['hometown_city'] ?? '', $row['hometown_state'] ?? '']);
        $row['location'] = $location_parts ? implode(', ', $location_parts) : '';

        $best = (int) ($row['best_finish'] ?? 0);
        if ($best === 1)        $row['status'] = 'winner';
        elseif ($best === 2)    $row['status'] = 'runner-up';
        else                    $row['status'] = 'played';

        $row['season_ids'] = $row['season_ids']
            ? array_map('intval', explode(',', $row['season_ids']))
            : [];

        $totals_for_card = bbj_v2_player_career_totals((int) $row['post_id']);
        $row['total_hoh']   = $totals_for_card['hoh'];
        $row['total_pov']   = $totals_for_card['pov'];
        $row['total_nom']   = $totals_for_card['noms'];
        $row['total_votes'] = $totals_for_card['votes_received'];
    }
    unset($row);

    return $rows;
}

/**
 * All published seasons with winner / runner-up / AFP names.
 *
 * Data sourcing:
 * - Dates / abbreviation / picture: wp_bbj_seasons (id-vs-post_id bridge for BB22+)
 * - Winner / runner-up: derived from wp_bbj_v2_player_season.finish_place = 1/2.
 *   The wp_bbj_seasons.season_winner / runner_up columns are unreliable —
 *   not maintained for most modern seasons and pointed at post-revision IDs
 *   on at least BB26. The junction table is the source of truth; the spoiler
 *   bar editor maintains it.
 * - AFP: still wp_bbj_seasons.afp, validated by joining publish-status posts.
 */
function bbj_v2_archive_all_seasons(): array
{
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT
            p.ID                          AS post_id,
            p.post_title,
            p.post_name                   AS slug,
            p.post_date,
            s.season_number,
            s.full_name                   AS season_full_name,
            s.abbreviation                AS season_abbr,
            s.start_date,
            s.end_date,
            s.season_picture,
            wj.bbj_player                 AS winner_id,
            wp.post_title                 AS winner_name,
            wp.post_name                  AS winner_slug,
            rj.bbj_player                 AS runner_up_id,
            rp.post_title                 AS runner_up_name,
            rp.post_name                  AS runner_up_slug,
            s.afp                         AS afp_id,
            ap.post_title                 AS afp_name,
            ap.post_name                  AS afp_slug
         FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->prefix}bbj_seasons s
                ON (s.post_id = p.ID OR (s.id = p.ID AND s.post_id = 0))
         LEFT JOIN {$wpdb->prefix}bbj_v2_player_season wj
                ON wj.bbj_season = p.ID AND wj.finish_place = 1
         LEFT JOIN {$wpdb->prefix}bbj_v2_player_season rj
                ON rj.bbj_season = p.ID AND rj.finish_place = 2
         LEFT JOIN {$wpdb->posts} wp ON wp.ID = wj.bbj_player AND wp.post_status = 'publish'
         LEFT JOIN {$wpdb->posts} rp ON rp.ID = rj.bbj_player AND rp.post_status = 'publish'
         LEFT JOIN {$wpdb->posts} ap ON ap.ID = s.afp         AND ap.post_status = 'publish'
         WHERE p.post_type   = 'bigbrother-seasons'
           AND p.post_status = 'publish'
         ORDER BY COALESCE(s.start_date, p.post_date) DESC",
        ARRAY_A
    );

    foreach ($rows as &$row) {
        if (empty($row['season_abbr']) && !empty($row['season_full_name'])) {
            if (preg_match('/Big Brother\s+(\d+)/i', $row['season_full_name'], $m)) {
                $row['season_abbr'] = 'BB' . $m[1];
            }
        }
        $row['display_name'] = $row['season_full_name'] ?: $row['post_title'];
    }
    unset($row);

    return $rows;
}
