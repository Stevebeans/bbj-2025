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
        $title = get_the_title($post_id);
        return [
            'post_id'        => $post_id,
            'title'          => $title,
            'slug'           => '',
            'name'           => $title,
            'number'         => 0,
            'abbr'           => '',
            'start_date'     => null,
            'end_date'       => null,
            'post_date'      => get_post_field('post_date', $post_id) ?: null,
            'content'        => '',
            'winner_post_id' => 0,
            'winner_name'    => '',
            'runner_up_id'   => 0,
            'afp_id'         => 0,
            'hg_count'       => 0,
            'days'           => null,
            'prize'          => null,
        ];
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
        'content'        => (string) ($row['content'] ?? ''),
        'winner_post_id' => $winner_post_id,
        'winner_name'    => $winner_name,
        'runner_up_id'   => (int) ($row['runner_up_id'] ?? 0),
        'afp_id'         => (int) ($row['afp_id'] ?? 0),
        'hg_count'       => $hg_count,
        'days'           => $days,
        'prize'          => $prize,
    ];
}

/**
 * Return ±$window seasons by season_number around the given season.
 * Falls back to ordering by post_date when season_number is missing.
 */
function bbj_v2_season_profile_neighbors(int $post_id, int $window = 5): array
{
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT
            p.ID         AS post_id,
            p.post_title AS title,
            p.post_name  AS slug,
            p.post_date  AS post_date,
            s.season_number,
            s.abbreviation
         FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->prefix}bbj_seasons s ON s.post_id = p.ID
         WHERE p.post_type = 'bigbrother-seasons' AND p.post_status = 'publish'
         ORDER BY COALESCE(s.season_number, 0) DESC, p.post_date DESC",
        ARRAY_A
    );

    if (!$rows) return [];

    // Find current season's index in the ordered list
    $current = null;
    foreach ($rows as $i => $r) {
        if ((int) $r['post_id'] === $post_id) {
            $current = $i;
            break;
        }
    }
    if ($current === null) return [];

    $start = max(0, $current - $window);
    $end   = min(count($rows) - 1, $current + $window);
    $slice = array_slice($rows, $start, $end - $start + 1);

    foreach ($slice as &$r) {
        // Derive season_number from title if missing
        if (empty($r['season_number']) && !empty($r['title'])) {
            if (preg_match('/(\d+)/', $r['title'], $m)) {
                $r['season_number'] = (int) $m[1];
            }
        }
        // Derive abbreviation if missing
        if (empty($r['abbreviation']) && !empty($r['season_number'])) {
            $r['abbreviation'] = 'BB' . $r['season_number'];
        }
        $r['is_current'] = ((int) $r['post_id'] === $post_id);
        $r['url']        = get_permalink((int) $r['post_id']);
    }
    unset($r);

    return $slice;
}

/**
 * Return the "Season Facts" dl content for the Overview block.
 * Each item is [label, value]. Skips anything we don't have.
 */
function bbj_v2_season_profile_facts(array $season): array
{
    $facts = [];
    if (!empty($season['start_date'])) {
        $facts[] = ['Premiere', date_i18n('M j, Y', strtotime($season['start_date']))];
    }
    if (!empty($season['end_date'])) {
        $facts[] = ['Finale', date_i18n('M j, Y', strtotime($season['end_date']))];
    }
    if (!empty($season['days'])) {
        $facts[] = ['Days', (string) $season['days']];
    }
    if (!empty($season['hg_count'])) {
        $facts[] = ['Houseguests', (string) $season['hg_count']];
    }
    if (!empty($season['prize'])) {
        $facts[] = ['Prize', $season['prize']];
    }
    return $facts;
}

/**
 * Return all houseguests for a season with player profile data joined.
 * Ordered by finish_place ASC (NULLs last → still-playing first).
 *
 * Uses the id OR (id AND post_id=0) join pattern to handle both
 * legacy-imported players (wp_bbj_players.post_id = wp_posts.ID) and
 * modern players from bbj-app admin (wp_bbj_players.id = wp_posts.ID,
 * post_id = 0). See memory/references/bbj_data_schema.md.
 */
function bbj_v2_season_profile_cast(int $post_id): array
{
    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                j.bbj_player        AS player_post_id,
                j.finish_place,
                j.bbj_evicted_date,
                j.current_jury,
                j.current_evicted,
                j.bbj_total_hoh,
                j.bbj_total_pov,
                j.bbj_total_nom,
                bp.first_name,
                bp.last_name,
                bp.official_nickname,
                bp.profile_picture,
                p.post_title        AS post_title,
                p.post_name         AS player_slug
             FROM {$wpdb->prefix}bbj_v2_player_season j
             INNER JOIN {$wpdb->posts} p ON p.ID = j.bbj_player
             LEFT JOIN {$wpdb->prefix}bbj_players bp
                    ON (bp.post_id = j.bbj_player OR (bp.id = j.bbj_player AND bp.post_id = 0))
             WHERE j.bbj_season = %d
               AND p.post_status = 'publish'
             ORDER BY (j.finish_place IS NULL), j.finish_place ASC, p.post_title ASC",
            $post_id
        ),
        ARRAY_A
    );

    if (!$rows) return [];

    foreach ($rows as &$row) {
        // Fall back to splitting wp_posts.post_title for first/last when
        // wp_bbj_players row is missing entirely (modern players that don't
        // have a row at all in either id-form or post_id-form).
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
 * Eviction order table data. Returns rows ordered by week_num ASC.
 * Vote tally: count rows in wp_bbj_weeks_players where voted_for = evictee
 * for that week.
 */
function bbj_v2_season_profile_evictions(int $post_id): array
{
    global $wpdb;

    $weeks = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, week_num, start_date, end_date
             FROM {$wpdb->prefix}bbj_weeks
             WHERE season_id = %d
             ORDER BY week_num ASC",
            $post_id
        ),
        ARRAY_A
    );
    if (!$weeks) return [];

    $week_ids    = array_map('intval', array_column($weeks, 'id'));
    $week_id_csv = implode(',', $week_ids);
    $max_week    = (int) max(array_column($weeks, 'week_num'));

    // Index weeks by id for join
    $weeks_by_id = [];
    foreach ($weeks as $w) {
        $weeks_by_id[(int) $w['id']] = $w;
    }
    $first_week_start = $weeks[0]['start_date'] ?? null;

    // All evictees in any week of this season
    $evictee_rows = $wpdb->get_results(
        "SELECT wp.id AS wpid, wp.week_id, wp.player_id, p.post_title, p.post_name AS player_slug, bp.profile_picture
         FROM {$wpdb->prefix}bbj_weeks_players wp
         INNER JOIN {$wpdb->posts} p ON p.ID = wp.player_id
         LEFT JOIN {$wpdb->prefix}bbj_players bp
                ON (bp.post_id = wp.player_id OR (bp.id = wp.player_id AND bp.post_id = 0))
         WHERE wp.evicted = 1 AND wp.week_id IN ({$week_id_csv})",
        ARRAY_A
    );
    if (!$evictee_rows) return [];

    // HoH per week
    $hoh_rows = $wpdb->get_results(
        "SELECT wp.week_id, p.post_title AS hoh_name
         FROM {$wpdb->prefix}bbj_weeks_players wp
         INNER JOIN {$wpdb->posts} p ON p.ID = wp.player_id
         WHERE wp.hoh = 1 AND wp.week_id IN ({$week_id_csv})",
        ARRAY_A
    );
    $hoh_by_week = [];
    foreach ($hoh_rows as $h) {
        $hoh_by_week[(int) $h['week_id']] = (string) $h['hoh_name'];
    }

    // Vote-counts: count voted_for occurrences per (week_id, evictee_id)
    $vote_rows = $wpdb->get_results(
        "SELECT week_id, voted_for, COUNT(*) AS n
         FROM {$wpdb->prefix}bbj_weeks_players
         WHERE week_id IN ({$week_id_csv}) AND voted_for > 0
         GROUP BY week_id, voted_for",
        ARRAY_A
    );
    $votes_by_pair = [];
    foreach ($vote_rows as $v) {
        $votes_by_pair[(int) $v['week_id'] . '_' . (int) $v['voted_for']] = (int) $v['n'];
    }

    // Group evictees by week_num to detect doubles
    $by_week_num = [];
    foreach ($evictee_rows as $e) {
        $w = $weeks_by_id[(int) $e['week_id']] ?? null;
        if (!$w) continue;
        $by_week_num[(int) $w['week_num']][] = $e + ['week_num' => (int) $w['week_num'], 'week_end' => $w['end_date']];
    }

    $out = [];
    foreach ($by_week_num as $week_num => $evs) {
        $type = count($evs) > 1 ? 'Double' : 'Regular';
        if ((int) $week_num === $max_week) $type = 'Finale';
        foreach ($evs as $e) {
            $day = '';
            if (!empty($e['week_end']) && !empty($first_week_start)) {
                try {
                    $d1 = new DateTime($first_week_start);
                    $d2 = new DateTime($e['week_end']);
                    $day = 'Day ' . max(0, (int) $d1->diff($d2)->days);
                } catch (Exception $ex) {}
            }
            $vote_count = $votes_by_pair[(int) $e['week_id'] . '_' . (int) $e['player_id']] ?? null;
            $out[] = [
                'week_num'    => (int) $week_num,
                'week_label'  => $type === 'Finale' ? 'Fin' : str_pad((string) $week_num, 2, '0', STR_PAD_LEFT),
                'name'        => (string) $e['post_title'],
                'slug'        => (string) $e['player_slug'],
                'player_id'   => (int) $e['player_id'],
                'profile_pic' => (int) ($e['profile_picture'] ?? 0),
                'day'         => $day,
                'vote'        => $vote_count !== null ? ($vote_count . '–?') : '',
                'type'        => $type,
                'hoh_name'    => $hoh_by_week[(int) $e['week_id']] ?? '',
            ];
        }
    }

    return $out;
}

/**
 * Top feed updates for the season, by total_rating DESC.
 * Date window: wp_bbj_seasons.start_date/end_date if available, else
 * season post_date as start + 100 days as end (covers BB22+).
 */
function bbj_v2_season_profile_top_feed_updates(int $post_id, int $limit = 9): array
{
    global $wpdb;

    $season = bbj_v2_season_profile_data($post_id);
    $start = $season['start_date'] ?: $season['post_date'];
    if (!$start) return [];

    if (!empty($season['end_date'])) {
        $end = $season['end_date'];
    } else {
        try {
            $end = (new DateTime($start))->modify('+100 days')->format('Y-m-d');
        } catch (Exception $e) {
            return [];
        }
    }

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                p.ID, p.post_title, p.post_excerpt, p.post_content, p.post_date, p.post_name,
                CAST(IFNULL(pm.meta_value, '0') AS UNSIGNED) AS rating
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm
                    ON pm.post_id = p.ID AND pm.meta_key = 'total_rating'
             WHERE p.post_type = 'live-feed-updates'
               AND p.post_status = 'publish'
               AND p.post_date BETWEEN %s AND %s
             ORDER BY rating DESC, p.post_date DESC
             LIMIT %d",
            $start . ' 00:00:00',
            $end . ' 23:59:59',
            $limit
        ),
        ARRAY_A
    ) ?: [];
}

/**
 * Articles tagged with this season's category.
 * Convention: each season has a category whose slug is "big-brother-{number}"
 * (e.g., "big-brother-27"). Returns [] if the category doesn't exist
 * or has no published posts.
 */
function bbj_v2_season_profile_articles(int $post_id, int $limit = 4): array
{
    $season = bbj_v2_season_profile_data($post_id);
    $number = (int) ($season['number'] ?? 0);
    if ($number <= 0) return [];

    $slug = 'big-brother-' . $number;
    $term = get_term_by('slug', $slug, 'category');
    if (!$term) return [];

    $posts = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'category'       => (int) $term->term_id,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    $out = [];
    foreach ($posts as $p) {
        $thumb_id = get_post_thumbnail_id($p->ID);
        $out[] = [
            'id'       => (int) $p->ID,
            'title'    => get_the_title($p),
            'excerpt'  => (string) get_the_excerpt($p),
            'url'      => get_permalink($p),
            'thumb'    => $thumb_id ? (wp_get_attachment_image_url($thumb_id, 'medium') ?: '') : '',
            'date'     => $p->post_date,
            'category' => 'BBJ Blog',
        ];
    }
    return $out;
}

/**
 * Comp winners weekly table. One row per week with HoH, PoV, nominees,
 * veto used on. Returns ordered by week_num ASC.
 */
function bbj_v2_season_profile_comps(int $post_id): array
{
    global $wpdb;

    $weeks = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, week_num
             FROM {$wpdb->prefix}bbj_weeks
             WHERE season_id = %d
             ORDER BY week_num ASC",
            $post_id
        ),
        ARRAY_A
    );
    if (!$weeks) return [];

    $week_id_csv = implode(',', array_map('intval', array_column($weeks, 'id')));

    $rows = $wpdb->get_results(
        "SELECT wp.week_id, wp.player_id, wp.hoh, wp.pov, wp.nom, wp.saved, p.post_title AS name
         FROM {$wpdb->prefix}bbj_weeks_players wp
         INNER JOIN {$wpdb->posts} p ON p.ID = wp.player_id
         WHERE wp.week_id IN ({$week_id_csv})
           AND (wp.hoh = 1 OR wp.pov = 1 OR wp.nom = 1 OR wp.saved = 1)",
        ARRAY_A
    );

    $by_week = [];
    foreach ($weeks as $w) {
        $by_week[(int) $w['id']] = [
            'week_num' => (int) $w['week_num'],
            'hoh'      => [],
            'pov'      => [],
            'nom'      => [],
            'saved'    => [],
        ];
    }
    foreach ($rows as $r) {
        $wid  = (int) $r['week_id'];
        $name = (string) $r['name'];
        if ((int) $r['hoh'])   $by_week[$wid]['hoh'][]   = $name;
        if ((int) $r['pov'])   $by_week[$wid]['pov'][]   = $name;
        if ((int) $r['nom'])   $by_week[$wid]['nom'][]   = $name;
        if ((int) $r['saved']) $by_week[$wid]['saved'][] = $name;
    }

    return array_values($by_week);
}
