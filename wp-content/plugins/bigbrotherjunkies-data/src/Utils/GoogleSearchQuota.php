<?php

namespace BigBrotherJunkies\Data\Utils;

/**
 * Track Google Custom Search API quota usage
 * Free tier: 100 queries/day
 */
class GoogleSearchQuota
{
    private const OPTION_KEY = 'bbj_google_search_quota';
    private const DAILY_LIMIT = 100;

    /**
     * Check if we can perform a search (quota not exceeded)
     */
    public static function canSearch(): bool
    {
        self::resetIfNewDay();
        $quota = self::getQuota();
        return $quota['count'] < self::DAILY_LIMIT;
    }

    /**
     * Increment the quota counter
     */
    public static function incrementQuota(): void
    {
        self::resetIfNewDay();
        $quota = self::getQuota();
        $quota['count']++;
        update_option(self::OPTION_KEY, $quota);
    }

    /**
     * Get remaining searches for today
     */
    public static function getRemaining(): int
    {
        self::resetIfNewDay();
        $quota = self::getQuota();
        return max(0, self::DAILY_LIMIT - $quota['count']);
    }

    /**
     * Get quota used today
     */
    public static function getUsed(): int
    {
        self::resetIfNewDay();
        $quota = self::getQuota();
        return $quota['count'];
    }

    /**
     * Get daily limit
     */
    public static function getLimit(): int
    {
        return self::DAILY_LIMIT;
    }

    /**
     * Reset quota if it's a new day
     */
    private static function resetIfNewDay(): void
    {
        $quota = self::getQuota();
        $today = date('Y-m-d');

        if ($quota['date'] !== $today) {
            update_option(self::OPTION_KEY, [
                'date' => $today,
                'count' => 0,
            ]);
        }
    }

    /**
     * Get current quota data
     */
    private static function getQuota(): array
    {
        $default = [
            'date' => date('Y-m-d'),
            'count' => 0,
        ];

        $quota = get_option(self::OPTION_KEY, $default);

        // Ensure proper structure
        if (!is_array($quota) || !isset($quota['date']) || !isset($quota['count'])) {
            return $default;
        }

        return $quota;
    }
}
