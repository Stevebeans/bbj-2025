<?php

namespace BigBrotherJunkies\Data\Api;

use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API routes for importing seasons and players
 */
class ImportRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        // Import seasons (bulk)
        register_rest_route(self::NAMESPACE, '/admin/import/seasons', [
            'methods' => 'POST',
            'callback' => [$this, 'importSeasons'],
            'permission_callback' => [$this, 'checkAdminAccess'],
        ]);

        // Import single season
        register_rest_route(self::NAMESPACE, '/admin/import/season', [
            'methods' => 'POST',
            'callback' => [$this, 'importSeason'],
            'permission_callback' => [$this, 'checkAdminAccess'],
            'args' => [
                'season_number' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'full_name' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'abbreviation' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'start_date' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'end_date' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Check which seasons exist
        register_rest_route(self::NAMESPACE, '/admin/import/seasons/check', [
            'methods' => 'GET',
            'callback' => [$this, 'checkExistingSeasons'],
            'permission_callback' => [$this, 'checkAdminAccess'],
        ]);
    }

    /**
     * Check which seasons already exist in the database
     */
    public function checkExistingSeasons(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_seasons';

        $existing = $wpdb->get_results(
            "SELECT id, season_number, full_name, abbreviation FROM {$table} ORDER BY CAST(season_number AS UNSIGNED)",
            ARRAY_A
        );

        return new WP_REST_Response([
            'success' => true,
            'existing_seasons' => $existing,
            'count' => count($existing),
        ], 200);
    }

    /**
     * Import a single season
     */
    public function importSeason(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_seasons';

        $seasonNumber = $request->get_param('season_number');
        $fullName = $request->get_param('full_name');
        $abbreviation = $request->get_param('abbreviation');
        $startDate = $request->get_param('start_date');
        $endDate = $request->get_param('end_date');

        // Check if season already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE season_number = %s",
            $seasonNumber
        ));

        if ($existing) {
            return new WP_REST_Response([
                'success' => false,
                'message' => "Season {$seasonNumber} already exists (ID: {$existing})",
                'existing_id' => (int) $existing,
            ], 409); // Conflict
        }

        // Create WordPress post for the season
        $slug = sanitize_title($abbreviation); // e.g., "bb1", "bb2"

        $postId = wp_insert_post([
            'post_title' => $fullName,
            'post_name' => $slug,
            'post_type' => 'bigbrother-seasons',
            'post_status' => 'publish',
            'post_content' => '',
        ], true);

        if (is_wp_error($postId)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to create WordPress post: ' . $postId->get_error_message(),
            ], 500);
        }

        // Insert into seasons table
        // Note: id will be same as post_id for consistency with existing seasons
        $inserted = $wpdb->insert(
            $table,
            [
                'id' => $postId,
                'post_id' => $postId,
                'season_number' => $seasonNumber,
                'full_name' => $fullName,
                'abbreviation' => $abbreviation,
                'start_date' => $startDate ?: null,
                'end_date' => $endDate ?: null,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            // Rollback: delete the post
            wp_delete_post($postId, true);

            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to insert season data: ' . $wpdb->last_error,
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => "Season {$fullName} imported successfully",
            'season' => [
                'id' => $postId,
                'post_id' => $postId,
                'season_number' => $seasonNumber,
                'full_name' => $fullName,
                'abbreviation' => $abbreviation,
                'slug' => $slug,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ], 201);
    }

    /**
     * Bulk import seasons
     */
    public function importSeasons(WP_REST_Request $request): WP_REST_Response
    {
        $seasons = $request->get_json_params()['seasons'] ?? [];

        if (empty($seasons)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No seasons provided',
            ], 400);
        }

        $results = [
            'imported' => [],
            'skipped' => [],
            'failed' => [],
        ];

        foreach ($seasons as $season) {
            // Create a mock request for each season
            $singleRequest = new WP_REST_Request('POST');
            $singleRequest->set_param('season_number', $season['season_number'] ?? '');
            $singleRequest->set_param('full_name', $season['full_name'] ?? '');
            $singleRequest->set_param('abbreviation', $season['abbreviation'] ?? '');
            $singleRequest->set_param('start_date', $season['start_date'] ?? '');
            $singleRequest->set_param('end_date', $season['end_date'] ?? '');

            $response = $this->importSeason($singleRequest);
            $data = $response->get_data();
            $status = $response->get_status();

            if ($status === 201) {
                $results['imported'][] = $data['season'];
            } elseif ($status === 409) {
                $results['skipped'][] = [
                    'season_number' => $season['season_number'],
                    'reason' => 'Already exists',
                    'existing_id' => $data['existing_id'] ?? null,
                ];
            } else {
                $results['failed'][] = [
                    'season_number' => $season['season_number'],
                    'reason' => $data['message'] ?? 'Unknown error',
                ];
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => sprintf(
                'Import complete: %d imported, %d skipped, %d failed',
                count($results['imported']),
                count($results['skipped']),
                count($results['failed'])
            ),
            'results' => $results,
        ], 200);
    }

    /**
     * Check if user has admin access
     */
    public function checkAdminAccess(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        return current_user_can('manage_options');
    }
}
