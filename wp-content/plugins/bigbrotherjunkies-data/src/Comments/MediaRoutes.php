<?php

namespace BigBrotherJunkies\Data\Comments;

/**
 * REST API routes for comment media uploads
 */
class MediaRoutes
{
    /**
     * Register routes
     */
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST API routes
     */
    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        // Upload media (before attaching to comment)
        register_rest_route($namespace, '/comments/media', [
            'methods' => 'POST',
            'callback' => [$this, 'uploadMedia'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
        ]);

        // Delete media
        register_rest_route($namespace, '/comments/media/(?P<media_id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteMedia'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'media_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Store Giphy GIF reference
        register_rest_route($namespace, '/comments/media/giphy', [
            'methods' => 'POST',
            'callback' => [$this, 'storeGiphy'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'giphy_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'url' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
                'width' => [
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'height' => [
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Search Giphy (proxy to avoid exposing API key)
        register_rest_route($namespace, '/comments/media/giphy/search', [
            'methods' => 'GET',
            'callback' => [$this, 'searchGiphy'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'q' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                ],
                'offset' => [
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Giphy trending
        register_rest_route($namespace, '/comments/media/giphy/trending', [
            'methods' => 'GET',
            'callback' => [$this, 'trendingGiphy'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Upload media file
     */
    public function uploadMedia(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();

        // Check if file was uploaded
        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'No file uploaded',
            ], 400);
        }

        $result = MediaUploader::upload($files['file'], $userId);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'media' => $result,
        ], 201);
    }

    /**
     * Delete media
     */
    public function deleteMedia(\WP_REST_Request $request): \WP_REST_Response
    {
        $mediaId = $request->get_param('media_id');
        $userId = get_current_user_id();

        $result = MediaUploader::delete($mediaId, $userId);

        if (is_wp_error($result)) {
            $status = $result->get_error_code() === 'forbidden' ? 403 : 400;
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], $status);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Media deleted',
        ], 200);
    }

    /**
     * Store a Giphy GIF reference
     */
    public function storeGiphy(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $giphyId = $request->get_param('giphy_id');
        $url = $request->get_param('url');
        $width = $request->get_param('width');
        $height = $request->get_param('height');

        // Validate Giphy URL
        if (!str_contains($url, 'giphy.com')) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid Giphy URL',
            ], 400);
        }

        $result = MediaUploader::storeGiphy($giphyId, $url, $userId, $width, $height);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'media' => $result,
        ], 201);
    }

    /**
     * Search Giphy (proxy endpoint)
     */
    public function searchGiphy(\WP_REST_Request $request): \WP_REST_Response
    {
        $query = $request->get_param('q');
        $limit = min($request->get_param('limit'), 50);
        $offset = $request->get_param('offset');

        $apiKey = get_option('bbj_giphy_api_key', '');

        if (empty($apiKey)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Giphy API key not configured',
            ], 500);
        }

        $url = add_query_arg([
            'api_key' => $apiKey,
            'q' => $query,
            'limit' => $limit,
            'offset' => $offset,
            'rating' => 'pg-13', // Family-friendly content
            'lang' => 'en',
        ], 'https://api.giphy.com/v1/gifs/search');

        $response = wp_remote_get($url, [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to fetch from Giphy',
            ], 500);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['data'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid response from Giphy',
            ], 500);
        }

        // Transform response to only include what we need
        $gifs = array_map(function ($gif) {
            return [
                'id' => $gif['id'],
                'title' => $gif['title'],
                'url' => $gif['images']['fixed_height']['url'],
                'preview_url' => $gif['images']['fixed_height_small']['url'] ?? $gif['images']['fixed_height']['url'],
                'width' => (int) $gif['images']['fixed_height']['width'],
                'height' => (int) $gif['images']['fixed_height']['height'],
            ];
        }, $body['data']);

        return new \WP_REST_Response([
            'success' => true,
            'gifs' => $gifs,
            'pagination' => [
                'total' => $body['pagination']['total_count'] ?? 0,
                'offset' => $body['pagination']['offset'] ?? 0,
            ],
        ], 200);
    }

    /**
     * Get trending Giphy GIFs
     */
    public function trendingGiphy(\WP_REST_Request $request): \WP_REST_Response
    {
        $limit = min($request->get_param('limit'), 50);

        $apiKey = get_option('bbj_giphy_api_key', '');

        if (empty($apiKey)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Giphy API key not configured',
            ], 500);
        }

        $url = add_query_arg([
            'api_key' => $apiKey,
            'limit' => $limit,
            'rating' => 'pg-13',
        ], 'https://api.giphy.com/v1/gifs/trending');

        $response = wp_remote_get($url, [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to fetch from Giphy',
            ], 500);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['data'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid response from Giphy',
            ], 500);
        }

        // Transform response
        $gifs = array_map(function ($gif) {
            return [
                'id' => $gif['id'],
                'title' => $gif['title'],
                'url' => $gif['images']['fixed_height']['url'],
                'preview_url' => $gif['images']['fixed_height_small']['url'] ?? $gif['images']['fixed_height']['url'],
                'width' => (int) $gif['images']['fixed_height']['width'],
                'height' => (int) $gif['images']['fixed_height']['height'],
            ];
        }, $body['data']);

        return new \WP_REST_Response([
            'success' => true,
            'gifs' => $gifs,
        ], 200);
    }

    /**
     * Permission callback: Check if user is logged in
     */
    public function checkUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }
}
