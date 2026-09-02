<?php

namespace BigBrotherJunkies\Data\Stories\Sources;

use BigBrotherJunkies\Data\Stories\Settings;

/**
 * Meta Graph Business Discovery: public media of any IG Business/Creator
 * account, read through OUR IG Business user id + the page token that the
 * content engine already stores. Never scrapes. Never rehosts on ingest.
 *
 * Inert until Settings ig_enabled=true (flip after Meta approves the
 * business-discovery permission on the app that owns the page token).
 */
class InstagramAdapter implements SourceAdapter
{
    private const API_BASE      = 'https://graph.facebook.com/v21.0';
    private const MEDIA_FIELDS  = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count';
    private const PER_HANDLE_MIN_SECONDS = 1800; // 30 min; 200 calls/hr budget

    public function fetch(array $source): array
    {
        $settings = Settings::settings();
        if (empty($settings['ig_enabled'])) {
            // Ingest::run() skips instagram sources entirely while disabled, so
            // this branch is only reached via Ingest::testSource() (the Sources
            // "Test" button), which needs an explanatory message rather than a
            // silent "no items returned".
            return ['items' => [], 'error' => 'instagram: disabled until Meta approves Business Discovery (Settings → Instagram enabled)'];
        }
        $igUserId = (string) $settings['ig_user_id'];
        if ($igUserId === '') {
            return ['items' => [], 'error' => 'instagram: ig_user_id not set (Settings → Resolve)'];
        }
        $handle = $source['key'];
        $last   = (int) (($settings['ig_last_polled'][$handle] ?? 0));
        if ($last > time() - self::PER_HANDLE_MIN_SECONDS) {
            // Throttled, not an error — and silent (not just non-fatal) because
            // this fires on most 15-minute ingest cycles (30-min per-handle
            // floor), so surfacing it would spam the run log every cycle.
            return ['items' => [], 'error' => null];
        }
        [, $token] = self::pageCredentials();
        if ($token === '') {
            return ['items' => [], 'error' => 'instagram: no page token in content engine settings'];
        }
        $url = self::API_BASE . '/' . rawurlencode($igUserId) . '?' . http_build_query([
            'fields'       => 'business_discovery.username(' . $handle . '){media.limit(25){' . self::MEDIA_FIELDS . '}}',
            'access_token' => $token,
        ]);
        $res = wp_remote_get($url, ['timeout' => 20]);
        $polled = $settings['ig_last_polled'];
        $polled[$handle] = time();
        Settings::persistServiceState(['ig_last_polled' => $polled]);

        if (is_wp_error($res)) {
            return ['items' => [], 'error' => 'instagram @' . $handle . ': ' . $res->get_error_message()];
        }
        $json = json_decode((string) wp_remote_retrieve_body($res), true);
        if (!is_array($json)) {
            return ['items' => [], 'error' => 'instagram @' . $handle . ': bad JSON'];
        }
        if (isset($json['error'])) {
            return ['items' => [], 'error' => 'instagram @' . $handle . ': ' . (string) ($json['error']['message'] ?? 'Graph error')];
        }
        return ['items' => self::parse($json, $source), 'error' => null];
    }

    /** @return array<int,array<string,mixed>> */
    public static function parse(array $json, array $source): array
    {
        $media = $json['business_discovery']['media']['data'] ?? null;
        if (!is_array($media)) {
            return [];
        }
        $handle = (string) ($json['business_discovery']['username'] ?? $source['key']);
        $out = [];
        foreach ($media as $m) {
            $permalink = (string) ($m['permalink'] ?? '');
            if ($permalink === '') {
                continue;
            }
            $caption = trim((string) ($m['caption'] ?? ''));
            $type    = (string) ($m['media_type'] ?? '');
            $mediaType = $type === 'VIDEO' ? 'video' : ($type === 'CAROUSEL_ALBUM' ? 'carousel' : 'image');
            $mediaUrl  = (string) ($m['media_url'] ?? '');
            $thumb     = $mediaType === 'video' ? (string) ($m['thumbnail_url'] ?? '') : $mediaUrl;
            $ts        = strtotime((string) ($m['timestamp'] ?? ''));
            $likes     = (int) ($m['like_count'] ?? 0);
            $comments  = (int) ($m['comments_count'] ?? 0);
            $out[] = [
                'show'          => $source['show'],
                'source'        => 'instagram',
                'source_key'    => $handle,
                'url'           => $permalink,
                'title'         => self::titleFromCaption($caption, $handle),
                'excerpt'       => mb_substr($caption, 0, 1200),
                'author'        => '@' . $handle,
                'media_type'    => $mediaType,
                'media_url'     => $mediaUrl,
                'thumbnail_url' => $thumb,
                'published_at'  => gmdate('Y-m-d H:i:s', $ts !== false ? $ts : time()),
                'trust'         => $source['trust'] ?? 'standard',
                'score'         => $likes + $comments * 3,
            ];
        }
        return $out;
    }

    /** First line of the caption without hashtags (max 140 chars); fallback names the account. */
    public static function titleFromCaption(string $caption, string $handle): string
    {
        $c = preg_replace('/\s*#\w+/u', '', $caption) ?? $caption;
        $c = trim(preg_split('/\r?\n/', $c)[0] ?? '');
        if ($c === '') {
            return 'Instagram post by @' . $handle;
        }
        return mb_substr($c, 0, 140);
    }

    /** @return array{id:?string,error:?string} */
    public static function resolveIgUserId(string $pageId, string $token): array
    {
        $res = wp_remote_get(self::API_BASE . '/' . rawurlencode($pageId) . '?' . http_build_query([
            'fields' => 'instagram_business_account', 'access_token' => $token,
        ]), ['timeout' => 15]);
        if (is_wp_error($res)) {
            return ['id' => null, 'error' => $res->get_error_message()];
        }
        $json = json_decode((string) wp_remote_retrieve_body($res), true);
        $id = $json['instagram_business_account']['id'] ?? null;
        return $id ? ['id' => (string) $id, 'error' => null]
                   : ['id' => null, 'error' => (string) ($json['error']['message'] ?? 'No instagram_business_account on this page')];
    }

    /**
     * Re-fetch one media's CDN links (they expire) by permalink — the item
     * stores the permalink as its url, not the IG media id.
     * @return array{media_url:?string,thumbnail_url:?string,error:?string}
     */
    public static function mediaFor(string $username, string $permalink): array
    {
        $settings = Settings::settings();
        [, $token] = self::pageCredentials();
        $url = self::API_BASE . '/' . rawurlencode((string) $settings['ig_user_id']) . '?' . http_build_query([
            'fields'       => 'business_discovery.username(' . $username . '){media.limit(25){id,media_url,thumbnail_url,permalink}}',
            'access_token' => $token,
        ]);
        $res  = wp_remote_get($url, ['timeout' => 20]);
        $json = is_wp_error($res) ? null : json_decode((string) wp_remote_retrieve_body($res), true);
        foreach ($json['business_discovery']['media']['data'] ?? [] as $m) {
            if ((string) ($m['permalink'] ?? '') === $permalink) {
                return ['media_url' => $m['media_url'] ?? null, 'thumbnail_url' => $m['thumbnail_url'] ?? null, 'error' => null];
            }
        }
        return ['media_url' => null, 'thumbnail_url' => null, 'error' => 'media not found in the latest 25'];
    }

    /** @return array{0:string,1:string} [pageId, token] */
    public static function pageCredentials(): array
    {
        $s    = get_option('bbjd_content_engine_settings', []);
        $page = $s['facebook_pages'][0] ?? [];
        return [(string) ($page['id'] ?? ''), (string) ($page['token'] ?? '')];
    }
}
