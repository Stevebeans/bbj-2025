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
            <?php else: ?>
                <?php get_template_part('template-parts/admin/pane-stub', null, [
                    'tab' => $active_tab,
                ]); ?>
            <?php endif; ?>
        </section>

    </div>
</div>

<?php get_footer(); ?>
