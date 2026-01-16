<?php

namespace BigBrotherJunkies\Data\Api;

/**
 * Search API Routes
 *
 * Provides comprehensive search across all content types:
 * - Posts & Pages (general)
 * - Players (bigbrother-players)
 * - Seasons (bigbrother-seasons)
 * - Feed Updates (live-feed-updates)
 */
class SearchRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('bbjd/v1', '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search'],
            'permission_callback' => '__return_true',
            'args' => [
                'query' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'per_page' => [
                    'default' => 10,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Search across all content types
     */
    public function search(\WP_REST_Request $request): array
    {
        $searchQuery = $request->get_param('query');
        $perPage = min($request->get_param('per_page'), 20); // Cap at 20

        // Run separate queries for each category to ensure balanced results
        // Order: Players, Seasons, Feed Updates, Posts/Pages
        return [
            'players' => $this->searchPlayers($searchQuery, $perPage),
            'seasons' => $this->searchSeasons($searchQuery, $perPage),
            'feed_updates' => $this->searchFeedUpdates($searchQuery, $perPage),
            'general' => $this->searchGeneral($searchQuery, $perPage),
        ];
    }

    /**
     * Search posts and pages
     */
    private function searchGeneral(string $searchQuery, int $perPage): array
    {
        $results = [];

        $query = new \WP_Query([
            'post_type' => ['post', 'page'],
            's' => $searchQuery,
            'posts_per_page' => $perPage,
            'post_status' => 'publish',
        ]);

        while ($query->have_posts()) {
            $query->the_post();
            $results[] = [
                'id' => get_the_ID(),
                'title' => html_entity_decode(get_the_title(), ENT_QUOTES, 'UTF-8'),
                'permalink' => get_the_permalink(),
                'excerpt' => html_entity_decode(wp_trim_words(get_the_excerpt(), 15), ENT_QUOTES, 'UTF-8'),
                'date' => get_the_date('M j, Y'),
            ];
        }

        wp_reset_postdata();
        return $results;
    }

    /**
     * Search players
     */
    private function searchPlayers(string $searchQuery, int $perPage): array
    {
        $results = [];

        $query = new \WP_Query([
            'post_type' => 'bigbrother-players',
            's' => $searchQuery,
            'posts_per_page' => $perPage,
            'post_status' => 'publish',
        ]);

        while ($query->have_posts()) {
            $query->the_post();
            $results[] = $this->formatPlayer(get_the_ID());
        }

        wp_reset_postdata();
        return $results;
    }

    /**
     * Search seasons
     */
    private function searchSeasons(string $searchQuery, int $perPage): array
    {
        $results = [];

        $query = new \WP_Query([
            'post_type' => 'bigbrother-seasons',
            's' => $searchQuery,
            'posts_per_page' => $perPage,
            'post_status' => 'publish',
        ]);

        while ($query->have_posts()) {
            $query->the_post();
            $results[] = [
                'id' => get_the_ID(),
                'title' => html_entity_decode(get_the_title(), ENT_QUOTES, 'UTF-8'),
                'permalink' => get_the_permalink(),
            ];
        }

        wp_reset_postdata();
        return $results;
    }

    /**
     * Search feed updates
     */
    private function searchFeedUpdates(string $searchQuery, int $perPage): array
    {
        $results = [];

        $query = new \WP_Query([
            'post_type' => 'live-feed-updates',
            's' => $searchQuery,
            'posts_per_page' => $perPage,
            'post_status' => 'publish',
        ]);

        while ($query->have_posts()) {
            $query->the_post();
            $results[] = $this->formatFeedUpdate(get_the_ID());
        }

        wp_reset_postdata();
        return $results;
    }

    /**
     * Format player data for search results
     */
    private function formatPlayer(int $postId): array
    {
        $playerImage = null;

        // Try to get profile picture from Meta Box
        if (function_exists('rwmb_meta')) {
            $playerImage = rwmb_meta('profile_picture', ['size' => 'thumbnail'], $postId);
        }

        // Get season abbreviation
        $abbreviation = $this->getPlayerSeasonAbbreviation($postId);

        return [
            'id' => $postId,
            'title' => html_entity_decode(get_the_title($postId), ENT_QUOTES, 'UTF-8'),
            'permalink' => get_the_permalink($postId),
            'player_image' => $playerImage,
            'abbreviation' => $abbreviation,
        ];
    }

    /**
     * Get the most recent season abbreviation for a player
     */
    private function getPlayerSeasonAbbreviation(int $postId): string
    {
        global $wpdb;

        // Get player row ID (for database lookup)
        $playerRowId = (int) get_post_meta($postId, 'player_row_id', true);
        if (!$playerRowId) {
            $playerRowId = $postId; // Fallback
        }

        $psTable = $wpdb->prefix . 'bbj_v2_player_season';
        $sTable = $wpdb->prefix . 'bbj_seasons';

        $abbreviation = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT s.abbreviation
                FROM {$psTable} ps
                JOIN {$sTable} s ON s.id = ps.bbj_season
                WHERE ps.bbj_player = %d
                ORDER BY s.id DESC
                LIMIT 1",
                $playerRowId
            )
        );

        return $abbreviation ?: '';
    }

    /**
     * Format feed update for search results
     */
    private function formatFeedUpdate(int $postId): array
    {
        $thumbnail = get_the_post_thumbnail_url($postId, 'thumbnail');
        $content = get_the_content(null, false, $postId);

        return [
            'id' => $postId,
            'title' => html_entity_decode(get_the_title($postId), ENT_QUOTES, 'UTF-8'),
            'permalink' => get_the_permalink($postId),
            'excerpt' => html_entity_decode(wp_trim_words(wp_strip_all_tags($content), 20), ENT_QUOTES, 'UTF-8'),
            'thumbnail' => $thumbnail ?: null,
            'date' => get_the_date('M j, Y', $postId),
            'time' => get_the_time('g:i a', $postId),
        ];
    }
}
