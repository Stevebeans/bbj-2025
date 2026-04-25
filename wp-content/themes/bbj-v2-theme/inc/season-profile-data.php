<?php
/**
 * Season profile data helpers.
 *
 * All functions are prefixed bbj_v2_season_profile_* and live in the global
 * namespace (matching the rest of this theme). They return normalized arrays
 * the template can iterate without further processing.
 *
 * Drift conventions (per memory/references/bbj_data_schema.md):
 * - LEFT JOIN wp_bbj_seasons (BB22+ has no row)
 * - LEFT JOIN wp_bbj_players ON (post_id = X OR (id = X AND post_id = 0))
 * - Use `finish_place` (no prefix) for placement
 * - Prefer finish_place === 1/2 over season_winner/runner_up post-pointers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ordinal formatter: 1 → "1st", 2 → "2nd", 3 → "3rd", etc.
 */
function bbj_v2_season_profile_ordinal(int $n): string
{
    if ($n <= 0) return (string) $n;
    $mod100 = $n % 100;
    if ($mod100 >= 11 && $mod100 <= 13) return $n . 'th';
    switch ($n % 10) {
        case 1:  return $n . 'st';
        case 2:  return $n . 'nd';
        case 3:  return $n . 'rd';
        default: return $n . 'th';
    }
}

/**
 * Full season profile data with LEFT JOIN fallbacks for BB22+.
 */
function bbj_v2_season_profile_data(int $post_id): array
{
    global $wpdb;

    // Core row (LEFT JOIN — BB22+ has no wp_bbj_seasons row)
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                p.ID                AS post_id,
                p.post_title        AS title,
                p.post_name         AS slug,
                p.post_date         AS post_date,
                p.post_content      AS content,
                s.full_name         AS season_full_name,
                s.abbreviation      AS season_abbr,
                s.season_number     AS season_number,
                s.start_date        AS start_date,
                s.end_date          AS end_date,
                s.season_winner     AS season_winner_id,
                s.runner_up         AS runner_up_id,
                s.afp               AS afp_id
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->prefix}bbj_seasons s ON s.post_id = p.ID
             WHERE p.ID = %d AND p.post_type = 'bigbrother-seasons'
             LIMIT 1",
            $post_id
        ),
        ARRAY_A
    );

    if (!$row) {
        return ['post_id' => $post_id, 'title' => get_the_title($post_id)];
    }

    // Derive season number from title if not stored ("Big Brother 27" -> 27)
    if (empty($row['season_number']) && !empty($row['title'])) {
        if (preg_match('/(\d+)/', $row['title'], $m)) {
            $row['season_number'] = (int) $m[1];
        }
    }

    // Derive abbreviation if missing ("Big Brother 27" -> "BB27")
    if (empty($row['season_abbr']) && !empty($row['season_number'])) {
        $row['season_abbr'] = 'BB' . $row['season_number'];
    }

    // Strip stats: winner, days, houseguest count
    $hg_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}bbj_v2_player_season WHERE bbj_season = %d",
        $post_id
    ));

    $winner_post_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT bbj_player FROM {$wpdb->prefix}bbj_v2_player_season
         WHERE bbj_season = %d AND finish_place = 1
         LIMIT 1",
        $post_id
    ));
    $winner_name = $winner_post_id ? get_the_title($winner_post_id) : '';

    $days = null;
    if (!empty($row['start_date']) && !empty($row['end_date'])) {
        try {
            $d1 = new DateTime($row['start_date']);
            $d2 = new DateTime($row['end_date']);
            $days = max(0, (int) $d1->diff($d2)->days);
        } catch (Exception $e) {}
    }

    // Prize fallback by era — no per-season field exists yet
    $prize = null;
    $sn = (int) ($row['season_number'] ?? 0);
    if ($sn >= 23)      $prize = '$750k';
    elseif ($sn >= 19)  $prize = '$500k';
    elseif ($sn >= 1)   $prize = '$500k';

    return [
        'post_id'        => $post_id,
        'title'          => $row['title'],
        'slug'           => $row['slug'],
        'name'           => $row['season_full_name'] ?: $row['title'],
        'number'         => (int) ($row['season_number'] ?? 0),
        'abbr'           => $row['season_abbr'] ?: '',
        'start_date'     => $row['start_date'] ?: null,
        'end_date'       => $row['end_date'] ?: null,
        'post_date'      => $row['post_date'],
        'content'        => $row['content'],
        'winner_post_id' => $winner_post_id,
        'winner_name'    => $winner_name,
        'runner_up_id'   => (int) ($row['runner_up_id'] ?? 0),
        'afp_id'         => (int) ($row['afp_id'] ?? 0),
        'hg_count'       => $hg_count,
        'days'           => $days,
        'prize'          => $prize,
    ];
}
