<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="bbj-sidebar-card">
    <h2 class="section-header mb-2"><?php esc_html_e('Newsletter', 'bbj-v2-theme'); ?></h2>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
        <?php esc_html_e('Get the latest Big Brother news in your inbox.', 'bbj-v2-theme'); ?>
    </p>
    <form class="space-y-2">
        <input type="email" placeholder="your@email.com" class="input text-sm" />
        <button type="submit" class="btn-primary w-full text-sm"><?php esc_html_e('Subscribe', 'bbj-v2-theme'); ?></button>
    </form>
</section>
