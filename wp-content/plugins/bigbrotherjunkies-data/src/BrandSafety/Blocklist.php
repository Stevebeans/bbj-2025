<?php
namespace BigBrotherJunkies\Data\BrandSafety;

class Blocklist
{
    public const OPTION = 'bbjd_brand_safety';

    /** Valid tiers for a term, in escalation order. */
    public const TIERS = ['censor', 'watch', 'off'];

    public const WATCH_DEFAULTS = [
        'ass', 'bet', 'bets', 'betting', 'boob', 'boobs', 'dick',
        'gun', 'guns', 'nude', 'sex', 'sexy', 'suck', 'sucks',
    ];

    private static ?array $tiers = null;

    /** @return array<string,string> term => censor|watch|off */
    public static function getTiers(): array
    {
        if (self::$tiers !== null) {
            return self::$tiers;
        }
        $file = dirname(__DIR__, 2) . '/data/brand-safety-blocklist.json';
        $terms = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($terms)) {
            return self::$tiers = []; // missing file → scanner no-ops (fail open)
        }
        $tiers = array_fill_keys(array_map('strtolower', $terms), 'censor');
        foreach (self::WATCH_DEFAULTS as $t) {
            if (isset($tiers[$t])) {
                $tiers[$t] = 'watch';
            }
        }
        $opt = get_option(self::OPTION, []);
        foreach (($opt['tier_overrides'] ?? []) as $term => $tier) {
            $term = strtolower($term);
            if (isset($tiers[$term]) && in_array($tier, self::TIERS, true)) {
                $tiers[$term] = $tier;
            }
        }
        return self::$tiers = $tiers;
    }

    public static function flushCache(): void
    {
        self::$tiers = null;
    }
}
