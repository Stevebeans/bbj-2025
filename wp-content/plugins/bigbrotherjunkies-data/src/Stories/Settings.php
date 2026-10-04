<?php

namespace BigBrotherJunkies\Data\Stories;

/**
 * The bbjd_stories option. Two write paths, Bean Bot style:
 *  - saveSettings(): the five USER keys only (a running job can never
 *    resurrect a setting Steve changed mid-flight).
 *  - persistServiceState(): the SERVICE keys only (re-reads fresh at write
 *    time so a mid-cycle settings change is never clobbered).
 */
class Settings
{
    public const OPTION = 'bbjd_stories';

    public const USER_KEYS    = ['paused', 'ig_enabled', 'ig_user_id', 'shows', 'sources'];
    public const SERVICE_KEYS = ['ig_last_polled', 'reddit_backoff_until', 'cycle'];

    public const DEFAULTS = [
        'paused'               => false,
        'ig_enabled'           => false,
        'ig_user_id'           => '',
        'shows'                => [],
        'sources'              => [],
        'ig_last_polled'       => [],   // username => unix ts
        'reddit_backoff_until' => 0,    // unix ts
        'cycle'                => [],   // show_slug => service state (Batch B)
    ];

    public static function settings(): array
    {
        $stored = get_option(self::OPTION, []);
        return array_merge(self::DEFAULTS, is_array($stored) ? $stored : []);
    }

    /** Whitelist + normalize a patch, persist, reschedule, return merged. */
    public static function saveSettings(array $patch): array
    {
        $current = self::settings();
        foreach (self::USER_KEYS as $k) {
            if (!array_key_exists($k, $patch)) {
                continue;
            }
            switch ($k) {
                case 'paused':
                case 'ig_enabled':
                    $current[$k] = filter_var($patch[$k], FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'ig_user_id':
                    $current[$k] = preg_replace('/\D/', '', (string) $patch[$k]) ?? '';
                    break;
                case 'shows':
                    $current[$k] = SettingsLogic::normalizeShows(is_array($patch[$k]) ? $patch[$k] : []);
                    break;
                case 'sources':
                    $current[$k] = SettingsLogic::normalizeSources(is_array($patch[$k]) ? $patch[$k] : []);
                    break;
            }
        }
        update_option(self::OPTION, $current);
        Scheduler::reschedule();
        return $current;
    }

    public static function persistServiceState(array $keys): void
    {
        $current = self::settings();
        foreach (self::SERVICE_KEYS as $k) {
            if (array_key_exists($k, $keys)) {
                $current[$k] = $keys[$k];
            }
        }
        update_option(self::OPTION, $current);
    }

    /** @return array<string,array> enabled shows keyed by slug ([] when paused) */
    public static function enabledShows(): array
    {
        $s = self::settings();
        if (!empty($s['paused'])) {
            return [];
        }
        return array_filter((array) $s['shows'], static fn($cfg) => !empty($cfg['enabled']));
    }

    /** @return array<int,array> enabled sources for one show */
    public static function sourcesFor(string $show): array
    {
        return array_values(array_filter(
            (array) self::settings()['sources'],
            static fn($src) => $src['show'] === $show && !empty($src['enabled'])
        ));
    }
}
