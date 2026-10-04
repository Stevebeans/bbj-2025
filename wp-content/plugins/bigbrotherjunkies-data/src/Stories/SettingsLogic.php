<?php

namespace BigBrotherJunkies\Data\Stories;

/** Pure normalizers for the bbjd_stories option. No WP calls (unit-tested). */
class SettingsLogic
{
    public const TRUST_TIERS   = ['trusted', 'standard', 'low'];
    public const SOURCE_TYPES  = ['reddit', 'rss', 'instagram', 'bluesky'];
    public const PUBLISH_MODES = ['inbox_only', 'engine'];
    public const FB_PUSH_MODES = ['confirmed_only', 'all', 'none'];

    public const SHOW_DEFAULTS = [
        'enabled'         => false,
        'label'           => '',
        'publish_mode'    => 'inbox_only',
        'auto_enabled'    => false,
        'max_per_day'     => 12,
        'min_gap_minutes' => 30,
        'quiet_from'      => '',
        'quiet_to'        => '',
        'fb_push'         => 'confirmed_only',
        'fb_max_per_day'  => 6,
    ];

    public static function slug(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim($s, '-');
    }

    /** @return array<string,array<string,mixed>> keyed by slug */
    public static function normalizeShows(array $shows): array
    {
        $out = [];
        foreach ($shows as $rawSlug => $cfg) {
            $slug = self::slug((string) $rawSlug);
            if ($slug === '' || !is_array($cfg)) {
                continue;
            }
            $c = array_merge(self::SHOW_DEFAULTS, $cfg);
            $out[$slug] = [
                'enabled'         => filter_var($c['enabled'], FILTER_VALIDATE_BOOLEAN),
                'label'           => trim((string) $c['label']) !== '' ? trim((string) $c['label']) : trim((string) $rawSlug),
                'publish_mode'    => in_array($c['publish_mode'], self::PUBLISH_MODES, true) ? $c['publish_mode'] : 'inbox_only',
                'auto_enabled'    => filter_var($c['auto_enabled'], FILTER_VALIDATE_BOOLEAN),
                'max_per_day'     => max(1, min(48, (int) $c['max_per_day'])),
                'min_gap_minutes' => max(5, min(240, (int) $c['min_gap_minutes'])),
                'quiet_from'      => self::hhmm((string) $c['quiet_from']),
                'quiet_to'        => self::hhmm((string) $c['quiet_to']),
                'fb_push'         => in_array($c['fb_push'], self::FB_PUSH_MODES, true) ? $c['fb_push'] : 'confirmed_only',
                'fb_max_per_day'  => max(0, min(48, (int) $c['fb_max_per_day'])),
            ];
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public static function normalizeSources(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $show = self::slug((string) ($row['show'] ?? ''));
            $type = (string) ($row['type'] ?? '');
            $key  = self::normalizeKey($type, (string) ($row['key'] ?? ''));
            if ($show === '' || !in_array($type, self::SOURCE_TYPES, true) || $key === '') {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if (!preg_match('/^[a-f0-9]{12}$/', $id)) {
                $id = substr(bin2hex(random_bytes(6)), 0, 12);
            }
            $trust = (string) ($row['trust'] ?? 'standard');
            $out[] = [
                'id'      => $id,
                'show'    => $show,
                'type'    => $type,
                'key'     => $key,
                'trust'   => in_array($trust, self::TRUST_TIERS, true) ? $trust : 'standard',
                'flair'   => trim((string) ($row['flair'] ?? '')),
                'enabled' => array_key_exists('enabled', $row) ? filter_var($row['enabled'], FILTER_VALIDATE_BOOLEAN) : true,
            ];
        }
        return $out;
    }

    private static function normalizeKey(string $type, string $key): string
    {
        $key = trim($key);
        switch ($type) {
            case 'reddit':
                $key = preg_replace('#^(https?://(www\.)?reddit\.com)?/?r/#i', '', $key) ?? $key;
                $key = trim($key, '/');
                return preg_match('/^[A-Za-z0-9_]{2,30}$/', $key) ? $key : '';
            case 'instagram':
            case 'bluesky':
                $key = ltrim($key, '@');
                return preg_match('/^[A-Za-z0-9._-]{1,64}$/', $key) ? $key : '';
            case 'rss':
                return filter_var($key, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $key) ? $key : '';
        }
        return '';
    }

    private static function hhmm(string $v): string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : '';
    }
}
