<?php
/**
 * Weekly tracker data helpers — junction-aware reads with object cache.
 *
 * Spec: docs/superpowers/specs/2026-04-25-weekly-tracker-design.md
 *
 * Public API:
 *   bbj_v2_comp_types_active()        — list of non-archived comp types
 *   bbj_v2_comp_types_all()           — every comp type incl. archived (admin pane)
 *   bbj_v2_player_career_totals(int)  — career stat counts (junction-first, fallback to legacy columns)
 *   bbj_v2_player_weeks(int, int)     — week-by-week timeline for a player in one season
 *   bbj_v2_season_weeks(int)          — every week of a season, with summary + cast
 *   bbj_v2_active_players_for_week(int, int) — players still in the house going INTO this week
 *   bbj_v2_save_week_comp(int, int, int, ?int, ?string) — upsert wp_bbj_week_comps row
 *   bbj_v2_delete_week_comp(int)      — remove a wp_bbj_week_comps row by id
 *   bbj_v2_save_week_player(array)    — upsert wp_bbj_weeks_players row
 *   bbj_v2_attach_week_comps(array, int) — internal: attach comps[] arrays to player rows
 *
 * All read helpers use wp_cache_* with the 'bbj_v2' group. Cache is busted
 * via do_action('bbj_v2_week_comp_saved', $week_id, $player_id) and
 * do_action('bbj_v2_week_player_saved', $week_id, $player_id) — wired in
 * inc/template-functions.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Function bodies implemented in subsequent tasks.
function bbj_v2_comp_types_active(): array
{
    $cache_key = 'bbj_v2_comp_types_active';
    $cached = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT id, slug, name, sort_order, is_archived
           FROM {$wpdb->prefix}bbj_comp_types
          WHERE is_archived = 0
          ORDER BY sort_order ASC, name ASC",
        ARRAY_A
    ) ?: [];

    wp_cache_set($cache_key, $rows, 'bbj_v2', HOUR_IN_SECONDS);
    return $rows;
}

function bbj_v2_comp_types_all(): array
{
    $cache_key = 'bbj_v2_comp_types_all';
    $cached = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT id, slug, name, sort_order, is_archived
           FROM {$wpdb->prefix}bbj_comp_types
          ORDER BY is_archived ASC, sort_order ASC, name ASC",
        ARRAY_A
    ) ?: [];

    wp_cache_set($cache_key, $rows, 'bbj_v2', HOUR_IN_SECONDS);
    return $rows;
}
function bbj_v2_player_career_totals(int $player_post_id): array { return []; }
function bbj_v2_player_weeks(int $player_post_id, int $season_post_id): array { return []; }
function bbj_v2_season_weeks(int $season_post_id): array
{
    $cache_key = 'bbj_v2_season_weeks_' . $season_post_id;
    $cached = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT w.id, w.season_id, w.week_num, w.start_date, w.end_date, w.summary,
                (SELECT COUNT(*) FROM {$wpdb->prefix}bbj_week_comps wc WHERE wc.week_id = w.id) AS comp_count,
                (SELECT COUNT(*) FROM {$wpdb->prefix}bbj_weeks_players wp_e WHERE wp_e.week_id = w.id AND wp_e.evicted = 1) AS evicted_count
           FROM {$wpdb->prefix}bbj_weeks w
          WHERE w.season_id = %d
          ORDER BY w.week_num ASC",
        $season_post_id
    ), ARRAY_A) ?: [];

    wp_cache_set($cache_key, $rows, 'bbj_v2', HOUR_IN_SECONDS);
    return $rows;
}

function bbj_v2_active_players_for_week(int $season_post_id, int $week_id): array
{
    global $wpdb;

    // First, check if rows already exist for this week — if so, use those.
    $existing = $wpdb->get_results($wpdb->prepare(
        "SELECT wp.id AS row_id, wp.player_id, p.post_title AS player_name,
                wp.nom, wp.evicted, wp.veto_played, wp.voted_for, wp.vote_to_win,
                wp.saved_by_player_id, wp.active
           FROM {$wpdb->prefix}bbj_weeks_players wp
           INNER JOIN {$wpdb->posts} p ON p.ID = wp.player_id
          WHERE wp.week_id = %d
          ORDER BY p.post_title ASC",
        $week_id
    ), ARRAY_A) ?: [];

    if (!empty($existing)) {
        return bbj_v2_attach_week_comps($existing, $week_id);
    }

    // No rows yet — derive cast from wp_bbj_v2_player_season minus anyone
    // evicted in a prior week of this season.
    $derived = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT j.bbj_player AS player_id, p.post_title AS player_name,
                0 AS row_id, 0 AS nom, 0 AS evicted, 0 AS veto_played,
                0 AS voted_for, 0 AS vote_to_win, NULL AS saved_by_player_id, 1 AS active
           FROM {$wpdb->prefix}bbj_v2_player_season j
           INNER JOIN {$wpdb->posts} p ON p.ID = j.bbj_player
          WHERE j.bbj_season = %d
            AND j.bbj_player NOT IN (
                SELECT wp_prior.player_id
                  FROM {$wpdb->prefix}bbj_weeks_players wp_prior
                  INNER JOIN {$wpdb->prefix}bbj_weeks w_prior ON w_prior.id = wp_prior.week_id
                 WHERE w_prior.season_id = %d
                   AND wp_prior.evicted = 1
            )
            AND p.post_status = 'publish'
          ORDER BY p.post_title ASC",
        $season_post_id,
        $season_post_id
    ), ARRAY_A) ?: [];

    return bbj_v2_attach_week_comps($derived, $week_id);
}

/**
 * Attach a `comps` array (rows from wp_bbj_week_comps joined to wp_bbj_comp_types)
 * to each player row.
 */
function bbj_v2_attach_week_comps(array $rows, int $week_id): array
{
    if (empty($rows) || $week_id <= 0) {
        foreach ($rows as &$r) { $r['comps'] = []; }
        return $rows;
    }

    global $wpdb;
    $player_ids = array_column($rows, 'player_id');
    $placeholders = implode(',', array_fill(0, count($player_ids), '%d'));

    $comp_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT wc.id, wc.week_id, wc.player_id, wc.comp_type_id, wc.opponents_count, wc.notes,
                ct.slug, ct.name
           FROM {$wpdb->prefix}bbj_week_comps wc
           INNER JOIN {$wpdb->prefix}bbj_comp_types ct ON ct.id = wc.comp_type_id
          WHERE wc.week_id = %d AND wc.player_id IN ($placeholders)",
        array_merge([$week_id], $player_ids)
    ), ARRAY_A) ?: [];

    $by_player = [];
    foreach ($comp_rows as $cr) {
        $by_player[(int) $cr['player_id']][] = $cr;
    }
    foreach ($rows as &$r) {
        $r['comps'] = $by_player[(int) $r['player_id']] ?? [];
    }
    return $rows;
}

function bbj_v2_save_week_comp(int $week_id, int $player_id, int $comp_type_id, ?int $opponents_count = null, ?string $notes = null): int
{
    global $wpdb;
    $existing = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}bbj_week_comps
         WHERE week_id = %d AND player_id = %d AND comp_type_id = %d
         LIMIT 1",
        $week_id, $player_id, $comp_type_id
    ));

    $data = [
        'week_id'         => $week_id,
        'player_id'       => $player_id,
        'comp_type_id'    => $comp_type_id,
        'opponents_count' => $opponents_count,
        'notes'           => $notes,
    ];

    if ($existing > 0) {
        $wpdb->update("{$wpdb->prefix}bbj_week_comps", $data, ['id' => $existing]);
        $id = $existing;
    } else {
        $wpdb->insert("{$wpdb->prefix}bbj_week_comps", $data);
        $id = (int) $wpdb->insert_id;
    }

    do_action('bbj_v2_week_comp_saved', $week_id, $player_id);
    return $id;
}

function bbj_v2_delete_week_comp(int $id): bool
{
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT week_id, player_id FROM {$wpdb->prefix}bbj_week_comps WHERE id = %d",
        $id
    ), ARRAY_A);
    if (!$row) {
        return false;
    }
    $deleted = $wpdb->delete("{$wpdb->prefix}bbj_week_comps", ['id' => $id]);
    if ($deleted) {
        do_action('bbj_v2_week_comp_saved', (int) $row['week_id'], (int) $row['player_id']);
    }
    return (bool) $deleted;
}

function bbj_v2_save_week_player(array $row): int
{
    global $wpdb;
    $week_id   = (int) ($row['week_id']   ?? 0);
    $player_id = (int) ($row['player_id'] ?? 0);
    if ($week_id <= 0 || $player_id <= 0) {
        return 0;
    }

    $existing = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}bbj_weeks_players
         WHERE week_id = %d AND player_id = %d
         LIMIT 1",
        $week_id, $player_id
    ));

    $data = [
        'week_id'             => $week_id,
        'player_id'           => $player_id,
        'season_id'           => (int) ($row['season_id'] ?? 0),
        'nom'                 => isset($row['nom'])                 ? (int) $row['nom']                 : 0,
        'evicted'             => isset($row['evicted'])             ? (int) $row['evicted']             : 0,
        'veto_played'         => isset($row['veto_played'])         ? (int) $row['veto_played']         : 0,
        'voted_for'           => isset($row['voted_for'])           ? (int) $row['voted_for']           : 0,
        'vote_to_win'         => isset($row['vote_to_win'])         ? (int) $row['vote_to_win']         : 0,
        'active'              => isset($row['active'])              ? (int) $row['active']              : 1,
        'saved_by_player_id'  => isset($row['saved_by_player_id'])  ? (int) $row['saved_by_player_id'] ?: null : null,
    ];

    if ($existing > 0) {
        $wpdb->update("{$wpdb->prefix}bbj_weeks_players", $data, ['id' => $existing]);
        $id = $existing;
    } else {
        $wpdb->insert("{$wpdb->prefix}bbj_weeks_players", $data);
        $id = (int) $wpdb->insert_id;
    }

    do_action('bbj_v2_week_player_saved', $week_id, $player_id);
    return $id;
}
