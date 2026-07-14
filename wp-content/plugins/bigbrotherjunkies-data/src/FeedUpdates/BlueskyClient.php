<?php

namespace BigBrotherJunkies\Data\FeedUpdates;

/**
 * Bluesky API Client
 *
 * Posts to Bluesky using the AT Protocol.
 * Credentials are stored in WordPress options.
 */
class BlueskyClient
{
    private const API_BASE = 'https://bsky.social/xrpc';
    private const CHAR_LIMIT = 300;
    private const LINK_DISPLAY = 'bigbrotherjunkies.com';

    /** Cache for the read access JWT (shared with BlueskyReader's authed search). */
    public const TOKEN_TRANSIENT = 'bbjd_bsky_access_token';

    private string $handle;
    private string $appPassword;
    private ?string $accessToken = null;
    private ?string $did = null;

    public function __construct()
    {
        $options = get_option('bbjd_social_settings', []);
        $this->handle = $options['bluesky_handle'] ?? '';
        $this->appPassword = $options['bluesky_app_password'] ?? '';
    }

    /**
     * Check if client is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->handle) && !empty($this->appPassword);
    }

    /**
     * Post to Bluesky
     *
     * @param string $text The text content to post
     * @param string|null $imageUrl Optional image URL to attach
     * @param string|null $linkUrl Optional URL back to the site (rendered as a clickable domain)
     * @return array Result with 'posted', 'url', 'error' keys
     */
    public function post(string $text, ?string $imageUrl = null, ?string $linkUrl = null): array
    {
        if (!$this->isConfigured()) {
            return [
                'posted' => false,
                'url' => null,
                'error' => 'Bluesky credentials not configured',
            ];
        }

        try {
            // Authenticate
            $this->authenticate();

            // Prepare the post record (hashtags + link line survive truncation)
            $composed = $this->composeText($text, $linkUrl);
            $record = [
                '$type' => 'app.bsky.feed.post',
                'text' => $composed,
                'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
            ];

            // Rich-text facets make hashtags and the site link clickable
            $facets = $this->buildFacets($composed, $linkUrl);
            if ($facets) {
                $record['facets'] = $facets;
            }

            // Handle image if provided
            if ($imageUrl) {
                $blob = $this->uploadImage($imageUrl);
                if ($blob) {
                    $record['embed'] = [
                        '$type' => 'app.bsky.embed.images',
                        'images' => [
                            [
                                'alt' => 'Feed update image',
                                'image' => $blob,
                            ],
                        ],
                    ];
                }
            }

            // Create the post
            $response = $this->request('com.atproto.repo.createRecord', [
                'repo' => $this->did,
                'collection' => 'app.bsky.feed.post',
                'record' => $record,
            ]);

            if (isset($response['uri'])) {
                // Convert AT URI to web URL
                $webUrl = $this->atUriToWebUrl($response['uri']);

                return [
                    'posted' => true,
                    'url' => $webUrl,
                    'error' => null,
                ];
            }

            return [
                'posted' => false,
                'url' => null,
                'error' => 'Failed to create post',
            ];
        } catch (\Exception $e) {
            return [
                'posted' => false,
                'url' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtain a read access JWT for authenticated GET calls (e.g. searchPosts),
     * reusing the brand account's stored credentials. Cached in a transient for
     * 30 minutes. Returns null when unconfigured or session creation fails, so
     * callers can fall back to the public AppView. Never returns the password;
     * the JWT is a short-lived access token, not the credential.
     */
    public function getAccessToken(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $cached = get_transient(self::TOKEN_TRANSIENT);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = $this->request('com.atproto.server.createSession', [
                'identifier' => $this->handle,
                'password'   => $this->appPassword,
            ], false);
        } catch (\Exception $e) {
            return null;
        }

        $token = $response['accessJwt'] ?? null;
        if (!is_string($token) || $token === '') {
            return null;
        }

        set_transient(self::TOKEN_TRANSIENT, $token, 30 * MINUTE_IN_SECONDS);
        return $token;
    }

    /**
     * Authenticate with Bluesky
     */
    private function authenticate(): void
    {
        $response = $this->request('com.atproto.server.createSession', [
            'identifier' => $this->handle,
            'password' => $this->appPassword,
        ], false);

        if (!isset($response['accessJwt']) || !isset($response['did'])) {
            throw new \Exception('Bluesky authentication failed');
        }

        $this->accessToken = $response['accessJwt'];
        $this->did = $response['did'];
    }

    /**
     * Upload an image to Bluesky
     */
    private function uploadImage(string $imageUrl): ?array
    {
        // Download the image
        $imageData = @file_get_contents($imageUrl);
        if (!$imageData) {
            return null;
        }

        // Detect mime type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($imageData);

        // Upload to Bluesky
        $response = wp_remote_post(self::API_BASE . '/com.atproto.repo.uploadBlob', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => $mimeType,
            ],
            'body' => $imageData,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body['blob'] ?? null;
    }

    /**
     * Make an API request
     */
    private function request(string $endpoint, array $data, bool $auth = true): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if ($auth && $this->accessToken) {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        $response = wp_remote_post(self::API_BASE . '/' . $endpoint, [
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode >= 400) {
            $error = $body['message'] ?? $body['error'] ?? 'API request failed';
            throw new \Exception($error);
        }

        return $body;
    }

    /**
     * Convert AT URI to web URL
     */
    private function atUriToWebUrl(string $atUri): string
    {
        // at://did:plc:xxx/app.bsky.feed.post/yyy -> https://bsky.app/profile/handle/post/yyy
        if (preg_match('/at:\/\/([^\/]+)\/app\.bsky\.feed\.post\/(.+)/', $atUri, $matches)) {
            return "https://bsky.app/profile/{$this->handle}/post/{$matches[2]}";
        }
        return $atUri;
    }

    /**
     * Compose the final post text within CHAR_LIMIT.
     * Trailing hashtags and the appended link line are peeled off first so
     * truncation only ever shortens the message body, never the tag or link.
     */
    private function composeText(string $text, ?string $linkUrl): string
    {
        $text = rtrim($text);

        // Peel trailing hashtag block (e.g. " #BB28" or " #BB28 #BigBrother")
        $tags = '';
        if (preg_match('/(\s+(?:#\w+\s*)+)$/u', $text, $m)) {
            $tags = ' ' . trim($m[1]);
            $text = rtrim(mb_substr($text, 0, mb_strlen($text) - mb_strlen($m[1])));
        }

        $suffix = $tags . ($linkUrl ? "\n" . self::LINK_DISPLAY : '');
        $budget = self::CHAR_LIMIT - mb_strlen($suffix);

        return $this->truncateText($text, $budget) . $suffix;
    }

    /**
     * Build rich-text facets (byte-offset annotations) for hashtags and the
     * site link. Without these, Bluesky renders them as plain dead text.
     */
    private function buildFacets(string $text, ?string $linkUrl): array
    {
        $facets = [];

        // Hashtags -> tag facets (preg offsets are byte offsets, as required)
        if (preg_match_all('/#(\w+)/u', $text, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $i => $match) {
                $facets[] = [
                    'index' => [
                        'byteStart' => $match[1],
                        'byteEnd' => $match[1] + strlen($match[0]),
                    ],
                    'features' => [[
                        '$type' => 'app.bsky.richtext.facet#tag',
                        'tag' => $m[1][$i][0],
                    ]],
                ];
            }
        }

        // The appended domain -> link facet pointing at the full update URL
        if ($linkUrl) {
            $pos = strrpos($text, self::LINK_DISPLAY);
            if ($pos !== false) {
                $facets[] = [
                    'index' => [
                        'byteStart' => $pos,
                        'byteEnd' => $pos + strlen(self::LINK_DISPLAY),
                    ],
                    'features' => [[
                        '$type' => 'app.bsky.richtext.facet#link',
                        'uri' => $linkUrl,
                    ]],
                ];
            }
        }

        return $facets;
    }

    /**
     * Truncate text to fit Bluesky's character limit
     */
    private function truncateText(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return mb_substr($text, 0, $limit - 3) . '...';
    }
}
