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
     * @return array Result with 'posted', 'url', 'error' keys
     */
    public function post(string $text, ?string $imageUrl = null): array
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

            // Prepare the post record
            $record = [
                '$type' => 'app.bsky.feed.post',
                'text' => $this->truncateText($text, 300),
                'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
            ];

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
