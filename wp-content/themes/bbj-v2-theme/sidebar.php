<?php
/**
 * Site-wide default sidebar — used by single.php, page.php, archives.
 * Homepage has its own sidebar stack inlined in front-page.php.
 *
 * Current skeleton: newsletter, hot posts, recent comments. Sticky ad slot
 * will slot in later once the sticky-ad-at-footer placement is finalized.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<aside class="space-y-6" aria-label="<?php esc_attr_e('Sidebar', 'bbj-v2-theme'); ?>">
    <?php get_template_part('template-parts/sidebar/newsletter'); ?>
    <?php get_template_part('template-parts/sidebar/hot-posts'); ?>
    <?php get_template_part('template-parts/sidebar/recent-comments'); ?>
</aside>
