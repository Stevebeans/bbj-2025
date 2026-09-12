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
     * The single rule for "this purchase includes the badge": All-Access
     * (annual) and Lifetime, supporter tier, bought while the window is open.
     * Both entitlement paths call this - createSubscription() for new buys and
     * changePlan() for switches - so the two cannot drift apart.
     */
    public static function maybeGrantForPlan(int $userId, string $planType, string $tier = 'supporter'): void
    {
        if (!self::qualifiesForPlan($planType, $tier)) {
            return;
        }
        if (!FoundersWindow::isOpen()) {
            return;
        }
        self::grant($userId, $planType);
    }

    /** Pure half of maybeGrantForPlan() - plan/tier eligibility, no window, no WP. */
    public static function qualifiesForPlan(string $planType, string $tier = 'supporter'): bool
    {
        return in_array($planType, ['annual', 'lifetime'], true) && $tier === 'supporter';
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
