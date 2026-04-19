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

/**
 * Return the hero post for the homepage.
 * Priority: most recent post with `_is_hero_post` meta = "1", fallback to
 * most recent post in the current-season category, final fallback to most
 * recent post of any category.
 *
 * @return WP_Post|null
 */
function bbj_v2_homepage_hero_post(): ?WP_Post
{
    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_key'       => '_is_hero_post',
        'meta_value'     => '1',
        'no_found_rows'  => true,
    ]);
    if ($q->have_posts()) {
        return $q->posts[0];
    }

    $slug = bbj_v2_current_season_slug();
    if ($slug !== '') {
        $q = new WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'category_name'  => $slug,
            'no_found_rows'  => true,
        ]);
        if ($q->have_posts()) {
            return $q->posts[0];
        }
    }

    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'no_found_rows'  => true,
    ]);
    return $q->have_posts() ? $q->posts[0] : null;
}

/**
 * 3 most recent posts in (current season) AND `spoilers` categories,
 * excluding a supplied list of post IDs.
 *
 * @param int[] $exclude_ids
 * @return WP_Post[]
 */
function bbj_v2_homepage_more_spoilers(array $exclude_ids = []): array
{
    $cache_key = 'homepage_more_spoilers_' . md5(serialize($exclude_ids));
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $season_term = get_term_by('slug', bbj_v2_current_season_slug(), 'category');
    $spoilers    = get_term_by('slug', 'spoilers', 'category');
    if (!$season_term || !$spoilers) {
        wp_cache_set($cache_key, [], 'bbj_v2', 300);
        return [];
    }

    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 3,
        'category__and'  => [(int) $season_term->term_id, (int) $spoilers->term_id],
        'post__not_in'   => array_map('intval', $exclude_ids),
        'no_found_rows'  => true,
    ]);

    wp_cache_set($cache_key, $q->posts, 'bbj_v2', 300);
    return $q->posts;
}

/**
 * 9 most recent posts in the current-season category, excluding given IDs.
 *
 * @param int[] $exclude_ids
 * @return WP_Post[]
 */
function bbj_v2_homepage_bb_stories(array $exclude_ids = []): array
{
    $cache_key = 'homepage_bb_stories_' . md5(serialize($exclude_ids));
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $slug = bbj_v2_current_season_slug();
    if ($slug === '') {
        wp_cache_set($cache_key, [], 'bbj_v2', 300);
        return [];
    }

    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 9,
        'category_name'  => $slug,
        'post__not_in'   => array_map('intval', $exclude_ids),
        'no_found_rows'  => true,
    ]);

    wp_cache_set($cache_key, $q->posts, 'bbj_v2', 300);
    return $q->posts;
}

/**
 * 15 most recent live-feed-updates, each decorated with its
 * update_type + update_location term names/slugs.
 *
 * @return array<int, array{post: WP_Post, type: ?array, location: ?array}>
 */
function bbj_v2_homepage_latest_feeds(int $limit = 15): array
{
    $cache_key = 'homepage_feeds_' . $limit;
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $q = new WP_Query([
        'post_type'      => 'live-feed-updates',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'no_found_rows'  => true,
    ]);

    $out = [];
    foreach ($q->posts as $post) {
        $type_terms = get_the_terms($post->ID, 'update_type');
        $loc_terms  = get_the_terms($post->ID, 'update_location');
        $type = (is_array($type_terms) && !empty($type_terms)) ? [
            'name' => $type_terms[0]->name,
            'slug' => $type_terms[0]->slug,
        ] : null;
        $loc = (is_array($loc_terms) && !empty($loc_terms)) ? [
            'name' => $loc_terms[0]->name,
            'slug' => $loc_terms[0]->slug,
        ] : null;
        $out[] = ['post' => $post, 'type' => $type, 'location' => $loc];
    }

    wp_cache_set($cache_key, $out, 'bbj_v2', 60);
    return $out;
}
