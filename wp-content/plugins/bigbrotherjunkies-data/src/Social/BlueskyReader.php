<?php

namespace BigBrotherJunkies\Data\Social;

use BigBrotherJunkies\Data\FeedUpdates\BlueskyClient;

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
    private const BASE      = 'https://public.api.bsky.app/xrpc/';
    private const AUTH_BASE  = 'https://bsky.social/xrpc/';
    private const TIMEOUT   = 20;

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

        $params = http_build_query([
            'q'     => $tag,
            'sort'  => 'latest',
            'limit' => 100,
        ]);

        // Prefer an authenticated search: the public AppView returns HTTP 403
        // from datacenter IPs. Reuse the brand account's stored credentials via
        // FeedUpdates\BlueskyClient (single credential source, never duplicated).
        // Returns null only when there are no usable credentials, in which case
        // we fall back to the public request exactly as before.
        $authed = self::authedSearch($tag, $params);
        if ($authed !== null) {
            return $authed;
        }

        $url = self::BASE . 'app.bsky.feed.searchPosts?' . $params;

        [$json, $error] = self::getJson($url, 'search ' . $tag);
        if ($json === null) {
            return ['rows' => [], 'error' => $error];
        }

        return ['rows' => BlueskyParser::fromSearch($json), 'error' => null];
    }

    /**
     * Authenticated tag search against the AppView entry on bsky.social, using
     * a bearer JWT from the brand account. Returns null when no token is
     * available (unconfigured or session creation failed) so the caller falls
     * back to the public path. On a 401 the cached token is stale, so it is
     * dropped and the search is retried ONCE with a fresh token. On any other
     * failure the returned error names the authed path (e.g.
     * "search #BB28 (authed): HTTP 403"). Never logs or returns the JWT.
     *
     * @return array{rows:array<int,array<string,string>>,error:?string}|null
     */
    private static function authedSearch(string $tag, string $params): ?array
    {
        $client = new BlueskyClient();
        $token  = $client->getAccessToken();
        if ($token === null) {
            return null; // no usable credentials -> caller uses the public path
        }

        $url     = self::AUTH_BASE . 'app.bsky.feed.searchPosts?' . $params;
        $context = 'search ' . $tag . ' (authed)';

        [$json, $error, $status] = self::getJsonAuthed($url, $token, $context);

        // A 401 means the cached token is stale/revoked: drop it, mint a fresh
        // one, and retry a single time before giving up.
        if ($status === 401) {
            delete_transient(BlueskyClient::TOKEN_TRANSIENT);
            $token = $client->getAccessToken();
            if ($token === null) {
                return ['rows' => [], 'error' => $error];
            }
            [$json, $error] = self::getJsonAuthed($url, $token, $context);
        }

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

    /**
     * Authenticated variant of getJson: adds a Bearer token and also surfaces
     * the HTTP status (so the caller can detect a 401 and retry with a fresh
     * token). The token is sent only in the Authorization header; it is never
     * logged or returned.
     *
     * @return array{0:?array,1:?string,2:?int}
     */
    private static function getJsonAuthed(string $url, string $token, string $context): array
    {
        $response = wp_remote_get($url, [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            error_log('[BBJ Social] Bluesky ' . $context . ' error: ' . $message);
            return [null, "{$context}: {$message}", null];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            error_log("[BBJ Social] Bluesky {$context} HTTP {$status}");
            return [null, "{$context}: HTTP {$status}", $status];
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($decoded)) {
            error_log("[BBJ Social] Bluesky {$context}: invalid JSON response");
            return [null, "{$context}: invalid JSON response", $status];
        }

        return [$decoded, null, $status];
    }
}
