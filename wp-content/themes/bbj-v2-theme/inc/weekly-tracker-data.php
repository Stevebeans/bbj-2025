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
function bbj_v2_season_weeks(int $season_post_id): array { return []; }
function bbj_v2_active_players_for_week(int $season_post_id, int $week_id): array { return []; }
function bbj_v2_save_week_comp(int $week_id, int $player_id, int $comp_type_id, ?int $opponents_count = null, ?string $notes = null): int { return 0; }
function bbj_v2_delete_week_comp(int $id): bool { return false; }
function bbj_v2_save_week_player(array $row): int { return 0; }
