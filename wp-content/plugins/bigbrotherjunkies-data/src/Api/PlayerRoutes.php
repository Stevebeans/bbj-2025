<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Permissions\PermissionChecker;
use BigBrotherJunkies\Data\Utils\Revalidation;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API routes for player profiles
 *
 * Used by Next.js frontend to fetch single player profile data
 */
class PlayerRoutes
{
    /**
     * API namespace
     */
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
        // Get players for map (must be registered before slug route)
        register_rest_route(self::NAMESPACE, '/players/map', [
            'methods'  => 'GET',
            'callback' => [$this, 'getPlayersForMap'],
            'permission_callback' => '__return_true',
            'args' => [
                'season' => [
                    'default' => '',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'gender' => [
                    'default' => '',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'achievement' => [
                    'default' => '',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'detail' => [
                    'default' => 'basic',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Get single player by slug
        register_rest_route(self::NAMESPACE, '/players/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods'  => 'GET',
            'callback' => [$this, 'getPlayerBySlug'],
            'permission_callback' => '__return_true',
            'args' => [
                'slug' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_title',
                ],
            ],
        ]);

        // Get all players (for directory and static generation)
        register_rest_route(self::NAMESPACE, '/players', [
            'methods'  => 'GET',
            'callback' => [$this, 'getAllPlayers'],
            'permission_callback' => '__return_true',
            'args' => [
                'fields' => [
                    'default' => 'full',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'per_page' => [
                    'default' => 100,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'page' => [
                    'default' => 1,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'search' => [
                    'default' => '',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'season' => [
                    'default' => '',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'gender' => [
                    'default' => '',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'status' => [
                    'default' => '',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'orderby' => [
                    'default' => 'name',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'order' => [
                    'default' => 'ASC',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // ========================================
        // ADMIN ENDPOINTS
        // ========================================

        // Update player
        register_rest_route(self::NAMESPACE, '/admin/players/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'updatePlayer'],
            'permission_callback' => [$this, 'checkPlayerManagementAccess'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Get single player by slug
     */
    public function getPlayerBySlug(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');

        // Find the player post by slug
        $posts = get_posts([
            'name' => $slug,
            'post_type' => 'bigbrother-players',
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);

        if (empty($posts)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Player not found',
            ], 404);
        }

        $post = $posts[0];
        $playerId = $post->ID;

        // Get player data from custom table
        $player = function_exists('bbj_v2_get_player')
            ? bbj_v2_get_player($playerId)
            : null;

        if (!$player) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Player data not found',
            ], 404);
        }

        // Get all seasons this player appeared in
        $seasons = function_exists('bbj_v2_get_seasons_by_player')
            ? bbj_v2_get_seasons_by_player($playerId)
            : [];

        // Calculate career stats and awards
        $careerStats = $this->calculateCareerStats($seasons, $playerId);
        $awards = $this->detectAwards($seasons, $playerId);

        // Get profile picture
        $photoData = $this->getPhotoData($player['profile_picture'] ?? 0);

        // Get banner image if exists
        $bannerData = $this->getPhotoData($player['player_banner'] ?? 0, 'large');

        // Format seasons data
        $formattedSeasons = $this->formatSeasons($seasons, $playerId, $player);

        // Get related posts (posts mentioning this player)
        $relatedPosts = $this->getRelatedPosts($player, $playerId);

        // Get related players (from same seasons)
        $relatedPlayers = $this->getRelatedPlayers($seasons, $playerId);

        // Calculate current age
        $age = $this->calculateAge($player['date_of_birth'] ?? null);

        // Build response
        $response = [
            'success' => true,
            'player' => [
                'id' => (int) $playerId,
                'slug' => $slug,
                'name' => trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '')),
                'first_name' => $player['first_name'] ?? '',
                'last_name' => $player['last_name'] ?? '',
                'nickname' => $player['official_nickname'] ?? '',
                'display_name' => !empty($player['official_nickname'])
                    ? '"' . $player['official_nickname'] . '"'
                    : ($player['first_name'] ?? ''),
                'photo' => $photoData,
                'banner' => $bannerData,
                'permalink' => get_permalink($playerId),
                'bio' => apply_filters('the_content', $post->post_content),
                'comment_count' => (int) get_comments_number($playerId),
                'age' => $age,
                'date_of_birth' => $player['date_of_birth'] ?? null,
                'gender' => $player['player_gender'] ?? '',
                'occupation' => $player['occupation'] ?? '',
                'hometown' => $this->formatHometown($player),
                'city' => $player['locality'] ?? '',
                'state' => $player['administrative_area_level_1'] ?? '',
                'social' => [
                    'twitter' => $player['twitter'] ?? null,
                    'instagram' => $player['instagram'] ?? null,
                    'facebook' => $player['facebook'] ?? null,
                    'tiktok' => $player['tiktok'] ?? null,
                ],
                'awards' => $awards,
                'stats' => $careerStats,
                'seasons' => $formattedSeasons,
            ],
            'related_posts' => $relatedPosts,
            'related_players' => $relatedPlayers,
        ];

        return new WP_REST_Response($response, 200);
    }

    /**
     * Get all players with filters for directory
     */
    public function getAllPlayers(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $fields = $request->get_param('fields');
        $perPage = min($request->get_param('per_page'), 500);
        $page = max(1, $request->get_param('page'));
        $search = $request->get_param('search');
        $seasonFilter = $request->get_param('season');
        $genderFilter = $request->get_param('gender');
        $statusFilter = $request->get_param('status');
        $achievementFilter = $request->get_param('achievement');
        $orderBy = $request->get_param('orderby');
        $order = strtoupper($request->get_param('order')) === 'DESC' ? 'DESC' : 'ASC';

        // For slug-only requests (static generation), use simple query
        if ($fields === 'slug') {
            $posts = get_posts([
                'post_type' => 'bigbrother-players',
                'post_status' => 'publish',
                'numberposts' => $perPage,
                'orderby' => 'title',
                'order' => 'ASC',
            ]);

            $players = array_map(function ($post) {
                return ['slug' => $post->post_name];
            }, $posts);

            return new WP_REST_Response([
                'success' => true,
                'players' => $players,
                'count' => count($players),
            ], 200);
        }

        // Build query for full player data
        $playersTable = $wpdb->prefix . 'bbj_players';
        $geoTable = $wpdb->prefix . 'bbj_geo';
        $seasonLinkTable = $wpdb->prefix . 'bbj_v2_player_season';
        $postsTable = $wpdb->prefix . 'posts';

        // Base query - get all players with geo data
        $where = ["p.post_status = 'publish'"];
        $params = [];

        // Search filter
        if (!empty($search)) {
            $searchLike = '%' . $wpdb->esc_like($search) . '%';
            $where[] = "(pl.first_name LIKE %s OR pl.last_name LIKE %s OR pl.official_nickname LIKE %s OR CONCAT(pl.first_name, ' ', pl.last_name) LIKE %s)";
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
        }

        // Gender filter (supports comma-separated values like "Male,Female")
        if (!empty($genderFilter)) {
            $genders = array_map('trim', explode(',', $genderFilter));
            $genderPlaceholders = implode(',', array_fill(0, count($genders), '%s'));
            $where[] = "pl.player_gender IN ($genderPlaceholders)";
            $params = array_merge($params, $genders);
        }

        // Achievement filter (supports comma-separated values like "winner,afp,runner_up")
        $seasonsTable = $wpdb->prefix . 'bbj_seasons';
        if (!empty($achievementFilter)) {
            $achievements = array_map('trim', array_map('strtolower', explode(',', $achievementFilter)));
            $achievementConditions = [];

            foreach ($achievements as $achievement) {
                switch ($achievement) {
                    case 'winner':
                        $achievementConditions[] = "EXISTS (SELECT 1 FROM {$seasonsTable} s WHERE s.season_winner = pl.id)";
                        break;
                    case 'afp':
                        $achievementConditions[] = "EXISTS (SELECT 1 FROM {$seasonsTable} s WHERE s.afp = pl.id)";
                        break;
                    case 'runner_up':
                        $achievementConditions[] = "EXISTS (SELECT 1 FROM {$seasonsTable} s WHERE s.runner_up = pl.id)";
                        break;
                }
            }

            if (!empty($achievementConditions)) {
                // Use OR to match any of the selected achievements
                $where[] = '(' . implode(' OR ', $achievementConditions) . ')';
            }
        }

        // Season filter - need to join with season link table
        $seasonJoin = '';
        if (!empty($seasonFilter)) {
            $seasonIds = array_map('intval', explode(',', $seasonFilter));
            $placeholders = implode(',', array_fill(0, count($seasonIds), '%d'));
            $seasonJoin = "INNER JOIN {$seasonLinkTable} psl ON pl.post_id = psl.bbj_player";
            $where[] = "psl.bbj_season IN ($placeholders)";
            $params = array_merge($params, $seasonIds);
        }

        // Status filter (winner, jury, evicted, etc.)
        if (!empty($statusFilter) && !empty($seasonFilter)) {
            $statuses = explode(',', $statusFilter);
            $statusConditions = [];
            foreach ($statuses as $status) {
                switch (strtolower(trim($status))) {
                    case 'winner':
                        $statusConditions[] = "psl.current_misc LIKE '%winner%'";
                        break;
                    case 'jury':
                        $statusConditions[] = "psl.current_jury = 1";
                        break;
                    case 'evicted':
                        $statusConditions[] = "psl.current_evicted = 1";
                        break;
                    case 'hoh':
                        $statusConditions[] = "psl.current_hoh = 1";
                        break;
                    case 'pov':
                        $statusConditions[] = "psl.current_pov = 1";
                        break;
                }
            }
            if (!empty($statusConditions)) {
                $where[] = '(' . implode(' OR ', $statusConditions) . ')';
            }
        }

        $whereClause = implode(' AND ', $where);

        // Order by
        $orderByClause = 'pl.first_name ASC, pl.last_name ASC';
        switch ($orderBy) {
            case 'name':
            case 'first_name':
                $orderByClause = "pl.first_name $order, pl.last_name $order";
                break;
            case 'last_name':
                $orderByClause = "pl.last_name $order, pl.first_name $order";
                break;
            case 'age':
                // Sort by date_of_birth (older = higher age, so ASC date = DESC age)
                // NULL dates go to the end
                $ageOrder = $order === 'ASC' ? 'DESC' : 'ASC';
                $orderByClause = "CASE WHEN pl.date_of_birth IS NULL OR pl.date_of_birth = '0000-00-00' THEN 1 ELSE 0 END, pl.date_of_birth $ageOrder, pl.first_name ASC";
                break;
            case 'season':
                if (!empty($seasonFilter)) {
                    $orderByClause = "psl.bbj_season $order, pl.first_name ASC";
                }
                break;
        }

        // Count total
        $countQuery = "
            SELECT COUNT(DISTINCT pl.id)
            FROM {$playersTable} pl
            INNER JOIN {$postsTable} p ON pl.id = p.ID
            LEFT JOIN {$geoTable} g ON pl.id = g.ID
            {$seasonJoin}
            WHERE {$whereClause}
        ";

        // Only use prepare if we have params, otherwise run query directly
        if (!empty($params)) {
            $totalCount = (int) $wpdb->get_var($wpdb->prepare($countQuery, $params));
        } else {
            $totalCount = (int) $wpdb->get_var($countQuery);
        }

        // Get paginated results
        $offset = ($page - 1) * $perPage;

        $seasonsTable = $wpdb->prefix . 'bbj_seasons';

        $query = "
            SELECT DISTINCT
                pl.id,
                pl.post_id,
                pl.first_name,
                pl.last_name,
                pl.official_nickname,
                pl.player_gender,
                pl.occupation,
                pl.profile_picture,
                pl.date_of_birth,
                g.locality as city,
                g.administrative_area_level_1 as state,
                p.post_name as slug,
                (SELECT COUNT(*) FROM {$seasonsTable} WHERE season_winner = pl.id) as is_winner,
                (SELECT COUNT(*) FROM {$seasonsTable} WHERE runner_up = pl.id) as is_runner_up,
                (SELECT COUNT(*) FROM {$seasonsTable} WHERE afp = pl.id) as is_afp,
                COALESCE(stats.total_hoh, 0) as total_hoh,
                COALESCE(stats.total_pov, 0) as total_pov,
                COALESCE(stats.total_nom, 0) as total_nom,
                COALESCE(stats.total_votes, 0) as total_votes,
                COALESCE(stats.made_jury, 0) as made_jury,
                COALESCE(stats.was_evicted, 0) as was_evicted
            FROM {$playersTable} pl
            INNER JOIN {$postsTable} p ON pl.id = p.ID
            LEFT JOIN {$geoTable} g ON pl.id = g.ID
            LEFT JOIN (
                SELECT bbj_player,
                    SUM(bbj_total_hoh) as total_hoh,
                    SUM(bbj_total_pov) as total_pov,
                    SUM(bbj_total_nom) as total_nom,
                    SUM(bbj_votes_received) as total_votes,
                    MAX(current_jury) as made_jury,
                    MAX(current_evicted) as was_evicted
                FROM {$seasonLinkTable}
                GROUP BY bbj_player
            ) stats ON pl.id = stats.bbj_player
            {$seasonJoin}
            WHERE {$whereClause}
            ORDER BY {$orderByClause}
            LIMIT %d OFFSET %d
        ";

        // Always have at least perPage and offset params
        $queryParams = array_merge($params, [$perPage, $offset]);

        $results = $wpdb->get_results($wpdb->prepare($query, $queryParams), ARRAY_A);

        // Format players
        $players = [];
        foreach ($results as $row) {
            $photoUrl = null;
            if (!empty($row['profile_picture'])) {
                $imageData = wp_get_attachment_image_src((int) $row['profile_picture'], 'bbj_v2_spoiler_bar');
                $photoUrl = $imageData ? $imageData[0] : null;
            }

            $hometown = '';
            if ($row['city'] && $row['state']) {
                $hometown = $row['city'] . ', ' . $row['state'];
            } elseif ($row['city'] || $row['state']) {
                $hometown = $row['city'] ?: $row['state'];
            }

            // Calculate age
            $age = null;
            if (!empty($row['date_of_birth']) && $row['date_of_birth'] !== '0000-00-00') {
                try {
                    $dob = new \DateTime($row['date_of_birth']);
                    $age = $dob->diff(new \DateTime())->y;
                } catch (\Exception $e) {
                    // Ignore
                }
            }

            // Check for achievements (for badges)
            $isWinner = (int) $row['is_winner'] > 0;
            $isRunnerUp = (int) $row['is_runner_up'] > 0;
            $isAfp = (int) $row['is_afp'] > 0;
            $madeJury = (int) $row['made_jury'] > 0;
            $wasEvicted = (int) $row['was_evicted'] > 0;

            // Determine status based on best placement (winner > runner_up > afp > jury > evicted > active)
            $status = 'active';
            $statusLabel = 'Player';
            if ($isWinner) {
                $status = 'winner';
                $statusLabel = 'Winner';
            } elseif ($isRunnerUp) {
                $status = 'runner_up';
                $statusLabel = 'Runner Up';
            } elseif ($isAfp) {
                $status = 'afp';
                $statusLabel = 'AFP';
            } elseif ($madeJury) {
                $status = 'jury';
                $statusLabel = 'Jury';
            } elseif ($wasEvicted) {
                $status = 'evicted';
                $statusLabel = 'Evicted';
            }

            $players[] = [
                'id' => (int) $row['id'],
                'slug' => $row['slug'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'nickname' => $row['official_nickname'] ?: null,
                'gender' => $row['player_gender'],
                'occupation' => $row['occupation'] ?: null,
                'age' => $age,
                'hometown' => $hometown ?: null,
                'photo' => $photoUrl,
                'permalink' => get_permalink((int) $row['id']),
                'status' => $status,
                'status_label' => $statusLabel,
                'is_winner' => $isWinner,
                'is_afp' => $isAfp,
                'stats' => [
                    'hoh' => (int) $row['total_hoh'],
                    'pov' => (int) $row['total_pov'],
                    'nom' => (int) $row['total_nom'],
                    'votes_received' => (int) $row['total_votes'],
                ],
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'players' => $players,
            'count' => count($players),
            'total' => $totalCount,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($totalCount / $perPage),
        ], 200);
    }

    /**
     * Get players with lat/lng for the interactive map
     */
    public function getPlayersForMap(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $seasonFilter = $request->get_param('season');
        $genderFilter = $request->get_param('gender');
        $achievementFilter = $request->get_param('achievement');
        $detail = $request->get_param('detail');

        $playersTable = $wpdb->prefix . 'bbj_players';
        $postsTable = $wpdb->prefix . 'posts';
        $seasonsTable = $wpdb->prefix . 'bbj_seasons';
        $seasonLinkTable = $wpdb->prefix . 'bbj_v2_player_season';

        $where = [
            "p.post_status = 'publish'",
            "pl.hometown_lat IS NOT NULL",
            "pl.hometown_lat != 0",
        ];
        $params = [];
        $joins = '';

        // Season filter
        if (!empty($seasonFilter)) {
            $seasonIds = array_map('intval', explode(',', $seasonFilter));
            $placeholders = implode(',', array_fill(0, count($seasonIds), '%d'));
            $joins .= " INNER JOIN {$seasonLinkTable} psl ON pl.id = psl.bbj_player";
            $where[] = "psl.bbj_season IN ($placeholders)";
            $params = array_merge($params, $seasonIds);
        }

        // Gender filter
        if (!empty($genderFilter)) {
            $genders = array_map('trim', explode(',', $genderFilter));
            $genderPlaceholders = implode(',', array_fill(0, count($genders), '%s'));
            $where[] = "pl.player_gender IN ($genderPlaceholders)";
            $params = array_merge($params, $genders);
        }

        // Achievement filter
        if (!empty($achievementFilter)) {
            $achievements = array_map('trim', array_map('strtolower', explode(',', $achievementFilter)));
            $achievementConditions = [];
            foreach ($achievements as $achievement) {
                switch ($achievement) {
                    case 'winner':
                        $achievementConditions[] = "EXISTS (SELECT 1 FROM {$seasonsTable} s WHERE s.season_winner = pl.id)";
                        break;
                    case 'afp':
                        $achievementConditions[] = "EXISTS (SELECT 1 FROM {$seasonsTable} s WHERE s.afp = pl.id)";
                        break;
                    case 'runner_up':
                        $achievementConditions[] = "EXISTS (SELECT 1 FROM {$seasonsTable} s WHERE s.runner_up = pl.id)";
                        break;
                }
            }
            if (!empty($achievementConditions)) {
                $where[] = '(' . implode(' OR ', $achievementConditions) . ')';
            }
        }

        $whereClause = implode(' AND ', $where);

        // Premium detail level adds extra columns
        $extraSelect = '';
        $extraJoin = '';
        if ($detail === 'premium') {
            $extraSelect = ",
                pl.player_gender,
                COALESCE(stats.total_hoh, 0) as total_hoh,
                COALESCE(stats.total_pov, 0) as total_pov,
                (SELECT COUNT(*) FROM {$seasonsTable} WHERE runner_up = pl.id) as is_runner_up";
            $extraJoin = "
                LEFT JOIN (
                    SELECT bbj_player,
                        SUM(bbj_total_hoh) as total_hoh,
                        SUM(bbj_total_pov) as total_pov
                    FROM {$seasonLinkTable}
                    GROUP BY bbj_player
                ) stats ON pl.id = stats.bbj_player";
        }

        $query = "
            SELECT DISTINCT
                pl.id,
                pl.first_name,
                pl.last_name,
                pl.profile_picture,
                pl.hometown_city,
                pl.hometown_state,
                pl.hometown_lat as lat,
                pl.hometown_lng as lng,
                p.post_name as slug,
                (SELECT COUNT(*) FROM {$seasonsTable} WHERE season_winner = pl.id) as is_winner,
                (SELECT COUNT(*) FROM {$seasonsTable} WHERE afp = pl.id) as is_afp
                {$extraSelect}
            FROM {$playersTable} pl
            INNER JOIN {$postsTable} p ON pl.id = p.ID
            {$joins}
            {$extraJoin}
            WHERE {$whereClause}
            ORDER BY pl.first_name ASC
        ";

        if (!empty($params)) {
            $results = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        } else {
            $results = $wpdb->get_results($query, ARRAY_A);
        }

        $players = [];
        // For premium detail, also collect state stats
        $stateStats = [];

        foreach ($results as $row) {
            $photoUrl = null;
            if (!empty($row['profile_picture'])) {
                $imageData = wp_get_attachment_image_src((int) $row['profile_picture'], 'bbj_v2_spoiler_bar');
                $photoUrl = $imageData ? $imageData[0] : null;
            }

            $isWinner = (int) $row['is_winner'] > 0;
            $isAfp = (int) $row['is_afp'] > 0;

            $player = [
                'id' => (int) $row['id'],
                'slug' => $row['slug'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'photo' => $photoUrl,
                'hometown_city' => $row['hometown_city'] ?: null,
                'hometown_state' => $row['hometown_state'] ?: null,
                'lat' => (float) $row['lat'],
                'lng' => (float) $row['lng'],
                'is_winner' => $isWinner,
                'is_afp' => $isAfp,
            ];

            if ($detail === 'premium') {
                $isRunnerUp = (int) ($row['is_runner_up'] ?? 0) > 0;

                // Determine status
                $status = 'active';
                if ($isWinner) $status = 'winner';
                elseif ($isRunnerUp) $status = 'runner_up';
                elseif ($isAfp) $status = 'afp';

                $player['gender'] = $row['player_gender'] ?? null;
                $player['status'] = $status;
                $player['total_hoh'] = (int) ($row['total_hoh'] ?? 0);
                $player['total_pov'] = (int) ($row['total_pov'] ?? 0);

                // Aggregate state stats
                $state = $row['hometown_state'] ?: 'Unknown';
                if (!isset($stateStats[$state])) {
                    $stateStats[$state] = ['count' => 0, 'cities' => []];
                }
                $stateStats[$state]['count']++;
                $city = $row['hometown_city'] ?: 'Unknown';
                if (!isset($stateStats[$state]['cities'][$city])) {
                    $stateStats[$state]['cities'][$city] = 0;
                }
                $stateStats[$state]['cities'][$city]++;
            }

            $players[] = $player;
        }

        $response = [
            'success' => true,
            'players' => $players,
            'count' => count($players),
        ];

        // Add state stats for premium
        if ($detail === 'premium' && !empty($stateStats)) {
            $formattedStats = [];
            foreach ($stateStats as $stateName => $data) {
                arsort($data['cities']);
                $topCity = array_key_first($data['cities']);
                $formattedStats[$stateName] = [
                    'count' => $data['count'],
                    'top_city' => $topCity,
                    'top_city_count' => $data['cities'][$topCity],
                ];
            }
            $response['state_stats'] = $formattedStats;
        }

        // Get seasons list for premium (for timeline)
        if ($detail === 'premium') {
            $seasonsList = $wpdb->get_results("
                SELECT p.ID as id, p.post_name as slug, s.abbreviation, s.start_date
                FROM {$seasonsTable} s
                INNER JOIN {$postsTable} p ON s.id = p.ID
                WHERE p.post_status = 'publish'
                ORDER BY s.start_date ASC
            ", ARRAY_A);

            // Get season assignments for each player
            $playerSeasons = $wpdb->get_results("
                SELECT psl.bbj_player, psl.bbj_season
                FROM {$seasonLinkTable} psl
                INNER JOIN {$postsTable} p ON psl.bbj_player = p.ID
                WHERE p.post_status = 'publish'
            ", ARRAY_A);

            $playerSeasonMap = [];
            foreach ($playerSeasons as $ps) {
                $pid = (int) $ps['bbj_player'];
                if (!isset($playerSeasonMap[$pid])) {
                    $playerSeasonMap[$pid] = [];
                }
                $playerSeasonMap[$pid][] = (int) $ps['bbj_season'];
            }

            // Attach season IDs to each player
            foreach ($players as &$p) {
                $p['season_ids'] = $playerSeasonMap[$p['id']] ?? [];
            }
            unset($p);

            $response['players'] = $players;
            $response['seasons'] = array_map(function($s) {
                return [
                    'id' => (int) $s['id'],
                    'abbreviation' => $s['abbreviation'],
                    'start_date' => $s['start_date'],
                ];
            }, $seasonsList);
        }

        return new WP_REST_Response($response, 200);
    }

    /**
     * Calculate career stats from all seasons
     */
    private function calculateCareerStats(array $seasons, int $playerId): array
    {
        $totalHoh = 0;
        $totalPov = 0;
        $totalNom = 0;
        $totalVotes = 0;
        $totalDays = 0;

        foreach ($seasons as $season) {
            $totalHoh += (int) ($season['bbj_total_hoh'] ?? 0);
            $totalPov += (int) ($season['bbj_total_pov'] ?? 0);
            $totalNom += (int) ($season['bbj_total_nom'] ?? 0);
            $totalVotes += (int) ($season['bbj_votes_received'] ?? 0);

            // Calculate days in house for this season
            $daysInHouse = $this->calculateDaysInHouse($season);
            $totalDays += $daysInHouse;
        }

        return [
            'total_seasons' => count($seasons),
            'total_hoh' => $totalHoh,
            'total_pov' => $totalPov,
            'total_nom' => $totalNom,
            'total_votes' => $totalVotes,
            'total_days' => $totalDays,
        ];
    }

    /**
     * Detect awards (winner, runner-up, AFP)
     */
    private function detectAwards(array $seasons, int $playerId): array
    {
        $awards = [
            'winner' => false,
            'runner_up' => false,
            'afp' => false,
        ];

        foreach ($seasons as $season) {
            if (!empty($season['season_winner']) && (int) $season['season_winner'] === $playerId) {
                $awards['winner'] = true;
            }
            if (!empty($season['runner_up']) && (int) $season['runner_up'] === $playerId) {
                $awards['runner_up'] = true;
            }
            if (!empty($season['afp']) && (int) $season['afp'] === $playerId) {
                $awards['afp'] = true;
            }
        }

        return $awards;
    }

    /**
     * Format seasons for API response
     */
    private function formatSeasons(array $seasons, int $playerId, array $player): array
    {
        $formatted = [];

        foreach ($seasons as $season) {
            $seasonId = (int) ($season['bbj_season'] ?? 0);
            $seasonName = $season['full_name'] ?? '';
            $seasonAbbr = $season['abbreviation'] ?? '';

            // Calculate dates and days
            $startDate = !empty($season['start_date']) ? new \DateTime($season['start_date']) : null;
            $endDate = !empty($season['end_date']) ? new \DateTime($season['end_date']) : null;
            $evictedDate = $this->parseDate($season['bbj_evicted_date'] ?? null);

            $totalDays = ($startDate && $endDate) ? $endDate->diff($startDate)->days + 1 : null;
            $exitDate = $evictedDate ?: $endDate;
            $daysInHouse = ($startDate && $exitDate) ? $exitDate->diff($startDate)->days + 1 : null;

            $progressPct = ($totalDays && $daysInHouse)
                ? max(0, min(100, (int) round(($daysInHouse / $totalDays) * 100)))
                : null;

            // Calculate age during season
            $ageDuring = null;
            if (!empty($player['date_of_birth']) && $startDate) {
                $dob = new \DateTime($player['date_of_birth']);
                $ageDuring = $dob->diff($startDate)->y;
            }

            // Determine result
            $result = $this->getSeasonResult($season, $playerId);

            // Get current status
            $status = $this->getPlayerStatus($season);

            // Get season slug from post
            $seasonPost = get_post($seasonId);
            $seasonSlug = $seasonPost ? $seasonPost->post_name : null;

            $formatted[] = [
                'season_id' => $seasonId,
                'season_slug' => $seasonSlug,
                'season_name' => $seasonName,
                'season_abbr' => $seasonAbbr,
                'season_permalink' => get_permalink($seasonId),
                'hoh' => (int) ($season['bbj_total_hoh'] ?? 0),
                'pov' => (int) ($season['bbj_total_pov'] ?? 0),
                'nom' => (int) ($season['bbj_total_nom'] ?? 0),
                'votes_received' => (int) ($season['bbj_votes_received'] ?? 0),
                'total_days' => $totalDays,
                'days_in_house' => $daysInHouse,
                'progress_pct' => $progressPct,
                'age_during' => $ageDuring,
                'result' => $result,
                'status' => $status,
                'evicted_date' => $evictedDate ? $evictedDate->format('Y-m-d') : null,
            ];
        }

        return $formatted;
    }

    /**
     * Get season result for player
     */
    private function getSeasonResult(array $season, int $playerId): string
    {
        if (!empty($season['season_winner']) && (int) $season['season_winner'] === $playerId) {
            return 'Winner';
        }
        if (!empty($season['runner_up']) && (int) $season['runner_up'] === $playerId) {
            return 'Runner Up';
        }
        if (!empty($season['afp']) && (int) $season['afp'] === $playerId) {
            return 'AFP';
        }
        if (!empty($season['current_jury'])) {
            return 'Jury';
        }
        if (!empty($season['current_evicted'])) {
            return 'Evicted';
        }
        return 'Active';
    }

    /**
     * Get player status in a season
     */
    private function getPlayerStatus(array $season): string
    {
        if (!empty($season['current_hoh'])) return 'hoh';
        if (!empty($season['current_pov'])) return 'pov';
        if (!empty($season['current_evicted'])) return 'evicted';
        if (!empty($season['current_jury'])) return 'jury';
        if (!empty($season['current_nom'])) return 'nom';
        if (!empty($season['current_havenot'])) return 'havenot';
        if (!empty($season['current_safe'])) return 'safe';
        return 'active';
    }

    /**
     * Get related posts mentioning this player
     */
    private function getRelatedPosts(array $player, int $playerId): array
    {
        $playerName = trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''));

        if (empty($playerName)) {
            return [];
        }

        // Search for posts containing the player's name
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            's' => $playerName,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $formatted = [];
        foreach ($posts as $post) {
            $formatted[] = [
                'id' => $post->ID,
                'slug' => $post->post_name,
                'title' => $post->post_title,
                'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 20),
                'date' => $post->post_date,
                'permalink' => get_permalink($post->ID),
                'featured_image' => get_the_post_thumbnail_url($post->ID, 'medium') ?: null,
            ];
        }

        return $formatted;
    }

    /**
     * Get related players from same seasons, grouped by season
     */
    private function getRelatedPlayers(array $seasons, int $currentPlayerId): array
    {
        if (empty($seasons)) {
            return [];
        }

        $result = [];

        foreach ($seasons as $season) {
            $seasonId = (int) ($season['bbj_season'] ?? 0);
            $seasonName = $season['full_name'] ?? '';
            $seasonAbbr = $season['abbreviation'] ?? '';

            if (!$seasonId) {
                continue;
            }

            // Get players from this season
            $seasonPlayers = function_exists('bbj_v2_get_season_players')
                ? bbj_v2_get_season_players($seasonId, 'bbj_v2_spoiler_bar')
                : [];

            $players = [];

            foreach ($seasonPlayers as $player) {
                $playerId = (int) ($player['bbj_player'] ?? $player['id'] ?? 0);

                // Skip current player
                if ($playerId === $currentPlayerId) {
                    continue;
                }

                $players[] = [
                    'id' => $playerId,
                    'name' => trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '')),
                    'nickname' => $player['official_nickname'] ?? '',
                    'photo' => $player['profile_picture_url'] ?? '',
                    'permalink' => $player['permalink'] ?? get_permalink($playerId),
                    'status' => $this->getPlayerStatus($player),
                ];
            }

            $result[] = [
                'season_id' => $seasonId,
                'season_name' => $seasonName,
                'season_abbr' => $seasonAbbr,
                'players' => $players,
            ];
        }

        return $result;
    }

    /**
     * Get photo data with dimensions
     */
    private function getPhotoData(int $attachmentId, string $size = 'bbj_v2_profile_image'): ?array
    {
        if (!$attachmentId) {
            return null;
        }

        $imageData = wp_get_attachment_image_src($attachmentId, $size);

        if (!$imageData) {
            return null;
        }

        return [
            'url' => $imageData[0],
            'width' => (int) $imageData[1],
            'height' => (int) $imageData[2],
        ];
    }

    /**
     * Format hometown from city and state
     */
    private function formatHometown(array $player): string
    {
        $city = $player['locality'] ?? '';
        $state = $player['administrative_area_level_1'] ?? '';

        if ($city && $state) {
            return "$city, $state";
        }

        return $city ?: $state ?: '';
    }

    /**
     * Calculate current age from date of birth
     */
    private function calculateAge(?string $dob): ?int
    {
        if (!$dob || $dob === '0000-00-00') {
            return null;
        }

        try {
            $birthDate = new \DateTime($dob);
            $today = new \DateTime();
            return $birthDate->diff($today)->y;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse date string to DateTime
     */
    private function parseDate(?string $dateStr): ?\DateTime
    {
        if (!$dateStr || $dateStr === '0000-00-00') {
            return null;
        }

        try {
            return new \DateTime($dateStr);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calculate days in house for a season
     */
    private function calculateDaysInHouse(array $season): int
    {
        $startDate = !empty($season['start_date']) ? new \DateTime($season['start_date']) : null;
        $endDate = !empty($season['end_date']) ? new \DateTime($season['end_date']) : null;
        $evictedDate = $this->parseDate($season['bbj_evicted_date'] ?? null);

        if (!$startDate) {
            return 0;
        }

        $exitDate = $evictedDate ?: $endDate;

        if (!$exitDate) {
            return 0;
        }

        return $exitDate->diff($startDate)->days + 1;
    }

    // ========================================
    // ADMIN ENDPOINT CALLBACKS
    // ========================================

    /**
     * Update player
     */
    public function updatePlayer(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $playerId = $request->get_param('id');
        $params = $request->get_json_params();

        // Get existing player post
        $post = get_post($playerId);

        if (!$post || $post->post_type !== 'bigbrother-players') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Player not found',
            ], 404);
        }

        // Tables
        $playersTable = $wpdb->prefix . 'bbj_players';
        $geoTable = $wpdb->prefix . 'bbj_geo';

        // Prepare player data update
        $playerData = [];
        $playerFormat = [];

        // Map allowed player fields
        $playerFields = [
            'first_name' => '%s',
            'last_name' => '%s',
            'official_nickname' => '%s',
            'player_gender' => '%s',
            'date_of_birth' => '%s',
            'occupation' => '%s',
            'twitter' => '%s',
            'instagram' => '%s',
            'facebook' => '%s',
            'tiktok' => '%s',
            'profile_picture' => '%d',
            'player_banner' => '%d',
        ];

        foreach ($playerFields as $field => $format) {
            if (isset($params[$field])) {
                $value = $params[$field];
                // Handle null/empty values for integer fields
                if ($format === '%d' && ($value === '' || $value === null)) {
                    $playerData[$field] = null;
                    $playerFormat[] = '%s'; // Use string format for NULL
                } else {
                    $playerData[$field] = $format === '%d' ? absint($value) : sanitize_text_field($value);
                    $playerFormat[] = $format;
                }
            }
        }

        // Update player table if there's data
        if (!empty($playerData)) {
            $result = $wpdb->update(
                $playersTable,
                $playerData,
                ['id' => $playerId],
                $playerFormat,
                ['%d']
            );

            if ($result === false) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Failed to update player: ' . $wpdb->last_error,
                ], 500);
            }
        }

        // Update geo data if provided
        $geoData = [];
        $geoFormat = [];

        $geoFields = [
            'locality' => '%s',           // city
            'administrative_area_level_1' => '%s', // state
            'lat' => '%s',
            'lng' => '%s',
        ];

        foreach ($geoFields as $field => $format) {
            if (isset($params[$field])) {
                $geoData[$field] = sanitize_text_field($params[$field]);
                $geoFormat[] = $format;
            }
        }

        if (!empty($geoData)) {
            // Check if geo record exists
            $existingGeo = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$geoTable} WHERE ID = %d",
                $playerId
            ));

            if ($existingGeo) {
                // Update existing
                $wpdb->update(
                    $geoTable,
                    $geoData,
                    ['ID' => $playerId],
                    $geoFormat,
                    ['%d']
                );
            } else {
                // Insert new
                $geoData['ID'] = $playerId;
                $wpdb->insert($geoTable, $geoData);
            }
        }

        // Update WordPress post if name changed
        $postUpdate = [];

        if (isset($params['first_name']) || isset($params['last_name'])) {
            // Get current player data for full name
            $currentPlayer = $wpdb->get_row($wpdb->prepare(
                "SELECT first_name, last_name FROM {$playersTable} WHERE id = %d",
                $playerId
            ), ARRAY_A);

            if ($currentPlayer) {
                $firstName = $params['first_name'] ?? $currentPlayer['first_name'];
                $lastName = $params['last_name'] ?? $currentPlayer['last_name'];
                $postUpdate['post_title'] = trim("$firstName $lastName");
            }
        }

        // Update bio if provided (stored as post_content)
        if (isset($params['bio'])) {
            $postUpdate['post_content'] = wp_kses_post($params['bio']);
        }

        if (!empty($postUpdate)) {
            $postUpdate['ID'] = $playerId;
            wp_update_post($postUpdate);
        }

        // Trigger Next.js revalidation
        Revalidation::revalidatePlayer($post->post_name);

        // Get updated player data
        $updatedPlayer = function_exists('bbj_v2_get_player')
            ? bbj_v2_get_player($playerId)
            : null;

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Player updated successfully',
            'player' => [
                'id' => $playerId,
                'slug' => $post->post_name,
                'name' => trim(($updatedPlayer['first_name'] ?? '') . ' ' . ($updatedPlayer['last_name'] ?? '')),
            ],
        ], 200);
    }

    // ========================================
    // PERMISSION CALLBACKS
    // ========================================

    /**
     * Check if user has player management access
     */
    public function checkPlayerManagementAccess(): bool
    {
        return PermissionChecker::userCan('player_management');
    }
}
