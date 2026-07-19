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
                'body_html' => '<p>Hi {{first_name}},</p>'
                    . '<p>I want to say a genuine <strong>thank you</strong>. Big Brother Junkies has always been a '
                    . 'labor of love, and supporters like you are the only reason it can stay independent, ad-free, '
                    . 'and run by actual fans instead of a corporation. It truly means the world to me.</p>'
                    . '<p>As a <strong>{{plan_name}}</strong>, here\'s everything you now have access to:</p>'
                    . '<ul style="padding-left:20px;margin:16px 0;">'
                    . '<li style="margin-bottom:8px;"><strong>Ad-free everywhere</strong> — browse the entire site with zero ads getting in the way.</li>'
                    . '<li style="margin-bottom:8px;"><strong>Interactive Player Map</strong> — heatmaps, state stats, nearest-player finder, and season timelines.</li>'
                    . '<li style="margin-bottom:8px;"><strong>Player Comparisons</strong> — head-to-head stat breakdowns, HoH/PoV charts, and performance analysis.</li>'
                    . '<li style="margin-bottom:8px;"><strong>Ask the Bean</strong> — {{bean_line}}</li>'
                    . '<li style="margin-bottom:8px;"><strong>Exclusive supporter badge</strong> — a little something next to your name so everyone knows you\'ve got our back.</li>'
                    . '<li style="margin-bottom:8px;"><strong>Independent BB coverage</strong> — you\'re directly keeping quality Big Brother content alive and corporate-free.</li>'
                    . '</ul>'
                    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;">'
                    . '<tr><td style="background:#f8fafc;border-left:3px solid #FFBF0F;padding:12px 16px;font-size:14px;color:#1e293b;">'
                    . '<strong>Your plan:</strong> {{plan_name}} ({{amount}})<br>'
                    . '<strong>Next billing:</strong> {{next_billing_date}}</td></tr></table>'
                    . '<p>You can manage or cancel anytime from <a href="{{manage_subscription_url}}" style="color:#35546e;">your subscription settings</a>.</p>'
                    . '<p>Seriously — thank you for being part of this. Now go enjoy that ad-free experience.</p>'
                    . '<p style="margin-bottom:0;">— Steve<br><span style="color:#64748b;">Big Brother Junkies</span></p>',
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
        $settings = get_option('bbjd_email_settings', []);
        $frontend = rtrim($settings['frontend_url'] ?? 'https://bigbrotherjunkies.com', '/');
        $first = get_user_meta($user->ID, 'first_name', true);
        $first = $first !== '' ? $first : $user->display_name;

        $planType = $sub['plan_type'] ?? '';
        $planName = $planType !== ''
            ? \BigBrotherJunkies\Data\Billing\SubscriptionManager::displayName($planType, $sub['tier'] ?? 'supporter')
            : '';
        $amount = isset($sub['amount_cents']) ? '$' . number_format(((int) $sub['amount_cents']) / 100, 2) : '';
        $nextBill = '';
        if (!empty($sub['current_period_end'])) {
            $nextBill = date_i18n('F j, Y', strtotime($sub['current_period_end']));
        } elseif (($sub['plan_type'] ?? '') === 'lifetime') {
            $nextBill = 'Never — lifetime access';
        }

        // Tier-aware Bean perk line ({{bean_line}} in the premium template).
        $beanLine = ($sub['tier'] ?? '') === 'full_bean'
            ? 'unlimited chats on our smartest model, and he remembers what matters about you between conversations.'
            : '30 chats a day with our Big Brother AI - he reads every feed report and remembers the whole season.';

        return [
            '{{bean_line}}'    => $beanLine,
            '{{first_name}}'   => esc_html($first),
            '{{display_name}}' => esc_html($user->display_name),
            '{{site_url}}'     => esc_url($frontend),
            '{{current_season}}' => esc_html((string) get_option('bbjd_current_season_label', '')),
            '{{plan_name}}'    => esc_html($planName),
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

    /**
     * Wrap body HTML in the fixed branded BBJ shell.
     *
     * Header = white bar with the site wordmark logo (matches the site header,
     * which is also white — the logo's dark-blue "Junkies" text needs a light bg).
     * Footer carries both {{settings_url}} (manage account/notifications) and
     * {{unsubscribe_url}}.
     *
     * NOTE: the footer's {{settings_url}}/{{unsubscribe_url}} are intentionally
     * resolved by the caller's render() pass (both ARE in the tagsFor() map), so
     * wrap() must always be followed by render($html, tagsFor(...)).
     */
    public static function wrap(string $bodyHtml): string
    {
        $year = date_i18n('Y');
        // WP-hosted, stable absolute URL (same asset the site header uses).
        // Native 395x37; displayed at 260px wide. width/height attrs keep Outlook honest.
        $logo = 'https://bigbrotherjunkies.com/wp-content/themes/BBJ/images/bbjlogo2020.png';
        return '<!DOCTYPE html><html><body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;">'
            . '<div style="max-width:600px;margin:0 auto;background:#ffffff;">'
            . '<div style="background:#ffffff;padding:20px 24px;text-align:center;border-bottom:1px solid #e2e8f0;">'
            . '<a href="https://bigbrotherjunkies.com" style="text-decoration:none;">'
            . '<img src="' . $logo . '" alt="Big Brother Junkies" width="260" height="24" '
            . 'style="display:inline-block;border:0;outline:none;width:260px;height:auto;max-width:100%;"></a></div>'
            . '<div style="padding:24px;color:#1e293b;font-size:15px;line-height:1.6;">' . $bodyHtml . '</div>'
            . '<div style="padding:16px 24px;background:#f8fafc;color:#64748b;font-size:12px;text-align:center;line-height:1.7;">'
            . '&copy; ' . $year . ' Big Brother Junkies<br>'
            . '<a href="{{settings_url}}" style="color:#64748b;">Manage account &amp; notifications</a> &middot; '
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
        // Idempotency guard: claim the send slot BEFORE dispatching so concurrent
        // webhook retries can't both pass the check and double-send.
        if (!empty($sub['id'])) {
            $flagKey = 'bbjd_premium_email_sent_' . (int) $sub['id'];
            if (get_option($flagKey)) { return; }
            update_option($flagKey, 1, false);
        }
        $t = self::getTemplates()['premium'];
        self::dispatch($u->user_email, $t['subject'], $t['body_html'], self::tagsFor($u, $sub), 'premium');
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
        // These are transactional emails (confirmation / welcome / premium receipt)
        // and intentionally do NOT honor the bulk-newsletter `pause_sending` switch —
        // pausing the newsletter must not break signup confirmation or premium receipts.
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
