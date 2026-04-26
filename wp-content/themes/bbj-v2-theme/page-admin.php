<?php
bbj_v2_require_admin(); // LINE 1 SAFEGUARD — DO NOT WRAP, DO NOT REMOVE.
/**
 * Front-end admin shell template.
 *
 * Auto-assigned to the "Admin" WP page via WP's page-{slug}.php hierarchy —
 * no Template Name header needed. Sub-panes selected via ?tab=<slug>.
 */

if (!defined('ABSPATH')) {
    exit;
}

bbj_v2_weekly_tracker_install();

$active_tab = get_query_var('tab');
if (!is_string($active_tab) || $active_tab === '') {
    $active_tab = 'overview';
}

get_header();
?>

<div class="bbj-admin-shell min-h-[60vh]">
    <div class="mx-auto max-w-screen-xl px-4 py-6 flex gap-6">

        <?php get_template_part('template-parts/admin/sidebar', null, [
            'active' => $active_tab,
        ]); ?>

        <section class="flex-1 min-w-0">
            <?php if ($active_tab === 'overview'): ?>
                <?php get_template_part('template-parts/admin/pane-overview'); ?>
            <?php elseif ($active_tab === 'seasons'): ?>
                <?php
                $edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
                if ($edit_id > 0) {
                    get_template_part('template-parts/admin/pane-seasons-edit', null, [
                        'season_id' => $edit_id,
                    ]);
                } else {
                    get_template_part('template-parts/admin/pane-seasons');
                }
                ?>
            <?php elseif ($active_tab === 'feed-updates'): ?>
                <?php
                bbj_v2_require_permission('feed_updates');
                get_template_part('template-parts/admin/pane-feed-updates');
                ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php get_template_part('template-parts/admin/pane-settings'); ?>
            <?php elseif ($active_tab === 'roadmap'): ?>
                <?php get_template_part('template-parts/admin/pane-roadmap'); ?>
            <?php elseif ($active_tab === 'comp-types'): ?>
                <?php get_template_part('template-parts/admin/pane-comp-types'); ?>
            <?php else: ?>
                <?php get_template_part('template-parts/admin/pane-stub', null, [
                    'tab' => $active_tab,
                ]); ?>
            <?php endif; ?>
        </section>

    </div>
</div>

<?php get_footer(); ?>
