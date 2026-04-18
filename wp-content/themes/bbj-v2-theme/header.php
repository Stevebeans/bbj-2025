<?php
/**
 * The header for all pages.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class('bg-slate-200 font-sans text-gray-900 dark:bg-slate-700 dark:text-gray-100'); ?>>
<?php wp_body_open(); ?>

<a href="#site-content" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:bg-secondary-500 focus:text-primary-700 focus:px-3 focus:py-2 focus:rounded">
    <?php esc_html_e('Skip to content', 'bbj-v2-theme'); ?>
</a>

<header id="site-header" class="relative z-30">
    <?php get_template_part('template-parts/header/utility-strip'); ?>
    <?php get_template_part('template-parts/header/logo-bar'); ?>
    <?php get_template_part('template-parts/header/nav'); ?>
    <?php
    // Spoiler bar lives in the header on all templates EXCEPT the homepage,
    // where front-page.php renders it in-content below the leaderboard ad
    // (per the Claude Design editorial layout).
    if ( ! is_front_page() ) {
        get_template_part('template-parts/header/spoiler-bar');
    }
    ?>
</header>

<?php
// Leaderboard ad slot — placeholder until wired to bbjd_ad().
// Tracked in docs/ad-slots.md.
get_template_part('template-parts/components/ad-placeholder', null, [
    'slot' => 'leaderboard_top',
    'size' => '970x90',
    'note' => __('Below nav · above content · eager-load', 'bbj-v2-theme'),
]);
?>

<main id="site-content" class="min-h-[50vh]" role="main">
