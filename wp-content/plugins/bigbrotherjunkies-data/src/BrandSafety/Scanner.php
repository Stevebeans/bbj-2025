<?php
namespace BigBrotherJunkies\Data\BrandSafety;

class Scanner
{
    /** Censor censor-tier terms in text nodes; report watch hits and unmaskable (in-tag) hits. */
    public static function censor(string $html): array
    {
        try {
            return self::run($html, true);
        } catch (\Throwable $e) {
            error_log('[bbjd brand-safety] scanner error: ' . $e->getMessage());
            return ['html' => $html, 'hits' => [], 'unmaskable' => false]; // fail open
        }
    }

    /** Read-only scan (dry-run/backfill reporting). */
    public static function scan(string $html): array
    {
        try {
            return self::run($html, false)['hits'];
        } catch (\Throwable $e) {
            error_log('[bbjd brand-safety] scanner error: ' . $e->getMessage());
            return [];
        }
    }

    private static function run(string $html, bool $mask): array
    {
        $tiers = Blocklist::getTiers();
        if (!$tiers) {
            return ['html' => $html, 'hits' => [], 'unmaskable' => false];
        }
        $regex = self::buildRegex(array_keys($tiers));
        $hits = [];
        $unmaskable = false;

        // Even indexes = text nodes, odd = tags (PREG_SPLIT_DELIM_CAPTURE keeps tags).
        $parts = preg_split('/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if ($part === '') {
                continue;
            }
            $isTag = ($i % 2) === 1;
            if ($isTag) {
                if (preg_match_all($regex, $part, $m)) {
                    foreach ($m[0] as $found) {
                        $term = self::normalize($found);
                        $tier = $tiers[$term] ?? 'censor';
                        if ($tier === 'off') {
                            continue;
                        }
                        $hits[] = ['term' => $term, 'tier' => $tier, 'action' => 'flagged'];
                        if ($tier === 'censor') {
                            $unmaskable = true;
                        }
                    }
                }
                continue;
            }
            $parts[$i] = preg_replace_callback($regex, function ($m) use ($tiers, &$hits, $mask) {
                $term = self::normalize($m[0]);
                $tier = $tiers[$term] ?? 'censor';
                if ($tier === 'off') {
                    return $m[0];
                }
                if ($tier === 'watch') {
                    $hits[] = ['term' => $term, 'tier' => $tier, 'action' => 'watched'];
                    return $m[0];
                }
                $hits[] = ['term' => $term, 'tier' => $tier, 'action' => 'censored'];
                return $mask ? self::mask($m[0]) : $m[0];
            }, $part);
        }

        return ['html' => implode('', $parts), 'hits' => $hits, 'unmaskable' => $unmaskable];
    }

    /** One cached alternation; longest terms first so phrases beat their substrings. */
    private static function buildRegex(array $terms): string
    {
        static $cache = null, $cacheKey = null;
        $key = md5(implode('|', $terms));
        if ($cache !== null && $cacheKey === $key) {
            return $cache;
        }
        usort($terms, fn($a, $b) => strlen($b) <=> strlen($a));
        $alts = array_map(
            fn($t) => preg_replace('/\\\\?\s+/', '\\s+', preg_quote($t, '/')),
            $terms
        );
        $cacheKey = $key;
        return $cache = '/(?<![\p{L}\p{N}])(?:' . implode('|', $alts) . ')(?![\p{L}\p{N}])/iu';
    }

    /** Collapse matched text back to its canonical blocklist form for tier lookup. */
    private static function normalize(string $found): string
    {
        return strtolower(preg_replace('/\s+/', ' ', $found));
    }

    /** bitch → b***h : keep first char, mask middle, keep last char masked-length-safe. */
    private static function mask(string $word): string
    {
        $len = mb_strlen($word);
        if ($len <= 2) {
            return str_repeat('*', $len);
        }
        return mb_substr($word, 0, 1) . str_repeat('*', $len - 2) . mb_substr($word, -1);
    }
}
