<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Utils\GoogleSearchQuota;
use BigBrotherJunkies\Data\Utils\PlayerPhotoFetcher;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API routes for player photo fetching
 */
class PlayerPhotoRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    /**
     * Initialize the routes
     */
    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST routes
     */
    public function registerRoutes(): void
    {
        // Search for player photos
        register_rest_route(self::NAMESPACE, '/admin/players/(?P<id>\d+)/search-photos', [
            'methods' => 'POST',
            'callback' => [$this, 'searchPhotos'],
            'permission_callback' => [$this, 'checkPlayerManagementAccess'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'player_name' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'season_name' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Select and save a photo
        register_rest_route(self::NAMESPACE, '/admin/players/(?P<id>\d+)/select-photo', [
            'methods' => 'POST',
            'callback' => [$this, 'selectPhoto'],
            'permission_callback' => [$this, 'checkPlayerManagementAccess'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'image_url' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ],
        ]);

        // Get quota status
        register_rest_route(self::NAMESPACE, '/admin/photos/quota', [
            'methods' => 'GET',
            'callback' => [$this, 'getQuota'],
            'permission_callback' => [$this, 'checkPlayerManagementAccess'],
        ]);
    }

    /**
     * Search for player photos via Google Custom Search
     */
    public function searchPhotos(WP_REST_Request $request): WP_REST_Response
    {
        // Check quota
        if (!GoogleSearchQuota::canSearch()) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Daily quota exceeded (100 searches/day). Try again tomorrow.',
                'quota_remaining' => 0,
            ], 429);
        }

        $playerId = $request->get_param('id');
        $playerName = $request->get_param('player_name');
        $seasonName = $request->get_param('season_name');

        // Perform search
        $photos = PlayerPhotoFetcher::searchPhotos($playerName, $seasonName);

        if (is_wp_error($photos)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $photos->get_error_message(),
                'quota_remaining' => GoogleSearchQuota::getRemaining(),
            ], 500);
        }

        // Increment quota after successful search
        GoogleSearchQuota::incrementQuota();

        return new WP_REST_Response([
            'success' => true,
            'player_id' => $playerId,
            'photos' => $photos,
            'quota_remaining' => GoogleSearchQuota::getRemaining(),
        ], 200);
    }

    /**
     * Select and save a photo for a player
     */
    public function selectPhoto(WP_REST_Request $request): WP_REST_Response
    {
        $playerId = $request->get_param('id');
        $imageUrl = $request->get_param('image_url');

        // Validate player exists
        global $wpdb;
        $player = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, pp.post_name as slug
             FROM {$wpdb->prefix}bbj_players p
             LEFT JOIN {$wpdb->posts} pp ON p.post_id = pp.ID
             WHERE p.id = %d",
            $playerId
        ));

        if (!$player) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Player not found',
            ], 404);
        }

        // Download and save the photo
        $result = PlayerPhotoFetcher::downloadAndSave($playerId, $imageUrl);

        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }

        // Trigger revalidation if available and player has a slug
        if (!empty($player->slug) && class_exists('BigBrotherJunkies\Data\Utils\Revalidation')) {
            \BigBrotherJunkies\Data\Utils\Revalidation::revalidatePlayer($player->slug);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Photo saved successfully',
            'photo' => [
                'url' => $result['url'],
                'width' => $result['width'],
                'height' => $result['height'],
            ],
        ], 200);
    }

    /**
     * Get quota status
     */
    public function getQuota(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'quota_used' => GoogleSearchQuota::getUsed(),
            'quota_limit' => GoogleSearchQuota::getLimit(),
            'quota_remaining' => GoogleSearchQuota::getRemaining(),
        ], 200);
    }

    /**
     * Check if user has player management access
     */
    public function checkPlayerManagementAccess(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        $allowedRoles = ['administrator', 'editor'];

        return !empty(array_intersect($allowedRoles, $user->roles));
    }
}
