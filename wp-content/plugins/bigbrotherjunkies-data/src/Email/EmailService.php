<?php

namespace BigBrotherJunkies\Data\Email;

/**
 * Core business logic for the BBJ Email System
 *
 * Handles subscribe/confirm/unsubscribe flows, subscriber queries,
 * analytics stats, and unsubscribe token generation.
 */
class EmailService
{
    /**
     * Subscribe an email to one or more lists
     *
     * - Existing + subscribed: just add to new lists
     * - Existing + unsubscribed: re-confirm flow
     * - New + logged in ($userId): auto-confirm
     * - New + anonymous: confirmation email required
     *
     * @param string   $email     Email address
     * @param string   $source    Where the subscription originated (widget, footer, etc.)
     * @param array    $listSlugs List slugs to subscribe to
     * @param int|null $userId    WordPress user ID if logged in
     * @return array   ['status' => string, 'subscriber_id' => int]
     */
    public function subscribe(string $email, string $source, array $listSlugs, ?int $userId = null): array
    {
        global $wpdb;

        $email = strtolower(trim($email));
        $table = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);

        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE email = %s", $email)
        );

        if ($existing) {
            if ($existing->status === 'subscribed') {
                $this->addToLists((int) $existing->id, $listSlugs);

                return [
                    'status' => 'already_subscribed',
                    'subscriber_id' => (int) $existing->id,
                ];
            }

            // Unsubscribed or unconfirmed — re-subscribe flow
            $token = $this->generateToken();

            $wpdb->update($table, [
                'status' => 'unconfirmed',
                'confirm_token' => $token,
                'source' => $source,
                'unsubscribed_at' => null,
            ], ['id' => $existing->id]);

            $this->addToLists((int) $existing->id, $listSlugs);
            $this->sendConfirmationEmail($email, $token);

            return [
                'status' => 'resubscribed_pending',
                'subscriber_id' => (int) $existing->id,
            ];
        }

        // New subscriber
        $now = current_time('mysql');

        if ($userId) {
            // Logged-in user: auto-confirm, no email needed
            $wpdb->insert($table, [
                'user_id' => $userId,
                'email' => $email,
                'status' => 'subscribed',
                'source' => $source,
                'subscribed_at' => $now,
                'confirmed_at' => $now,
                'created_at' => $now,
            ]);

            $subscriberId = (int) $wpdb->insert_id;
            $this->addToLists($subscriberId, $listSlugs);

            return [
                'status' => 'subscribed',
                'subscriber_id' => $subscriberId,
            ];
        }

        // Anonymous: confirmation required
        $token = $this->generateToken();

        $wpdb->insert($table, [
            'email' => $email,
            'status' => 'unconfirmed',
            'confirm_token' => $token,
            'source' => $source,
            'created_at' => $now,
        ]);

        $subscriberId = (int) $wpdb->insert_id;
        $this->addToLists($subscriberId, $listSlugs);
        $this->sendConfirmationEmail($email, $token);

        return [
            'status' => 'pending',
            'subscriber_id' => $subscriberId,
        ];
    }

    /**
     * Confirm a subscription via token
     *
     * @param string $token Confirmation token from email link
     * @return bool  True if confirmed, false if token invalid/expired
     */
    public function confirm(string $token): bool
    {
        global $wpdb;

        $table = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);

        $subscriber = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE confirm_token = %s AND status = 'unconfirmed'",
                $token
            )
        );

        if (!$subscriber) {
            return false;
        }

        $now = current_time('mysql');

        $wpdb->update($table, [
            'status' => 'subscribed',
            'confirm_token' => null,
            'subscribed_at' => $now,
            'confirmed_at' => $now,
        ], ['id' => $subscriber->id]);

        return true;
    }

    /**
     * Unsubscribe a user via HMAC token verification
     *
     * @param string $email Subscriber email
     * @param string $token HMAC unsubscribe token
     * @return bool  True if unsubscribed, false if token invalid
     */
    public function unsubscribe(string $email, string $token): bool
    {
        $email = strtolower(trim($email));

        if (!$this->verifyUnsubscribeToken($email, $token)) {
            return false;
        }

        global $wpdb;

        $table = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);
        $now = current_time('mysql');

        $updated = $wpdb->update($table, [
            'status' => 'unsubscribed',
            'unsubscribed_at' => $now,
        ], ['email' => $email]);

        return $updated !== false && $updated > 0;
    }

    /**
     * Get paginated subscribers for a specific list
     *
     * @param string $listSlug List slug (e.g. 'post-notifications')
     * @param array  $filters  Optional: page, per_page, status, search
     * @return array Paginated result with subscribers, total, page, per_page, total_pages
     */
    public function getSubscribers(string $listSlug, array $filters = []): array
    {
        global $wpdb;

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $subTable = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);
        $lsTable = EmailSchema::table(EmailSchema::TABLE_LIST_SUBSCRIBERS);
        $listTable = EmailSchema::table(EmailSchema::TABLE_LISTS);

        $where = ["l.slug = %s"];
        $params = [$listSlug];

        if (!empty($filters['status'])) {
            $where[] = "s.status = %s";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = "s.email LIKE %s";
            $params[] = '%' . $wpdb->esc_like($filters['search']) . '%';
        }

        $whereClause = implode(' AND ', $where);

        $countSql = $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$subTable} s
             INNER JOIN {$lsTable} ls ON ls.subscriber_id = s.id
             INNER JOIN {$listTable} l ON l.id = ls.list_id
             WHERE {$whereClause}",
            ...$params
        );

        $total = (int) $wpdb->get_var($countSql);

        $dataSql = $wpdb->prepare(
            "SELECT s.id, s.email, s.user_id, s.status, s.source, s.subscribed_at, s.confirmed_at, s.unsubscribed_at, s.created_at
             FROM {$subTable} s
             INNER JOIN {$lsTable} ls ON ls.subscriber_id = s.id
             INNER JOIN {$listTable} l ON l.id = ls.list_id
             WHERE {$whereClause}
             ORDER BY s.created_at DESC
             LIMIT %d OFFSET %d",
            ...array_merge($params, [$perPage, $offset])
        );

        $subscribers = $wpdb->get_results($dataSql, ARRAY_A);

        return [
            'subscribers' => $subscribers ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Get active (subscribed) subscriber emails for a list
     *
     * @param string $listSlug List slug
     * @return array Array of objects with id and email
     */
    public function getActiveSubscriberEmails(string $listSlug): array
    {
        global $wpdb;

        $subTable = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);
        $lsTable = EmailSchema::table(EmailSchema::TABLE_LIST_SUBSCRIBERS);
        $listTable = EmailSchema::table(EmailSchema::TABLE_LISTS);

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.email
             FROM {$subTable} s
             INNER JOIN {$lsTable} ls ON ls.subscriber_id = s.id
             INNER JOIN {$listTable} l ON l.id = ls.list_id
             WHERE l.slug = %s
               AND l.is_active = 1
               AND s.status = 'subscribed'
             ORDER BY s.id ASC",
            $listSlug
        ));

        return $results ?: [];
    }

    /**
     * Get aggregate email system stats
     *
     * @return array Subscriber counts by status + 90-day send stats
     */
    public function getStats(): array
    {
        global $wpdb;

        $subTable = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);
        $sendsTable = EmailSchema::table(EmailSchema::TABLE_SENDS);

        // Subscriber counts by status
        $statusCounts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$subTable} GROUP BY status",
            ARRAY_A
        );

        $subscribers = [];
        foreach ($statusCounts ?: [] as $row) {
            $subscribers[$row['status']] = (int) $row['count'];
        }

        // Send stats from last 90 days
        $ninetyDaysAgo = gmdate('Y-m-d H:i:s', strtotime('-90 days'));

        $sendStats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
                SUM(CASE WHEN bounced_at IS NOT NULL THEN 1 ELSE 0 END) as bounced
             FROM {$sendsTable}
             WHERE sent_at >= %s",
            $ninetyDaysAgo
        ), ARRAY_A);

        $total = (int) ($sendStats['total'] ?? 0);
        $delivered = (int) ($sendStats['delivered'] ?? 0);
        $opened = (int) ($sendStats['opened'] ?? 0);
        $clicked = (int) ($sendStats['clicked'] ?? 0);
        $bounced = (int) ($sendStats['bounced'] ?? 0);

        return [
            'subscribers' => $subscribers,
            'sends_90d' => [
                'total' => $total,
                'delivered' => $delivered,
                'open_rate' => $total > 0 ? round(($opened / $total) * 100, 1) : 0,
                'click_rate' => $total > 0 ? round(($clicked / $total) * 100, 1) : 0,
                'bounce_rate' => $total > 0 ? round(($bounced / $total) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Get engagement scoring breakdown
     *
     * Groups subscribed users by their last open date:
     * - active: opened within 30 days
     * - inactive: opened 30-90 days ago
     * - dormant: opened 90+ days ago
     * - never_opened: no opens recorded
     *
     * @return array Engagement tier counts
     */
    public function getEngagementScoring(): array
    {
        global $wpdb;

        $subTable = EmailSchema::table(EmailSchema::TABLE_SUBSCRIBERS);
        $sendsTable = EmailSchema::table(EmailSchema::TABLE_SENDS);

        $results = $wpdb->get_results(
            "SELECT
                CASE
                    WHEN last_open IS NULL THEN 'never_opened'
                    WHEN last_open >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'active'
                    WHEN last_open >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 'inactive'
                    ELSE 'dormant'
                END AS tier,
                COUNT(*) AS count
             FROM (
                SELECT s.id, MAX(se.opened_at) AS last_open
                FROM {$subTable} s
                LEFT JOIN {$sendsTable} se ON se.subscriber_id = s.id
                WHERE s.status = 'subscribed'
                GROUP BY s.id
             ) AS engagement
             GROUP BY tier",
            ARRAY_A
        );

        $scoring = [
            'active' => 0,
            'inactive' => 0,
            'dormant' => 0,
            'never_opened' => 0,
        ];

        foreach ($results ?: [] as $row) {
            $scoring[$row['tier']] = (int) $row['count'];
        }

        return $scoring;
    }

    /**
     * Get recent sends grouped by blast (subject + date)
     *
     * @param int $limit Max number of blast rows to return
     * @return array Per-blast stats: subject, sent_at, total, opens, clicks, bounces
     */
    public function getRecentSends(int $limit = 20): array
    {
        global $wpdb;

        $sendsTable = EmailSchema::table(EmailSchema::TABLE_SENDS);

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                subject,
                DATE(sent_at) AS sent_at,
                COUNT(*) AS total,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opens,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS clicks,
                SUM(CASE WHEN bounced_at IS NOT NULL THEN 1 ELSE 0 END) AS bounces
             FROM {$sendsTable}
             GROUP BY subject, DATE(sent_at)
             ORDER BY DATE(sent_at) DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Generate an HMAC unsubscribe token for a subscriber email
     *
     * @param string $email Subscriber email
     * @return string HMAC-SHA256 hex token
     */
    public function generateUnsubscribeToken(string $email): string
    {
        $email = strtolower(trim($email));
        $settings = $this->getEmailSettings();
        $secret = $settings['unsubscribe_secret'] ?? wp_salt('auth');

        return hash_hmac('sha256', $email, $secret);
    }

    private function addToLists(int $subscriberId, array $listSlugs): void
    {
        global $wpdb;

        $listTable = EmailSchema::table(EmailSchema::TABLE_LISTS);
        $lsTable = EmailSchema::table(EmailSchema::TABLE_LIST_SUBSCRIBERS);

        foreach ($listSlugs as $slug) {
            $listId = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM {$listTable} WHERE slug = %s", $slug)
            );

            if (!$listId) {
                continue;
            }

            $wpdb->replace($lsTable, [
                'subscriber_id' => $subscriberId,
                'list_id' => (int) $listId,
                'subscribed_at' => current_time('mysql'),
            ]);
        }
    }

    private function sendConfirmationEmail(string $email, string $token): void
    {
        $settings = $this->getEmailSettings();
        $frontendUrl = rtrim($settings['frontend_url'] ?? 'https://bigbrotherjunkies.com', '/');
        $confirmUrl = $frontendUrl . '/email/confirm?token=' . urlencode($token);

        $subject = $settings['confirmation_subject'] ?? 'Confirm your BBJ subscription';
        $html = $this->buildConfirmationTemplate($confirmUrl);

        $client = new ResendClient();

        if (!$client->isConfigured()) {
            error_log('[BBJ Email] Cannot send confirmation — Resend API key not configured');
            return;
        }

        $resendId = $client->send($email, $subject, $html, [
            ['name' => 'category', 'value' => 'confirmation'],
        ]);

        if (!$resendId) {
            error_log("[BBJ Email] Failed to send confirmation to {$email}");
        }
    }

    private function buildConfirmationTemplate(string $confirmUrl): string
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
  <h2 style="margin:0 0 16px;color:#333333;font-size:22px;">Confirm Your Subscription</h2>
  <p style="margin:0 0 24px;color:#555555;font-size:16px;line-height:1.5;">
    Thanks for subscribing to Big Brother Junkies! Click the button below to confirm your email and start receiving updates.
  </p>
  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;">
  <tr>
    <td style="border-radius:6px;background-color:#35546e;">
      <a href="{$confirmUrl}" style="display:inline-block;padding:14px 32px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">
        Confirm Subscription
      </a>
    </td>
  </tr>
  </table>
  <p style="margin:24px 0 0;color:#999999;font-size:13px;line-height:1.4;">
    If you didn't request this, you can safely ignore this email.
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

    private function verifyUnsubscribeToken(string $email, string $token): bool
    {
        $expected = $this->generateUnsubscribeToken($email);
        return hash_equals($expected, $token);
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function getEmailSettings(): array
    {
        return get_option('bbjd_email_settings', []);
    }
}
