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
