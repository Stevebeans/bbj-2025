<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Fetches a day's Facebook page posts and their per-post performance, merged
 * with our own content queue, and caches the shaped payload per day. All Graph
 * API and transient access lives here; the transforms are pure in InsightsLogic.
 *
 * Degrades cleanly: with no page token (local/dev) or on any FB error it returns
 * the queue rows we already have with metrics => null plus an insights_error
 * string, and does NOT cache that partial result.
 */
class FbInsightsService
{
    private const API_BASE      = 'https://graph.facebook.com/v21.0';
    private const CACHE_PREFIX  = 'bbjd_fb_day_insights_';
    private const HTTP_TIMEOUT  = 20;

    /**
     * Posts in [$fromUtc, $toUtc) with metrics, merged over our queue.
     *
     * @param string $fromUtc UTC 'Y-m-d H:i:s'
     * @param string $toUtc   UTC 'Y-m-d H:i:s'
     * @return array{items:array<int,array>,insights_error:?string}
     */
    public function dayPosts(string $fromUtc, string $toUtc): array
    {
        $dayKey   = substr($fromUtc, 0, 10); // Y-m-d
        $cacheKey = self::CACHE_PREFIX . $dayKey;

        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $queueRows = $this->postedQueueRows($fromUtc, $toUtc);

        [$pageId, $token] = $this->pageCredentials();
        if (!$pageId || !$token) {
            // Degraded path: no token locally. Queue rows only, uncached.
            return $this->queueOnly($queueRows, 'No Facebook page token configured');
        }

        $feed = $this->fetchFeed($pageId, $token, $fromUtc, $toUtc);
        if ($feed['error'] !== null) {
            return $this->queueOnly($queueRows, $feed['error']);
        }

        // Per-post metrics (one bad post never kills the day).
        $metricsById = [];
        foreach ($feed['posts'] as $post) {
            $id = (string) ($post['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $metricsById[$id] = $this->fetchPostMetrics($id, $token);
        }

        $merged = InsightsLogic::mergePosts($queueRows, $feed['posts']);
        foreach ($merged as &$item) {
            $item['metrics'] = $metricsById[$item['fb_post_id']] ?? null;
        }
        unset($item);

        $result = ['items' => $merged, 'insights_error' => null];

        $todayYmd = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        set_transient($cacheKey, $result, InsightsLogic::cacheTtl($dayKey, $todayYmd));

        return $result;
    }

    /**
     * Queue rows we posted (have an fb_post_id) whose posted_at is in the window.
     *
     * @return array<int,array{fb_post_id:string,body:string,posted_at:string,source:string}>
     */
    private function postedQueueRows(string $fromUtc, string $toUtc): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_content_queue';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT fb_post_id, body, posted_at, source
               FROM {$table}
              WHERE status = 'posted'
                AND fb_post_id IS NOT NULL AND fb_post_id != ''
                AND posted_at >= %s AND posted_at < %s
              ORDER BY posted_at ASC",
            $fromUtc,
            $toUtc
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<int,array> $queueRows
     * @return array{items:array<int,array>,insights_error:?string}
     */
    private function queueOnly(array $queueRows, string $error): array
    {
        $merged = InsightsLogic::mergePosts($queueRows, []);
        foreach ($merged as &$item) {
            $item['metrics'] = null;
        }
        unset($item);

        return ['items' => $merged, 'insights_error' => $error];
    }

    /**
     * @return array{0:string,1:string} [pageId, token] - '' when unset.
     */
    private function pageCredentials(): array
    {
        $settings = get_option('bbjd_content_engine_settings', []);
        $page = $settings['facebook_pages'][0] ?? [];
        return [(string) ($page['id'] ?? ''), (string) ($page['token'] ?? '')];
    }

    /**
     * One call for the day's published posts.
     *
     * @return array{posts:array<int,array>,error:?string}
     */
    private function fetchFeed(string $pageId, string $token, string $fromUtc, string $toUtc): array
    {
        $tz    = new \DateTimeZone('UTC');
        $since = (new \DateTimeImmutable($fromUtc, $tz))->getTimestamp();
        $until = (new \DateTimeImmutable($toUtc, $tz))->getTimestamp();

        $url = self::API_BASE . "/{$pageId}/published_posts?" . http_build_query([
            'since'        => $since,
            'until'        => $until,
            'fields'       => 'id,message,created_time,permalink_url,attachments{media_type}',
            'limit'        => 100,
            'access_token' => $token,
        ]);

        $json = $this->getJson($url);
        if ($json['error'] !== null) {
            return ['posts' => [], 'error' => $json['error']];
        }

        return ['posts' => $json['data']['data'] ?? [], 'error' => null];
    }

    /**
     * Insights + fields for one post, shaped. Errors degrade to zeros rather
     * than aborting the whole day.
     *
     * @return array{impressions:int,reach:int,clicks:int,reactions:int,comments:int,shares:int}
     */
    private function fetchPostMetrics(string $postId, string $token): array
    {
        $insightsUrl = self::API_BASE . "/{$postId}/insights?" . http_build_query([
            'metric'       => 'post_impressions,post_impressions_unique,post_clicks',
            'access_token' => $token,
        ]);
        $fieldsUrl = self::API_BASE . "/{$postId}?" . http_build_query([
            'fields'       => 'reactions.summary(true),comments.summary(true),shares',
            'access_token' => $token,
        ]);

        $insights = $this->getJson($insightsUrl);
        $fields   = $this->getJson($fieldsUrl);

        return InsightsLogic::shapeMetrics(
            $insights['error'] === null ? $insights['data'] : [],
            $fields['error'] === null ? $fields['data'] : []
        );
    }

    /**
     * GET + decode JSON with WP-error, HTTP-status and Graph-error handling.
     *
     * @return array{data:array,error:?string}
     */
    private function getJson(string $url): array
    {
        $response = wp_remote_get($url, ['timeout' => self::HTTP_TIMEOUT]);

        if (is_wp_error($response)) {
            return ['data' => [], 'error' => $response->get_error_message()];
        }

        $body   = json_decode(wp_remote_retrieve_body($response), true);
        $status = (int) wp_remote_retrieve_response_code($response);

        if (!is_array($body)) {
            return ['data' => [], 'error' => 'Invalid Facebook response'];
        }
        if (isset($body['error'])) {
            return ['data' => [], 'error' => (string) ($body['error']['message'] ?? 'Facebook API error')];
        }
        if ($status >= 400) {
            return ['data' => [], 'error' => "Facebook API request failed ({$status})"];
        }

        return ['data' => $body, 'error' => null];
    }
}
