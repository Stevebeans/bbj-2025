<?php

namespace BigBrotherJunkies\Data\Email;

/**
 * Send orchestration for the BBJ Email System
 *
 * Hooks into WordPress post transitions to auto-send notifications,
 * handles batch sending, test emails, and re-confirmation campaigns.
 */
class EmailSender
{
    public function init(): void
    {
        add_action('transition_post_status', [$this, 'onPostStatusChange'], 10, 3);
    }

    /**
     * Only triggers for new posts being published (not updates).
     */
    public function onPostStatusChange(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        // Only new publications (not edits of already-published posts)
        if ($newStatus !== 'publish' || $oldStatus === 'publish') {
            return;
        }

        // Only blog posts
        if ($post->post_type !== 'post') {
            return;
        }

        // Check pause_sending setting
        $settings = get_option('bbjd_email_settings', []);
        if (!empty($settings['pause_sending'])) {
            return;
        }

        // Schedule async send via WP Cron
        wp_schedule_single_event(time(), 'bbj_send_post_notification', [$post->ID]);
    }

    /**
     * Send post notification to all active subscribers on the post-notifications list
     *
     * @param int $postId WordPress post ID
     */
    public function sendPostNotification(int $postId): void
    {
        $post = get_post($postId);

        if (!$post || $post->post_status !== 'publish') {
            return;
        }

        $emailService = new EmailService();
        $subscribers = $emailService->getActiveSubscriberEmails('post-notifications');

        if (empty($subscribers)) {
            return;
        }

        $client = new ResendClient();

        if (!$client->isConfigured()) {
            error_log('[BBJ Email] Cannot send post notification — Resend API key not configured');
            return;
        }

        $subject = $post->post_title;
        $baseHtml = $this->buildPostNotificationTemplate($post);

        $tags = [
            ['name' => 'category', 'value' => 'post_notification'],
            ['name' => 'post_id', 'value' => (string) $postId],
        ];

        // Build per-subscriber emails with personalized unsubscribe links
        $emails = [];
        foreach ($subscribers as $sub) {
            $token = $emailService->generateUnsubscribeToken($sub->email);
            $html = $this->injectUnsubscribeLink($baseHtml, $sub->email, $token);

            $emails[] = [
                'to' => $sub->email,
                'subject' => $subject,
                'html' => $html,
                'tags' => $tags,
            ];
        }

        $resendIds = $client->sendBatch($emails);
        $this->recordSends($subscribers, $resendIds, $subject);
    }

    /**
     * Send re-confirmation emails to specific subscribers
     *
     * Generates fresh tokens, sets status to unconfirmed, sends branded re-confirm email.
     *
     * @param array $subscriberIds Array of subscriber IDs
     * @return int  Number of emails sent
     */
    public function sendReconfirmation(array $subscriberIds): int
    {
        global $wpdb;

        if (empty($subscriberIds)) {
            return 0;
        }

        $subTable = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);
        $settings = get_option('bbjd_email_settings', []);
        $frontendUrl = rtrim($settings['frontend_url'] ?? 'https://bigbrotherjunkies.com', '/');

        $client = new ResendClient();

        if (!$client->isConfigured()) {
            error_log('[BBJ Email] Cannot send reconfirmation — Resend API key not configured');
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($subscriberIds), '%d'));
        $subscribers = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, email FROM {$subTable} WHERE id IN ({$placeholders})",
                ...$subscriberIds
            )
        );

        if (empty($subscribers)) {
            return 0;
        }

        $emails = [];
        foreach ($subscribers as $sub) {
            $token = bin2hex(random_bytes(32));

            // Update subscriber: fresh token, set to unconfirmed
            $wpdb->update($subTable, [
                'status' => 'unconfirmed',
                'confirm_token' => $token,
            ], ['id' => $sub->id]);

            $confirmUrl = $frontendUrl . '/email/confirm?token=' . urlencode($token);
            $html = $this->buildReconfirmationTemplate($confirmUrl);

            $emails[] = [
                'to' => $sub->email,
                'subject' => 'BB Season is Coming — Still want updates from BBJ?',
                'html' => $html,
                'tags' => [
                    ['name' => 'category', 'value' => 'reconfirmation'],
                ],
            ];
        }

        $resendIds = $client->sendBatch($emails);

        $this->recordSends(
            $subscribers,
            $resendIds,
            'BB Season is Coming — Still want updates from BBJ?'
        );

        return count($emails);
    }

    /**
     * Send a test email using the latest published post
     *
     * @param string $toEmail Recipient email for the test
     * @return bool  True if sent successfully
     */
    public function sendTestEmail(string $toEmail): bool
    {
        $latestPost = get_posts([
            'numberposts' => 1,
            'post_status' => 'publish',
            'post_type' => 'post',
        ]);

        if (empty($latestPost)) {
            error_log('[BBJ Email] No published posts available for test email');
            return false;
        }

        $post = $latestPost[0];
        $client = new ResendClient();

        if (!$client->isConfigured()) {
            error_log('[BBJ Email] Cannot send test email — Resend API key not configured');
            return false;
        }

        $html = $this->buildPostNotificationTemplate($post);
        $subject = '[TEST] ' . $post->post_title;

        // Use a dummy unsubscribe link for test emails
        $emailService = new EmailService();
        $token = $emailService->generateUnsubscribeToken($toEmail);
        $html = $this->injectUnsubscribeLink($html, $toEmail, $token);

        $resendId = $client->send($toEmail, $subject, $html, [
            ['name' => 'category', 'value' => 'test'],
        ]);

        return $resendId !== null;
    }

    private function buildPostNotificationTemplate(\WP_Post $post): string
    {
        $title = esc_html($post->post_title);
        $permalink = esc_url(get_permalink($post));
        $excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 40, '...');
        $excerpt = esc_html($excerpt);

        // Featured image
        $imageHtml = '';
        $thumbnailId = get_post_thumbnail_id($post->ID);
        if ($thumbnailId) {
            $imageUrl = wp_get_attachment_image_url($thumbnailId, 'medium_large');
            if ($imageUrl) {
                $imageUrl = esc_url($imageUrl);
                $imageHtml = <<<HTML
<tr>
<td style="padding:0;">
  <a href="{$permalink}" style="display:block;">
    <img src="{$imageUrl}" alt="{$title}" width="600" style="display:block;width:100%;height:auto;border-radius:0;" />
  </a>
</td>
</tr>
HTML;
            }
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background-color:#f4f4f7;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7;">
<tr><td align="center" style="padding:40px 20px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;width:100%;">

<!-- Header -->
<tr>
<td style="background-color:#35546e;padding:24px 40px;text-align:center;">
  <a href="https://bigbrotherjunkies.com" style="text-decoration:none;">
    <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700;">Big Brother Junkies</h1>
  </a>
</td>
</tr>

<!-- Featured Image -->
{$imageHtml}

<!-- Body -->
<tr>
<td style="padding:32px 40px;">
  <h2 style="margin:0 0 16px;">
    <a href="{$permalink}" style="color:#35546e;text-decoration:none;font-size:22px;font-weight:700;">{$title}</a>
  </h2>
  <p style="margin:0 0 24px;color:#555555;font-size:15px;line-height:1.6;">{$excerpt}</p>
  <table role="presentation" cellpadding="0" cellspacing="0">
  <tr>
    <td style="border-radius:6px;background-color:#35546e;">
      <a href="{$permalink}" style="display:inline-block;padding:12px 28px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;">
        Read More &rarr;
      </a>
    </td>
  </tr>
  </table>
</td>
</tr>

<!-- Footer -->
<tr>
<td style="padding:20px 40px;background-color:#f9fafb;text-align:center;border-top:1px solid #e5e7eb;">
  <p style="margin:0 0 8px;color:#9ca3af;font-size:12px;">Big Brother Junkies &mdash; bigbrotherjunkies.com</p>
  <p style="margin:0;color:#9ca3af;font-size:12px;">{{UNSUBSCRIBE_LINK}}</p>
</td>
</tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

    private function buildReconfirmationTemplate(string $confirmUrl): string
    {
        $confirmUrl = esc_url($confirmUrl);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background-color:#f4f4f7;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7;">
<tr><td align="center" style="padding:40px 20px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;width:100%;">

<!-- Header -->
<tr>
<td style="background-color:#35546e;padding:30px 40px;text-align:center;">
  <h1 style="margin:0;color:#ffffff;font-size:28px;font-weight:700;">Big Brother Junkies</h1>
</td>
</tr>

<!-- Body -->
<tr>
<td style="padding:40px;">
  <h2 style="margin:0 0 8px;color:#333333;font-size:22px;">BB Season is Almost Here!</h2>
  <p style="margin:0 0 24px;color:#555555;font-size:16px;line-height:1.5;">
    A new Big Brother season is on the horizon and we want to make sure you still want to hear from us. Click the button below to keep getting updates from BBJ.
  </p>
  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;">
  <tr>
    <td style="border-radius:6px;background-color:#35546e;">
      <a href="{$confirmUrl}" style="display:inline-block;padding:14px 32px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">
        Keep Me Subscribed
      </a>
    </td>
  </tr>
  </table>
  <p style="margin:24px 0 0;color:#999999;font-size:13px;line-height:1.4;">
    If you take no action, you will be automatically unsubscribed. We only want to email people who want to hear from us!
  </p>
</td>
</tr>

<!-- Footer -->
<tr>
<td style="padding:20px 40px;background-color:#f9fafb;text-align:center;border-top:1px solid #e5e7eb;">
  <p style="margin:0;color:#9ca3af;font-size:12px;">Big Brother Junkies &mdash; bigbrotherjunkies.com</p>
</td>
</tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

    private function injectUnsubscribeLink(string $html, string $email, string $token): string
    {
        $settings = get_option('bbjd_email_settings', []);
        $frontendUrl = rtrim($settings['frontend_url'] ?? 'https://bigbrotherjunkies.com', '/');

        $url = $frontendUrl . '/unsubscribe?' . http_build_query([
            'email' => $email,
            'token' => $token,
        ]);

        $url = esc_url($url);

        $link = '<a href="' . $url . '" style="color:#9ca3af;text-decoration:underline;">Unsubscribe</a>';

        return str_replace('{{UNSUBSCRIBE_LINK}}', $link, $html);
    }

    /**
     * Record sent emails in the sends table
     *
     * @param array  $subscribers Array of subscriber objects (id, email)
     * @param array  $resendIds   Array of Resend message IDs (parallel to subscribers)
     * @param string $subject     Email subject line
     */
    private function recordSends(array $subscribers, array $resendIds, string $subject): void
    {
        global $wpdb;

        $sendsTable = EmailSchema::table(EmailSchema::TABLE_SENDS);
        $now = current_time('mysql');

        foreach ($subscribers as $i => $sub) {
            $resendId = $resendIds[$i] ?? '';

            $wpdb->insert($sendsTable, [
                'subscriber_id' => $sub->id,
                'subject' => $subject,
                'resend_id' => $resendId ?: null,
                'status' => $resendId ? 'sent' : 'failed',
                'sent_at' => $now,
            ]);
        }
    }
}
