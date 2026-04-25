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
 * Stub — full implementation lands in Task 2.
 * Always returns at least post_id + title so Task 1 can render.
 */
function bbj_v2_season_profile_data(int $post_id): array
{
    return [
        'post_id' => $post_id,
        'title'   => get_the_title($post_id),
    ];
}
