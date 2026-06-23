<?php

namespace BigBrotherJunkies\Data\Bean;

/**
 * Ask the Bean — tier resolution + daily usage metering.
 *
 * Tiers (spec-aligned):
 *   free       — Haiku, small daily cap, no memory
 *   supporter  — Haiku, bigger daily cap (existing $6.95 role), no memory
 *   full_bean  — Sonnet, unlimited, memory ($14.99 tier; new role)
 *
 * Caps are admin-overridable via the `bbj_bean_settings` option.
 */
class BeanUsage
{
    /** Resolve a user's tier from their WordPress roles. */
    public static function tierForUser(int $userId): array
    {
        $user = get_userdata($userId);
        $roles = $user ? array_map('strval', (array) $user->roles) : [];
        $caps = self::caps();

        if (array_intersect($roles, ['full_bean', 'administrator', 'editor'])) {
            return ['id' => 'full_bean', 'cap' => $caps['full_bean'], 'memory' => true];
        }
        if (array_intersect($roles, ['supporter', 'lifetime'])) {
            return ['id' => 'supporter', 'cap' => $caps['supporter'], 'memory' => false];
        }
        return ['id' => 'free', 'cap' => $caps['free'], 'memory' => false];
    }

    /** Daily caps (0 = unlimited). Admin-overridable. */
    public static function caps(): array
    {
        $opt = get_option('bbj_bean_settings', []);
        return [
            'free'      => (int) ($opt['cap_free'] ?? 5),
            'supporter' => (int) ($opt['cap_supporter'] ?? 30),
            'full_bean' => (int) ($opt['cap_full_bean'] ?? 0),
        ];
    }

    public static function usedToday(int $userId): int
    {
        global $wpdb;
        $t = BeanSchema::table(BeanSchema::TABLE_USAGE);
        $today = current_time('Y-m-d');
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT msg_count FROM {$t} WHERE user_id = %d AND usage_date = %s", $userId, $today)
        );
    }

    /** Atomically bump today's counter and return the new count. */
    public static function increment(int $userId): int
    {
        global $wpdb;
        $t = BeanSchema::table(BeanSchema::TABLE_USAGE);
        $today = current_time('Y-m-d');
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$t} (user_id, usage_date, msg_count) VALUES (%d, %s, 1)
                 ON DUPLICATE KEY UPDATE msg_count = msg_count + 1",
                $userId,
                $today
            )
        );
        return self::usedToday($userId);
    }

    /** Full state the app needs: tier, cap, used, remaining, allowed. */
    public static function snapshot(int $userId): array
    {
        $tier = self::tierForUser($userId);
        $used = self::usedToday($userId);
        $unlimited = $tier['cap'] <= 0;
        return [
            'tier'      => $tier['id'],
            'memory'    => $tier['memory'],
            'cap'       => $unlimited ? null : $tier['cap'],
            'used'      => $used,
            'remaining' => $unlimited ? null : max(0, $tier['cap'] - $used),
            'allowed'   => $unlimited || $used < $tier['cap'],
        ];
    }
}
