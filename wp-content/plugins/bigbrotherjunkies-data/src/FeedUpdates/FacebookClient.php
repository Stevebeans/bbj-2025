<?php

namespace BigBrotherJunkies\Data\FeedUpdates;

/**
 * Facebook Graph API Client
 *
 * Posts to a Facebook Page using the Graph API.
 * Requires a Page Access Token with pages_manage_posts permission.
 */
class FacebookClient
{
    private const API_BASE = 'https://graph.facebook.com/v18.0';

    private string $pageId;
    private string $pageToken;

    public function __construct()
    {
        $options = get_option('bbjd_social_settings', []);
        $this->pageId = $options['facebook_page_id'] ?? '';
        $this->pageToken = $options['facebook_page_token'] ?? '';
    }

    /**
     * Check if client is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->pageId) && !empty($this->pageToken);
    }

    /**
     * Post to Facebook Page
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
                'error' => 'Facebook credentials not configured',
            ];
        }

        try {
            if ($imageUrl) {
                return $this->postWithPhoto($text, $imageUrl);
            }

            return $this->postText($text);
        } catch (\Exception $e) {
            return [
                'posted' => false,
                'url' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Post text-only to Facebook
     */
    private function postText(string $text): array
    {
        $response = wp_remote_post(self::API_BASE . "/{$this->pageId}/feed", [
            'body' => [
                'message' => $text,
                'access_token' => $this->pageToken,
            ],
            'timeout' => 15,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Post with a photo to Facebook
     */
    private function postWithPhoto(string $text, string $imageUrl): array
    {
        $response = wp_remote_post(self::API_BASE . "/{$this->pageId}/photos", [
            'body' => [
                'message' => $text,
                'url' => $imageUrl,
                'access_token' => $this->pageToken,
            ],
            'timeout' => 30,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Handle API response
     */
    private function handleResponse($response): array
    {
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode >= 400) {
            $error = $body['error']['message'] ?? 'Facebook API request failed';
            throw new \Exception($error);
        }

        $postId = $body['id'] ?? $body['post_id'] ?? null;

        if ($postId) {
            // For photos, the ID format is "pageId_photoId", extract the post ID
            $webUrl = "https://facebook.com/{$postId}";

            return [
                'posted' => true,
                'url' => $webUrl,
                'error' => null,
            ];
        }

        return [
            'posted' => false,
            'url' => null,
            'error' => 'Failed to create post - no ID returned',
        ];
    }

    /**
     * Verify the access token is valid
     */
    public function verifyToken(): array
    {
        if (!$this->isConfigured()) {
            return [
                'valid' => false,
                'error' => 'Credentials not configured',
            ];
        }

        $response = wp_remote_get(self::API_BASE . "/me?access_token={$this->pageToken}");

        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return [
                'valid' => false,
                'error' => $body['error']['message'] ?? 'Token invalid',
            ];
        }

        return [
            'valid' => true,
            'page_name' => $body['name'] ?? 'Unknown',
            'page_id' => $body['id'] ?? null,
        ];
    }
}
