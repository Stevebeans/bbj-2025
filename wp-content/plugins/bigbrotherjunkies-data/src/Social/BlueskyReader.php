<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Read-only client for the Bluesky public AppView (no auth).
 *
 * One page per source per poll (limit=100, no cursor walking) keeps volume
 * polite and well under a page at the 10-minute default cadence. Non-200 or
 * transport failures are error_log'd AND returned as an `error` string
 * alongside the (empty) rows, so a single bad handle never aborts the
 * caller but a typo'd handle or an outage is still visible in the poll
 * result rather than silently reporting "0 posts".
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
     * @return array{rows:array<int,array<string,string>>,error:?string}
     */
    public static function authorFeed(string $handle): array
    {
        $handle = strtolower(ltrim(trim($handle), '@'));
        if ($handle === '') {
            return ['rows' => [], 'error' => null];
        }

        $url = self::BASE . 'app.bsky.feed.getAuthorFeed?' . http_build_query([
            'actor'  => $handle,
            'limit'  => 100,
            'filter' => 'posts_no_replies',
        ]);

        [$json, $error] = self::getJson($url, 'author feed @' . $handle);
        if ($json === null) {
            return ['rows' => [], 'error' => $error];
        }

        return ['rows' => BlueskyParser::fromAuthorFeed($json), 'error' => null];
    }

    /**
     * Latest posts matching a hashtag / query string.
     *
     * @return array{rows:array<int,array<string,string>>,error:?string}
     */
    public static function search(string $tag): array
    {
        $tag = trim($tag);
        if ($tag === '') {
            return ['rows' => [], 'error' => null];
        }

        $url = self::BASE . 'app.bsky.feed.searchPosts?' . http_build_query([
            'q'     => $tag,
            'sort'  => 'latest',
            'limit' => 100,
        ]);

        [$json, $error] = self::getJson($url, 'search ' . $tag);
        if ($json === null) {
            return ['rows' => [], 'error' => $error];
        }

        return ['rows' => BlueskyParser::fromSearch($json), 'error' => null];
    }

    /**
     * GET + decode. Returns [decoded array, null] on success or [null, error
     * message] on any failure. The error message names the source
     * (`$context`) and the failure kind only; it never includes an API key
     * (this client sends none) or the raw response body.
     *
     * @return array{0:?array,1:?string}
     */
    private static function getJson(string $url, string $context): array
    {
        $response = wp_remote_get($url, [
            'timeout' => self::TIMEOUT,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            error_log('[BBJ Social] Bluesky ' . $context . ' error: ' . $message);
            return [null, "{$context}: {$message}"];
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            error_log("[BBJ Social] Bluesky {$context} HTTP {$status}");
            return [null, "{$context}: HTTP {$status}"];
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($decoded)) {
            error_log("[BBJ Social] Bluesky {$context}: invalid JSON response");
            return [null, "{$context}: invalid JSON response"];
        }

        return [$decoded, null];
    }
}
