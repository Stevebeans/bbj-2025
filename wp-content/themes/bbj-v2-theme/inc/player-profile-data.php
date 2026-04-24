<?php
/**
 * Player profile data helpers.
 *
 * Pure functions that query wp_bbj_players, wp_bbj_geo, wp_bbj_v2_player_season,
 * and wp_bbj_seasons, returning normalized arrays for the
 * single-bigbrother-players.php template.
 *
 * All functions are prefixed bbj_v2_player_profile_* and live in the global
 * namespace (following the rest of this theme's convention).
 */

if (!defined('ABSPATH')) {
    exit;
}

// Implementations land in Tasks 2–4.

/**
 * Fetch the core player record + geo data for a player post_id.
 *
 * Returns a normalized array or null if the player doesn't exist.
 */
function bbj_v2_player_profile_player_data(int $post_id): ?array
{
    global $wpdb;

    $player = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bbj_players WHERE post_id = %d LIMIT 1",
            $post_id
        ),
        ARRAY_A
    );

    if (!$player) {
        return null;
    }

    // Geo lives in a side table keyed by post_id (may be missing for older players).
    $geo = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT locality, administrative_area_level_1 FROM {$wpdb->prefix}bbj_geo WHERE post_id = %d LIMIT 1",
            $post_id
        ),
        ARRAY_A
    );

    $socials = [];
    foreach (['facebook', 'instagram', 'twitter', 'tiktok'] as $platform) {
        if (!empty($player[$platform])) {
            $socials[$platform] = $player[$platform];
        }
    }

    $hometown_parts = array_filter([
        $geo['locality'] ?? '',
        $geo['administrative_area_level_1'] ?? '',
    ]);
    $hometown = $hometown_parts ? implode(', ', $hometown_parts) : '';

    return [
        'post_id'          => $post_id,
        'first_name'       => $player['first_name'] ?? '',
        'last_name'        => $player['last_name'] ?? '',
        'full_name'        => trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '')),
        'nickname'         => $player['official_nickname'] ?? '',
        'gender'           => $player['player_gender'] ?? '',
        'profile_picture'  => (int) ($player['profile_picture'] ?? 0),
        'player_banner'    => (int) ($player['player_banner'] ?? 0),
        'date_of_birth'    => $player['date_of_birth'] ?? null,
        'occupation'       => $player['occupation'] ?? '',
        'hometown'         => $hometown,
        'city'             => $geo['locality'] ?? '',
        'state'            => $geo['administrative_area_level_1'] ?? '',
        'socials'          => $socials,
    ];
}

/**
 * Fetch all junction rows for a player, joined with season metadata.
 * Ordered by season start_date DESC (most recent first).
 */
function bbj_v2_player_profile_seasons(int $post_id): array
{
    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                j.bbj_season,
                j.bbj_evicted_date,
                j.bbj_finish_place,
                j.bbj_total_hoh,
                j.bbj_total_pov,
                j.bbj_total_nom,
                j.bbj_total_havenot,
                j.bbj_total_saved,
                j.bbj_votes_received,
                j.bbj_veto_played,
                j.current_evicted,
                j.current_jury,
                s.full_name       AS season_name,
                s.abbreviation    AS season_abbr,
                s.start_date      AS season_start,
                s.end_date        AS season_end,
                s.season_winner,
                s.runner_up,
                s.afp,
                p.post_name       AS season_slug
             FROM {$wpdb->prefix}bbj_v2_player_season j
             INNER JOIN {$wpdb->prefix}bbj_seasons s ON s.post_id = j.bbj_season
             INNER JOIN {$wpdb->posts} p ON p.ID = j.bbj_season
             WHERE j.bbj_player = %d
             ORDER BY s.start_date DESC",
            $post_id
        ),
        ARRAY_A
    );

    return $rows ?: [];
}

/**
 * Aggregate career totals across all seasons.
 *
 * $seasons is the array returned by bbj_v2_player_profile_seasons()
 * (pass it in rather than re-querying).
 */
function bbj_v2_player_profile_career_totals(array $seasons): array
{
    $totals = [
        'season_count' => count($seasons),
        'hoh'          => 0,
        'pov'          => 0,
        'nom'          => 0,
        'votes'        => 0,
        'days'         => 0,
        'veto_played'  => 0,
        'havenot'      => 0,
        'saved'        => 0,
    ];

    foreach ($seasons as $row) {
        $totals['hoh']         += (int) ($row['bbj_total_hoh']      ?? 0);
        $totals['pov']         += (int) ($row['bbj_total_pov']      ?? 0);
        $totals['nom']         += (int) ($row['bbj_total_nom']      ?? 0);
        $totals['votes']       += (int) ($row['bbj_votes_received'] ?? 0);
        $totals['veto_played'] += (int) ($row['bbj_veto_played']    ?? 0);
        $totals['havenot']     += (int) ($row['bbj_total_havenot']  ?? 0);
        $totals['saved']       += (int) ($row['bbj_total_saved']    ?? 0);

        // Days = evicted_date (or season end if made finale) - season start.
        $start   = $row['season_start']     ?? null;
        $evicted = $row['bbj_evicted_date'] ?? null;
        $end     = $row['season_end']       ?? null;

        if ($start) {
            $end_for_days = $evicted ?: ($end ?: null);
            if ($end_for_days) {
                $d1 = new DateTime($start);
                $d2 = new DateTime($end_for_days);
                $totals['days'] += max(0, (int) $d1->diff($d2)->days);
            }
        }
    }

    return $totals;
}

/**
 * Fetch other players who appeared in the same season, with placement data
 * so the template can render status tags (Winner / Runner-up / AFP / Jury / Out).
 *
 * Excludes the given $player_post_id from the result.
 */
function bbj_v2_player_profile_castmates(int $player_post_id, int $season_post_id): array
{
    global $wpdb;

    if ($season_post_id <= 0) {
        return [];
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                j.bbj_player        AS player_post_id,
                j.bbj_finish_place,
                j.bbj_evicted_date,
                j.current_jury,
                j.current_evicted,
                bp.first_name,
                bp.last_name,
                bp.official_nickname,
                bp.profile_picture,
                p.post_name         AS player_slug,
                s.season_winner,
                s.runner_up,
                s.afp
             FROM {$wpdb->prefix}bbj_v2_player_season j
             INNER JOIN {$wpdb->prefix}bbj_players bp ON bp.post_id = j.bbj_player
             INNER JOIN {$wpdb->posts} p ON p.ID = j.bbj_player
             INNER JOIN {$wpdb->prefix}bbj_seasons s ON s.post_id = j.bbj_season
             WHERE j.bbj_season = %d
               AND j.bbj_player != %d
             ORDER BY (j.bbj_finish_place IS NULL), j.bbj_finish_place ASC",
            $season_post_id,
            $player_post_id
        ),
        ARRAY_A
    );

    return $rows ?: [];
}
