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
 * @return array<int, array<string, mixed>> Player rows, empty array on failure.
 */
function bbj_v2_get_spoiler_bar(): array
{
    $cache_key = 'spoiler_bar_players';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    if (!function_exists('rest_do_request')) {
        return [];
    }

    $request  = new WP_REST_Request('GET', '/bbjd/v1/spoiler-bar');
    $response = rest_do_request($request);
    if ($response->is_error()) {
        return [];
    }

    $data = $response->get_data();
    $players = is_array($data) && isset($data['players']) && is_array($data['players'])
        ? $data['players']
        : (is_array($data) ? $data : []);

    wp_cache_set($cache_key, $players, 'bbj_v2', 300);
    return $players;
}

/**
 * Cache busters — invalidate when relevant CPTs are saved.
 */
add_action('save_post_bigbrother-players', 'bbj_v2_bust_spoiler_cache');
add_action('save_post_bigbrother-seasons', 'bbj_v2_bust_spoiler_cache');
function bbj_v2_bust_spoiler_cache(): void
{
    wp_cache_delete('spoiler_bar_players', 'bbj_v2');
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
