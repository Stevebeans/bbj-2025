<?php
/**
 * Homepage sidebar — used by front-page.php.
 * Widget order: Houseboard → Season Stats → Recent Comments → Sticky Ad → Paramount+ → Socials → Newsletter.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<aside class="space-y-6">
    <?php get_template_part('template-parts/home/houseboard'); ?>
    <?php get_template_part('template-parts/sidebar/season-stats'); ?>
    <?php get_template_part('template-parts/sidebar/recent-comments'); ?>
    <?php get_template_part('template-parts/sidebar/sticky-ad'); ?>
    <?php get_template_part('template-parts/sidebar/paramount-plus'); ?>
    <?php get_template_part('template-parts/sidebar/socials'); ?>
    <?php get_template_part('template-parts/sidebar/newsletter'); ?>
</aside>
