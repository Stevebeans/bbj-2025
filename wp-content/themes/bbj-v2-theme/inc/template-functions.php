<?php
/**
 * Template helper functions used across the theme.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether ads should render on the current request.
 * Honors per-page kill-switch (login/register/privacy) and the bigbrotherjunkies-data role rules.
 */
function bbj_v2_should_show_ads(): bool
{
    if (is_page(['login', 'register', 'privacy', 'privacy-policy', 'terms', 'contact'])) {
        return false;
    }
    if (function_exists('is_login') && is_login()) {
        return false;
    }
    // Delegate remaining decisions to the plugin's role-based filters (applied inside bbjd_ad()).
    return (bool) apply_filters('bbj_v2_should_show_ads', true);
}

/**
 * Current Big Brother time (Pacific) for the header utility strip.
 */
function bbj_v2_bb_time(): string
{
    try {
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Los_Angeles'));
        return $now->format('D, M jS g:i A');
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Cached fetch of spoiler bar data from the bigbrotherjunkies-data plugin.
 *
 * @return array{season: array<string, mixed>, players: array<int, array<string, mixed>>}
 */
function bbj_v2_get_spoiler_bar(): array
{
    $cache_key = 'spoiler_bar_v2';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $empty = ['season' => [], 'players' => []];
    if (!function_exists('rest_do_request')) {
        return $empty;
    }

    $request  = new WP_REST_Request('GET', '/bbjd/v1/spoiler-bar');
    $response = rest_do_request($request);
    if ($response->is_error()) {
        return $empty;
    }

    $data = $response->get_data();
    $payload = [
        'season'  => (is_array($data) && isset($data['season']) && is_array($data['season']))  ? $data['season']  : [],
        'players' => (is_array($data) && isset($data['players']) && is_array($data['players'])) ? $data['players'] : [],
    ];

    wp_cache_set($cache_key, $payload, 'bbj_v2', 300);
    return $payload;
}

/**
 * Cache busters — invalidate when relevant CPTs are saved.
 */
add_action('save_post_bigbrother-players', 'bbj_v2_bust_spoiler_cache');
add_action('save_post_bigbrother-seasons', 'bbj_v2_bust_spoiler_cache');
function bbj_v2_bust_spoiler_cache(): void
{
    wp_cache_delete('spoiler_bar_v2', 'bbj_v2');
    wp_cache_delete('spoiler_bar_players', 'bbj_v2'); // old key from earlier cache shape
}

/**
 * Map a status string from the plugin to a spoiler-bar pill CSS class.
 */
function bbj_v2_status_class(string $status): string
{
    $map = [
        'hoh'       => 'spoilerbar-hoh',
        'pov'       => 'spoilerbar-pov',
        'active'    => 'spoilerbar-active',
        'nominated' => 'spoilerbar-nom',
        'safe'      => 'spoilerbar-safe',
        'evicted'   => 'spoilerbar-evicted',
        'jury'      => 'spoilerbar-jury',
        'winner'    => 'spoilerbar-winner',
        'runner_up' => 'spoilerbar-runnerup',
        'runner-up' => 'spoilerbar-runnerup',
        'runnerup'  => 'spoilerbar-runnerup',
        '2nd'       => 'spoilerbar-runnerup',
        'afp'       => 'spoilerbar-afp',
        'have-not'  => 'spoilerbar-havenot',
        'havenot'   => 'spoilerbar-havenot',
    ];
    $key = strtolower(trim($status));
    return $map[$key] ?? 'spoilerbar-active';
}

/**
 * Image filter class for a status (greyscale treatments for evicted/jury).
 */
function bbj_v2_status_img_class(string $status): string
{
    $key = strtolower(trim($status));
    if ($key === 'evicted') return 'spoilerbar-evicted-img';
    if ($key === 'jury')    return 'spoilerbar-jury-img';
    return '';
}

/**
 * Append a `link_class` arg (passed to wp_nav_menu) onto every <a> it renders.
 */
add_filter('nav_menu_link_attributes', function (array $atts, $item, $args) {
    if (!empty($args->link_class)) {
        $atts['class'] = trim(($atts['class'] ?? '') . ' ' . $args->link_class);
    }
    return $atts;
}, 10, 3);

/**
 * Rewrite menu item URLs that were hardcoded to the production host. Runs at
 * wp_setup_nav_menu_item so the rewrite happens BEFORE WP computes which item
 * is the current-menu-item (that detection in _wp_menu_item_classes_by_context
 * compares the stored URL to the current request URL). Rebasing at render time
 * would fix the rendered href but leave the current-page class on the wrong item.
 */
add_filter('wp_setup_nav_menu_item', function ($item) {
    if (isset($item->url)) {
        $item->url = bbj_v2_rebase_prod_url((string) $item->url);
    }
    return $item;
});

/**
 * If a URL was saved with the canonical production host, swap that host for the
 * current site's home URL. Keeps menu entries portable across environments.
 */
function bbj_v2_rebase_prod_url(string $url): string
{
    return preg_replace(
        '#^https?://(www\.)?bigbrotherjunkies\.com#i',
        rtrim(home_url(), '/'),
        $url
    ) ?: $url;
}

/**
 * Comp Types: save (create or update) handler.
 * Posts to admin-post.php?action=bbj_v2_save_comp_type.
 */
add_action('admin_post_bbj_v2_save_comp_type', 'bbj_v2_handle_save_comp_type');
function bbj_v2_handle_save_comp_type(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Forbidden', ['response' => 403]);
    }
    check_admin_referer('bbj_v2_save_comp_type');

    global $wpdb;
    $id   = isset($_POST['id'])   ? absint($_POST['id'])         : 0;
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $slug_raw = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
    $slug = $slug_raw !== '' ? sanitize_title($slug_raw) : sanitize_title($name);
    $sort = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;

    $redirect = add_query_arg('tab', 'comp-types', home_url('/admin/'));

    if ($name === '' || $slug === '') {
        wp_safe_redirect(add_query_arg('bbj_msg', 'name_required', $redirect));
        exit;
    }

    $data = [
        'name'       => $name,
        'slug'       => $slug,
        'sort_order' => $sort,
    ];

    if ($id > 0) {
        $wpdb->update("{$wpdb->prefix}bbj_comp_types", $data, ['id' => $id]);
    } else {
        $wpdb->insert("{$wpdb->prefix}bbj_comp_types", $data + ['is_archived' => 0]);
    }

    do_action('bbj_v2_comp_types_changed');
    wp_safe_redirect(add_query_arg('bbj_msg', 'saved', $redirect));
    exit;
}

/**
 * Comp Types: archive / unarchive toggle.
 */
add_action('admin_post_bbj_v2_toggle_comp_type', 'bbj_v2_handle_toggle_comp_type');
function bbj_v2_handle_toggle_comp_type(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Forbidden', ['response' => 403]);
    }
    check_admin_referer('bbj_v2_toggle_comp_type');

    global $wpdb;
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $redirect = add_query_arg('tab', 'comp-types', home_url('/admin/'));

    if ($id <= 0) {
        wp_safe_redirect($redirect);
        exit;
    }

    $current = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT is_archived FROM {$wpdb->prefix}bbj_comp_types WHERE id = %d",
        $id
    ));
    $wpdb->update(
        "{$wpdb->prefix}bbj_comp_types",
        ['is_archived' => $current ? 0 : 1],
        ['id' => $id]
    );

    do_action('bbj_v2_comp_types_changed');
    wp_safe_redirect(add_query_arg('bbj_msg', 'toggled', $redirect));
    exit;
}

/**
 * Cache buster for comp-types reads.
 */
add_action('bbj_v2_comp_types_changed', 'bbj_v2_bust_comp_types_cache');
function bbj_v2_bust_comp_types_cache(): void
{
    wp_cache_delete('bbj_v2_comp_types_active', 'bbj_v2');
    wp_cache_delete('bbj_v2_comp_types_all',    'bbj_v2');
}

/**
 * Cache busters for week comp and week player mutations.
 * Wired to the actions fired by bbj_v2_save_week_comp, bbj_v2_delete_week_comp,
 * and bbj_v2_save_week_player in inc/weekly-tracker-data.php.
 */
add_action('bbj_v2_week_comp_saved',   'bbj_v2_bust_weekly_caches', 10, 2);
add_action('bbj_v2_week_player_saved', 'bbj_v2_bust_weekly_caches', 10, 2);
function bbj_v2_bust_weekly_caches(int $week_id, int $player_id): void
{
    global $wpdb;
    $season_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT season_id FROM {$wpdb->prefix}bbj_weeks WHERE id = %d",
        $week_id
    ));

    wp_cache_delete('bbj_v2_season_weeks_' . $season_id, 'bbj_v2');
    wp_cache_delete('bbj_v2_player_career_totals_' . $player_id, 'bbj_v2');
    wp_cache_delete('bbj_v2_player_weeks_' . $player_id . '_' . $season_id, 'bbj_v2');
    wp_cache_delete('bbj_v2_archive_all_players', 'bbj_v2');
    wp_cache_delete('season_profile_data_' . $season_id, 'bbj_v2');
    wp_cache_delete('season_profile_week_summaries_' . $season_id, 'bbj_v2');
}

// ---------------------------------------------------------------------------
// Admin-post handlers: Add Week + Save Week
// ---------------------------------------------------------------------------

add_action('admin_post_bbj_v2_add_week', 'bbj_v2_handle_add_week');
function bbj_v2_handle_add_week(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Forbidden', ['response' => 403]);
    }
    check_admin_referer('bbj_v2_add_week');

    global $wpdb;
    $season_post_id = isset($_POST['season_post_id']) ? absint($_POST['season_post_id']) : 0;
    if ($season_post_id <= 0) {
        wp_safe_redirect(home_url('/admin/?tab=seasons'));
        exit;
    }

    // Find the highest existing week_num for this season
    $next_num = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(MAX(week_num), 0) + 1 FROM {$wpdb->prefix}bbj_weeks WHERE season_id = %d",
        $season_post_id
    ));

    // Today as default
    $today = current_time('Y-m-d');
    $wpdb->insert("{$wpdb->prefix}bbj_weeks", [
        'season_id'  => $season_post_id,
        'week_num'   => $next_num,
        'start_date' => $today,
        'end_date'   => $today,
    ]);
    $new_week_id = (int) $wpdb->insert_id;

    // Seed wp_bbj_weeks_players from previous week's active+non-evicted players,
    // or from full cast if week 1.
    if ($next_num === 1) {
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}bbj_weeks_players (week_id, season_id, player_id, active)
             SELECT %d, %d, j.bbj_player, 1
               FROM {$wpdb->prefix}bbj_v2_player_season j
              WHERE j.bbj_season = %d",
            $new_week_id, $season_post_id, $season_post_id
        ));
    } else {
        $prev_week_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}bbj_weeks
              WHERE season_id = %d AND week_num = %d
              LIMIT 1",
            $season_post_id, $next_num - 1
        ));
        if ($prev_week_id > 0) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}bbj_weeks_players (week_id, season_id, player_id, active)
                 SELECT %d, %d, prev.player_id, 1
                   FROM {$wpdb->prefix}bbj_weeks_players prev
                  WHERE prev.week_id = %d AND prev.evicted = 0",
                $new_week_id, $season_post_id, $prev_week_id
            ));
        }
    }

    wp_cache_delete('bbj_v2_season_weeks_' . $season_post_id, 'bbj_v2');

    // Bust per-player week caches for everyone seeded into the new week —
    // otherwise a profile viewed when zero weeks existed keeps the empty []
    // cached for an hour.
    $seeded_player_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT player_id FROM {$wpdb->prefix}bbj_weeks_players WHERE week_id = %d",
        $new_week_id
    )) ?: [];
    foreach ($seeded_player_ids as $pid) {
        wp_cache_delete('bbj_v2_player_weeks_' . (int) $pid . '_' . $season_post_id, 'bbj_v2');
        wp_cache_delete('bbj_v2_player_career_totals_' . (int) $pid, 'bbj_v2');
    }

    $redirect = add_query_arg([
        'tab'     => 'seasons',
        'edit'    => $season_post_id,
        'week'    => $new_week_id,
        'bbj_msg' => 'week_added',
    ], home_url('/admin/'));
    wp_safe_redirect($redirect . '#weeks');
    exit;
}

add_action('admin_post_bbj_v2_save_week', 'bbj_v2_handle_save_week');
function bbj_v2_handle_save_week(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Forbidden', ['response' => 403]);
    }
    check_admin_referer('bbj_v2_save_week');

    global $wpdb;
    $season_post_id = isset($_POST['season_post_id']) ? absint($_POST['season_post_id']) : 0;
    $week_id        = isset($_POST['week_id'])        ? absint($_POST['week_id'])        : 0;
    if ($season_post_id <= 0 || $week_id <= 0) {
        wp_safe_redirect(home_url('/admin/?tab=seasons'));
        exit;
    }

    $wpdb->update(
        "{$wpdb->prefix}bbj_weeks",
        [
            'week_num'   => isset($_POST['week_num']) ? (int) $_POST['week_num'] : 1,
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date'   => sanitize_text_field($_POST['end_date'] ?? ''),
            'summary'    => wp_kses_post($_POST['summary'] ?? ''),
        ],
        ['id' => $week_id]
    );

    $rows = isset($_POST['rows']) && is_array($_POST['rows']) ? $_POST['rows'] : [];
    foreach ($rows as $row) {
        $player_id = isset($row['player_id']) ? (int) $row['player_id'] : 0;
        if ($player_id <= 0) {
            continue;
        }

        $saved_by = isset($row['saved_by_player_id']) && $row['saved_by_player_id'] !== ''
            ? (int) $row['saved_by_player_id']
            : null;

        bbj_v2_save_week_player([
            'week_id'            => $week_id,
            'player_id'          => $player_id,
            'season_id'          => $season_post_id,
            'nom'                => isset($row['nom'])     ? 1 : 0,
            'evicted'            => isset($row['evicted']) ? 1 : 0,
            'voted_for'          => isset($row['voted_for']) ? (int) $row['voted_for'] : 0,
            'saved_by_player_id' => $saved_by,
            'active'             => isset($row['evicted']) ? 0 : 1,
        ]);

        // Sync comp wins: insert newly checked, delete existing-but-now-unchecked
        $checked = isset($row['comps']) && is_array($row['comps'])
            ? array_map('intval', $row['comps'])
            : [];

        // Misc pill resolves to either the chosen subtype or the generic 'misc' comp type.
        if (!empty($row['misc'])) {
            $subtype_id = isset($row['misc_subtype']) ? (int) $row['misc_subtype'] : 0;
            if ($subtype_id > 0) {
                $checked[] = $subtype_id;
            } else {
                $misc_id = bbj_v2_comp_type_id_by_slug('misc');
                if ($misc_id > 0) {
                    $checked[] = $misc_id;
                }
            }
        }
        $checked = array_values(array_unique(array_filter($checked, static fn($id) => $id > 0)));

        $existing = $wpdb->get_results($wpdb->prepare(
            "SELECT id, comp_type_id FROM {$wpdb->prefix}bbj_week_comps
              WHERE week_id = %d AND player_id = %d",
            $week_id, $player_id
        ), ARRAY_A) ?: [];
        $existing_type_ids = array_map('intval', array_column($existing, 'comp_type_id'));

        foreach ($checked as $type_id) {
            if (!in_array($type_id, $existing_type_ids, true)) {
                bbj_v2_save_week_comp($week_id, $player_id, $type_id);
            }
        }
        foreach ($existing as $row_e) {
            if (!in_array((int) $row_e['comp_type_id'], $checked, true)) {
                bbj_v2_delete_week_comp((int) $row_e['id']);
            }
        }
    }

    $redirect = add_query_arg([
        'tab'     => 'seasons',
        'edit'    => $season_post_id,
        'week'    => $week_id,
        'bbj_msg' => 'week_saved',
    ], home_url('/admin/'));
    wp_safe_redirect($redirect . '#weeks');
    exit;
}
