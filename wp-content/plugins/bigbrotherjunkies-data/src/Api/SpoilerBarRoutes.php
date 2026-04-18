<?php

namespace BigBrotherJunkies\Data\Api;

use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API routes for spoiler bar / current season players
 *
 * Used by Next.js frontend to fetch player data for spoiler bar
 */
class SpoilerBarRoutes
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
        // Get spoiler bar data (sorted for display)
        register_rest_route(self::NAMESPACE, '/spoiler-bar', [
            'methods'  => 'GET',
            'callback' => [$this, 'getSpoilerBar'],
            'permission_callback' => '__return_true',
        ]);

        // Get current season players (full data)
        register_rest_route(self::NAMESPACE, '/current-season-players', [
            'methods'  => 'GET',
            'callback' => [$this, 'getCurrentSeasonPlayers'],
            'permission_callback' => '__return_true',
            'args' => [
                'size' => [
                    'default' => 'bbj_v2_spoiler_bar',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Get players for a specific season
        register_rest_route(self::NAMESPACE, '/seasons/(?P<season_id>\d+)/players', [
            'methods'  => 'GET',
            'callback' => [$this, 'getSeasonPlayers'],
            'permission_callback' => '__return_true',
            'args' => [
                'season_id' => [
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
    }

    /**
     * Get spoiler bar data (sorted for display)
     */
    public function getSpoilerBar(WP_REST_Request $request): WP_REST_Response
    {
        $currentSeasonId = get_option('bbj_v2_current_season', '');

        if (empty($currentSeasonId)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No current season configured',
                'players' => [],
            ], 200);
        }

        $season = function_exists('bbj_v2_get_season_by_id')
            ? bbj_v2_get_season_by_id($currentSeasonId)
            : [];

        $players = function_exists('bbj_v2_get_season_players')
            ? bbj_v2_get_season_players($currentSeasonId, 'bbj_v2_spoiler_bar')
            : [];

        // Sort players by spoiler weight
        usort($players, [$this, 'sortBySpoilerWeight']);

        // Format players for API response
        $formattedPlayers = array_map([$this, 'formatPlayer'], $players);

        return new WP_REST_Response([
            'success' => true,
            'season' => [
                'id' => (int) $currentSeasonId,
                'name' => $season['full_name'] ?? '',
                'season_number' => (int) ($season['season_number'] ?? 0),
            ],
            'players' => $formattedPlayers,
        ], 200);
    }

    /**
     * Get current season players (full data)
     */
    public function getCurrentSeasonPlayers(WP_REST_Request $request): WP_REST_Response
    {
        $currentSeasonId = get_option('bbj_v2_current_season', '');
        $size = $request->get_param('size') ?: 'bbj_v2_profile_image';

        if (empty($currentSeasonId)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No current season configured',
                'players' => [],
            ], 200);
        }

        $season = function_exists('bbj_v2_get_season_by_id')
            ? bbj_v2_get_season_by_id($currentSeasonId)
            : [];

        $players = function_exists('bbj_v2_get_season_players')
            ? bbj_v2_get_season_players($currentSeasonId, $size)
            : [];

        // Sort players by spoiler weight
        usort($players, [$this, 'sortBySpoilerWeight']);

        // Format players with full details
        $formattedPlayers = array_map([$this, 'formatPlayerFull'], $players);

        return new WP_REST_Response([
            'success' => true,
            'season' => [
                'id' => (int) $currentSeasonId,
                'name' => $season['full_name'] ?? '',
                'season_number' => (int) ($season['season_number'] ?? 0),
                'show_type' => $season['show_type'] ?? 'bb',
                'afp_id' => isset($season['afp']) ? (int) $season['afp'] : null,
            ],
            'players' => $formattedPlayers,
            'count' => count($formattedPlayers),
        ], 200);
    }

    /**
     * Get players for a specific season
     */
    public function getSeasonPlayers(WP_REST_Request $request): WP_REST_Response
    {
        $seasonId = $request->get_param('season_id');
        $size = $request->get_param('size') ?: 'bbj_v2_profile_image';

        $season = function_exists('bbj_v2_get_season_by_id')
            ? bbj_v2_get_season_by_id($seasonId)
            : [];

        if (empty($season)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Season not found',
                'players' => [],
            ], 404);
        }

        $players = function_exists('bbj_v2_get_season_players')
            ? bbj_v2_get_season_players($seasonId, $size)
            : [];

        usort($players, [$this, 'sortBySpoilerWeight']);
        $formattedPlayers = array_map([$this, 'formatPlayerFull'], $players);

        return new WP_REST_Response([
            'success' => true,
            'season' => [
                'id' => (int) $seasonId,
                'name' => $season['full_name'] ?? '',
                'season_number' => (int) ($season['season_number'] ?? 0),
                'show_type' => $season['show_type'] ?? 'bb',
            ],
            'players' => $formattedPlayers,
            'count' => count($formattedPlayers),
        ], 200);
    }

    /**
     * Sort players by spoiler weight (HoH > PoV > Active > Nom > Jury > Evicted)
     *
     * Sorting priority:
     * 1. Active players first (no finish_place), sorted by status weight then name
     * 2. Eliminated players by finish_place ASC (1=winner first, then runner-up, etc.)
     * 3. Fallback to evicted_date DESC if finish_place not set
     */
    private function sortBySpoilerWeight(array $a, array $b): int
    {
        // Check if either player has finish_place (is eliminated with placement)
        $finishA = isset($a['finish_place']) && $a['finish_place'] !== null ? (int)$a['finish_place'] : null;
        $finishB = isset($b['finish_place']) && $b['finish_place'] !== null ? (int)$b['finish_place'] : null;

        // Both have finish_place - sort by placement (1=winner first)
        if ($finishA !== null && $finishB !== null) {
            return $finishA <=> $finishB;
        }

        // One has finish_place, one doesn't - active players (no finish_place) come first
        if ($finishA !== null && $finishB === null) {
            return 1; // A is eliminated, B is active -> B first
        }
        if ($finishA === null && $finishB !== null) {
            return -1; // A is active, B is eliminated -> A first
        }

        // Neither has finish_place - use status weight sorting
        $wa = $this->getSpoilerWeight($a);
        $wb = $this->getSpoilerWeight($b);

        if ($wa !== $wb) {
            return $wa - $wb;
        }

        // Same bucket: Jury (5) or Evicted (6) → sort by eviction date (newest first)
        if ($wa === 5 || $wa === 6) {
            $da = $this->parseEvictionTimestamp($a['bbj_evicted_date'] ?? null);
            $db = $this->parseEvictionTimestamp($b['bbj_evicted_date'] ?? null);

            if ($da === $db) {
                // Tie-break by name
                $an = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
                $bn = trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''));
                $cmp = strcasecmp($an, $bn);
                return $cmp !== 0 ? $cmp : ((int)($a['bbj_player'] ?? 0) <=> (int)($b['bbj_player'] ?? 0));
            }
            if ($da === null) return 1;
            if ($db === null) return -1;

            return $db <=> $da; // Newest first
        }

        // Other buckets: sort by name
        $an = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
        $bn = trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''));
        return strcasecmp($an, $bn);
    }

    /**
     * Get numeric weight for sorting (lower = higher priority)
     */
    private function getSpoilerWeight(array $player): int
    {
        if (!empty($player['current_hoh']))     return 1;
        if (!empty($player['current_pov']))     return 2;

        // Active = no special status
        if (
            empty($player['current_hoh']) &&
            empty($player['current_pov']) &&
            empty($player['current_nom']) &&
            empty($player['current_jury']) &&
            empty($player['current_evicted'])
        ) {
            return 3;
        }

        if (!empty($player['current_nom']))     return 4;
        if (!empty($player['current_jury']))    return 5;
        if (!empty($player['current_evicted'])) return 6;

        return 3; // Default to active
    }

    /**
     * Get status label(s) for a player
     */
    private function getStatusLabel(array $player): string
    {
        // Check finish_place first for winner/runner-up
        $finishPlace = isset($player['finish_place']) ? (int) $player['finish_place'] : null;
        if ($finishPlace === 1) return 'Winner';
        if ($finishPlace === 2) return '2nd';

        $labels = [];
        if (!empty($player['current_hoh']))     $labels[] = 'HoH';
        if (!empty($player['current_pov']))     $labels[] = 'PoV';
        if (!empty($player['current_nom']))     $labels[] = 'Nom';
        if (!empty($player['current_havenot'])) $labels[] = 'HN';
        if (!empty($player['current_jury']))    $labels[] = 'Jury';
        if (!empty($player['current_evicted'])) $labels[] = 'Evicted';
        if (empty($labels) && !empty($player['current_safe'])) $labels[] = 'Safe';
        if (empty($labels) && !empty($player['current_misc'])) {
            $labels[] = $player['misc_notes'] ?: 'Misc';
        }
        return !empty($labels) ? implode(', ', $labels) : 'Active';
    }

    /**
     * Get primary status color key for a player
     */
    private function getStatusColor(array $player): string
    {
        // Check finish_place first for winner/runner-up (takes priority over all other statuses)
        $finishPlace = isset($player['finish_place']) ? (int) $player['finish_place'] : null;
        if ($finishPlace === 1) return 'winner';
        if ($finishPlace === 2) return 'runner_up';

        // Eliminated statuses
        if (!empty($player['current_jury']))    return 'jury';
        if (!empty($player['current_evicted'])) return 'evicted';

        // Active game statuses
        if (!empty($player['current_hoh']))     return 'hoh';
        if (!empty($player['current_pov']))     return 'pov';
        if (!empty($player['current_nom']))     return 'nom';
        if (!empty($player['current_havenot'])) return 'havenot';
        if (!empty($player['current_safe']))    return 'safe';
        if (!empty($player['current_misc']))    return 'misc';
        return 'active';
    }

    /**
     * Format player for spoiler bar API response
     */
    private function formatPlayer(array $player): array
    {
        $displayName = !empty($player['official_nickname'])
            ? '"' . $player['official_nickname'] . '"'
            : ($player['first_name'] ?? '');

        return [
            'player_id'     => (int) ($player['bbj_player'] ?? $player['id'] ?? 0),
            'name'          => trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '')),
            'first_name'    => $player['first_name'] ?? '',
            'last_name'     => $player['last_name'] ?? '',
            'nickname'      => $player['official_nickname'] ?? '',
            'display_name'  => $displayName,
            'photo'         => $player['profile_picture_url'] ?? '',
            'permalink'     => $player['permalink'] ?? '',
            'status'        => $this->getStatusColor($player),
            'status_label'  => $this->getStatusLabel($player),
            'finish_place'  => isset($player['finish_place']) && $player['finish_place'] !== null
                                ? (int) $player['finish_place']
                                : null,
        ];
    }

    /**
     * Format player with full details for players API
     */
    private function formatPlayerFull(array $player): array
    {
        $base = $this->formatPlayer($player);

        return array_merge($base, [
            'season_id'         => (int) ($player['bbj_season'] ?? 0),
            'age'               => (int) ($player['bbj_age'] ?? 0),
            'hometown'          => $player['hometown'] ?? '',
            'occupation'        => $player['occupation'] ?? '',
            'photo_width'       => (int) ($player['profile_picture_width'] ?? 0),
            'photo_height'      => (int) ($player['profile_picture_height'] ?? 0),
            'evicted_date'      => $player['bbj_evicted_date'] ?? null,
            'eviction_order'    => (int) ($player['bbj_eviction_order'] ?? 0),
            'finish_place'      => isset($player['finish_place']) && $player['finish_place'] !== null
                                    ? (int) $player['finish_place']
                                    : null,
            'social' => [
                'twitter'   => $player['bbj_twitter'] ?? null,
                'instagram' => $player['bbj_instagram'] ?? null,
            ],
            'game_status' => [
                'hoh'       => !empty($player['current_hoh']),
                'pov'       => !empty($player['current_pov']),
                'nom'       => !empty($player['current_nom']),
                'jury'      => !empty($player['current_jury']),
                'evicted'   => !empty($player['current_evicted']),
                'havenot'   => !empty($player['current_havenot']),
                'safe'      => !empty($player['current_safe']),
                'misc'      => !empty($player['current_misc']),
                'misc_notes'=> $player['misc_notes'] ?? '',
            ],
        ]);
    }

    /**
     * Parse eviction timestamp (handles various formats)
     */
    private function parseEvictionTimestamp($value): ?int
    {
        if ($value === null) return null;

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || $value === '0' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                return null;
            }
        }

        if (is_numeric($value)) {
            $intVal = (int) $value;
            if ($intVal > 2000000000) $intVal = (int) floor($intVal / 1000);
            return $intVal > 0 ? $intVal : null;
        }

        $ts = strtotime($value);
        return ($ts !== false && $ts > 0) ? $ts : null;
    }
}
