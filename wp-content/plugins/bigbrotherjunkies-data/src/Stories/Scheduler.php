<?php

namespace BigBrotherJunkies\Data\Stories;

/** Action Scheduler wiring — mirrors BeanBotService's ensure/reschedule pattern. */
class Scheduler
{
    public const INGEST_HOOK = 'bbjd_stories_ingest';
    public const CYCLE_HOOK  = 'bbjd_stories_cycle'; // Batch B
    public const AS_GROUP    = 'bbjd-stories';
    public const INGEST_INTERVAL = 900; // 15 min

    /** Idempotent init-time guard. */
    public static function ensureScheduled(): void
    {
        if (!function_exists('as_schedule_recurring_action') || !function_exists('as_next_scheduled_action')) {
            return;
        }
        if (!Settings::enabledShows()) {
            return;
        }
        if (as_next_scheduled_action(self::INGEST_HOOK) === false) {
            as_schedule_recurring_action(time() + 60, self::INGEST_INTERVAL, self::INGEST_HOOK, [], self::AS_GROUP, true);
        }
    }

    /** Clear + re-schedule from current settings (called by Settings::saveSettings). */
    public static function reschedule(): void
    {
        if (!function_exists('as_unschedule_all_actions')) {
            return;
        }
        as_unschedule_all_actions(self::INGEST_HOOK, [], self::AS_GROUP);
        if (!Settings::enabledShows() || !function_exists('as_schedule_recurring_action')) {
            return;
        }
        as_schedule_recurring_action(time() + 60, self::INGEST_INTERVAL, self::INGEST_HOOK, [], self::AS_GROUP, true);
    }

    /** Never throws into Action Scheduler. */
    public static function cronIngest(): void
    {
        try {
            Ingest::run();
        } catch (\Throwable $e) {
            error_log('[BBJ Stories] ingest error: ' . $e->getMessage());
        }
    }
}
