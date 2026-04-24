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

/**
 * Fetch the core player record for a player post_id.
 *
 * Always returns a shape — if wp_bbj_players has no row (common for new
 * houseguests entered via the spoiler bar but not fully provisioned yet),
 * we fall back to a shim derived from the WP post (title, permalink)
 * so the template can still render.
 */
function bbj_v2_player_profile_player_data(int $post_id): array
{
    global $wpdb;

    $player = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bbj_players WHERE post_id = %d LIMIT 1",
            $post_id
        ),
        ARRAY_A
    ) ?: [];

    // Hometown has two possible sources:
    //   1) wp_bbj_geo — Meta Box "Geolocation" side-panel writes new entries here
    //      (note: this table keys by `ID`, not `post_id`)
    //   2) wp_bbj_players.hometown_city / hometown_state — legacy-imported players
    // Prefer (1) since that's where wp-admin saves today; fall back to (2).
    $geo = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT locality, administrative_area_level_1
               FROM {$wpdb->prefix}bbj_geo
              WHERE ID = %d
              LIMIT 1",
            $post_id
        ),
        ARRAY_A
    );
    $city  = $geo['locality']                     ?? ($player['hometown_city']  ?? '');
    $state = $geo['administrative_area_level_1']  ?? ($player['hometown_state'] ?? '');

    $hometown_parts = array_filter([$city, $state]);
    $hometown = $hometown_parts ? implode(', ', $hometown_parts) : '';

    // Meta Box field reader: prefer the custom-table row, fall back to
    // rwmb_meta() (which handles post_meta storage for fields that didn't
    // land in the custom table). Seen on staging where Ava Pearl's row
    // existed with some fields blank but wp-admin showed values — those
    // were living in post_meta instead of wp_bbj_players.
    $mb = function (string $key) use ($player, $post_id) {
        if (!empty($player[$key])) return $player[$key];
        if (function_exists('rwmb_meta')) {
            $v = rwmb_meta($key, [], $post_id);
            if (!empty($v)) return $v;
        }
        return '';
    };

    $socials = [];
    foreach (['facebook', 'instagram', 'twitter', 'tiktok'] as $platform) {
        $v = $mb($platform);
        if (!empty($v)) {
            $socials[$platform] = $v;
        }
    }

    // Split WP post title as a last-resort name source for players missing their
    // wp_bbj_players row. e.g. "Ava Pearl" -> first="Ava", last="Pearl".
    $post_title = get_the_title($post_id);
    $title_parts = array_values(array_filter(explode(' ', trim((string) $post_title), 2)));
    $fallback_first = $title_parts[0] ?? '';
    $fallback_last  = $title_parts[1] ?? '';

    $first = $mb('first_name');
    $last  = $mb('last_name');
    $full  = trim($first . ' ' . $last);

    return [
        'post_id'          => $post_id,
        'first_name'       => $first !== '' ? $first : $fallback_first,
        'last_name'        => $last  !== '' ? $last  : $fallback_last,
        'full_name'        => $full !== '' ? $full : (string) $post_title,
        'nickname'         => $mb('official_nickname'),
        'gender'           => $mb('player_gender'),
        'profile_picture'  => (int) $mb('profile_picture'),
        'player_banner'    => (int) $mb('player_banner'),
        'date_of_birth'    => $mb('date_of_birth') ?: null,
        'occupation'       => $mb('occupation'),
        'hometown'         => $hometown,
        'city'             => $city,
        'state'            => $state,
        'socials'          => $socials,
    ];
}

/**
 * Fetch all junction rows for a player, joined with season metadata.
 * Ordered by season start_date DESC (falls back to post_date when start_date is NULL).
 *
 * wp_bbj_seasons is LEFT JOINed because modern seasons (BB22+) created via the
 * bbj-app/bigbrotherjunkies-data admin don't have a companion row in that table.
 * Falls back to wp_posts.post_title for the season name and derives the
 * abbreviation in PHP when the seasons row is missing.
 */
function bbj_v2_player_profile_seasons(int $post_id): array
{
    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                j.bbj_season,
                j.bbj_evicted_date,
                j.finish_place,
                j.bbj_total_hoh,
                j.bbj_total_pov,
                j.bbj_total_nom,
                j.bbj_total_havenot,
                j.bbj_total_saved,
                j.bbj_votes_received,
                j.bbj_veto_played,
                j.current_evicted,
                j.current_jury,
                COALESCE(s.full_name, p.post_title) AS season_name,
                s.abbreviation    AS season_abbr,
                s.start_date      AS season_start,
                s.end_date        AS season_end,
                s.season_winner,
                s.runner_up,
                s.afp,
                p.post_name       AS season_slug,
                p.post_date       AS season_post_date,
                (SELECT COUNT(*) FROM {$wpdb->prefix}bbj_v2_player_season j2
                    WHERE j2.bbj_season = j.bbj_season) AS season_size
             FROM {$wpdb->prefix}bbj_v2_player_season j
             INNER JOIN {$wpdb->posts} p ON p.ID = j.bbj_season
             LEFT JOIN {$wpdb->prefix}bbj_seasons s ON s.post_id = j.bbj_season
             WHERE j.bbj_player = %d
               AND p.post_status = 'publish'
             ORDER BY COALESCE(s.start_date, p.post_date) DESC",
            $post_id
        ),
        ARRAY_A
    );

    if (!$rows) {
        return [];
    }

    // Derive the season abbreviation from the season name when the
    // wp_bbj_seasons row is missing (e.g. "Big Brother 27" -> "BB27").
    foreach ($rows as &$row) {
        if (empty($row['season_abbr']) && !empty($row['season_name'])) {
            if (preg_match('/Big Brother\s+(\d+)/i', $row['season_name'], $m)) {
                $row['season_abbr'] = 'BB' . $m[1];
            }
        }
    }
    unset($row);

    return $rows;
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
                j.finish_place,
                j.bbj_evicted_date,
                j.current_jury,
                j.current_evicted,
                bp.first_name,
                bp.last_name,
                bp.official_nickname,
                bp.profile_picture,
                p.post_title        AS post_title,
                p.post_name         AS player_slug,
                s.season_winner,
                s.runner_up,
                s.afp
             FROM {$wpdb->prefix}bbj_v2_player_season j
             INNER JOIN {$wpdb->posts} p ON p.ID = j.bbj_player
             LEFT JOIN {$wpdb->prefix}bbj_players bp ON bp.post_id = j.bbj_player
             LEFT JOIN {$wpdb->prefix}bbj_seasons s ON s.post_id = j.bbj_season
             WHERE j.bbj_season = %d
               AND j.bbj_player != %d
               AND p.post_status = 'publish'
             ORDER BY (j.finish_place IS NULL), j.finish_place ASC",
            $season_post_id,
            $player_post_id
        ),
        ARRAY_A
    );

    if (!$rows) {
        return [];
    }

    // Fall back to splitting the WP post_title for first/last name when
    // wp_bbj_players has no row (modern players added via bbj-app admin).
    foreach ($rows as &$row) {
        if (empty($row['first_name']) && empty($row['last_name'])) {
            $parts = array_values(array_filter(explode(' ', trim((string) ($row['post_title'] ?? '')), 2)));
            $row['first_name'] = $parts[0] ?? '';
            $row['last_name']  = $parts[1] ?? '';
        }
    }
    unset($row);

    return $rows;
}

/**
 * Compute derived values for display: age now, age in house, days in house,
 * placement label, eviction day + week, status kicker, tag chips.
 *
 * $player   — return value from bbj_v2_player_profile_player_data()
 * $seasons  — return value from bbj_v2_player_profile_seasons() (may be empty)
 */
function bbj_v2_player_profile_derive(array $player, array $seasons): array
{
    $latest = $seasons[0] ?? null;

    // Age now (from DOB).
    $age_now = null;
    if (!empty($player['date_of_birth'])) {
        try {
            $dob = new DateTime($player['date_of_birth']);
            $age_now = $dob->diff(new DateTime('now'))->y;
        } catch (Exception $e) {
            // Malformed date — leave null.
        }
    }

    // Age in house (at evicted_date or season start if no evict).
    $age_in_house = null;
    if ($latest && !empty($player['date_of_birth'])) {
        $ref = $latest['bbj_evicted_date'] ?: $latest['season_start'];
        if ($ref) {
            try {
                $dob = new DateTime($player['date_of_birth']);
                $age_in_house = $dob->diff(new DateTime($ref))->y;
            } catch (Exception $e) {}
        }
    }

    // Days in house (latest season).
    $days_in_house = null;
    $eviction_week = null;
    $eviction_day  = null;
    if ($latest && !empty($latest['season_start'])) {
        $end = $latest['bbj_evicted_date'] ?: ($latest['season_end'] ?: date('Y-m-d'));
        try {
            $start = new DateTime($latest['season_start']);
            $evict = new DateTime($end);
            $diff  = $start->diff($evict);
            $days_in_house = max(0, (int) $diff->days);
            if (!empty($latest['bbj_evicted_date'])) {
                $eviction_day  = $days_in_house;
                $eviction_week = (int) floor($days_in_house / 7) + 1;
            }
        } catch (Exception $e) {}
    }

    // Status kicker: Winner / Runner-up / AFP / Jury / Pre-jury / Active.
    // finish_place is the authoritative signal (lives in the junction table for
    // every player); the season_winner/runner_up post-pointer fallbacks only
    // matter for old data where finish_place wasn't populated.
    $status_kicker = 'Houseguest';
    if ($latest) {
        $abbr   = $latest['season_abbr'] ?: 'BB';
        $finish = (int) ($latest['finish_place'] ?? 0);
        $is_winner_ptr   = (int) ($latest['season_winner'] ?? 0) === (int) $player['post_id'];
        $is_runnerup_ptr = (int) ($latest['runner_up']     ?? 0) === (int) $player['post_id'];
        $is_afp_ptr      = (int) ($latest['afp']           ?? 0) === (int) $player['post_id'];

        $status_kicker = 'Houseguest · ' . $abbr;
        if ($finish === 1 || $is_winner_ptr) {
            $status_kicker .= ' · Winner';
        } elseif ($finish === 2 || $is_runnerup_ptr) {
            $status_kicker .= ' · Runner-up';
        } elseif ($is_afp_ptr) {
            $status_kicker .= " · America's Favorite";
        } elseif (!empty($latest['current_jury'])) {
            $status_kicker .= ' · Jury';
        } elseif (!empty($latest['bbj_evicted_date'])) {
            $status_kicker .= ' · Pre-jury';
        } else {
            $status_kicker .= ' · Active';
        }
    }

    // Placement label for bio strip (e.g. "5th · AFP winner" or "Currently playing").
    $placement_label = '';
    if ($latest) {
        $place         = (int) ($latest['finish_place'] ?? 0);
        $is_afp_ptr    = (int) ($latest['afp']          ?? 0) === (int) $player['post_id'];
        $is_winner_ptr = (int) ($latest['season_winner'] ?? 0) === (int) $player['post_id'];
        if ($place > 0) {
            $placement_label = bbj_v2_player_profile_ordinal($place);
            if ($is_afp_ptr) {
                $placement_label .= ' · AFP winner';
            } elseif ($place === 1 || $is_winner_ptr) {
                $placement_label .= ' · Winner';
            } elseif ($place === 2) {
                $placement_label .= ' · Runner-up';
            }
        } else {
            $placement_label = 'Currently playing';
        }
    }

    // Tag chips: only where count > 0.
    $totals = bbj_v2_player_profile_career_totals($seasons);
    $chips = [];
    if ($latest && (int) $latest['afp'] === (int) $player['post_id']) {
        $chips[] = ['text' => "♥ America's Favorite", 'class' => 'afp'];
    }
    if ($latest && !empty($latest['current_jury'])) {
        $chips[] = ['text' => 'Jury member', 'class' => ''];
    }
    if ($totals['hoh'] > 0)   { $chips[] = ['text' => "{$totals['hoh']}× HoH", 'class' => '']; }
    if ($totals['pov'] > 0)   { $chips[] = ['text' => "{$totals['pov']}× PoV", 'class' => '']; }
    if ($totals['nom'] > 0)   { $chips[] = ['text' => "{$totals['nom']}× Nominated", 'class' => '']; }

    // Has-AFP-ever: used for the hero portrait badge.
    $is_afp_anywhere = false;
    foreach ($seasons as $s) {
        if ((int) $s['afp'] === (int) $player['post_id']) {
            $is_afp_anywhere = true;
            break;
        }
    }

    return [
        'age_now'          => $age_now,
        'age_in_house'     => $age_in_house,
        'days_in_house'    => $days_in_house,
        'eviction_day'     => $eviction_day,
        'eviction_week'    => $eviction_week,
        'status_kicker'    => $status_kicker,
        'placement_label'  => $placement_label,
        'chips'            => $chips,
        'is_afp_anywhere'  => $is_afp_anywhere,
        'latest_season'    => $latest,
    ];
}

/**
 * Ordinal formatter: 1 → "1st", 2 → "2nd", 3 → "3rd", etc.
 */
function bbj_v2_player_profile_ordinal(int $n): string
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
