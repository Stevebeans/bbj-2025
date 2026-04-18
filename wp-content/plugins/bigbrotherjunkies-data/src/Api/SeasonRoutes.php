<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Permissions\PermissionChecker;
use BigBrotherJunkies\Data\Utils\Revalidation;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API routes for Seasons
 *
 * Provides endpoints for:
 * - Public: Get all seasons, get single season by slug, get season players
 * - Admin: Update season, add/remove players
 */
class SeasonRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        // ========================================
        // PUBLIC ENDPOINTS
        // ========================================

        // Get all seasons
        register_rest_route(self::NAMESPACE, '/seasons', [
            'methods' => 'GET',
            'callback' => [$this, 'getSeasons'],
            'permission_callback' => '__return_true',
            'args' => [
                'order_by' => [
                    'default' => 'season_number',
                    'type' => 'string',
                    'enum' => ['season_number', 'full_name', 'start_date', 'end_date'],
                ],
                'order' => [
                    'default' => 'DESC',
                    'type' => 'string',
                    'enum' => ['ASC', 'DESC'],
                ],
            ],
        ]);

        // Get single season by slug
        register_rest_route(self::NAMESPACE, '/seasons/by-slug/(?P<slug>[a-z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getSeasonBySlug'],
            'permission_callback' => '__return_true',
            'args' => [
                'slug' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_title',
                ],
                'size' => [
                    'default' => 'bbj_v2_profile_image',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Get single season by ID
        register_rest_route(self::NAMESPACE, '/seasons/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getSeasonById'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'size' => [
                    'default' => 'bbj_v2_profile_image',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // ========================================
        // ADMIN ENDPOINTS
        // ========================================

        // Update season
        register_rest_route(self::NAMESPACE, '/admin/seasons/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'updateSeason'],
            'permission_callback' => [$this, 'checkSeasonManagementAccess'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Add player to season
        register_rest_route(self::NAMESPACE, '/admin/seasons/(?P<season_id>\d+)/players', [
            'methods' => 'POST',
            'callback' => [$this, 'addPlayerToSeason'],
            'permission_callback' => [$this, 'checkSeasonManagementAccess'],
            'args' => [
                'season_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'player_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Remove player from season
        register_rest_route(self::NAMESPACE, '/admin/seasons/(?P<season_id>\d+)/players/(?P<player_id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'removePlayerFromSeason'],
            'permission_callback' => [$this, 'checkSeasonManagementAccess'],
        ]);

        // Search players (for add player dropdown)
        // Note: Using /search/players to avoid conflict with /players/{slug} route
        register_rest_route(self::NAMESPACE, '/search/players', [
            'methods' => 'GET',
            'callback' => [$this, 'searchPlayers'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'exclude' => [
                    'default' => '',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'default' => 10,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Update roster status (bulk player status updates)
        register_rest_route(self::NAMESPACE, '/admin/seasons/(?P<id>\d+)/roster-status', [
            'methods' => 'POST',
            'callback' => [$this, 'updateRosterStatus'],
            'permission_callback' => [$this, 'checkSeasonManagementAccess'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Purge cache for a season (Redis + Varnish)
        register_rest_route(self::NAMESPACE, '/admin/seasons/(?P<id>\d+)/purge-cache', [
            'methods' => 'POST',
            'callback' => [$this, 'purgeSeasonCache'],
            'permission_callback' => [$this, 'checkSeasonManagementAccess'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    // ========================================
    // PUBLIC ENDPOINT CALLBACKS
    // ========================================

    /**
     * Get all seasons
     */
    public function getSeasons(WP_REST_Request $request): WP_REST_Response
    {
        $orderBy = $request->get_param('order_by');
        $order = $request->get_param('order');

        $seasons = function_exists('bbj_v2_get_seasons')
            ? bbj_v2_get_seasons($orderBy, $order)
            : [];

        $formattedSeasons = array_map([$this, 'formatSeasonBasic'], $seasons);

        return new WP_REST_Response([
            'success' => true,
            'seasons' => $formattedSeasons,
            'count' => count($formattedSeasons),
        ], 200);
    }

    /**
     * Get single season by slug
     */
    public function getSeasonBySlug(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $size = $request->get_param('size');

        // Find the post by slug (custom post type: bigbrother-seasons)
        $posts = get_posts([
            'name' => $slug,
            'post_type' => 'bigbrother-seasons',
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);

        if (empty($posts)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Season not found',
            ], 404);
        }

        $post = $posts[0];

        // Get season data from custom table using id (which IS the post ID)
        global $wpdb;
        $table = defined('BBJ_V2_TABLE_SEASONS') ? BBJ_V2_TABLE_SEASONS : $wpdb->prefix . 'bbj_seasons';

        $season = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $post->ID
        ), ARRAY_A);

        if (!$season) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Season data not found',
            ], 404);
        }

        // Get players for this season
        $players = function_exists('bbj_v2_get_season_players')
            ? bbj_v2_get_season_players($season['id'], $size)
            : [];

        // Format the response
        $formattedSeason = $this->formatSeasonFull($season, $post);
        $formattedPlayers = array_map([$this, 'formatPlayer'], $players);

        // Sort players by status (HoH > PoV > Active > Nom > Jury > Evicted)
        usort($formattedPlayers, function ($a, $b) {
            $order = ['winner' => 0, 'hoh' => 1, 'pov' => 2, 'active' => 3, 'nom' => 4, 'jury' => 5, 'evicted' => 6];
            $wa = $order[$a['status']] ?? 99;
            $wb = $order[$b['status']] ?? 99;
            if ($wa !== $wb) return $wa - $wb;
            return strcasecmp($a['name'], $b['name']);
        });

        // Look up matching blog category by slug for related articles
        $categoryTerm = get_term_by('slug', $slug, 'category');
        $categoryId = $categoryTerm ? (int) $categoryTerm->term_id : null;
        $articleCount = $categoryId ? (int) $categoryTerm->count : 0;

        return new WP_REST_Response([
            'success' => true,
            'season' => $formattedSeason,
            'players' => $formattedPlayers,
            'count' => count($formattedPlayers),
            'category_id' => $categoryId,
            'article_count' => $articleCount,
        ], 200);
    }

    /**
     * Get single season by ID
     */
    public function getSeasonById(WP_REST_Request $request): WP_REST_Response
    {
        $seasonId = $request->get_param('id');
        $size = $request->get_param('size');

        $season = function_exists('bbj_v2_get_season_by_id')
            ? bbj_v2_get_season_by_id($seasonId)
            : null;

        if (!$season) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Season not found',
            ], 404);
        }

        // Get associated post for slug/permalink
        $post = get_post($season['post_id']);

        // Get players for this season
        $players = function_exists('bbj_v2_get_season_players')
            ? bbj_v2_get_season_players($seasonId, $size)
            : [];

        $formattedSeason = $this->formatSeasonFull($season, $post);
        $formattedPlayers = array_map([$this, 'formatPlayer'], $players);

        return new WP_REST_Response([
            'success' => true,
            'season' => $formattedSeason,
            'players' => $formattedPlayers,
            'count' => count($formattedPlayers),
        ], 200);
    }

    // ========================================
    // ADMIN ENDPOINT CALLBACKS
    // ========================================

    /**
     * Update season
     */
    public function updateSeason(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $seasonId = $request->get_param('id');
        $params = $request->get_json_params();

        // Get existing season
        $season = function_exists('bbj_v2_get_season_by_id')
            ? bbj_v2_get_season_by_id($seasonId)
            : null;

        if (!$season) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Season not found',
            ], 404);
        }

        // Prepare update data
        $updateData = [];
        $updateFormat = [];

        // Map allowed fields
        $allowedFields = [
            'full_name' => '%s',
            'abbreviation' => '%s',
            'start_date' => '%s',
            'end_date' => '%s',
            'season_number' => '%s',
            'season_picture' => '%d',
            'season_banner_image' => '%d',
            'season_winner' => '%d',
            'runner_up' => '%d',
            'afp' => '%d',
        ];

        foreach ($allowedFields as $field => $format) {
            if (isset($params[$field])) {
                $value = $params[$field];
                // Handle null/empty values for integer fields
                if (in_array($format, ['%d']) && ($value === '' || $value === null)) {
                    $updateData[$field] = null;
                    $updateFormat[] = '%s'; // Use string format for NULL
                } else {
                    $updateData[$field] = $format === '%d' ? absint($value) : sanitize_text_field($value);
                    $updateFormat[] = $format;
                }
            }
        }

        $hasMetaUpdate = isset($params['description']);

        if (empty($updateData) && !$hasMetaUpdate) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No valid fields to update',
            ], 400);
        }

        // Update the season table (skip if only meta fields changed)
        if (!empty($updateData)) {
            $table = defined('BBJ_V2_TABLE_SEASONS') ? BBJ_V2_TABLE_SEASONS : $wpdb->prefix . 'bbj_seasons';

            $result = $wpdb->update(
                $table,
                $updateData,
                ['id' => $seasonId],
                $updateFormat,
                ['%d']
            );

            if ($result === false) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Failed to update season: ' . $wpdb->last_error,
                ], 500);
            }
        }

        // Save season description to post meta
        if (isset($params['description'])) {
            $postId = $season['post_id'] ?? $seasonId;
            update_post_meta($postId, '_bbj_season_description', sanitize_textarea_field($params['description']));
        }

        // Also update the WordPress post if full_name changed
        if (isset($params['full_name']) && $season['post_id']) {
            wp_update_post([
                'ID' => $season['post_id'],
                'post_title' => sanitize_text_field($params['full_name']),
            ]);
        }

        // Bust cache
        if (function_exists('bbj_spoiler_bar_bust_cache')) {
            bbj_spoiler_bar_bust_cache($seasonId);
        }

        // Get updated season
        $updatedSeason = function_exists('bbj_v2_get_season_by_id')
            ? bbj_v2_get_season_by_id($seasonId)
            : null;

        $post = $updatedSeason ? get_post($updatedSeason['post_id']) : null;

        // Trigger Next.js revalidation
        if ($post) {
            Revalidation::revalidateSeason($post->post_name);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Season updated successfully',
            'season' => $this->formatSeasonFull($updatedSeason, $post),
        ], 200);
    }

    /**
     * Add player to season
     */
    public function addPlayerToSeason(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $seasonId = $request->get_param('season_id');
        $playerId = $request->get_param('player_id');

        // Verify season exists
        $season = function_exists('bbj_v2_get_season_by_id')
            ? bbj_v2_get_season_by_id($seasonId)
            : null;

        if (!$season) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Season not found',
            ], 404);
        }

        // Check if link already exists
        $linksTable = defined('BBJ_V2_TABLE_LINKS') ? BBJ_V2_TABLE_LINKS : $wpdb->prefix . 'bbj_links';

        $existingLink = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$linksTable} WHERE bbj_player = %d AND bbj_season = %d",
            $playerId,
            $seasonId
        ));

        if ($existingLink) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Player already in this season',
            ], 400);
        }

        // Insert new link
        $result = $wpdb->insert($linksTable, [
            'bbj_player' => $playerId,
            'bbj_season' => $seasonId,
            'current_hoh' => 0,
            'current_pov' => 0,
            'current_nom' => 0,
            'current_jury' => 0,
            'current_evicted' => 0,
            'current_havenot' => 0,
            'current_safe' => 0,
            'current_misc' => 0,
        ], ['%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d']);

        if ($result === false) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to add player: ' . $wpdb->last_error,
            ], 500);
        }

        // Bust cache
        if (function_exists('bbj_spoiler_bar_bust_cache')) {
            bbj_spoiler_bar_bust_cache($seasonId);
        }

        // Trigger Next.js revalidation
        if ($season['post_id']) {
            $post = get_post($season['post_id']);
            if ($post) {
                Revalidation::revalidateSeason($post->post_name);
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Player added to season',
            'link_id' => $wpdb->insert_id,
        ], 201);
    }

    /**
     * Remove player from season
     */
    public function removePlayerFromSeason(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $seasonId = $request->get_param('season_id');
        $playerId = $request->get_param('player_id');

        $linksTable = defined('BBJ_V2_TABLE_LINKS') ? BBJ_V2_TABLE_LINKS : $wpdb->prefix . 'bbj_links';

        $result = $wpdb->delete(
            $linksTable,
            [
                'bbj_player' => $playerId,
                'bbj_season' => $seasonId,
            ],
            ['%d', '%d']
        );

        if ($result === false) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to remove player: ' . $wpdb->last_error,
            ], 500);
        }

        if ($result === 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Player not found in this season',
            ], 404);
        }

        // Bust cache
        if (function_exists('bbj_spoiler_bar_bust_cache')) {
            bbj_spoiler_bar_bust_cache($seasonId);
        }

        // Trigger Next.js revalidation
        $season = function_exists('bbj_v2_get_season_by_id')
            ? bbj_v2_get_season_by_id($seasonId)
            : null;

        if ($season && $season['post_id']) {
            $post = get_post($season['post_id']);
            if ($post) {
                Revalidation::revalidateSeason($post->post_name);
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Player removed from season',
        ], 200);
    }

    /**
     * Update roster status (bulk player status updates for spoiler bar editor)
     */
    public function updateRosterStatus(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $seasonId = $request->get_param('id');
        $params = $request->get_json_params();
        $players = $params['players'] ?? [];

        if (empty($players)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No players provided',
            ], 400);
        }

        // Verify season exists
        $season = function_exists('bbj_v2_get_season_by_id')
            ? bbj_v2_get_season_by_id($seasonId)
            : null;

        if (!$season) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Season not found',
            ], 404);
        }

        $linksTable = defined('BBJ_V2_TABLE_LINKS') ? BBJ_V2_TABLE_LINKS : $wpdb->prefix . 'bbj_v2_player_season';
        $updatedCount = 0;
        $errors = [];

        foreach ($players as $playerData) {
            $playerId = absint($playerData['player_id'] ?? 0);
            if (!$playerId) {
                continue;
            }

            // Build update data from allowed fields
            $updateData = [];
            $updateFormat = [];

            // Status flags (tinyint)
            $statusFields = [
                'current_hoh', 'current_pov', 'current_nom', 'current_safe',
                'current_havenot', 'current_jury', 'current_evicted', 'current_misc'
            ];

            foreach ($statusFields as $field) {
                if (isset($playerData[$field])) {
                    $updateData[$field] = $playerData[$field] ? 1 : 0;
                    $updateFormat[] = '%d';
                }
            }

            // finish_place (tinyint unsigned, nullable)
            if (array_key_exists('finish_place', $playerData)) {
                $updateData['finish_place'] = $playerData['finish_place'] === null || $playerData['finish_place'] === ''
                    ? null
                    : absint($playerData['finish_place']);
                $updateFormat[] = $updateData['finish_place'] === null ? '%s' : '%d';
            }

            // evicted_date (date, nullable)
            if (array_key_exists('evicted_date', $playerData)) {
                $evictedDate = $playerData['evicted_date'];
                if (empty($evictedDate)) {
                    $updateData['bbj_evicted_date'] = null;
                    $updateFormat[] = '%s';
                } else {
                    // Validate date format
                    $parsed = \DateTime::createFromFormat('Y-m-d', $evictedDate);
                    if ($parsed && $parsed->format('Y-m-d') === $evictedDate) {
                        $updateData['bbj_evicted_date'] = $evictedDate;
                        $updateFormat[] = '%s';
                    }
                }
            }

            // misc_notes (varchar)
            if (isset($playerData['misc_notes'])) {
                $updateData['misc_notes'] = sanitize_text_field($playerData['misc_notes']);
                $updateFormat[] = '%s';
            }

            // Season stat totals (int fields)
            $statFields = [
                'bbj_total_hoh', 'bbj_total_pov', 'bbj_total_nom',
                'bbj_total_misc', 'bbj_votes_received', 'bbj_veto_played',
                'bbj_total_saved', 'bbj_total_havenot',
            ];

            foreach ($statFields as $field) {
                if (array_key_exists($field, $playerData)) {
                    $updateData[$field] = absint($playerData[$field]);
                    $updateFormat[] = '%d';
                }
            }

            if (empty($updateData)) {
                continue;
            }

            $result = $wpdb->update(
                $linksTable,
                $updateData,
                [
                    'bbj_player' => $playerId,
                    'bbj_season' => $seasonId,
                ],
                $updateFormat,
                ['%d', '%d']
            );

            if ($result === false) {
                $errors[] = "Failed to update player {$playerId}: " . $wpdb->last_error;
            } else {
                $updatedCount++;
            }
        }

        // Bust cache
        if (function_exists('bbj_spoiler_bar_bust_cache')) {
            bbj_spoiler_bar_bust_cache($seasonId);
        }

        // Trigger Next.js revalidation
        if ($season['post_id']) {
            $post = get_post($season['post_id']);
            if ($post) {
                Revalidation::revalidateSeason($post->post_name);
                Revalidation::revalidateSpoilerBar();
            }
        }

        // Return updated player data for live preview
        $updatedPlayers = function_exists('bbj_v2_get_season_players')
            ? bbj_v2_get_season_players($seasonId, 'bbj_v2_spoiler_bar')
            : [];

        $formattedPlayers = array_map([$this, 'formatPlayer'], $updatedPlayers);

        return new WP_REST_Response([
            'success' => true,
            'message' => "Updated {$updatedCount} player(s)",
            'updated_count' => $updatedCount,
            'errors' => $errors,
            'players' => $formattedPlayers,
        ], 200);
    }

    /**
     * Purge all caches for a season (Redis object cache + Varnish)
     */
    public function purgeSeasonCache(WP_REST_Request $request): WP_REST_Response
    {
        $seasonId = $request->get_param('id');

        // Verify season exists
        $season = function_exists('bbj_v2_get_season_by_id')
            ? bbj_v2_get_season_by_id($seasonId)
            : null;

        if (!$season) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Season not found',
            ], 404);
        }

        // Bust Redis object cache
        if (function_exists('bbj_spoiler_bar_bust_cache')) {
            bbj_spoiler_bar_bust_cache($seasonId);
        }

        // Additional: flush entire Redis cache group if available
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('bbj_v2');
        }

        // Clear Breeze/Varnish cache
        if (function_exists('bbj_purge_varnish_cache')) {
            bbj_purge_varnish_cache();
        }

        // Trigger Next.js revalidation
        if ($season['post_id']) {
            $post = get_post($season['post_id']);
            if ($post) {
                Revalidation::revalidateSeason($post->post_name);
                Revalidation::revalidateSpoilerBar();
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Cache purged successfully (Redis + Varnish + Next.js)',
        ], 200);
    }

    /**
     * Search players for add player dropdown
     */
    public function searchPlayers(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $query = $request->get_param('q');
        $excludeParam = $request->get_param('exclude');
        $limit = min($request->get_param('limit'), 50);

        // Parse exclude IDs
        $excludeIds = [];
        if (!empty($excludeParam)) {
            $excludeIds = array_map('absint', explode(',', $excludeParam));
        }

        $playersTable = defined('BBJ_V2_TABLE_PLAYERS') ? BBJ_V2_TABLE_PLAYERS : $wpdb->prefix . 'bbj_players';

        // Build query
        $sql = "SELECT id, first_name, last_name, official_nickname, profile_picture
                FROM {$playersTable}
                WHERE (first_name LIKE %s OR last_name LIKE %s OR official_nickname LIKE %s)";

        $searchTerm = '%' . $wpdb->esc_like($query) . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];

        if (!empty($excludeIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeIds), '%d'));
            $sql .= " AND id NOT IN ({$placeholders})";
            $params = array_merge($params, $excludeIds);
        }

        $sql .= " ORDER BY first_name ASC LIMIT %d";
        $params[] = $limit;

        $players = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        $formattedPlayers = array_map(function ($player) {
            $imageUrl = '';
            if (!empty($player['profile_picture'])) {
                $imageUrl = wp_get_attachment_image_url($player['profile_picture'], 'thumbnail') ?: '';
            }

            return [
                'id' => (int) $player['id'],
                'name' => trim($player['first_name'] . ' ' . $player['last_name']),
                'first_name' => $player['first_name'],
                'last_name' => $player['last_name'],
                'nickname' => $player['official_nickname'] ?? '',
                'image' => $imageUrl,
            ];
        }, $players);

        return new WP_REST_Response([
            'success' => true,
            'players' => $formattedPlayers,
            'count' => count($formattedPlayers),
        ], 200);
    }

    // ========================================
    // PERMISSION CALLBACKS
    // ========================================

    /**
     * Check if user has season management access (admin only)
     */
    public function checkSeasonManagementAccess(): bool
    {
        return PermissionChecker::userCan('season_management');
    }

    // ========================================
    // FORMATTERS
    // ========================================

    /**
     * Format season for list view (basic info)
     */
    private function formatSeasonBasic($season): array
    {
        // Season id IS the post ID (they're the same)
        $post = isset($season['id']) ? get_post($season['id']) : null;
        $slug = $post ? $post->post_name : '';

        // Get cover image URL
        $coverImageUrl = '';
        if (!empty($season['season_picture'])) {
            $coverImageUrl = wp_get_attachment_image_url($season['season_picture'], 'medium') ?: '';
        }

        // Get winner, runner_up, and afp player details
        $winner = null;
        $runnerUp = null;
        $afp = null;

        if (!empty($season['season_winner'])) {
            $winner = $this->getPlayerBasicInfo((int) $season['season_winner']);
        }
        if (!empty($season['runner_up'])) {
            $runnerUp = $this->getPlayerBasicInfo((int) $season['runner_up']);
        }
        if (!empty($season['afp'])) {
            $afp = $this->getPlayerBasicInfo((int) $season['afp']);
        }

        return [
            'id' => (int) $season['id'],
            'name' => $season['full_name'] ?? '',
            'abbreviation' => $season['abbreviation'] ?? '',
            'slug' => $slug,
            'season_number' => $season['season_number'] ?? '',
            'start_date' => $season['start_date'] ?? null,
            'end_date' => $season['end_date'] ?? null,
            'cover_image' => $coverImageUrl,
            'permalink' => $post ? get_permalink($post) : '',
            'winner' => $winner,
            'runner_up' => $runnerUp,
            'afp' => $afp,
        ];
    }

    /**
     * Get basic player info for season display
     */
    private function getPlayerBasicInfo(int $playerId): ?array
    {
        global $wpdb;
        $playersTable = defined('BBJ_V2_TABLE_PLAYERS') ? BBJ_V2_TABLE_PLAYERS : $wpdb->prefix . 'bbj_players';
        $postsTable = $wpdb->posts;

        $player = $wpdb->get_row($wpdb->prepare(
            "SELECT pl.id, pl.first_name, pl.last_name, pl.official_nickname, pl.profile_picture, p.post_name as slug
             FROM {$playersTable} pl
             INNER JOIN {$postsTable} p ON pl.id = p.ID
             WHERE pl.id = %d",
            $playerId
        ), ARRAY_A);

        if (!$player) {
            return null;
        }

        $name = trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''));
        $photo = '';
        if (!empty($player['profile_picture'])) {
            $photo = wp_get_attachment_image_url($player['profile_picture'], 'thumbnail') ?: '';
        }

        return [
            'id' => (int) $player['id'],
            'name' => $name,
            'nickname' => $player['official_nickname'] ?? '',
            'slug' => $player['slug'] ?? '',
            'photo' => $photo,
            'permalink' => get_permalink($player['id']),
        ];
    }

    /**
     * Format season for detail view (full info)
     */
    private function formatSeasonFull($season, $post = null): array
    {
        $basic = $this->formatSeasonBasic($season);

        // Calculate season status
        $today = date('Y-m-d');
        $isActive = false;
        $status = 'upcoming';

        if (!empty($season['start_date']) && !empty($season['end_date'])) {
            if ($today >= $season['start_date'] && $today <= $season['end_date']) {
                $isActive = true;
                $status = 'current';
            } elseif ($today > $season['end_date']) {
                $status = 'completed';
            }
        }

        // Calculate days
        $totalDays = 0;
        $daysElapsed = 0;
        $daysRemaining = 0;
        $progressPct = 0;

        if (!empty($season['start_date']) && !empty($season['end_date'])) {
            $startDate = new \DateTime($season['start_date']);
            $endDate = new \DateTime($season['end_date']);
            $todayDate = new \DateTime($today);

            $totalDays = $startDate->diff($endDate)->days + 1;

            if ($isActive) {
                $daysElapsed = $startDate->diff($todayDate)->days + 1;
                $daysRemaining = max(0, $totalDays - $daysElapsed);
                $progressPct = round(($daysElapsed / $totalDays) * 100, 1);
            } elseif ($status === 'completed') {
                $daysElapsed = $totalDays;
                $daysRemaining = 0;
                $progressPct = 100;
            }
        }

        // Get banner image URL
        $bannerImageUrl = '';
        if (!empty($season['season_banner_image'])) {
            $bannerImageUrl = wp_get_attachment_image_url($season['season_banner_image'], 'large') ?: '';
        }

        return array_merge($basic, [
            'post_id' => (int) ($season['post_id'] ?? 0),
            'status' => $status,
            'is_active' => $isActive,
            'total_days' => $totalDays,
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            'progress_pct' => $progressPct,
            'banner_image' => $bannerImageUrl,
            'winner_id' => $season['season_winner'] ? (int) $season['season_winner'] : null,
            'runner_up_id' => $season['runner_up'] ? (int) $season['runner_up'] : null,
            'afp_id' => $season['afp'] ? (int) $season['afp'] : null,
            'description' => get_post_meta((int) ($season['post_id'] ?? $season['id']), '_bbj_season_description', true) ?: '',
        ]);
    }

    /**
     * Format player for API response
     */
    private function formatPlayer(array $player): array
    {
        // Determine status
        $status = 'active';
        if (!empty($player['current_evicted'])) {
            $status = 'evicted';
        } elseif (!empty($player['current_jury'])) {
            $status = 'jury';
        } elseif (!empty($player['current_hoh'])) {
            $status = 'hoh';
        } elseif (!empty($player['current_pov'])) {
            $status = 'pov';
        } elseif (!empty($player['current_nom'])) {
            $status = 'nom';
        }

        // Get status label
        $statusLabels = [];
        if (!empty($player['current_hoh'])) $statusLabels[] = 'HoH';
        if (!empty($player['current_pov'])) $statusLabels[] = 'PoV';
        if (!empty($player['current_nom'])) $statusLabels[] = 'Nom';
        if (!empty($player['current_jury'])) $statusLabels[] = 'Jury';
        if (!empty($player['current_evicted'])) $statusLabels[] = 'Evicted';
        if (!empty($player['current_havenot'])) $statusLabels[] = 'HN';

        $statusLabel = !empty($statusLabels) ? implode(', ', $statusLabels) : 'Active';

        return [
            'id' => (int) ($player['bbj_player'] ?? $player['id'] ?? 0),
            'player_id' => (int) ($player['bbj_player'] ?? $player['id'] ?? 0),
            'link_id' => (int) ($player['link_id'] ?? 0),
            'name' => trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '')),
            'first_name' => $player['first_name'] ?? '',
            'last_name' => $player['last_name'] ?? '',
            'nickname' => $player['official_nickname'] ?? '',
            'photo' => $player['profile_picture_url'] ?? '',
            'permalink' => $player['permalink'] ?? '',
            'status' => $status,
            'status_label' => $statusLabel,
            'age' => (int) ($player['bbj_age'] ?? 0),
            'hometown' => trim(($player['locality'] ?? '') . ($player['locality'] && ($player['administrative_area_level_1'] ?? '') ? ', ' : '') . ($player['administrative_area_level_1'] ?? '')),
            'occupation' => $player['occupation'] ?? '',
            'stats' => [
                'hoh' => (int) ($player['bbj_total_hoh'] ?? 0),
                'pov' => (int) ($player['bbj_total_pov'] ?? 0),
                'nom' => (int) ($player['bbj_total_nom'] ?? 0),
                'misc' => (int) ($player['bbj_total_misc'] ?? 0),
                'votes_received' => (int) ($player['bbj_votes_received'] ?? 0),
                'veto_played' => (int) ($player['bbj_veto_played'] ?? 0),
                'saved' => (int) ($player['bbj_total_saved'] ?? 0),
                'havenot' => (int) ($player['bbj_total_havenot'] ?? 0),
                'days_in_house' => (int) ($player['bbj_days_in_house'] ?? 0),
            ],
            'game_status' => [
                'hoh' => !empty($player['current_hoh']),
                'pov' => !empty($player['current_pov']),
                'nom' => !empty($player['current_nom']),
                'jury' => !empty($player['current_jury']),
                'evicted' => !empty($player['current_evicted']),
                'havenot' => !empty($player['current_havenot']),
                'safe' => !empty($player['current_safe']),
                'misc' => !empty($player['current_misc']),
                'misc_notes' => $player['misc_notes'] ?? '',
            ],
            'evicted_date' => $player['bbj_evicted_date'] ?? null,
            'finish_place' => isset($player['finish_place']) && $player['finish_place'] !== null
                                ? (int) $player['finish_place']
                                : null,
        ];
    }
}
