<?php

namespace BigBrotherJunkies\Data\Billing;

/**
 * The Founding Junkie badge. One usermeta row per holder; era-stamped.
 * Deliberately NOT a role and NOT a bbj_subscriptions row - it's cosmetic,
 * must coexist with any subscription state, and survives cancellations.
 */
class FoundingBadge
{
    public const META        = 'bbjd_founding_badge';
    public const ERA         = 'bb28';
    public const PRICE_CENTS = 500;

    /** Idempotent. Returns true if the user holds the badge after the call. */
    public static function grant(int $userId, string $source): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if (self::has($userId)) {
            return true;
        }
        return (bool) update_user_meta($userId, self::META, wp_json_encode([
            'era'        => self::ERA,
            'source'     => $source,
            'granted_at' => gmdate('Y-m-d H:i:s'),
        ]));
    }

    /**
     * The single rule for "this purchase includes the badge": any annual plan
     * or Lifetime, bought while the window is open. Both entitlement paths
     * call this - createSubscription() for new buys and changePlan() for
     * switches - so the two cannot drift apart.
     */
    public static function maybeGrantForPlan(int $userId, string $planType): void
    {
        if (!self::qualifiesForPlan($planType)) {
            return;
        }
        if (!FoundersWindow::isOpen()) {
            return;
        }
        self::grant($userId, $planType);
    }

    /**
     * Pure half of maybeGrantForPlan() - plan eligibility, no window, no WP.
     *
     * Tier-blind by decision (2026-09-12): Full Bean Annual is the most
     * expensive plan on the site, so excluding it while a $45 All-Access
     * member got the badge was the wrong way round. `plan_type` is the
     * ENUM-safe value, so `full_bean_annual` arrives here as 'annual' and
     * `full_bean_monthly` as 'monthly', which still does not qualify.
     */
    public static function qualifiesForPlan(string $planType): bool
    {
        return in_array($planType, ['annual', 'lifetime'], true);
    }

    /**
     * Grant the badge to everyone who already paid for a qualifying plan
     * before the window opened. Run once, on the closed->open transition in
     * SubscriptionAdminRoutes::saveFounders(); grant() is idempotent, so a
     * repeat run is inert.
     *
     * Mirrors qualifiesForPlan(): any live annual subscription (either tier)
     * or any lifetime row.
     *
     * @return int badges actually granted
     */
    public static function backfillExisting(): int
    {
        global $wpdb;
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);

        $userIds = (array) $wpdb->get_col(
            "SELECT DISTINCT user_id FROM {$table}
              WHERE (plan_type = 'annual' AND status = 'active')
                 OR (plan_type = 'lifetime' AND status = 'lifetime')"
        );

        $granted = 0;
        foreach ($userIds as $rawId) {
            $userId = (int) $rawId;
            if ($userId <= 0 || self::has($userId)) {
                continue;
            }
            if (self::grant($userId, 'backfill')) {
                $granted++;
            }
        }

        error_log('BBJ Billing: founders backfill granted ' . $granted . ' badges');

        return $granted;
    }

    public static function has(int $userId): bool
    {
        return $userId > 0 && get_user_meta($userId, self::META, true) !== '';
    }

    public static function count(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s",
            self::META
        ));
    }
}
