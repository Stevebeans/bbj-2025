<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Read-only client for the Bluesky public AppView (no auth).
 *
 * One page per source per poll (limit=100, no cursor walking) keeps volume
 * polite and well under a page at the 10-minute default cadence. Non-200 or
 * transport failures return an empty array and error_log, so a single bad
 * handle never aborts the caller.
 *
 * @see https://public.api.bsky.app/xrpc/
 */
class BlueskyReader
{
    private const BASE    = 'https://public.api.bsky.app/xrpc/';
    private const TIMEOUT = 20;

    /**
     * Author feed for one handle, replies and reposts filtered out by the
     * AppView (filter=posts_no_replies) and the parser (reposts).
     *
     * @return array<int,array<string,string>> normalized BlueskyParser rows
     */
    public static function authorFeed(string $handle): array
    {
        $handle = strtolower(ltrim(trim($handle), '@'));
        if ($handle === '') {
            return [];
        }

        $url = self::BASE . 'app.bsky.feed.getAuthorFeed?' . http_build_query([
            'actor'  => $handle,
            'limit'  => 100,
            'filter' => 'posts_no_replies',
        ]);

        $json = self::getJson($url, 'author feed ' . $handle);
        if ($json === null) {
            return [];
        }

        return BlueskyParser::fromAuthorFeed($json);
    }

    /**
     * Latest posts matching a hashtag / query string.
     *
     * @return array<int,array<string,string>> normalized BlueskyParser rows
     */
    public static function search(string $tag): array
    {
        $tag = trim($tag);
        if ($tag === '') {
            return [];
        }

        $url = self::BASE . 'app.bsky.feed.searchPosts?' . http_build_query([
            'q'     => $tag,
            'sort'  => 'latest',
            'limit' => 100,
        ]);

        $json = self::getJson($url, 'search ' . $tag);
        if ($json === null) {
            return [];
        }

        return BlueskyParser::fromSearch($json);
    }

    /**
     * GET + decode. Returns the decoded array or null on any failure.
     */
    private static function getJson(string $url, string $context): ?array
    {
        $response = wp_remote_get($url, [
            'timeout' => self::TIMEOUT,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            error_log('[BBJ Social] Bluesky ' . $context . ' error: ' . $response->get_error_message());
            return null;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            error_log("[BBJ Social] Bluesky {$context} HTTP {$status}");
            return null;
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($decoded)) {
            error_log("[BBJ Social] Bluesky {$context}: invalid JSON response");
            return null;
        }

        return $decoded;
    }
}
