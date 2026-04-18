<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

use BigBrotherJunkies\Data\Email\EmailSchema;
use BigBrotherJunkies\Data\Email\EmailSender;
use BigBrotherJunkies\Data\Email\EmailService;

class EmailsPage
{
    public const MENU_SLUG = 'bbjd-mailing-emails';

    public function handleActions(): void
    {
        add_action('admin_post_bbjd_send_test_email', [$this, 'handleSendTestEmail']);
        add_action('admin_post_bbjd_send_reconfirmation', [$this, 'handleSendReconfirmation']);
    }

    public function handleSendTestEmail(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('bbjd_send_test_email');

        $sender = new EmailSender();
        $success = $sender->sendTestEmail(wp_get_current_user()->user_email);

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => $success ? 'test_sent' : 'test_failed',
        ], admin_url('admin.php')));
        exit;
    }

    public function handleSendReconfirmation(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('bbjd_send_reconfirmation');

        $days = intval($_POST['days'] ?? 90);

        global $wpdb;
        $subTable = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);
        $sendsTable = EmailSchema::table(EmailSchema::TABLE_SENDS);

        $dormantIds = $wpdb->get_col($wpdb->prepare(
            "SELECT s.id FROM {$subTable} s
             LEFT JOIN (SELECT subscriber_id, MAX(opened_at) as last_open FROM {$sendsTable} GROUP BY subscriber_id) se
             ON s.id = se.subscriber_id
             WHERE s.status = 'subscribed'
             AND (se.last_open IS NULL OR se.last_open < DATE_SUB(NOW(), INTERVAL %d DAY))",
            $days
        ));

        $sender = new EmailSender();
        $sent = $sender->sendReconfirmation($dormantIds);

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'reconfirm_sent',
            'count' => $sent,
        ], admin_url('admin.php')));
        exit;
    }

    public function render(): void
    {
        $settings = get_option('bbjd_email_settings', []);
        $message = sanitize_text_field($_GET['message'] ?? '');
        $isPaused = !empty($settings['pause_sending']);
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6 bbjd-max-w-4xl">
                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-6">
                    Emails
                </h1>

                <?php $this->renderMessages($message); ?>

                <?php if ($isPaused): ?>
                <div class="bbjd-bg-red-100 bbjd-border-l-4 bbjd-border-red-500 bbjd-text-red-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
                    <p><strong>Sending is paused.</strong> No emails will be sent until you re-enable sending in Settings.</p>
                </div>
                <?php endif; ?>

                <!-- Post Notifications -->
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                    <div class="bbjd-flex bbjd-items-start bbjd-justify-between">
                        <div>
                            <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-1">Post Notifications</h2>
                            <p class="bbjd-text-sm bbjd-text-gray-600">
                                Automatically sent to the <code class="bbjd-bg-gray-100 bbjd-px-1 bbjd-rounded bbjd-text-xs">post-notifications</code> list when a new blog post is published.
                            </p>
                        </div>
                        <span class="bbjd-inline-block bbjd-px-3 bbjd-py-1 bbjd-text-xs bbjd-rounded-full <?php echo $isPaused ? 'bbjd-bg-red-100 bbjd-text-red-700' : 'bbjd-bg-green-100 bbjd-text-green-700'; ?>">
                            <?php echo $isPaused ? 'Paused' : 'Active'; ?>
                        </span>
                    </div>

                    <div class="bbjd-mt-4 bbjd-pt-4 bbjd-border-t">
                        <h3 class="bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">Send Test Email</h3>
                        <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mb-3">
                            Sends a preview using the latest published post to: <strong><?php echo esc_html(wp_get_current_user()->user_email); ?></strong>
                        </p>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                            <?php wp_nonce_field('bbjd_send_test_email'); ?>
                            <input type="hidden" name="action" value="bbjd_send_test_email">
                            <button type="submit" class="bbjd-bg-primary500 bbjd-text-white bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-text-sm hover:bbjd-bg-primaryHard bbjd-transition-colors">
                                Send Test Email
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Re-confirmation Campaign -->
                <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                    <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-1">Re-confirmation Campaign</h2>
                    <p class="bbjd-text-sm bbjd-text-gray-600 bbjd-mb-4">
                        Send a "Still want to hear from us?" email to dormant subscribers.
                        Subscribers who don't re-confirm will be flagged for removal.
                    </p>

                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('bbjd_send_reconfirmation'); ?>
                        <input type="hidden" name="action" value="bbjd_send_reconfirmation">

                        <div class="bbjd-flex bbjd-items-end bbjd-gap-4">
                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                    Inactive for at least
                                </label>
                                <div class="bbjd-flex bbjd-items-center bbjd-gap-2">
                                    <input type="number" name="days" value="<?php echo esc_attr($settings['dormant_threshold_days'] ?? 90); ?>"
                                           min="30" max="365"
                                           class="bbjd-w-20 bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm">
                                    <span class="bbjd-text-sm bbjd-text-gray-600">days</span>
                                </div>
                            </div>
                            <button type="submit" class="bbjd-bg-amber-500 bbjd-text-white bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-text-sm hover:bbjd-bg-amber-600 bbjd-transition-colors"
                                    onclick="return confirm('This will set matching subscribers to unconfirmed and send them a re-confirmation email. Continue?');">
                                Send Re-confirmation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    private function renderMessages(string $message): void
    {
        if ($message === 'test_sent') {
            echo '<div class="bbjd-bg-green-100 bbjd-border-l-4 bbjd-border-green-500 bbjd-text-green-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded"><p>Test email sent! Check your inbox.</p></div>';
        } elseif ($message === 'test_failed') {
            echo '<div class="bbjd-bg-red-100 bbjd-border-l-4 bbjd-border-red-500 bbjd-text-red-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded"><p>Failed to send test email. Check your Resend API key in Settings.</p></div>';
        } elseif ($message === 'reconfirm_sent') {
            $count = intval($_GET['count'] ?? 0);
            echo '<div class="bbjd-bg-green-100 bbjd-border-l-4 bbjd-border-green-500 bbjd-text-green-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded"><p>Re-confirmation sent to ' . $count . ' subscribers.</p></div>';
        }
    }
}
