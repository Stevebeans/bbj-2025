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

/**
 * Hourly feed-update counts for the last `$hours` hours.
 * Returns 8 entries (oldest first) — missing hours filled with 0.
 *
 * @return array<int, array{hour: int, label: string, count: int}>
 */
function bbj_v2_homepage_house_pulse(int $hours = 8): array
{
    $cache_key = 'homepage_pulse_' . $hours;
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT HOUR(post_date) AS h, COUNT(*) AS c
         FROM {$wpdb->posts}
         WHERE post_type = 'live-feed-updates'
           AND post_status = 'publish'
           AND post_date >= DATE_SUB(NOW(), INTERVAL %d HOUR)
         GROUP BY HOUR(post_date)",
        $hours
    ), ARRAY_A);

    $counts = [];
    foreach ($rows as $r) {
        $counts[(int) $r['h']] = (int) $r['c'];
    }

    $out = [];
    for ($i = $hours - 1; $i >= 0; $i--) {
        $ts   = strtotime("-{$i} hour");
        $hour = (int) date('H', $ts);
        $label = date('ga', $ts);
        $out[] = [
            'hour'  => $hour,
            'label' => $label,
            'count' => $counts[$hour] ?? 0,
        ];
    }

    wp_cache_set($cache_key, $out, 'bbj_v2', 300);
    return $out;
}

/**
 * Season stats: top 3 HoH, PoV, Nominee counts for the current season.
 *
 * @return array{hoh: array, pov: array, noms: array, total_weeks: int}
 */
function bbj_v2_homepage_season_stats(): array
{
    $cache_key = 'homepage_season_stats';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $season_id = (int) get_option('bbj_v2_current_season', 0);
    $empty = ['hoh' => [], 'pov' => [], 'noms' => [], 'total_weeks' => 0];
    if ($season_id <= 0) {
        wp_cache_set($cache_key, $empty, 'bbj_v2', 300);
        return $empty;
    }

    global $wpdb;
    $link_table   = defined('BBJ_V2_TABLE_LINKS')   ? BBJ_V2_TABLE_LINKS   : ($wpdb->prefix . 'bbj_v2_player_season');
    $player_table = defined('BBJ_V2_TABLE_PLAYERS') ? BBJ_V2_TABLE_PLAYERS : ($wpdb->prefix . 'bbj_players');

    $fetch = function (string $col) use ($wpdb, $link_table, $player_table, $season_id): array {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.first_name, p.last_name, p.official_nickname, l.{$col} AS count
             FROM {$link_table} l
             INNER JOIN {$player_table} p ON p.id = l.bbj_player
             WHERE l.bbj_season = %d AND l.{$col} > 0
             ORDER BY l.{$col} DESC, p.first_name ASC
             LIMIT 3",
            $season_id
        ), ARRAY_A) ?: [];
    };

    $out = [
        'hoh'          => $fetch('bbj_total_hoh'),
        'pov'          => $fetch('bbj_total_pov'),
        'noms'         => $fetch('bbj_total_nom'),
        'total_weeks'  => (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(bbj_total_hoh + bbj_total_pov), 0)
             FROM {$link_table} WHERE bbj_season = %d",
            $season_id
        )),
    ];

    wp_cache_set($cache_key, $out, 'bbj_v2', 300);
    return $out;
}

/**
 * Last N approved comments, site-wide.
 *
 * @return WP_Comment[]
 */
function bbj_v2_homepage_recent_comments(int $limit = 5): array
{
    $cache_key = 'homepage_recent_comments_' . $limit;
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $comments = get_comments([
        'number'  => $limit,
        'status'  => 'approve',
        'type'    => 'comment',
        'orderby' => 'comment_date_gmt',
        'order'   => 'DESC',
    ]);

    wp_cache_set($cache_key, $comments, 'bbj_v2', 60);
    return $comments;
}

/**
 * Status strip payload: day number, percent elapsed, next CBS show string.
 *
 * @return array{
 *   active: bool,
 *   season_number: int,
 *   day_number: ?int,
 *   percent_elapsed: ?int,
 *   next_show: ?string,
 *   premiere_label: ?string,
 * }
 */
function bbj_v2_homepage_status(): array
{
    $cache_key = 'homepage_status';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $out = [
        'active'          => bbj_v2_is_active_season(),
        'season_number'   => bbj_v2_current_season_number(),
        'day_number'      => null,
        'percent_elapsed' => null,
        'next_show'       => null,
        'premiere_label'  => null,
    ];

    $season_id = (int) get_option('bbj_v2_current_season', 0);
    if ($season_id > 0 && function_exists('bbj_v2_get_season_by_id')) {
        $season = bbj_v2_get_season_by_id($season_id);
        $start  = !empty($season['start_date']) ? strtotime((string) $season['start_date']) : false;
        $end    = !empty($season['end_date'])   ? strtotime((string) $season['end_date'])   : false;

        if ($start !== false) {
            $now = time();
            $out['day_number'] = max(1, (int) floor(($now - $start) / DAY_IN_SECONDS) + 1);
            if ($end !== false && $end > $start) {
                $pct = (int) round((($now - $start) / ($end - $start)) * 100);
                $out['percent_elapsed'] = max(0, min(100, $pct));
            }
        }
    }

    if ($out['active']) {
        $override = $season_id > 0
            ? get_post_meta($season_id, 'bbj_next_show_override', true)
            : '';
        $out['next_show'] = bbj_v2_format_next_cbs_show((string) $override);
    } else {
        $premiere = get_option('bbj_next_season_premiere', '');
        $out['premiere_label'] = $premiere ? date('M j', (int) strtotime((string) $premiere)) : null;
    }

    wp_cache_set($cache_key, $out, 'bbj_v2', 60);
    return $out;
}

/**
 * Format the next CBS airing label ("Tonight at 8pm ET / 5pm PT" etc.).
 * If $override (a date string) is a future datetime, it wins.
 */
function bbj_v2_format_next_cbs_show(string $override = ''): string
{
    $et = new DateTimeZone('America/New_York');
    $pt = new DateTimeZone('America/Los_Angeles');
    $now_et = new DateTimeImmutable('now', $et);

    $target_et = null;
    if ($override !== '') {
        try {
            $candidate = new DateTimeImmutable($override, $et);
            if ($candidate > $now_et) {
                $target_et = $candidate;
            }
        } catch (Exception $_) {}
    }

    if ($target_et === null) {
        $days = [0, 3, 4]; // Sun, Wed, Thu
        $best = null;
        foreach ($days as $d) {
            $candidate = $now_et->setTime(20, 0);
            while ((int) $candidate->format('w') !== $d || $candidate <= $now_et) {
                $candidate = $candidate->modify('+1 day')->setTime(20, 0);
            }
            if ($best === null || $candidate < $best) {
                $best = $candidate;
            }
        }
        $target_et = $best;
    }

    if ($target_et === null) return '';
    $target_pt = $target_et->setTimezone($pt);

    $diff_days = (int) $now_et->diff($target_et)->format('%a');
    $time_part = $target_et->format('ga') . ' ET / ' . $target_pt->format('ga') . ' PT';

    if ($target_et->format('Y-m-d') === $now_et->format('Y-m-d')) {
        return 'Tonight at ' . $time_part;
    }
    if ($target_et->format('Y-m-d') === $now_et->modify('+1 day')->format('Y-m-d')) {
        return 'Tomorrow at ' . $time_part;
    }
    if ($diff_days <= 6) {
        return $target_et->format('D') . ' at ' . $time_part;
    }
    return $target_et->format('M j') . ' at ' . $time_part;
}

/**
 * Cache invalidation — bust homepage keys when the underlying data moves.
 */
add_action('save_post_post',                       'bbj_v2_homepage_bust_posts');
add_action('save_post_live-feed-updates',          'bbj_v2_homepage_bust_feeds');
add_action('save_post_bigbrother-seasons',         'bbj_v2_homepage_bust_seasons');
add_action('update_option_bbj_v2_current_season',  'bbj_v2_homepage_bust_all');
add_action('update_option_bbj_v2_season_active',   'bbj_v2_homepage_bust_all');
add_action('comment_post',                         'bbj_v2_homepage_bust_comments', 10, 3);
add_action('wp_set_comment_status',                'bbj_v2_homepage_bust_comments');
add_action('created_update_type',                  'bbj_v2_homepage_bust_feeds');
add_action('edited_update_type',                   'bbj_v2_homepage_bust_feeds');
add_action('created_update_location',              'bbj_v2_homepage_bust_feeds');
add_action('edited_update_location',               'bbj_v2_homepage_bust_feeds');

function bbj_v2_homepage_bust_posts(): void
{
    wp_cache_delete('homepage_more_spoilers_' . md5(serialize([])), 'bbj_v2');
    wp_cache_delete('homepage_bb_stories_'    . md5(serialize([])), 'bbj_v2');
}

function bbj_v2_homepage_bust_feeds(): void
{
    wp_cache_delete('homepage_feeds_15', 'bbj_v2');
    wp_cache_delete('homepage_pulse_8',  'bbj_v2');
}

function bbj_v2_homepage_bust_seasons(): void
{
    wp_cache_delete('homepage_status',                 'bbj_v2');
    wp_cache_delete('homepage_season_stats',           'bbj_v2');
    wp_cache_delete('homepage_active_season',          'bbj_v2');
    wp_cache_delete('homepage_current_season_number',  'bbj_v2');
    wp_cache_delete('homepage_current_season_slug',    'bbj_v2');
}

function bbj_v2_homepage_bust_all(): void
{
    bbj_v2_homepage_bust_posts();
    bbj_v2_homepage_bust_feeds();
    bbj_v2_homepage_bust_seasons();
}

function bbj_v2_homepage_bust_comments(): void
{
    wp_cache_delete('homepage_recent_comments_5', 'bbj_v2');
}
