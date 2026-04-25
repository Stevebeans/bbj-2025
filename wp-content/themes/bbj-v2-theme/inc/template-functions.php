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
}
