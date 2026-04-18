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
    $version = BBJ_V2_THEME_VERSION;

    // Compiled Tailwind
    wp_enqueue_style(
        'bbj-v2-style',
        BBJ_V2_THEME_URL . '/build/style.css',
        [],
        $version
    );

    // Main JS — small, deferred
    wp_enqueue_script(
        'bbj-v2-main',
        BBJ_V2_THEME_URL . '/src/js/main.js',
        [],
        $version,
        ['strategy' => 'defer', 'in_footer' => true]
    );

    // Dark mode toggle handler
    wp_enqueue_script(
        'bbj-v2-dark-mode',
        BBJ_V2_THEME_URL . '/src/js/dark-mode.js',
        [],
        $version,
        ['strategy' => 'defer', 'in_footer' => true]
    );

    // Auth assets — anonymous users only.
    if (!is_user_logged_in()) {
        wp_enqueue_script(
            'bbj-v2-auth-modal',
            BBJ_V2_THEME_URL . '/src/js/auth-modal.js',
            [],
            filemtime(BBJ_V2_THEME_PATH . '/src/js/auth-modal.js'),
            true
        );
        wp_localize_script('bbj-v2-auth-modal', 'BBJAuth', [
            'api'     => esc_url_raw(rest_url('bbjd/v1/')),
            'nonce'   => wp_create_nonce('bbj_auth'),
            'debug'   => defined('WP_DEBUG') && WP_DEBUG,
            'homeUrl' => esc_url_raw(home_url('/')),
        ]);
        wp_enqueue_script(
            'bbj-v2-auth-forms',
            BBJ_V2_THEME_URL . '/src/js/auth-forms.js',
            ['bbj-v2-auth-modal'],
            filemtime(BBJ_V2_THEME_PATH . '/src/js/auth-forms.js'),
            true
        );
        wp_enqueue_script(
            'bbj-v2-auth-google',
            BBJ_V2_THEME_URL . '/src/js/auth-google.js',
            ['bbj-v2-auth-modal'],
            filemtime(BBJ_V2_THEME_PATH . '/src/js/auth-google.js'),
            true
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
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style"
        href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Oswald:wght@400;500;600;700&family=Yanone+Kaffeesatz:wght@400;500;600;700&family=Caveat:wght@400;500;600;700&display=swap">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Oswald:wght@400;500;600;700&family=Yanone+Kaffeesatz:wght@400;500;600;700&family=Caveat:wght@400;500;600;700&display=swap"
        media="print" onload="this.media='all'">
    <noscript>
      <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Oswald:wght@400;500;600;700&family=Yanone+Kaffeesatz:wght@400;500;600;700&family=Caveat:wght@400;500;600;700&display=swap">
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
