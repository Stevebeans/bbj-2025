<?php
/**
 * Admin shell — generic "Coming soon" stub pane used for every non-overview tab.
 * Receives $args['tab'] — the current tab slug.
 */

if (!defined('ABSPATH')) {
    exit;
}

$tab_slug = isset($args['tab']) ? (string) $args['tab'] : '';

// Match labels to the admin sidebar item table (kept in sync by hand for v1).
$labels = [
    'posts'          => 'Posts',
    'feed-updates'   => 'Feed Updates',
    'comments'       => 'Comments',
    'players'        => 'Players',
    'seasons'        => 'Seasons',
    'announcements'  => 'Announcements',
    'content-engine' => 'Content',
    'users'          => 'Users',
    'stats'          => 'Stats',
    'settings'       => 'Settings',
    'spoiler-bar'    => 'Spoiler Bar',
];
$label = $labels[$tab_slug] ?? ucwords(str_replace('-', ' ', $tab_slug));
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">
    <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500 mb-2">
        <?php printf(
            /* translators: %s: tab label */
            esc_html__('Coming soon — %s.', 'bbj-v2-theme'),
            esc_html($label)
        ); ?>
    </h1>
    <p class="text-stone-600 dark:text-slate-400">
        <?php esc_html_e('This section is part of the shell but hasn\'t been built out yet. Check back after the next sprint.', 'bbj-v2-theme'); ?>
    </p>
</section>
