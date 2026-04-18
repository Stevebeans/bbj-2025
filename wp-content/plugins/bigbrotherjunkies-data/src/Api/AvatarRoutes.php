<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Comments\AvatarUploader;

/**
 * Avatar API Routes
 *
 * Provides endpoints for:
 * - Uploading user avatars
 * - Getting avatar URLs
 * - Deleting avatars
 */
class AvatarRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        // Upload avatar
        register_rest_route($namespace, '/avatar', [
            'methods' => 'POST',
            'callback' => [$this, 'uploadAvatar'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
        ]);

        // Get avatar URL for a user
        register_rest_route($namespace, '/avatar/(?P<user_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getAvatar'],
            'permission_callback' => '__return_true',
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'size' => [
                    'default' => 64,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Delete own avatar
        register_rest_route($namespace, '/avatar', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteAvatar'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
        ]);

        // Get current user's avatar info
        register_rest_route($namespace, '/avatar/me', [
            'methods' => 'GET',
            'callback' => [$this, 'getMyAvatar'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
        ]);
    }

    /**
     * Upload avatar for current user
     */
    public function uploadAvatar(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $files = $request->get_file_params();

        if (empty($files['avatar'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'No avatar file provided',
            ], 400);
        }

        $result = AvatarUploader::upload($files['avatar'], $userId);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            // Add cache-buster to force browsers to reload
            'avatar_url' => $result['url'] . '?v=' . time(),
        ], 200);
    }

    /**
     * Get avatar URL for a user
     */
    public function getAvatar(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = $request->get_param('user_id');
        $size = $request->get_param('size');

        // Verify user exists
        $user = get_user_by('ID', $userId);
        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $avatarUrl = AvatarUploader::getAvatarUrl($userId, $size);
        $hasCustomAvatar = AvatarUploader::hasCustomAvatar($userId);

        return new \WP_REST_Response([
            'success' => true,
            'user_id' => $userId,
            'avatar_url' => $avatarUrl,
            'has_custom_avatar' => $hasCustomAvatar,
        ], 200);
    }

    /**
     * Get current user's avatar info
     */
    public function getMyAvatar(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $avatarUrl = AvatarUploader::getAvatarUrl($userId);
        $hasCustomAvatar = AvatarUploader::hasCustomAvatar($userId);

        return new \WP_REST_Response([
            'success' => true,
            'avatar_url' => $avatarUrl,
            'has_custom_avatar' => $hasCustomAvatar,
        ], 200);
    }

    /**
     * Delete current user's avatar
     */
    public function deleteAvatar(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();

        $result = AvatarUploader::delete($userId);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }

        // Return the fallback (Gravatar) URL
        $avatarUrl = AvatarUploader::getAvatarUrl($userId);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Avatar deleted successfully',
            'avatar_url' => $avatarUrl, // Now shows Gravatar fallback
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
