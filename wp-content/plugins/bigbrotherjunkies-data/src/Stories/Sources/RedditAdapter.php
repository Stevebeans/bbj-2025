<?php

namespace BigBrotherJunkies\Data\Stories\Sources;

use BigBrotherJunkies\Data\Stories\Settings;

/**
 * Public listing JSON (no OAuth). Reddit rate-limits anonymous clients by
 * User-Agent + IP (~10 req/min); a 429 sets a 15-minute backoff shared by
 * every subreddit source.
 */
class RedditAdapter implements SourceAdapter
{
    private const BACKOFF_SECONDS = 900;
    private const UA = 'BigBrotherJunkiesStories/1.0 (+https://bigbrotherjunkies.com)';

    public function fetch(array $source): array
    {
        $settings = Settings::settings();
        if ((int) $settings['reddit_backoff_until'] > time()) {
            return ['items' => [], 'error' => 'reddit r/' . $source['key'] . ': backing off until ' . gmdate('H:i', (int) $settings['reddit_backoff_until']) . ' UTC'];
        }
        $url = 'https://www.reddit.com/r/' . rawurlencode($source['key']) . '/new.json?limit=50&raw_json=1';
        $res = wp_remote_get($url, ['timeout' => 15, 'user-agent' => self::UA]);
        if (is_wp_error($res)) {
            return ['items' => [], 'error' => 'reddit r/' . $source['key'] . ': ' . $res->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code === 429) {
            Settings::persistServiceState(['reddit_backoff_until' => time() + self::BACKOFF_SECONDS]);
            return ['items' => [], 'error' => 'reddit r/' . $source['key'] . ': 429, backing off 15 min'];
        }
        if ($code !== 200) {
            return ['items' => [], 'error' => 'reddit r/' . $source['key'] . ': HTTP ' . $code];
        }
        $json = json_decode((string) wp_remote_retrieve_body($res), true);
        return ['items' => self::parse(is_array($json) ? $json : [], $source), 'error' => null];
    }

    /** @return array<int,array<string,mixed>> */
    public static function parse(array $json, array $source): array
    {
        $children = $json['data']['children'] ?? null;
        if (!is_array($children)) {
            return [];
        }
        $flairs = array_values(array_filter(array_map(
            static fn($f) => strtolower(trim($f)),
            explode(',', (string) ($source['flair'] ?? ''))
        )));
        $out = [];
        foreach ($children as $child) {
            $d = $child['data'] ?? null;
            if (!is_array($d) || empty($d['permalink'])) {
                continue;
            }
            if ($flairs && !in_array(strtolower(trim((string) ($d['link_flair_text'] ?? ''))), $flairs, true)) {
                continue;
            }
            $isSelf  = !empty($d['is_self']);
            $outUrl  = (string) ($d['url'] ?? '');
            $excerpt = $isSelf ? (string) ($d['selftext'] ?? '') : $outUrl;
            $thumb   = (string) ($d['thumbnail'] ?? '');
            if (!preg_match('#^https?://#', $thumb)) {
                $thumb = '';
            }
            $hint = (string) ($d['post_hint'] ?? '');
            $out[] = [
                'show'          => $source['show'],
                'source'        => 'reddit',
                'source_key'    => $source['key'],
                'url'           => 'https://www.reddit.com' . $d['permalink'],
                'title'         => mb_substr(trim((string) ($d['title'] ?? '')), 0, 255),
                'excerpt'       => mb_substr(trim($excerpt), 0, 1200),
                'author'        => 'u/' . (string) ($d['author'] ?? ''),
                'media_type'    => $hint === 'image' ? 'image' : (in_array($hint, ['hosted:video', 'rich:video'], true) ? 'video' : ''),
                'media_url'     => $hint === 'image' ? $outUrl : '',
                'thumbnail_url' => $thumb,
                'published_at'  => gmdate('Y-m-d H:i:s', (int) ($d['created_utc'] ?? time())),
                'trust'         => $source['trust'] ?? 'standard',
                'score'         => (int) ($d['score'] ?? 0),
            ];
        }
        return $out;
    }
}
