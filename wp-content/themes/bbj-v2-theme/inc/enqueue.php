<?php
/**
 * Frontend asset enqueue — fonts, compiled Tailwind, vanilla JS.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'bbj_v2_enqueue_assets');
function bbj_v2_enqueue_assets(): void
{
    // filemtime-based versions — rsync bumps mtime per deploy so caches bust automatically.
    wp_enqueue_style(
        'bbj-v2-style',
        BBJ_V2_THEME_URL . '/build/style.css',
        [],
        bbj_v2_asset_ver('/build/style.css')
    );

    wp_enqueue_script(
        'bbj-v2-main',
        BBJ_V2_THEME_URL . '/src/js/main.js',
        [],
        bbj_v2_asset_ver('/src/js/main.js'),
        ['strategy' => 'defer', 'in_footer' => true]
    );

    wp_enqueue_script(
        'bbj-v2-dark-mode',
        BBJ_V2_THEME_URL . '/src/js/dark-mode.js',
        [],
        bbj_v2_asset_ver('/src/js/dark-mode.js'),
        ['strategy' => 'defer', 'in_footer' => true]
    );

    wp_enqueue_script(
        'bbj-v2-mobile-nav',
        BBJ_V2_THEME_URL . '/src/js/mobile-nav.js',
        [],
        bbj_v2_asset_ver('/src/js/mobile-nav.js'),
        ['strategy' => 'defer', 'in_footer' => true]
    );

    wp_enqueue_script(
        'bbj-v2-spoiler-bar',
        BBJ_V2_THEME_URL . '/src/js/spoiler-bar.js',
        [],
        bbj_v2_asset_ver('/src/js/spoiler-bar.js'),
        ['strategy' => 'defer', 'in_footer' => true]
    );

    wp_enqueue_script(
        'bbj-v2-search-modal',
        BBJ_V2_THEME_URL . '/src/js/search-modal.js',
        [],
        bbj_v2_asset_ver('/src/js/search-modal.js'),
        ['strategy' => 'defer', 'in_footer' => true]
    );

    // Auth assets — anonymous users only.
    if (!is_user_logged_in()) {
        wp_enqueue_script(
            'bbj-v2-auth-modal',
            BBJ_V2_THEME_URL . '/src/js/auth-modal.js',
            [],
            bbj_v2_asset_ver('/src/js/auth-modal.js'),
            true
        );
        wp_localize_script('bbj-v2-auth-modal', 'BBJAuth', [
            'api'       => esc_url_raw(rest_url('bbjd/v1/')),
            'nonce'     => wp_create_nonce('bbj_auth'),
            'debug'     => defined('WP_DEBUG') && WP_DEBUG,
            'homeUrl'   => esc_url_raw(home_url('/')),
            'recaptcha' => (string) get_option('bbj_recaptcha_site_key', ''),
        ]);
        wp_enqueue_script(
            'bbj-v2-auth-forms',
            BBJ_V2_THEME_URL . '/src/js/auth-forms.js',
            ['bbj-v2-auth-modal'],
            bbj_v2_asset_ver('/src/js/auth-forms.js'),
            true
        );
        wp_enqueue_script(
            'bbj-v2-auth-google',
            BBJ_V2_THEME_URL . '/src/js/auth-google.js',
            ['bbj-v2-auth-modal'],
            bbj_v2_asset_ver('/src/js/auth-google.js'),
            true
        );
    }

    // Admin Feed Updates pane — load JS only on that tab.
    if (is_page('admin') && get_query_var('tab') === 'feed-updates') {
        wp_enqueue_script(
            'bbj-v2-admin-feed-updates',
            BBJ_V2_THEME_URL . '/src/js/admin-feed-updates.js',
            [],
            bbj_v2_asset_ver('/src/js/admin-feed-updates.js'),
            ['strategy' => 'defer', 'in_footer' => true]
        );
    }

    // Single player profile — scoped stylesheet + editorial fonts.
    if (is_singular('bigbrother-players')) {
        wp_enqueue_style(
            'bbj-v2-single-player',
            BBJ_V2_THEME_URL . '/css/single-bigbrother-players.css',
            [],
            bbj_v2_asset_ver('/css/single-bigbrother-players.css')
        );
    }

    // WP core block styles are frontend-unused for our theme — dequeue
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('global-styles');
    wp_dequeue_style('classic-theme-styles');
}

// Preload Google Fonts in <head>
add_action('wp_head', 'bbj_v2_preload_fonts', 2);
function bbj_v2_preload_fonts(): void
{
    $base_url = 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Oswald:wght@400;500;600;700&family=Yanone+Kaffeesatz:wght@400;500;600;700&family=Caveat:wght@400;500;600;700';

    // Editorial fonts for the player profile (retiring Yanone on that page).
    if (is_singular('bigbrother-players')) {
        $base_url .= '&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;0,8..60,700;1,8..60,400&family=Inter+Tight:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500';
    }

    $fonts_url = $base_url . '&display=swap';
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="<?php echo esc_url($fonts_url); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($fonts_url); ?>" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="<?php echo esc_url($fonts_url); ?>">
    </noscript>
    <?php
}

// jQuery removal — theme and our JS must not depend on it
add_action('wp_enqueue_scripts', 'bbj_v2_drop_jquery', 100);
function bbj_v2_drop_jquery(): void
{
    if (!is_admin()) {
        wp_deregister_script('jquery');
    }
}

/**
 * filemtime-based asset version, cached per-request in a static so repeat
 * lookups for the same file don't re-stat. Falls back to the theme version
 * if the file is missing.
 */
function bbj_v2_asset_ver(string $relative_path): string
{
    static $cache = [];
    if (isset($cache[$relative_path])) {
        return $cache[$relative_path];
    }
    $mtime = @filemtime(BBJ_V2_THEME_PATH . $relative_path);
    return $cache[$relative_path] = $mtime ? (string) $mtime : BBJ_V2_THEME_VERSION;
}
