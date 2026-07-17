<?php

namespace BigBrotherJunkies\Data\Bean;

/**
 * Ask the Bean — per-user evolving memory note (Full Bean cross-session memory).
 * One row per user in bbj_bean_memory.
 */
class BeanMemory
{
    /** Server-side backstop on stored length (chars). */
    public const MAX_LEN = 4000;

    public static function get(int $userId): string
    {
        global $wpdb;
        $t = BeanSchema::table(BeanSchema::TABLE_MEMORY);
        $val = $wpdb->get_var(
            $wpdb->prepare("SELECT summary FROM {$t} WHERE user_id = %d", $userId)
        );
        return is_string($val) ? $val : '';
    }

    public static function set(int $userId, string $summary): void
    {
        global $wpdb;
        $t = BeanSchema::table(BeanSchema::TABLE_MEMORY);
        $summary = mb_substr($summary, 0, self::MAX_LEN);
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$t} (user_id, summary) VALUES (%d, %s)
                 ON DUPLICATE KEY UPDATE summary = VALUES(summary)",
                $userId,
                $summary
            )
        );
    }

    public static function clear(int $userId): void
    {
        global $wpdb;
        $t = BeanSchema::table(BeanSchema::TABLE_MEMORY);
        $wpdb->delete($t, ['user_id' => $userId], ['%d']);
    }

    /**
     * Shared "site facts" note — Steve's admin-curated knowledge injected into
     * EVERY user's Bean chats. Lives in the same table as the reserved row
     * user_id = 0 (no real WP user has id 0), written only via the admin-gated
     * facts endpoint.
     */
    public const SHARED_ID = 0;

    public static function getShared(): string
    {
        return self::get(self::SHARED_ID);
    }

    public static function setShared(string $facts): void
    {
        self::set(self::SHARED_ID, $facts);
    }
}
