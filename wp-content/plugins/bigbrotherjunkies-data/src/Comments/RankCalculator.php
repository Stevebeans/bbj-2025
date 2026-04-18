<?php

namespace BigBrotherJunkies\Data\Comments;

/**
 * Calculates user ranks based on comment count and karma
 *
 * Rank Requirements:
 * - Newbie: 0-9 comments
 * - Bronze: 10+ comments AND 25+ karma
 * - Silver: 75+ comments AND 150+ karma
 * - Gold: 300+ comments AND 600+ karma
 * - Platinum: 1000+ comments AND 2000+ karma
 * - Diamond: 2500+ comments AND 5000+ karma
 * - Legend: 5000+ comments AND 10000+ karma (Top 0.5%)
 *
 * Karma = (upvotes received) - (downvotes received) on user's comments
 */
class RankCalculator
{
    /**
     * Rank definitions with requirements
     *
     * Thresholds designed for exclusivity:
     * - Legend: Top 0.5% (~6 users with 5000+)
     * - Diamond: Top 1.3% (~15 users)
     * - Platinum: Top 3% (~35 users)
     * - Gold: Top 7% (~80 users)
     * - Silver: Top 15% (~180 users)
     * - Bronze: Top 25% (~300 users)
     * - Newbie: Everyone else
     */
    public const RANKS = [
        'newbie' => [
            'name' => 'Newbie',
            'min_comments' => 0,
            'min_karma' => 0,
            'color' => 'gray-500',
            'bg_color' => 'gray-100',
            'icon' => null,
        ],
        'bronze' => [
            'name' => 'Bronze Contributor',
            'min_comments' => 10,
            'min_karma' => 25,
            'color' => 'orange-600',
            'bg_color' => 'orange-100',
            'icon' => 'medal',
        ],
        'silver' => [
            'name' => 'Silver Contributor',
            'min_comments' => 75,
            'min_karma' => 150,
            'color' => 'cyan-600',
            'bg_color' => 'cyan-100',
            'icon' => 'medal',
        ],
        'gold' => [
            'name' => 'Gold Contributor',
            'min_comments' => 300,
            'min_karma' => 600,
            'color' => 'yellow-600',
            'bg_color' => 'yellow-100',
            'icon' => 'medal',
        ],
        'platinum' => [
            'name' => 'Platinum Contributor',
            'min_comments' => 1000,
            'min_karma' => 2000,
            'color' => 'purple-600',
            'bg_color' => 'purple-100',
            'icon' => 'gem',
        ],
        'diamond' => [
            'name' => 'Diamond Contributor',
            'min_comments' => 2500,
            'min_karma' => 5000,
            'color' => 'teal-600',
            'bg_color' => 'teal-100',
            'icon' => 'gem',
        ],
        'legend' => [
            'name' => 'Legend',
            'min_comments' => 5000,
            'min_karma' => 10000,
            'color' => 'amber-500',
            'bg_color' => 'amber-200',
            'icon' => 'trophy',
            'ring' => 'amber-400',
        ],
    ];

    /**
     * Special ranks (manually assigned, override calculated ranks)
     */
    public const SPECIAL_RANKS = [
        'admin' => [
            'name' => 'Admin',
            'color' => 'red-600',
            'bg_color' => 'red-100',
            'icon' => 'crown',
        ],
        'big_boss' => [
            'name' => 'Big Boss',
            'color' => 'orange-600',
            'bg_color' => 'amber-100',
            'icon' => 'crown',
            'ring' => 'orange-400',
        ],
        'moderator' => [
            'name' => 'Moderator',
            'color' => 'blue-600',
            'bg_color' => 'blue-100',
            'icon' => 'shield',
        ],
        'vip' => [
            'name' => 'VIP',
            'color' => 'pink-600',
            'bg_color' => 'pink-100',
            'icon' => 'star',
        ],
    ];

    /**
     * Calculate rank for a user based on their stats
     *
     * @param int $userId
     * @return array Rank info including name, color, icon, stats
     */
    public static function calculateRank(int $userId): array
    {
        // Check for special rank first (stored in user meta)
        $specialRank = get_user_meta($userId, 'bbj_special_rank', true);
        if ($specialRank && isset(self::SPECIAL_RANKS[$specialRank])) {
            return array_merge(self::SPECIAL_RANKS[$specialRank], [
                'key' => $specialRank,
                'is_special' => true,
            ]);
        }

        // Auto-detect admins and moderators (only if no special rank set)
        $user = get_userdata($userId);
        if ($user) {
            // Check if user is an administrator
            if (in_array('administrator', $user->roles, true)) {
                return array_merge(self::SPECIAL_RANKS['admin'], [
                    'key' => 'admin',
                    'is_special' => true,
                ]);
            }
            // Check if user can moderate comments (editors, custom moderator role)
            if (user_can($userId, 'moderate_comments')) {
                return array_merge(self::SPECIAL_RANKS['admin'], [
                    'key' => 'admin',
                    'is_special' => true,
                ]);
            }
        }

        // Get user stats
        $stats = self::getUserStats($userId);

        // Calculate karma
        $karma = $stats['upvotes_received'] - $stats['downvotes_received'];

        // Determine rank based on comments and karma
        $rankKey = 'newbie';
        foreach (array_reverse(self::RANKS, true) as $key => $rank) {
            if ($stats['total_comments'] >= $rank['min_comments'] &&
                $karma >= $rank['min_karma']) {
                $rankKey = $key;
                break;
            }
        }

        $rank = self::RANKS[$rankKey];

        return array_merge($rank, [
            'key' => $rankKey,
            'is_special' => false,
            'stats' => [
                'comments' => $stats['total_comments'],
                'karma' => $karma,
                'upvotes_received' => $stats['upvotes_received'],
                'downvotes_received' => $stats['downvotes_received'],
            ],
            'next_rank' => self::getNextRankProgress($rankKey, $stats['total_comments'], $karma),
        ]);
    }

    /**
     * Get user statistics for rank calculation
     *
     * @param int $userId
     * @return array
     */
    public static function getUserStats(int $userId): array
    {
        global $wpdb;

        // Get total approved comments
        $totalComments = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->comments}
            WHERE user_id = %d AND comment_approved = '1'
        ", $userId));

        // Get votes received on user's comments from new table
        $votesTable = CommentSchema::table(CommentSchema::TABLE_VOTES);

        $votesReceived = $wpdb->get_row($wpdb->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN v.vote_type = 1 THEN 1 ELSE 0 END), 0) as upvotes,
                COALESCE(SUM(CASE WHEN v.vote_type = -1 THEN 1 ELSE 0 END), 0) as downvotes
            FROM {$votesTable} v
            INNER JOIN {$wpdb->comments} c ON v.comment_id = c.comment_ID
            WHERE c.user_id = %d AND c.comment_approved = '1'
        ", $userId), ARRAY_A);

        return [
            'total_comments' => $totalComments,
            'upvotes_received' => (int) ($votesReceived['upvotes'] ?? 0),
            'downvotes_received' => (int) ($votesReceived['downvotes'] ?? 0),
        ];
    }

    /**
     * Get progress towards next rank
     *
     * @param string $currentRank
     * @param int $comments
     * @param int $karma
     * @return array|null
     */
    private static function getNextRankProgress(string $currentRank, int $comments, int $karma): ?array
    {
        $rankKeys = array_keys(self::RANKS);
        $currentIndex = array_search($currentRank, $rankKeys);

        // If already at highest rank
        if ($currentIndex === count($rankKeys) - 1) {
            return null;
        }

        $nextRankKey = $rankKeys[$currentIndex + 1];
        $nextRank = self::RANKS[$nextRankKey];

        $commentsNeeded = max(0, $nextRank['min_comments'] - $comments);
        $karmaNeeded = max(0, $nextRank['min_karma'] - $karma);

        // Calculate percentage progress
        $commentProgress = $nextRank['min_comments'] > 0
            ? min(100, round(($comments / $nextRank['min_comments']) * 100))
            : 100;
        $karmaProgress = $nextRank['min_karma'] > 0
            ? min(100, round(($karma / $nextRank['min_karma']) * 100))
            : 100;

        return [
            'rank_key' => $nextRankKey,
            'rank_name' => $nextRank['name'],
            'comments_needed' => $commentsNeeded,
            'karma_needed' => $karmaNeeded,
            'comment_progress' => $commentProgress,
            'karma_progress' => $karmaProgress,
        ];
    }

    /**
     * Update cached rank in user meta
     * Call this after a user posts a comment or receives a vote
     *
     * @param int $userId
     * @return array The updated rank
     */
    public static function updateUserRank(int $userId): array
    {
        $rank = self::calculateRank($userId);

        // Store the rank key for quick lookups
        update_user_meta($userId, 'bbj_user_rank', $rank['name']);

        // Store full stats for API responses
        if (isset($rank['stats'])) {
            update_user_meta($userId, 'bbj_total_comments', $rank['stats']['comments']);
            update_user_meta($userId, 'bbj_total_likes', $rank['stats']['upvotes_received']);
            update_user_meta($userId, 'bbj_total_dislikes', $rank['stats']['downvotes_received']);
            update_user_meta($userId, 'bbj_total_karma', $rank['stats']['karma']);
        }

        return $rank;
    }

    /**
     * Bulk update all user ranks
     * Useful after vote migration or periodic recalculation
     *
     * @param int $limit Max users to process (0 = all)
     * @return array Results
     */
    public static function bulkUpdateRanks(int $limit = 0): array
    {
        global $wpdb;

        // Get all users who have at least one comment
        $query = "
            SELECT DISTINCT user_id
            FROM {$wpdb->comments}
            WHERE user_id > 0 AND comment_approved = '1'
        ";

        if ($limit > 0) {
            $query .= " LIMIT {$limit}";
        }

        $userIds = $wpdb->get_col($query);

        $results = [
            'processed' => 0,
            'updated' => [],
        ];

        foreach ($userIds as $userId) {
            $rank = self::updateUserRank((int) $userId);
            $results['processed']++;
            $results['updated'][$userId] = $rank['name'];
        }

        return $results;
    }

    /**
     * Set special rank for a user
     *
     * @param int $userId
     * @param string|null $rankKey Rank key or null to remove
     * @return bool
     */
    public static function setSpecialRank(int $userId, ?string $rankKey): bool
    {
        if ($rankKey === null) {
            delete_user_meta($userId, 'bbj_special_rank');
            // Recalculate to regular rank
            self::updateUserRank($userId);
            return true;
        }

        if (!isset(self::SPECIAL_RANKS[$rankKey])) {
            return false;
        }

        update_user_meta($userId, 'bbj_special_rank', $rankKey);
        update_user_meta($userId, 'bbj_user_rank', self::SPECIAL_RANKS[$rankKey]['name']);

        return true;
    }

    /**
     * Get all available ranks for display
     *
     * @return array
     */
    public static function getAllRanks(): array
    {
        return [
            'regular' => self::RANKS,
            'special' => self::SPECIAL_RANKS,
        ];
    }
}
