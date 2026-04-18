<?php
/**
 * Theme setup: supports, menus, image sizes, sidebars.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', 'bbj_v2_setup');
function bbj_v2_setup(): void
{
    load_theme_textdomain('bbj-v2-theme', BBJ_V2_THEME_PATH . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', [
        'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script',
    ]);
    add_theme_support('customize-selective-refresh-widgets');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');

    register_nav_menus([
        'primary' => __('Primary Navigation', 'bbj-v2-theme'),
        'footer'  => __('Footer Navigation', 'bbj-v2-theme'),
    ]);

    // Image sizes must mirror the ones documented in CLAUDE.md
    add_image_size('bbj_v2_profile_image', 375, 375, true);
    add_image_size('bbj_v2_spoiler_bar',   100, 100, true);
    add_image_size('bbj_v2_index_hero',    1350, 450, ['center', 'center']);
    add_image_size('bbj_v2_index_mobile',  400, 333, ['center', 'center']);
    add_image_size('featured-thumbnail',   400, 200, true);
    add_image_size('player-banner',        1200, 350, true);
}

add_action('widgets_init', 'bbj_v2_widgets_init');
function bbj_v2_widgets_init(): void
{
    register_sidebar([
        'name'          => __('Primary Sidebar', 'bbj-v2-theme'),
        'id'            => 'sidebar-primary',
        'description'   => __('Main sidebar beside content', 'bbj-v2-theme'),
        'before_widget' => '<section id="%1$s" class="widget %2$s mb-4">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="section-header mb-2">',
        'after_title'   => '</h2>',
    ]);

    foreach ([1, 2, 3, 4] as $col) {
        register_sidebar([
            'name'          => sprintf(__('Footer Column %d', 'bbj-v2-theme'), $col),
            'id'            => 'footer-' . $col,
            'before_widget' => '<section id="%1$s" class="widget %2$s mb-4">',
            'after_widget'  => '</section>',
            'before_title'  => '<h3 class="font-osw uppercase tracking-wider text-secondary-500 mb-2">',
            'after_title'   => '</h3>',
        ]);
    }
}

/**
 * Set content width for embedded media.
 */
add_action('after_setup_theme', 'bbj_v2_content_width', 0);
function bbj_v2_content_width(): void
{
    $GLOBALS['content_width'] = 1080;
}
