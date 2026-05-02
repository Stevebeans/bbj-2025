<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="bbj-sidebar-card">
    <div class="bg-primary-500 text-white p-4">
        <h2 class="font-display text-2xl mb-2"><?php esc_html_e('Newsletter', 'bbj-v2-theme'); ?></h2>
        <p class="text-sm opacity-90 mb-3">
            <?php esc_html_e('Get the latest Big Brother news in your inbox.', 'bbj-v2-theme'); ?>
        </p>
        <form class="space-y-2">
            <input type="email"
                   placeholder="your@email.com"
                   class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/60 focus:outline-none focus:border-white/50 focus:bg-white/15 text-sm transition" />
            <button type="submit" class="btn-secondary w-full text-center block text-sm">
                <?php esc_html_e('Subscribe', 'bbj-v2-theme'); ?>
            </button>
        </form>
    </div>
</section>
