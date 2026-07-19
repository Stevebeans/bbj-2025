<?php

namespace BigBrotherJunkies\Data\Cron;

use BigBrotherJunkies\Data\Billing\BillingSchema;
use BigBrotherJunkies\Data\Billing\SubscriptionManager;

/**
 * Hourly sweep: subscriptions canceled at period end (Stripe OR PayPal)
 * whose paid period has now lapsed flip to 'expired', which removes the
 * supporter/full_bean role. Stripe usually beats us to it via the
 * customer.subscription.deleted webhook; this is the safety net and the
 * ONLY expiry path for PayPal period-end cancels (2026-07-18 funnel fix —
 * PayPal cancels used to strip access immediately, contradicting the FAQ).
 */
class BillingExpiryCron
{
    public const HOOK = 'bbjd_billing_expiry_check';

    public function init(): void
    {
        add_action('init', [$this, 'schedule']);
        add_action(self::HOOK, [$this, 'expireLapsed']);
    }

    public function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::HOOK);
        }
    }

    public function expireLapsed(): void
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);
        $rows  = $wpdb->get_results(
            "SELECT id FROM {$table}
              WHERE status = 'active' AND cancel_at_period_end = 1
                AND current_period_end IS NOT NULL AND current_period_end < UTC_TIMESTAMP()",
            ARRAY_A
        ) ?: [];
        if (!$rows) {
            return;
        }
        $manager = SubscriptionManager::getInstance();
        foreach ($rows as $row) {
            $manager->updateSubscriptionStatus((int) $row['id'], 'expired');
            error_log('BBJ Billing: subscription ' . $row['id'] . ' expired at period end');
        }
    }
}
