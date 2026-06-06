<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

use BigBrotherJunkies\Data\Push\VapidKeys;
use BigBrotherJunkies\Data\Push\PushSender;
use BigBrotherJunkies\Data\Push\PushSubscriptionRepository;

/**
 * Push Notifications admin page — generate VAPID keys and send manual broadcasts
 */
class PushPage
{
    public const MENU_SLUG = 'bbjd-push';

    /**
     * Register action handlers
     */
    public function handleActions(): void
    {
        add_action('admin_post_bbjd_push_gen_vapid', [$this, 'handleGenVapid']);
        add_action('admin_post_bbjd_push_broadcast', [$this, 'handleBroadcast']);
    }

    /**
     * Generate VAPID keys
     */
    public function handleGenVapid(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_push_gen_vapid');
        VapidKeys::ensureGenerated();
        $this->redirect('VAPID keys generated.');
    }

    /**
     * Send a manual broadcast to all subscribers
     */
    public function handleBroadcast(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_push_broadcast');

        $res = PushSender::broadcast(
            sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['body'] ?? '')),
            esc_url_raw(wp_unslash($_POST['url'] ?? ''))
        );

        $this->redirect(sprintf(
            'Sent %d, failed %d, pruned %d.',
            $res['sent'] ?? 0,
            $res['failed'] ?? 0,
            $res['pruned'] ?? 0
        ));
    }

    /**
     * Render the page
     */
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $configured = VapidKeys::isConfigured();
        $count = PushSubscriptionRepository::count();
        $notice = isset($_GET['bbjd_push_msg'])
            ? sanitize_text_field(wp_unslash($_GET['bbjd_push_msg']))
            : '';
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6 bbjd-max-w-3xl">
                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-6">
                    Push Notifications
                </h1>

                <?php if ($notice): ?>
                    <div class="bbjd-bg-green-100 bbjd-border-l-4 bbjd-border-green-500 bbjd-text-green-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
                        <p><?php echo esc_html($notice); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Status card -->
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                    <div class="bbjd-grid bbjd-grid-cols-2 bbjd-gap-4">
                        <div class="bbjd-bg-<?php echo $configured ? 'green' : 'red'; ?>-50 bbjd-border bbjd-border-<?php echo $configured ? 'green' : 'red'; ?>-200 bbjd-rounded-lg bbjd-p-4">
                            <div class="bbjd-text-<?php echo $configured ? 'green' : 'red'; ?>-700 bbjd-text-sm bbjd-font-medium">VAPID Keys</div>
                            <div class="bbjd-text-<?php echo $configured ? 'green' : 'red'; ?>-900 bbjd-text-xl bbjd-font-bold bbjd-mt-1">
                                <?php echo $configured ? 'Configured' : 'Not configured'; ?>
                            </div>
                        </div>
                        <div class="bbjd-bg-blue-50 bbjd-border bbjd-border-blue-200 bbjd-rounded-lg bbjd-p-4">
                            <div class="bbjd-text-blue-700 bbjd-text-sm bbjd-font-medium">Subscribers</div>
                            <div class="bbjd-text-blue-900 bbjd-text-2xl bbjd-font-bold bbjd-mt-1"><?php echo (int) $count; ?></div>
                        </div>
                    </div>
                </div>

                <?php if (!$configured): ?>
                    <!-- Generate VAPID keys -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-2">
                            Generate VAPID Keys
                        </h2>
                        <p class="bbjd-text-gray-600 bbjd-text-sm bbjd-mb-4">
                            VAPID keys are required before push notifications can be sent. Generate them once — they are stored in the database and reused.
                        </p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('bbjd_push_gen_vapid'); ?>
                            <input type="hidden" name="action" value="bbjd_push_gen_vapid">
                            <button type="submit" class="bbjd-bg-primary500 bbjd-text-white bbjd-px-6 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors">
                                Generate VAPID Keys
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Broadcast form -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6">
                        <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-2">
                            Send Broadcast
                        </h2>
                        <p class="bbjd-text-gray-600 bbjd-text-sm bbjd-mb-4">
                            Send a push notification to all <?php echo (int) $count; ?> subscriber<?php echo $count !== 1 ? 's' : ''; ?>.
                        </p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('bbjd_push_broadcast'); ?>
                            <input type="hidden" name="action" value="bbjd_push_broadcast">
                            <div class="bbjd-space-y-4">
                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Title <span class="bbjd-text-red-500">*</span>
                                    </label>
                                    <input type="text" name="title" required
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm"
                                           placeholder="e.g. Live Eviction Results">
                                </div>
                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Body <span class="bbjd-text-red-500">*</span>
                                    </label>
                                    <input type="text" name="body" required
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm"
                                           placeholder="e.g. Week 5 eviction — tap to see who went home">
                                </div>
                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        URL <span class="bbjd-text-gray-400 bbjd-font-normal">(optional)</span>
                                    </label>
                                    <input type="text" name="url"
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm"
                                           placeholder="/posts/week-5-eviction">
                                    <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-1">Path the notification opens when tapped. Leave blank to open the homepage.</p>
                                </div>
                                <div class="bbjd-flex bbjd-justify-end">
                                    <button type="submit"
                                            class="bbjd-bg-primary500 bbjd-text-white bbjd-px-6 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors"
                                            onclick="return confirm('Send push notification to all <?php echo (int) $count; ?> subscriber<?php echo $count !== 1 ? 's' : ''; ?>?');">
                                        Send to all (<?php echo (int) $count; ?>)
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Redirect back to the Push page with a status message
     */
    private function redirect(string $msg): void
    {
        wp_safe_redirect(add_query_arg(
            'bbjd_push_msg',
            rawurlencode($msg),
            admin_url('admin.php?page=' . self::MENU_SLUG)
        ));
        exit;
    }
}
