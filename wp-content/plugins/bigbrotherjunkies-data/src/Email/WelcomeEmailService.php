<?php

namespace BigBrotherJunkies\Data\Email;

/**
 * Builds and sends the three transactional emails:
 * confirmation, welcome (+ optional newsletter fragment), premium.
 * Templates editable in admin, stored in option 'bbjd_welcome_emails'.
 */
class WelcomeEmailService
{
    public const OPTION = 'bbjd_welcome_emails';

    /** Default templates seeded when option is empty. */
    public static function defaults(): array
    {
        return [
            'confirmation' => [
                'subject' => 'Confirm your email — Big Brother Junkies',
                'body_html' => '<p>Hi {{first_name}},</p><p>Thanks for registering at Big Brother Junkies! '
                    . 'Please confirm your email to activate your account.</p>'
                    . '<p><a href="{{confirm_url}}">Confirm my email</a></p>'
                    . '<p>If you didn\'t sign up, you can ignore this email.</p>',
            ],
            'welcome' => [
                'subject' => 'Welcome to Big Brother Junkies!',
                'body_html' => '<p>Hi {{first_name}},</p><p>Your email is confirmed — welcome to the BBJ community!</p>'
                    . '<p>Jump back in at <a href="{{site_url}}">{{site_url}}</a>.</p>',
            ],
            'welcome_newsletter_fragment' =>
                '<hr><p><strong>You\'re subscribed to post notifications.</strong> '
                . 'We\'ll email you when new posts go up. You can unsubscribe anytime via '
                . '<a href="{{settings_url}}">your settings</a> or the link at the bottom of any email.</p>',
            'premium' => [
                'subject' => 'Thank you for supporting Big Brother Junkies!',
                'body_html' => '<p>Hi {{first_name}},</p><p>Thank you for becoming a {{plan_name}} supporter — '
                    . 'your support keeps BBJ independent and ad-free for you.</p>'
                    . '<p><strong>Plan:</strong> {{plan_name}} ({{amount}})<br>'
                    . '<strong>Next billing:</strong> {{next_billing_date}}</p>'
                    . '<p>Manage or cancel anytime from <a href="{{manage_subscription_url}}">your subscription settings</a>.</p>',
            ],
        ];
    }

    /** Get templates merged over defaults. */
    public static function getTemplates(): array
    {
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) {
            $saved = [];
        }
        return array_replace_recursive(self::defaults(), $saved);
    }

    /** Persist templates (expects the same shape as defaults()). */
    public static function saveTemplates(array $templates): bool
    {
        return update_option(self::OPTION, $templates);
    }

    /**
     * Build the merge-tag map for a user (+ optional subscription row).
     * @param \WP_User $user
     * @param array|null $sub Subscription row (plan_type, amount_cents, current_period_end, processor)
     */
    public static function tagsFor(\WP_User $user, ?array $sub = null): array
    {
        $frontend = rtrim(get_option('bbjd_email_settings')['frontend_url'] ?? 'https://bigbrotherjunkies.com', '/');
        $first = get_user_meta($user->ID, 'first_name', true);
        $first = $first !== '' ? $first : $user->display_name;

        $planNames = ['monthly' => 'Monthly Supporter', 'annual' => 'Season Pass', 'lifetime' => 'Lifetime Member'];
        $planType = $sub['plan_type'] ?? '';
        $amount = isset($sub['amount_cents']) ? '$' . number_format(((int) $sub['amount_cents']) / 100, 2) : '';
        $nextBill = '';
        if (!empty($sub['current_period_end'])) {
            $nextBill = date_i18n('F j, Y', strtotime($sub['current_period_end']));
        } elseif (($sub['plan_type'] ?? '') === 'lifetime') {
            $nextBill = 'Never — lifetime access';
        }

        return [
            '{{first_name}}'   => esc_html($first),
            '{{display_name}}' => esc_html($user->display_name),
            '{{site_url}}'     => esc_url($frontend),
            '{{current_season}}' => esc_html((string) get_option('bbjd_current_season_label', '')),
            '{{plan_name}}'    => esc_html($planNames[$planType] ?? $planType),
            '{{amount}}'       => esc_html($amount),
            '{{next_billing_date}}' => esc_html($nextBill),
            '{{settings_url}}' => esc_url($frontend . '/settings'),
            '{{manage_subscription_url}}' => esc_url($frontend . '/settings?tab=premium'),
            '{{cancel_url}}'   => esc_url($frontend . '/settings?tab=premium'),
            '{{unsubscribe_url}}' => esc_url($frontend . '/unsubscribe?email=' . rawurlencode($user->user_email)),
        ];
    }

    /** Replace tags in a string. Unknown tags collapse to empty. */
    public static function render(string $html, array $tags): string
    {
        $out = strtr($html, $tags);
        // Collapse any leftover {{...}} tags to empty so nothing leaks.
        return preg_replace('/\{\{[a-z_]+\}\}/', '', $out);
    }

    /** Wrap body HTML in the fixed branded BBJ shell. */
    public static function wrap(string $bodyHtml): string
    {
        $year = date_i18n('Y');
        return '<!DOCTYPE html><html><body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;">'
            . '<div style="max-width:600px;margin:0 auto;background:#ffffff;">'
            . '<div style="background:#35546e;padding:20px;text-align:center;">'
            . '<a href="https://bigbrotherjunkies.com" style="color:#FFBF0F;font-size:22px;font-weight:bold;text-decoration:none;">Big Brother Junkies</a></div>'
            . '<div style="padding:24px;color:#1e293b;font-size:15px;line-height:1.6;">' . $bodyHtml . '</div>'
            . '<div style="padding:16px 24px;background:#f8fafc;color:#64748b;font-size:12px;text-align:center;">'
            . '&copy; ' . $year . ' Big Brother Junkies &middot; '
            . '<a href="{{unsubscribe_url}}" style="color:#64748b;">Unsubscribe</a></div>'
            . '</div></body></html>';
    }

    /** Send the account-confirmation email. */
    public static function sendConfirmation(int $userId, string $confirmUrl): void
    {
        $u = get_user_by('ID', $userId);
        if (!$u) { return; }
        $t = self::getTemplates()['confirmation'];
        $tags = self::tagsFor($u);
        $tags['{{confirm_url}}'] = esc_url($confirmUrl);
        self::dispatch($u->user_email, $t['subject'], $t['body_html'], $tags, 'confirmation');
    }

    /** Send the welcome email (optionally appending the newsletter fragment). */
    public static function sendWelcome(int $userId, bool $includeNewsletter): void
    {
        $u = get_user_by('ID', $userId);
        if (!$u) { return; }
        $tpl = self::getTemplates();
        $body = $tpl['welcome']['body_html'];
        if ($includeNewsletter) {
            $body .= $tpl['welcome_newsletter_fragment'];
        }
        self::dispatch($u->user_email, $tpl['welcome']['subject'], $body, self::tagsFor($u), 'welcome');
    }

    /** Send the premium welcome/receipt email once per subscription. */
    public static function sendPremium(int $userId, array $sub): void
    {
        $u = get_user_by('ID', $userId);
        if (!$u) { return; }
        // Idempotency guard: never send twice for the same subscription row.
        if (!empty($sub['id'])) {
            $flagKey = 'bbjd_premium_email_sent_' . (int) $sub['id'];
            if (get_option($flagKey)) { return; }
        }
        $t = self::getTemplates()['premium'];
        self::dispatch($u->user_email, $t['subject'], $t['body_html'], self::tagsFor($u, $sub), 'premium');
        if (!empty($sub['id'])) {
            update_option('bbjd_premium_email_sent_' . (int) $sub['id'], 1, false);
        }
    }

    /** Render a preview (subject + wrapped HTML) for the admin "send test" path. */
    public static function renderFor(string $which, \WP_User $u, ?array $sub = null): array
    {
        $tpl = self::getTemplates();
        $tags = self::tagsFor($u, $sub);
        if ($which === 'confirmation') {
            $tags['{{confirm_url}}'] = esc_url(($tags['{{site_url}}'] ?? '') . '/register/confirm?token=PREVIEW');
            return ['subject' => self::render($tpl['confirmation']['subject'], $tags),
                    'html' => self::render(self::wrap($tpl['confirmation']['body_html']), $tags)];
        }
        if ($which === 'welcome') {
            $body = $tpl['welcome']['body_html'] . $tpl['welcome_newsletter_fragment'];
            return ['subject' => self::render($tpl['welcome']['subject'], $tags),
                    'html' => self::render(self::wrap($body), $tags)];
        }
        // premium
        return ['subject' => self::render($tpl['premium']['subject'], $tags),
                'html' => self::render(self::wrap($tpl['premium']['body_html']), $tags)];
    }

    /** Core send: render body in shell, resolve tags, send via Resend. Best-effort. */
    private static function dispatch(string $to, string $subject, string $bodyHtml, array $tags, string $category): void
    {
        try {
            $client = new ResendClient();
            if (!$client->isConfigured()) {
                error_log('[BBJ WelcomeEmail] Resend not configured; skipping ' . $category);
                return;
            }
            $subjectOut = self::render($subject, $tags);
            $htmlOut = self::render(self::wrap($bodyHtml), $tags);
            $id = $client->send($to, $subjectOut, $htmlOut, [['name' => 'category', 'value' => $category]]);
            if (!$id) {
                error_log("[BBJ WelcomeEmail] send failed: {$category} -> {$to}");
            }
        } catch (\Throwable $e) {
            error_log('[BBJ WelcomeEmail] exception: ' . $e->getMessage());
        }
    }
}
