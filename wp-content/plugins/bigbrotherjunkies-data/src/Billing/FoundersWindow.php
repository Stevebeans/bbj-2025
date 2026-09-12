<?php

namespace BigBrotherJunkies\Data\Billing;

/**
 * The Founders launch window: gates the Founding Junkie badge and the
 * Lifetime revival. One option, read-heavy, written from admin only.
 * All datetimes UTC 'Y-m-d H:i:s'.
 */
class FoundersWindow
{
    public const OPTION = 'bbjd_founders_window';

    public const DEFAULTS = [
        'enabled'      => false,
        'opened_at'    => '',
        'closes_at'    => '',
        'lifetime_cap' => 50,
    ];

    public static function settings(): array
    {
        $saved = get_option(self::OPTION, []);
        return array_merge(self::DEFAULTS, is_array($saved) ? $saved : []);
    }

    public static function isOpen(?int $nowTs = null): bool
    {
        return self::isOpenPure(self::settings(), $nowTs ?? time());
    }

    /** Pure so it can be unit-tested without WP. Garbage close date fails OPEN. */
    public static function isOpenPure(array $s, int $nowTs): bool
    {
        if (empty($s['enabled'])) {
            return false;
        }
        $closes = (string) ($s['closes_at'] ?? '');
        if ($closes === '') {
            return true;
        }
        $closesTs = strtotime($closes . ' UTC');
        return $closesTs === false ? true : $nowTs < $closesTs;
    }

    /** Lifetime rows created during THIS window (legacy lifetimers don't count against the cap). */
    public static function lifetimeSpotsSold(): int
    {
        global $wpdb;
        $s = self::settings();
        if ($s['opened_at'] === '') {
            return 0;
        }
        $table = BillingSchema::table(BillingSchema::TABLE_SUBSCRIPTIONS);
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE plan_type = 'lifetime' AND status = 'lifetime' AND created_at >= %s",
            $s['opened_at']
        ));
    }

    public static function lifetimeSpotsLeft(): int
    {
        $s = self::settings();
        return max(0, (int) $s['lifetime_cap'] - self::lifetimeSpotsSold());
    }
}
