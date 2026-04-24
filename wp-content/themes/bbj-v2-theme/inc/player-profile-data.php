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
