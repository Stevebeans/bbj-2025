<?php
/**
 * Feed Updates Hub data helpers.
 *
 * All functions prefixed bbj_v2_fuh_*. Powers archive-live-feed-updates.php.
 * Cache group: bbj_v2 (matches the rest of the theme).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Current-season meta: name, abbreviation, day-of-season, week-of-season.
 *
 * Pulls from bbj_v2_get_spoiler_bar()['season']; falls back to the
 * `bbj_v2_current_season` option's wp_bbj_seasons row.
 *
 * @return array{name:string, abbr:string, day:int, week:int, post_id:int}
 */
function bbj_v2_fuh_current_season_meta(): array
{
    $cache_key = 'fuh_current_season_meta';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $sb         = bbj_v2_get_spoiler_bar();
    $season     = is_array($sb['season'] ?? null) ? $sb['season'] : [];
    $name       = (string) ($season['name']         ?? $season['full_name'] ?? '');
    $abbr       = (string) ($season['abbreviation'] ?? $season['abbr']      ?? '');
    $start_date = (string) ($season['start_date']   ?? '');
    $post_id    = (int)    ($season['post_id']      ?? $season['id']        ?? 0);

    if ($post_id === 0 || $name === '' || $start_date === '') {
        $current_season_id = (int) get_option('bbj_v2_current_season', 0);
        if ($current_season_id > 0) {
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT s.full_name, s.abbreviation, s.start_date, p.ID AS post_id
                   FROM {$wpdb->posts} p
              LEFT JOIN {$wpdb->prefix}bbj_seasons s
                     ON (s.post_id = p.ID OR (s.id = p.ID AND s.post_id = 0))
                  WHERE p.ID = %d
                  LIMIT 1",
                $current_season_id
            ), ARRAY_A);
            if ($row) {
                $name       = $name       ?: (string) $row['full_name'];
                $abbr       = $abbr       ?: (string) $row['abbreviation'];
                $start_date = $start_date ?: (string) $row['start_date'];
                $post_id    = $post_id    ?: (int) $row['post_id'];
            }
        }
    }

    if ($abbr === '' && $name !== '' && preg_match('/Big Brother\s+(\d+)/i', $name, $m)) {
        $abbr = 'BB' . $m[1];
    }

    $day = $week = 0;
    if ($start_date && $start_date !== '0000-00-00') {
        try {
            $start = new DateTime($start_date);
            $now   = new DateTime('now');
            $diff  = (int) $start->diff($now)->days;
            if ($now >= $start) {
                $day  = max(1, $diff + 1);
                $week = max(1, (int) ceil($day / 7));
            }
        } catch (Exception $e) { /* leave 0 */ }
    }

    $payload = [
        'name'    => $name,
        'abbr'    => $abbr,
        'day'     => $day,
        'week'    => $week,
        'post_id' => $post_id,
    ];

    wp_cache_set($cache_key, $payload, 'bbj_v2', 300);
    return $payload;
}

/**
 * Counts of published feed updates for: today, this week, this season.
 * Season count uses the current season's start_date as the lower bound;
 * falls back to "all time" if no current-season start known.
 *
 * @return array{today:int, week:int, season:int}
 */
function bbj_v2_fuh_stat_counts(): array
{
    $cache_key = 'fuh_stat_counts';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $season_meta = bbj_v2_fuh_current_season_meta();
    $today_sql   = "SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE post_type='live-feed-updates' AND post_status='publish'
                       AND DATE(post_date) = CURDATE()";
    $week_sql    = "SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE post_type='live-feed-updates' AND post_status='publish'
                       AND post_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

    $season_count = 0;
    if ($season_meta['post_id'] > 0) {
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT s.start_date
               FROM {$wpdb->prefix}bbj_seasons s
              WHERE (s.post_id = %d OR (s.id = %d AND s.post_id = 0))
              LIMIT 1",
            $season_meta['post_id'],
            $season_meta['post_id']
        ), ARRAY_A);
        $start = $row['start_date'] ?? '';
        if ($start && $start !== '0000-00-00') {
            $season_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                  WHERE post_type='live-feed-updates' AND post_status='publish'
                    AND post_date >= %s",
                $start . ' 00:00:00'
            ));
        }
    }

    $payload = [
        'today'  => (int) $wpdb->get_var($today_sql),
        'week'   => (int) $wpdb->get_var($week_sql),
        'season' => $season_count,
    ];

    wp_cache_set($cache_key, $payload, 'bbj_v2', 60);
    return $payload;
}

/**
 * The most-recent published update — placeholder until a "pinned" mechanic
 * exists. Returns the same shape as a thread row.
 *
 * @return ?array{post:WP_Post, type:?array, location:?array, author:string, comments:int, time_12h:string, relative:string}
 */
function bbj_v2_fuh_featured_update(): ?array
{
    $cache_key = 'fuh_featured_update';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $q = new WP_Query([
        'post_type'      => 'live-feed-updates',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'no_found_rows'  => true,
    ]);
    if (empty($q->posts)) {
        wp_cache_set($cache_key, null, 'bbj_v2', 60);
        return null;
    }
    $row = bbj_v2_fuh_format_thread_row($q->posts[0]);
    wp_cache_set($cache_key, $row, 'bbj_v2', 60);
    return $row;
}

/**
 * Paginated thread of published updates — newest first, with type/location
 * terms + author + comment count attached. Skips the featured update when
 * $skip_first === true (so it doesn't render twice).
 *
 * @return array{rows:array, total:int, page:int, per_page:int, has_more:bool}
 */
function bbj_v2_fuh_thread_updates(int $page = 1, int $per_page = 20, bool $skip_first = false): array
{
    $page     = max(1, $page);
    $per_page = max(1, min($per_page, 50));

    $offset = ($page - 1) * $per_page + ($skip_first ? 1 : 0);

    $q = new WP_Query([
        'post_type'      => 'live-feed-updates',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'offset'         => $offset,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => false,
    ]);

    $rows = [];
    foreach ($q->posts as $post) {
        $rows[] = bbj_v2_fuh_format_thread_row($post);
    }

    $total = (int) $q->found_posts;
    return [
        'rows'     => $rows,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $per_page,
        'has_more' => ($offset + count($rows)) < $total,
    ];
}

/**
 * Shape one update post for thread rendering.
 */
function bbj_v2_fuh_format_thread_row(WP_Post $post): array
{
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

    $author = (string) get_the_author_meta('display_name', $post->post_author);
    return [
        'post'     => $post,
        'type'     => $type,
        'location' => $loc,
        'author'   => $author !== '' ? $author : 'BBJ',
        'comments' => (int) get_comments_number($post->ID),
        'time_12h' => get_the_date('g:i A', $post),
        'relative' => human_time_diff(get_post_time('U', true, $post), current_time('timestamp', true)) . ' ago',
        'date_key' => get_the_date('Y-m-d', $post),
        'date_label_short' => get_the_date('M j', $post),
        'date_label_long'  => get_the_date('l · M j', $post),
    ];
}

/**
 * Today's update count grouped by update_type term — feeds the toolbar tabs.
 *
 * @return array<int, array{name:string, slug:string, count:int}>
 */
function bbj_v2_fuh_category_counts(): array
{
    $cache_key = 'fuh_category_counts';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT t.name, t.slug, COUNT(DISTINCT p.ID) AS c
           FROM {$wpdb->posts} p
           INNER JOIN {$wpdb->term_relationships}    tr ON tr.object_id        = p.ID
           INNER JOIN {$wpdb->term_taxonomy}         tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
           INNER JOIN {$wpdb->terms}                  t ON t.term_id           = tt.term_id
          WHERE p.post_type='live-feed-updates' AND p.post_status='publish'
            AND tt.taxonomy='update_type'
            AND DATE(p.post_date) = CURDATE()
          GROUP BY t.term_id
          ORDER BY c DESC",
        ARRAY_A
    );
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'name'  => (string) $r['name'],
            'slug'  => (string) $r['slug'],
            'count' => (int)    $r['c'],
        ];
    }

    wp_cache_set($cache_key, $out, 'bbj_v2', 60);
    return $out;
}

/**
 * Hot This Week — top blog posts (post_type=post) by comment count over 7d.
 *
 * @return array<int, array{post_id:int, title:string, permalink:string, comments:int, relative:string}>
 */
function bbj_v2_fuh_hot_posts(int $limit = 4): array
{
    $cache_key = 'fuh_hot_posts_' . $limit;
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_title, comment_count, post_date
           FROM {$wpdb->posts}
          WHERE post_type='post' AND post_status='publish'
            AND post_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          ORDER BY comment_count DESC, post_date DESC
          LIMIT %d",
        $limit
    ), ARRAY_A);

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'post_id'   => (int) $r['ID'],
            'title'     => (string) $r['post_title'],
            'permalink' => get_permalink((int) $r['ID']),
            'comments'  => (int) $r['comment_count'],
            'relative'  => human_time_diff(strtotime($r['post_date']), current_time('timestamp')) . ' ago',
        ];
    }

    wp_cache_set($cache_key, $out, 'bbj_v2', 600);
    return $out;
}

/**
 * Sidebar Live Right Now: HoH / Veto / on Block / next eviction window.
 * Derived from bbj_v2_get_spoiler_bar() players. Returns an array shape ready
 * for the template (string values, "—" placeholder when missing).
 *
 * @return array{hoh:string, veto:string, block:string, next_evict:string, watch_url:string}
 */
function bbj_v2_fuh_live_status(): array
{
    $cache_key = 'fuh_live_status';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $sb      = bbj_v2_get_spoiler_bar();
    $players = is_array($sb['players'] ?? null) ? $sb['players'] : [];

    $hoh = $veto = '';
    $blocked = [];
    foreach ($players as $p) {
        $status = strtolower((string) ($p['status'] ?? ''));
        $name   = (string) ($p['first_name'] ?? $p['name'] ?? '');
        if ($status === 'hoh' && $hoh === '')   $hoh = $name;
        if (in_array($status, ['pov','veto'], true) && $veto === '') $veto = $name;
        if (in_array($status, ['nominated','nom','on_block','block'], true)) $blocked[] = $name;
    }

    $payload = [
        'hoh'        => $hoh ?: '—',
        'veto'       => $veto ?: '—',
        'block'      => empty($blocked) ? '—' : implode(' · ', array_slice($blocked, 0, 3)),
        'next_evict' => 'Thursday',
        'watch_url'  => 'https://www.paramountplus.com/shows/big-brother/',
    ];

    wp_cache_set($cache_key, $payload, 'bbj_v2', 300);
    return $payload;
}
