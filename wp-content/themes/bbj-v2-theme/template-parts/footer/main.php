<?php
/**
 * Footer body — 4 widget columns + copyright line.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<footer id="site-footer" class="bg-primary-500 text-white mt-8">
    <div class="mx-auto max-w-screen-xl px-4 py-10 grid grid-cols-1 md:grid-cols-4 gap-8">
        <?php foreach ([1, 2, 3, 4] as $col) : ?>
            <div class="footer-col">
                <?php if (is_active_sidebar('footer-' . $col)) : ?>
                    <?php dynamic_sidebar('footer-' . $col); ?>
                <?php else : ?>
                    <h3 class="font-osw uppercase tracking-wider text-secondary-500 mb-2">
                        <?php printf(esc_html__('Column %d', 'bbj-v2-theme'), $col); ?>
                    </h3>
                    <p class="text-sm text-gray-300"><?php esc_html_e('Add widgets in Appearance → Widgets → Footer Column %d', 'bbj-v2-theme'); ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="border-t border-primary-600">
        <div class="mx-auto max-w-screen-xl px-4 py-4 flex flex-col md:flex-row items-center justify-between gap-2 text-xs text-gray-300">
            <span>
                &copy; <?php echo esc_html(date('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php esc_html_e('All rights reserved.', 'bbj-v2-theme'); ?>
            </span>
            <span>
                <?php
                wp_nav_menu([
                    'theme_location' => 'footer',
                    'container'      => false,
                    'menu_class'     => 'flex items-center gap-4',
                    'depth'          => 1,
                    'fallback_cb'    => false,
                ]);
                ?>
            </span>
        </div>
    </div>
</footer>
