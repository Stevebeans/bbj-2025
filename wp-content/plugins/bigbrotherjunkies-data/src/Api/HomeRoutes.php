<?php

namespace BigBrotherJunkies\Data\Api;

/**
 * Home Page API Routes
 *
 * Provides all data needed for the homepage:
 * - Featured/hero post
 * - Feed updates
 * - Houseboard (current HoH, PoV, Nominees, Have Nots)
 * - Season stats/standings
 */
class HomeRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        // Feed updates endpoint
        register_rest_route('bbjd/v1', '/feed-updates', [
            'methods' => 'GET',
            'callback' => [$this, 'getFeedUpdates'],
            'permission_callback' => '__return_true',
            'args' => [
                'per_page' => [
                    'default' => 15,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'offset' => [
                    'default' => 0,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Houseboard endpoint (HoH, PoV, Nominees, Have Nots)
        register_rest_route('bbjd/v1', '/houseboard', [
            'methods' => 'GET',
            'callback' => [$this, 'getHouseboard'],
            'permission_callback' => '__return_true',
        ]);

        // Season stats/standings endpoint
        register_rest_route('bbjd/v1', '/season-stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getSeasonStats'],
            'permission_callback' => '__return_true',
        ]);

        // Hero/featured post endpoint
        register_rest_route('bbjd/v1', '/hero-post', [
            'methods' => 'GET',
            'callback' => [$this, 'getHeroPost'],
            'permission_callback' => '__return_true',
        ]);

        // Recent comments endpoint
        register_rest_route('bbjd/v1', '/recent-comments', [
            'methods' => 'GET',
            'callback' => [$this, 'getRecentComments'],
            'permission_callback' => '__return_true',
            'args' => [
                'per_page' => [
                    'default' => 5,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Feed updates by date (for blog post live feed threads)
        register_rest_route('bbjd/v1', '/feed-updates-by-date', [
            'methods' => 'GET',
            'callback' => [$this, 'getFeedUpdatesByDate'],
            'permission_callback' => '__return_true',
            'args' => [
                'date' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Date in Y-m-d format',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Get feed updates
     */
    public function getFeedUpdates(\WP_REST_Request $request): array
    {
        $perPage = min($request->get_param('per_page'), 30);
        $offset = $request->get_param('offset');

        $query = new \WP_Query([
            'post_type' => 'live-feed-updates',
            'posts_per_page' => $perPage,
            'offset' => $offset,
            'orderby' => 'modified',
            'order' => 'DESC',
            'post_status' => 'publish',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ]);

        $updates = [];

        while ($query->have_posts()) {
            $query->the_post();
            $postId = get_the_ID();
            $authorId = (int) get_the_author_meta('ID');

            $updates[] = [
                'id' => $postId,
                'slug' => get_post_field('post_name', $postId),
                'title' => html_entity_decode(get_the_title(), ENT_QUOTES, 'UTF-8'),
                'content' => apply_filters('the_content', get_the_content()),
                'excerpt' => html_entity_decode(wp_trim_words(wp_strip_all_tags(get_the_content()), 30), ENT_QUOTES, 'UTF-8'),
                'permalink' => get_the_permalink(),
                'date' => get_the_date('c'),
                'date_formatted' => get_the_date('M j, Y'),
                'time' => get_the_time('g:i a'),
                'modified' => get_the_modified_date('c'),
                'time_ago' => human_time_diff(get_the_modified_time('U'), current_time('timestamp')) . ' ago',
                'thumbnail' => get_the_post_thumbnail_url($postId, 'medium') ?: null,
                'comment_count' => (int) get_comments_number(),
                'author' => [
                    'id' => $authorId,
                    'name' => get_the_author_meta('display_name'),
                    'avatar' => get_avatar_url($authorId, ['size' => 64]),
                ],
            ];
        }

        wp_reset_postdata();

        return [
            'updates' => $updates,
            'total' => count($updates),
        ];
    }

    /**
     * Get houseboard data (HoH, PoV, Nominees, Have Nots)
     */
    public function getHouseboard(): array
    {
        $currentSeasonId = (int) get_option('bbj_v2_current_season');

        if ($currentSeasonId <= 0) {
            return ['error' => 'No current season set'];
        }

        // Get season info
        $seasonInfo = function_exists('bbj_v2_get_season_by_id')
            ? bbj_v2_get_season_by_id($currentSeasonId)
            : [];

        // Get players with their current status
        $seasonPlayers = function_exists('bbj_v2_get_season_players')
            ? bbj_v2_get_season_players($currentSeasonId, 'bbj_v2_spoiler_bar')
            : [];

        $houseboard = [
            'hoh' => [],
            'pov' => [],
            'nominees' => [],
            'have_nots' => [],
        ];

        foreach ($seasonPlayers as $player) {
            $playerData = [
                'id' => $player['bbj_player'] ?? 0,
                'name' => $player['official_nickname'] ?: ($player['first_name'] ?? ''),
                'image' => $player['profile_picture_url'] ?? '',
                'permalink' => get_permalink($player['bbj_player'] ?? 0),
            ];

            if (!empty($player['current_hoh'])) {
                $houseboard['hoh'][] = $playerData;
            }
            if (!empty($player['current_pov'])) {
                $houseboard['pov'][] = $playerData;
            }
            if (!empty($player['current_nom'])) {
                $houseboard['nominees'][] = $playerData;
            }
            if (!empty($player['current_havenot'])) {
                $houseboard['have_nots'][] = $playerData;
            }
        }

        return [
            'season' => [
                'id' => $currentSeasonId,
                'name' => $seasonInfo['full_name'] ?? '',
                'permalink' => get_permalink($currentSeasonId),
            ],
            'houseboard' => $houseboard,
        ];
    }

    /**
     * Get season stats/standings
     */
    public function getSeasonStats(): array
    {
        $currentSeasonId = (int) get_option('bbj_v2_current_season');

        if ($currentSeasonId <= 0) {
            return ['error' => 'No current season set'];
        }

        $seasonInfo = function_exists('bbj_v2_get_season_by_id')
            ? bbj_v2_get_season_by_id($currentSeasonId)
            : [];

        $seasonPlayers = function_exists('bbj_v2_get_season_players')
            ? bbj_v2_get_season_players($currentSeasonId, 'bbj_v2_spoiler_bar')
            : [];

        // Calculate season progress
        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $currentDate = new \DateTime('now', $tz);
        $startDate = !empty($seasonInfo['start_date']) ? new \DateTime($seasonInfo['start_date'], $tz) : null;
        $endDate = !empty($seasonInfo['end_date']) ? new \DateTime($seasonInfo['end_date'], $tz) : null;

        $totalDays = ($startDate && $endDate) ? max(0, $startDate->diff($endDate)->days + 1) : 0;
        $daysElapsed = $startDate ? max(0, $startDate->diff($currentDate)->days) : 0;
        if ($endDate && $currentDate > $endDate) {
            $daysElapsed = $totalDays;
        }
        $daysRemaining = max(0, $totalDays - $daysElapsed);
        $progressPct = $totalDays > 0 ? round(($daysElapsed / $totalDays) * 100) : 0;
        $isActive = $startDate && $endDate && $currentDate >= $startDate && $currentDate <= $endDate;

        // Sort players: active first (by HoH wins), then evicted (by eviction date desc)
        usort($seasonPlayers, function ($a, $b) use ($endDate, $currentDate, $tz) {
            $aEvicted = ((int)($a['current_jury'] ?? 0) === 1) || ((int)($a['current_evicted'] ?? 0) === 1);
            $bEvicted = ((int)($b['current_jury'] ?? 0) === 1) || ((int)($b['current_evicted'] ?? 0) === 1);

            if ($aEvicted !== $bEvicted) {
                return $aEvicted <=> $bEvicted;
            }

            if (!$aEvicted && !$bEvicted) {
                return ($b['bbj_total_hoh'] ?? 0) <=> ($a['bbj_total_hoh'] ?? 0);
            }

            // Both evicted - sort by eviction date (later first)
            $fallbackEnd = $endDate ?: $currentDate;
            $aRaw = trim($a['bbj_evicted_date'] ?? '');
            $bRaw = trim($b['bbj_evicted_date'] ?? '');

            $aDate = (!$aRaw || $aRaw === '0000-00-00') ? $fallbackEnd : new \DateTime($aRaw, $tz);
            $bDate = (!$bRaw || $bRaw === '0000-00-00') ? $fallbackEnd : new \DateTime($bRaw, $tz);

            return $bDate->getTimestamp() <=> $aDate->getTimestamp();
        });

        // Format players for response
        $players = [];
        foreach ($seasonPlayers as $player) {
            $isEvicted = ((int)($player['current_jury'] ?? 0) === 1) || ((int)($player['current_evicted'] ?? 0) === 1);

            // Calculate days in house
            $evRaw = trim($player['bbj_evicted_date'] ?? '');
            $effectiveEnd = $endDate ? ($currentDate < $endDate ? $currentDate : $endDate) : $currentDate;
            $hasValidEviction = ($evRaw && $evRaw !== '0000-00-00');
            $playerEnd = $hasValidEviction ? new \DateTime($evRaw, $tz) : $effectiveEnd;
            $daysInHouse = $startDate ? max(0, $startDate->diff($playerEnd)->days + 1) : 0;

            $players[] = [
                'id' => $player['bbj_player'] ?? 0,
                'name' => $player['official_nickname'] ?: ($player['first_name'] ?? ''),
                'image' => $player['profile_picture_url'] ?? '',
                'permalink' => get_permalink($player['bbj_player'] ?? 0),
                'is_evicted' => $isEvicted,
                'stats' => [
                    'hoh' => (int)($player['bbj_total_hoh'] ?? 0),
                    'pov' => (int)($player['bbj_total_pov'] ?? 0),
                    'nom' => (int)($player['bbj_total_nom'] ?? 0),
                    'votes_received' => (int)($player['bbj_votes_received'] ?? 0),
                    'days_in_house' => $daysInHouse,
                ],
            ];
        }

        return [
            'season' => [
                'id' => $currentSeasonId,
                'name' => $seasonInfo['full_name'] ?? '',
                'total_days' => $totalDays,
                'days_elapsed' => min($daysElapsed, $totalDays),
                'days_remaining' => $daysRemaining,
                'progress_pct' => $progressPct,
                'is_active' => $isActive,
            ],
            'players' => $players,
        ];
    }

    /**
     * Get hero/featured post
     */
    public function getHeroPost(): array
    {
        $query = new \WP_Query([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
        ]);

        if (!$query->have_posts()) {
            return ['post' => null];
        }

        $query->the_post();
        $postId = get_the_ID();

        // Get excerpt
        $excerpt = get_post_field('post_excerpt', $postId);
        if (empty($excerpt)) {
            $excerpt = wp_strip_all_tags(get_post_field('post_content', $postId));
        }

        $heroPost = [
            'id' => $postId,
            'slug' => get_post_field('post_name', $postId),
            'title' => html_entity_decode(get_the_title(), ENT_QUOTES, 'UTF-8'),
            'excerpt' => html_entity_decode(wp_trim_words($excerpt, 40), ENT_QUOTES, 'UTF-8'),
            'permalink' => get_the_permalink(),
            'date' => get_the_date('c'),
            'date_formatted' => get_the_date('M j, Y'),
            'modified' => get_the_modified_date('c'),
            'time_ago' => human_time_diff(get_the_modified_time('U'), current_time('timestamp')) . ' ago',
            'comment_count' => (int) get_comments_number(),
            'featured_image' => [
                'desktop' => get_the_post_thumbnail_url($postId, 'large') ?: null,
                'mobile' => get_the_post_thumbnail_url($postId, 'medium_large') ?: null,
            ],
            'author' => [
                'name' => get_the_author_meta('display_name'),
                'avatar' => get_avatar_url(get_the_author_meta('ID'), ['size' => 48]),
            ],
        ];

        wp_reset_postdata();

        // Get current season info for the header
        $currentSeasonId = (int) get_option('bbj_v2_current_season');
        $seasonInfo = function_exists('bbj_v2_get_season_by_id')
            ? bbj_v2_get_season_by_id($currentSeasonId)
            : [];

        return [
            'post' => $heroPost,
            'season' => [
                'id' => $currentSeasonId,
                'name' => $seasonInfo['full_name'] ?? 'Big Brother',
                'permalink' => get_permalink($currentSeasonId),
            ],
        ];
    }

    /**
     * Get recent comments
     */
    public function getRecentComments(\WP_REST_Request $request): array
    {
        $perPage = min($request->get_param('per_page'), 20);

        $comments = get_comments([
            'number' => $perPage,
            'status' => 'approve',
            'orderby' => 'comment_date_gmt',
            'order' => 'DESC',
            'type' => 'comment',
        ]);

        $recentComments = [];

        foreach ($comments as $comment) {
            $postId = (int) $comment->comment_post_ID;
            $post = get_post($postId);

            if (!$post || $post->post_status !== 'publish') {
                continue;
            }

            $recentComments[] = [
                'id' => (int) $comment->comment_ID,
                'author' => html_entity_decode($comment->comment_author, ENT_QUOTES, 'UTF-8'),
                'avatar' => get_avatar_url($comment->comment_author_email, ['size' => 40]),
                'content' => html_entity_decode(
                    wp_trim_words(wp_strip_all_tags($comment->comment_content), 15),
                    ENT_QUOTES,
                    'UTF-8'
                ),
                'date' => get_comment_date('c', $comment),
                'time_ago' => human_time_diff(
                    strtotime($comment->comment_date_gmt),
                    current_time('timestamp', true)
                ) . ' ago',
                'post' => [
                    'id' => $postId,
                    'title' => html_entity_decode(get_the_title($postId), ENT_QUOTES, 'UTF-8'),
                    'permalink' => get_permalink($postId),
                    'type' => $post->post_type,
                ],
            ];
        }

        return [
            'comments' => $recentComments,
            'total' => count($recentComments),
        ];
    }

    /**
     * Get feed updates for a specific date
     * Used for "Live Feed Thread" feature on blog posts
     */
    public function getFeedUpdatesByDate(\WP_REST_Request $request): array
    {
        $dateStr = $request->get_param('date');

        // Parse the date
        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('America/Los_Angeles');
        $date = \DateTime::createFromFormat('Y-m-d', $dateStr, $tz);

        if (!$date) {
            return [
                'updates' => [],
                'total' => 0,
                'error' => 'Invalid date format. Use Y-m-d.',
            ];
        }

        $query = new \WP_Query([
            'post_type' => 'live-feed-updates',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC',
            'post_status' => 'publish',
            'date_query' => [
                [
                    'year' => $date->format('Y'),
                    'month' => $date->format('n'),
                    'day' => $date->format('j'),
                ],
            ],
        ]);

        $updates = [];

        while ($query->have_posts()) {
            $query->the_post();
            $postId = get_the_ID();
            $authorId = (int) get_the_author_meta('ID');

            $updates[] = [
                'id' => $postId,
                'title' => html_entity_decode(get_the_title(), ENT_QUOTES, 'UTF-8'),
                'content' => apply_filters('the_content', get_the_content()),
                'date' => get_the_date('c'),
                'time' => get_the_time('g:i a'),
                'time_ago' => human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ago',
                'thumbnail' => get_the_post_thumbnail_url($postId, 'large') ?: null,
                'author' => [
                    'id' => $authorId,
                    'name' => get_the_author_meta('display_name'),
                    'avatar' => get_avatar_url($authorId, ['size' => 32]),
                ],
            ];
        }

        wp_reset_postdata();

        return [
            'updates' => $updates,
            'total' => count($updates),
            'date' => $dateStr,
            'date_formatted' => $date->format('F j, Y'),
        ];
    }
}
