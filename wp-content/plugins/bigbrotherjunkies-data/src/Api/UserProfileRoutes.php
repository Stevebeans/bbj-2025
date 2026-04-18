<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Comments\CommentSchema;
use BigBrotherJunkies\Data\Comments\RankCalculator;

/**
 * User Profile API Routes
 *
 * Provides endpoints for user profiles, follows, and stats
 */
class UserProfileRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        // Get user profile by ID
        register_rest_route($namespace, '/users/(?P<user_id>\d+)/profile', [
            'methods' => 'GET',
            'callback' => [$this, 'getProfile'],
            'permission_callback' => '__return_true',
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Get user profile by username
        register_rest_route($namespace, '/users/by-username/(?P<username>[a-zA-Z0-9_.-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getProfileByUsername'],
            'permission_callback' => '__return_true',
            'args' => [
                'username' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_user',
                ],
            ],
        ]);

        // Get user's comment history (paginated)
        register_rest_route($namespace, '/users/(?P<user_id>\d+)/comments', [
            'methods' => 'GET',
            'callback' => [$this, 'getUserComments'],
            'permission_callback' => '__return_true',
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'page' => [
                    'default' => 1,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default' => 10,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Follow a user
        register_rest_route($namespace, '/users/(?P<user_id>\d+)/follow', [
            'methods' => 'POST',
            'callback' => [$this, 'followUser'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Unfollow a user
        register_rest_route($namespace, '/users/(?P<user_id>\d+)/follow', [
            'methods' => 'DELETE',
            'callback' => [$this, 'unfollowUser'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Get followers
        register_rest_route($namespace, '/users/(?P<user_id>\d+)/followers', [
            'methods' => 'GET',
            'callback' => [$this, 'getFollowers'],
            'permission_callback' => '__return_true',
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Get following
        register_rest_route($namespace, '/users/(?P<user_id>\d+)/following', [
            'methods' => 'GET',
            'callback' => [$this, 'getFollowing'],
            'permission_callback' => '__return_true',
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Get user profile with stats and recent comments
     */
    public function getProfile(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $userId = $request->get_param('user_id');
        $currentUserId = get_current_user_id();

        // Get user info
        $user = get_user_by('ID', $userId);
        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Get rank
        $rank = RankCalculator::calculateRank($userId);

        // Get stats
        $votesTable = CommentSchema::table(CommentSchema::TABLE_VOTES);
        $followsTable = CommentSchema::table(CommentSchema::TABLE_FOLLOWS);
        $sessionsTable = CommentSchema::table(CommentSchema::TABLE_SESSIONS);

        // Comment count
        $commentCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_approved = '1'",
            $userId
        ));

        // Total votes received
        $votesReceived = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN v.vote_type = 1 THEN 1 ELSE 0 END), 0) as upvotes,
                COALESCE(SUM(CASE WHEN v.vote_type = -1 THEN 1 ELSE 0 END), 0) as downvotes
            FROM {$votesTable} v
            JOIN {$wpdb->comments} c ON v.comment_id = c.comment_ID
            WHERE c.user_id = %d",
            $userId
        ), ARRAY_A);

        // Follower/following counts
        $followerCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$followsTable} WHERE following_id = %d",
            $userId
        ));

        $followingCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$followsTable} WHERE follower_id = %d",
            $userId
        ));

        // Check if current user follows this user
        $isFollowing = false;
        if ($currentUserId > 0 && $currentUserId !== $userId) {
            $isFollowing = (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$followsTable} WHERE follower_id = %d AND following_id = %d",
                $currentUserId,
                $userId
            ));
        }

        // Check online status and get last activity
        $sessionData = $wpdb->get_row($wpdb->prepare(
            "SELECT last_activity, last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) as is_online FROM {$sessionsTable} WHERE user_id = %d",
            $userId
        ), ARRAY_A);

        $isOnline = (bool) ($sessionData['is_online'] ?? false);
        $lastActive = null;
        if ($sessionData && $sessionData['last_activity']) {
            $lastActive = human_time_diff(strtotime($sessionData['last_activity']), time()) . ' ago';
        }

        // Get recent comments (last 5)
        $recentComments = $wpdb->get_results($wpdb->prepare("
            SELECT
                c.comment_ID,
                c.comment_content,
                c.comment_date,
                c.comment_post_ID,
                p.post_title
            FROM {$wpdb->comments} c
            LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
            WHERE c.user_id = %d AND c.comment_approved = '1'
            ORDER BY c.comment_date DESC
            LIMIT 5
        ", $userId), ARRAY_A);

        $formattedComments = array_map(function ($c) {
            return [
                'id' => (int) $c['comment_ID'],
                'content' => wp_trim_words($c['comment_content'], 20),
                'date' => $c['comment_date'],
                'time_ago' => human_time_diff(strtotime($c['comment_date']), time()) . ' ago',
                'post_id' => (int) $c['comment_post_ID'],
                'post_title' => $c['post_title'] ?? 'Untitled',
            ];
        }, $recentComments);

        // Get user bio
        $bio = get_user_meta($userId, 'description', true) ?: '';

        // Get favorite player
        $favoritePlayerId = get_user_meta($userId, 'bbj_favorite_player_id', true);
        $favoritePlayer = null;
        if ($favoritePlayerId) {
            $favoritePlayer = $this->getPlayerById((int) $favoritePlayerId);
        }

        // Determine supporter status
        $adSettings = get_option('bbjd_ad_settings', []);
        $supporterRoles = $adSettings['global_hidden_roles'] ?? ['administrator', 'editor', 'supporter', 'lifetime'];
        $userRoles = $user->roles;
        $isSupporter = !empty(array_intersect($userRoles, $supporterRoles));

        // Determine supporter type
        $supporterType = null;
        if (in_array('lifetime', $userRoles, true)) {
            $supporterType = 'lifetime';
        } elseif ($isSupporter && !in_array('administrator', $userRoles, true) && !in_array('editor', $userRoles, true)) {
            $supporterType = 'supporter';
        }

        return new \WP_REST_Response([
            'success' => true,
            'profile' => [
                'id' => $userId,
                'username' => $user->user_login,
                'name' => $user->display_name,
                'avatar' => get_avatar_url($userId, ['size' => 128]),
                'bio' => $bio,
                'is_online' => $isOnline,
                'last_active' => $lastActive,
                'member_since' => $user->user_registered,
                'member_since_formatted' => date('F Y', strtotime($user->user_registered)),
                'favorite_player' => $favoritePlayer,
                'is_supporter' => $isSupporter,
                'supporter_type' => $supporterType,
                'rank' => $rank ? [
                    'key' => $rank['key'],
                    'name' => $rank['name'],
                    'color' => $rank['color'],
                    'bg_color' => $rank['bg_color'],
                    'icon' => $rank['icon'] ?? null,
                    'is_special' => $rank['is_special'],
                ] : null,
                'stats' => [
                    'comments' => $commentCount,
                    'upvotes_received' => (int) ($votesReceived['upvotes'] ?? 0),
                    'downvotes_received' => (int) ($votesReceived['downvotes'] ?? 0),
                    'karma' => (int) (($votesReceived['upvotes'] ?? 0) - ($votesReceived['downvotes'] ?? 0)),
                    'followers' => $followerCount,
                    'following' => $followingCount,
                ],
                'recent_comments' => $formattedComments,
                'is_following' => $isFollowing,
                'is_self' => $currentUserId === $userId,
            ],
        ], 200);
    }

    /**
     * Get user profile by username
     */
    public function getProfileByUsername(\WP_REST_Request $request): \WP_REST_Response
    {
        $username = $request->get_param('username');

        // Look up user by login
        $user = get_user_by('login', $username);
        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Delegate to getProfile with user_id
        $request->set_param('user_id', $user->ID);
        return $this->getProfile($request);
    }

    /**
     * Get paginated comment history for a user
     */
    public function getUserComments(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $userId = $request->get_param('user_id');
        $page = max(1, $request->get_param('page'));
        $perPage = min(50, max(1, $request->get_param('per_page')));
        $offset = ($page - 1) * $perPage;

        // Verify user exists
        $user = get_user_by('ID', $userId);
        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Get total count
        $totalComments = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_approved = '1'",
            $userId
        ));

        $totalPages = ceil($totalComments / $perPage);

        // Get votes table
        $votesTable = CommentSchema::table(CommentSchema::TABLE_VOTES);

        // Get comments with vote scores and reply counts
        $comments = $wpdb->get_results($wpdb->prepare("
            SELECT
                c.comment_ID,
                c.comment_content,
                c.comment_date,
                c.comment_post_ID,
                c.comment_parent,
                p.post_title,
                p.post_name as post_slug,
                COALESCE(v.upvotes, 0) as upvotes,
                COALESCE(v.downvotes, 0) as downvotes,
                (SELECT COUNT(*) FROM {$wpdb->comments} r WHERE r.comment_parent = c.comment_ID AND r.comment_approved = '1') as reply_count
            FROM {$wpdb->comments} c
            LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
            LEFT JOIN (
                SELECT comment_id,
                    SUM(CASE WHEN vote_type = 1 THEN 1 ELSE 0 END) as upvotes,
                    SUM(CASE WHEN vote_type = -1 THEN 1 ELSE 0 END) as downvotes
                FROM {$votesTable}
                GROUP BY comment_id
            ) v ON c.comment_ID = v.comment_id
            WHERE c.user_id = %d AND c.comment_approved = '1'
            ORDER BY c.comment_date DESC
            LIMIT %d OFFSET %d
        ", $userId, $perPage, $offset), ARRAY_A);

        $formattedComments = array_map(function ($c) {
            $upvotes = (int) $c['upvotes'];
            $downvotes = (int) $c['downvotes'];

            return [
                'id' => (int) $c['comment_ID'],
                'content' => wp_trim_words(strip_tags($c['comment_content']), 30),
                'content_full' => $c['comment_content'],
                'date' => $c['comment_date'],
                'time_ago' => human_time_diff(strtotime($c['comment_date']), time()) . ' ago',
                'post_id' => (int) $c['comment_post_ID'],
                'post_title' => $c['post_title'] ?? 'Untitled',
                'post_slug' => $c['post_slug'] ?? '',
                'post_url' => $c['post_slug'] ? '/' . $c['post_slug'] : null,
                'is_reply' => (int) $c['comment_parent'] > 0,
                'vote_score' => $upvotes - $downvotes,
                'upvotes' => $upvotes,
                'downvotes' => $downvotes,
                'reply_count' => (int) $c['reply_count'],
            ];
        }, $comments);

        return new \WP_REST_Response([
            'success' => true,
            'comments' => $formattedComments,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalComments,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
        ], 200);
    }

    /**
     * Follow a user
     */
    public function followUser(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $userId = $request->get_param('user_id');
        $currentUserId = get_current_user_id();

        // Can't follow yourself
        if ($userId === $currentUserId) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Cannot follow yourself',
            ], 400);
        }

        // Check user exists
        if (!get_user_by('ID', $userId)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $followsTable = CommentSchema::table(CommentSchema::TABLE_FOLLOWS);

        // Check if already following
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$followsTable} WHERE follower_id = %d AND following_id = %d",
            $currentUserId,
            $userId
        ));

        if ($existing) {
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Already following',
                'is_following' => true,
            ], 200);
        }

        // Insert follow
        $wpdb->insert($followsTable, [
            'follower_id' => $currentUserId,
            'following_id' => $userId,
        ], ['%d', '%d']);

        // Get updated follower count
        $followerCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$followsTable} WHERE following_id = %d",
            $userId
        ));

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Now following user',
            'is_following' => true,
            'follower_count' => $followerCount,
        ], 200);
    }

    /**
     * Unfollow a user
     */
    public function unfollowUser(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $userId = $request->get_param('user_id');
        $currentUserId = get_current_user_id();

        $followsTable = CommentSchema::table(CommentSchema::TABLE_FOLLOWS);

        $wpdb->delete($followsTable, [
            'follower_id' => $currentUserId,
            'following_id' => $userId,
        ], ['%d', '%d']);

        // Get updated follower count
        $followerCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$followsTable} WHERE following_id = %d",
            $userId
        ));

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Unfollowed user',
            'is_following' => false,
            'follower_count' => $followerCount,
        ], 200);
    }

    /**
     * Get user's followers
     */
    public function getFollowers(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $userId = $request->get_param('user_id');

        $followsTable = CommentSchema::table(CommentSchema::TABLE_FOLLOWS);

        $followers = $wpdb->get_results($wpdb->prepare("
            SELECT f.follower_id, f.created_at
            FROM {$followsTable} f
            WHERE f.following_id = %d
            ORDER BY f.created_at DESC
        ", $userId), ARRAY_A);

        $formatted = array_map(function ($f) {
            $user = get_user_by('ID', $f['follower_id']);
            if (!$user) return null;

            return [
                'id' => (int) $f['follower_id'],
                'name' => $user->display_name,
                'avatar' => get_avatar_url($f['follower_id'], ['size' => 48]),
                'followed_at' => $f['created_at'],
            ];
        }, $followers);

        return new \WP_REST_Response([
            'success' => true,
            'followers' => array_filter($formatted),
        ], 200);
    }

    /**
     * Get users this user is following
     */
    public function getFollowing(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $userId = $request->get_param('user_id');

        $followsTable = CommentSchema::table(CommentSchema::TABLE_FOLLOWS);

        $following = $wpdb->get_results($wpdb->prepare("
            SELECT f.following_id, f.created_at
            FROM {$followsTable} f
            WHERE f.follower_id = %d
            ORDER BY f.created_at DESC
        ", $userId), ARRAY_A);

        $formatted = array_map(function ($f) {
            $user = get_user_by('ID', $f['following_id']);
            if (!$user) return null;

            return [
                'id' => (int) $f['following_id'],
                'name' => $user->display_name,
                'avatar' => get_avatar_url($f['following_id'], ['size' => 48]),
                'followed_at' => $f['created_at'],
            ];
        }, $following);

        return new \WP_REST_Response([
            'success' => true,
            'following' => array_filter($formatted),
        ], 200);
    }

    /**
     * Permission callback: Check if user is logged in
     */
    public function checkUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get player by ID for favorite player display
     */
    private function getPlayerById(int $playerId): ?array
    {
        global $wpdb;

        $player = $wpdb->get_row($wpdb->prepare("
            SELECT
                p.id,
                p.first_name,
                p.last_name,
                p.official_nickname,
                p.profile_picture,
                p.slug,
                GROUP_CONCAT(DISTINCT s.post_title ORDER BY ps.bbj_season SEPARATOR ', ') as seasons
            FROM {$wpdb->prefix}bbj_players p
            LEFT JOIN {$wpdb->prefix}bbj_v2_player_season ps ON p.id = ps.bbj_player
            LEFT JOIN {$wpdb->posts} s ON ps.bbj_season = s.ID AND s.post_status = 'publish'
            WHERE p.id = %d
            GROUP BY p.id
            LIMIT 1
        ", $playerId), ARRAY_A);

        if (!$player) {
            return null;
        }

        $name = trim($player['first_name'] . ' ' . $player['last_name']);
        $nickname = $player['official_nickname'];

        $photoUrl = null;
        if ($player['profile_picture']) {
            $photoUrl = wp_get_attachment_image_url((int) $player['profile_picture'], 'thumbnail');
        }

        // Build permalink
        $permalink = $player['slug'] ? '/bigbrother-players/' . $player['slug'] : null;

        return [
            'id' => (int) $player['id'],
            'name' => $name,
            'nickname' => $nickname,
            'display_name' => $nickname ? "\"{$nickname}\" {$name}" : $name,
            'seasons' => $player['seasons'] ?: null,
            'photo_url' => $photoUrl,
            'permalink' => $permalink,
        ];
    }
}
