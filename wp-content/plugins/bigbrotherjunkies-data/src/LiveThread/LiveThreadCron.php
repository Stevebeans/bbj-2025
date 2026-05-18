<?php

namespace BigBrotherJunkies\Data\LiveThread;

/**
 * Checks every 5 minutes whether the currently-active live thread has passed
 * its `live_end` timestamp. If so, closes it.
 *
 * Defense-in-depth: the state derivation in LiveThreadState::getState() also
 * treats past-end threads as closed. This cron is what stamps _bbjd_closed_at
 * and clears the global option so the rest of the site updates.
 */
class LiveThreadCron
{
    public const HOOK = 'bbjd_live_thread_autoclose';

    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 60, 'five_minutes', self::HOOK);
        }
    }

    public static function unschedule(): void
    {
        $next = wp_next_scheduled(self::HOOK);
        if ($next) {
            wp_unschedule_event($next, self::HOOK);
        }
    }

    public static function registerInterval(array $schedules): array
    {
        if (!isset($schedules['five_minutes'])) {
            $schedules['five_minutes'] = [
                'interval' => 5 * 60,
                'display'  => 'Every 5 Minutes',
            ];
        }
        return $schedules;
    }

    public static function run(): void
    {
        $activeId = (int) get_option(LiveThreadState::OPTION_ACTIVE, 0);
        if ($activeId <= 0) {
            return;
        }

        $end = (int) get_post_meta($activeId, LiveThreadState::META_LIVE_END, true);
        // 0 = continuous; do nothing
        if ($end === 0) {
            return;
        }

        if (time() > $end) {
            LiveThreadState::closeThread($activeId);
        }
    }
}
