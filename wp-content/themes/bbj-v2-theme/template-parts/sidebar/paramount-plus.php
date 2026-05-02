<?php
if (!defined('ABSPATH')) {
    exit;
}

$url = apply_filters('bbj_v2_paramount_plus_url', '#');
?>
<section class="bbj-sidebar-card">
    <div class="bg-primary-500 text-white p-4">
        <h2 class="font-display text-2xl mb-2"><?php esc_html_e('Watch Live on Paramount+', 'bbj-v2-theme'); ?></h2>
        <p class="text-sm opacity-90 mb-3">
            <?php esc_html_e('Stream the full BB live feeds and CBS episodes. 7-day free trial.', 'bbj-v2-theme'); ?>
        </p>
        <a href="<?php echo esc_url($url); ?>"
           class="btn-secondary w-full text-center block"
           target="_blank" rel="noopener sponsored">
            <?php esc_html_e('Start free trial', 'bbj-v2-theme'); ?>
        </a>
    </div>
</section>
