<?php
/**
 * Homepage data layer — cached queries shared across the home template parts.
 * All helpers cache in the `bbj_v2` object-cache group and are busted via
 * save_post_* / option / term hooks registered at the bottom of this file.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Numeric season number (e.g. 26) of the currently configured season.
 * Returns 0 if no current season is set or the CPT lookup fails.
 */
function bbj_v2_current_season_number(): int
{
    $cache_key = 'homepage_current_season_number';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if ($cached !== false) {
        return (int) $cached;
    }

    $season_id = (int) get_option('bbj_v2_current_season', 0);
    if ($season_id <= 0) {
        wp_cache_set($cache_key, 0, 'bbj_v2', 300);
        return 0;
    }

    $num = 0;
    if (function_exists('bbj_v2_get_season_by_id')) {
        $season = bbj_v2_get_season_by_id($season_id);
        $num = (int) ($season['season_number'] ?? 0);
    }

    wp_cache_set($cache_key, $num, 'bbj_v2', 300);
    return $num;
}

/**
 * Category slug for the current season's posts (e.g. "big-brother-26").
 * Returns '' if no current season.
 */
function bbj_v2_current_season_slug(): string
{
    $cache_key = 'homepage_current_season_slug';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if ($cached !== false) {
        return (string) $cached;
    }

    $num = bbj_v2_current_season_number();
    $slug = $num > 0 ? "big-brother-{$num}" : '';

    wp_cache_set($cache_key, $slug, 'bbj_v2', 300);
    return $slug;
}

/**
 * Whether the current season is still active (finale not yet reached).
 * Decision order:
 *   1. Manual override via option `bbj_v2_season_active` (0 | 1). Respected if set.
 *   2. Otherwise: current season's `end_date` in the future → active.
 *   3. Fallback: true (fail open).
 */
function bbj_v2_is_active_season(): bool
{
    $cache_key = 'homepage_active_season';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if ($cached !== false) {
        return (bool) $cached;
    }

    $override = get_option('bbj_v2_season_active', null);
    if ($override !== null && $override !== '') {
        $active = (bool) (int) $override;
        wp_cache_set($cache_key, $active ? 1 : 0, 'bbj_v2', 300);
        return $active;
    }

    $season_id = (int) get_option('bbj_v2_current_season', 0);
    $active = true;
    if ($season_id > 0 && function_exists('bbj_v2_get_season_by_id')) {
        $season = bbj_v2_get_season_by_id($season_id);
        $end    = $season['end_date'] ?? '';
        if ($end !== '') {
            $end_ts = strtotime($end);
            if ($end_ts !== false && $end_ts < time()) {
                $active = false;
            }
        }
    }

    wp_cache_set($cache_key, $active ? 1 : 0, 'bbj_v2', 300);
    return $active;
}
