<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Pure helpers (no WordPress) for the Schedule / FB-insights feature: cache TTL
 * policy, merging our own queue rows with the live page feed, and shaping raw
 * Graph API metric payloads into a flat chip set. All wp_remote_get / transient
 * work lives in FbInsightsService — this class only transforms arrays.
 */
class InsightsLogic
{
    /** Days at or newer than this are still moving -> short cache. */
    public const FRESH_DAYS = 3;
    public const TTL_FRESH = 1800;   // 30 min
    public const TTL_STALE = 604800; // 7 days

    /**
     * Cache lifetime for a day's insight payload. Engagement keeps moving for a
     * few days then freezes, so recent days get a short TTL and older days a
     * week. Both args are 'Y-m-d'; boundary of exactly FRESH_DAYS counts as
     * fresh.
     */
    public static function cacheTtl(string $dayYmd, string $todayYmd): int
    {
        $tz    = new \DateTimeZone('UTC');
        $day   = \DateTimeImmutable::createFromFormat('Y-m-d', $dayYmd, $tz);
        $today = \DateTimeImmutable::createFromFormat('Y-m-d', $todayYmd, $tz);

        if ($day === false || $today === false) {
            return self::TTL_STALE;
        }

        $day   = $day->setTime(0, 0, 0);
        $today = $today->setTime(0, 0, 0);
        $ageDays = (int) $day->diff($today)->days;

        return $ageDays <= self::FRESH_DAYS ? self::TTL_FRESH : self::TTL_STALE;
    }

    /**
     * Merge our queue rows with the live page feed into one deduped, time-sorted
     * timeline. A post can appear in both (we posted it) or only in the feed
     * (posted by hand) - queue rows win on body/source, the feed contributes the
     * permalink.
     *
     * @param array<int,array{fb_post_id?:string,body?:string,posted_at?:string,source?:string}> $queueRows
     * @param array<int,array{id?:string,message?:string,created_time?:string,permalink_url?:string}> $fbPosts
     * @return array<int,array{fb_post_id:string,body:string,posted_at:string,source:string,permalink:?string}>
     */
    public static function mergePosts(array $queueRows, array $fbPosts): array
    {
        // Index the feed by id so queue rows can borrow the permalink.
        $fbById = [];
        foreach ($fbPosts as $p) {
            $id = (string) ($p['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $fbById[$id] = $p;
        }

        $merged = [];

        // Feed posts first (default source = posted by hand).
        foreach ($fbById as $id => $p) {
            $merged[$id] = [
                'fb_post_id' => $id,
                'body'       => (string) ($p['message'] ?? ''),
                'posted_at'  => self::isoToUtcMysql((string) ($p['created_time'] ?? '')),
                'source'     => 'manual_fb',
                'permalink'  => self::permalinkOrNull($p),
            ];
        }

        // Queue rows override: our body/source wins, permalink from the feed.
        foreach ($queueRows as $r) {
            $id = (string) ($r['fb_post_id'] ?? '');
            $fbRow = $id !== '' ? ($fbById[$id] ?? null) : null;
            $row = [
                'fb_post_id' => $id,
                'body'       => (string) ($r['body'] ?? ''),
                'posted_at'  => (string) ($r['posted_at'] ?? ''),
                'source'     => (string) ($r['source'] ?? 'manual_fb'),
                'permalink'  => $fbRow ? self::permalinkOrNull($fbRow) : null,
            ];
            // Keyless (empty fb_post_id) queue rows stay distinct.
            if ($id === '') {
                $merged[] = $row;
            } else {
                $merged[$id] = $row;
            }
        }

        $out = array_values($merged);
        usort($out, static fn($a, $b) => strcmp($a['posted_at'], $b['posted_at']));
        return $out;
    }

    /**
     * Flatten the two Graph payloads (an /insights response + a fields response)
     * into the six chips the UI shows, defaulting anything absent to 0.
     *
     * @return array{impressions:int,reach:int,clicks:int,reactions:int,comments:int,shares:int}
     */
    public static function shapeMetrics(array $insightsJson, array $fieldsJson): array
    {
        $byName = [];
        foreach ($insightsJson['data'] ?? [] as $metric) {
            $name = $metric['name'] ?? '';
            if ($name !== '') {
                $byName[$name] = (int) ($metric['values'][0]['value'] ?? 0);
            }
        }

        return [
            'impressions' => $byName['post_impressions'] ?? 0,
            'reach'       => $byName['post_impressions_unique'] ?? 0,
            'clicks'      => $byName['post_clicks'] ?? 0,
            'reactions'   => (int) ($fieldsJson['reactions']['summary']['total_count'] ?? 0),
            'comments'    => (int) ($fieldsJson['comments']['summary']['total_count'] ?? 0),
            'shares'      => (int) ($fieldsJson['shares']['count'] ?? 0),
        ];
    }

    /**
     * FB ISO8601 (2026-07-25T14:00:00+0000) -> UTC 'Y-m-d H:i:s'. Returns '' for
     * an unparseable value so it sorts to the front rather than throwing.
     */
    private static function isoToUtcMysql(string $iso): string
    {
        $iso = trim($iso);
        if ($iso === '') {
            return '';
        }
        try {
            $dt = new \DateTimeImmutable($iso);
        } catch (\Exception $e) {
            return '';
        }
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /**
     * @param array{permalink_url?:string} $fbRow
     */
    private static function permalinkOrNull(array $fbRow): ?string
    {
        $url = trim((string) ($fbRow['permalink_url'] ?? ''));
        return $url === '' ? null : $url;
    }
}
