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
