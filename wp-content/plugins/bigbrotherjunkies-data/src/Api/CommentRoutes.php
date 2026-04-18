<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Comments\AvatarUploader;
use BigBrotherJunkies\Data\Comments\CommentSchema;
use BigBrotherJunkies\Data\Comments\RankCalculator;
use BigBrotherJunkies\Data\Comments\MediaUploader;
use BigBrotherJunkies\Data\Comments\NotificationService;

/**
 * Comment System API Routes
 *
 * Provides endpoints for:
 * - Getting comments with votes and user ranks
 * - Voting on comments
 * - Reporting comments
 * - Admin moderation actions
 */
class CommentRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        // Get comments for a post
        register_rest_route($namespace, '/comments/(?P<post_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getComments'],
            'permission_callback' => '__return_true',
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default' => 20,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'page' => [
                    'default' => 1,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'sort' => [
                    'default' => 'newest',
                    'type' => 'string',
                    'enum' => ['newest', 'oldest', 'popular'],
                ],
            ],
        ]);

        // Vote on a comment
        register_rest_route($namespace, '/comments/(?P<comment_id>\d+)/vote', [
            'methods' => 'POST',
            'callback' => [$this, 'voteOnComment'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'comment_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'vote_type' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['up', 'down', 'remove'],
                ],
            ],
        ]);

        // Get user's vote on a comment
        register_rest_route($namespace, '/comments/(?P<comment_id>\d+)/my-vote', [
            'methods' => 'GET',
            'callback' => [$this, 'getUserVote'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'comment_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Report a comment
        register_rest_route($namespace, '/comments/(?P<comment_id>\d+)/report', [
            'methods' => 'POST',
            'callback' => [$this, 'reportComment'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'comment_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'reason' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['spam', 'abuse', 'off_topic', 'misinformation', 'other'],
                ],
                'details' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
            ],
        ]);

        // Get user rank info
        register_rest_route($namespace, '/users/(?P<user_id>\d+)/rank', [
            'methods' => 'GET',
            'callback' => [$this, 'getUserRank'],
            'permission_callback' => '__return_true',
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Get all ranks info
        register_rest_route($namespace, '/ranks', [
            'methods' => 'GET',
            'callback' => [$this, 'getAllRanks'],
            'permission_callback' => '__return_true',
        ]);

        // Post a new comment
        register_rest_route($namespace, '/comments', [
            'methods' => 'POST',
            'callback' => [$this, 'postComment'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'content' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'wp_kses_post',
                ],
                'parent_id' => [
                    'default' => 0,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'media_id' => [
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Edit a comment
        register_rest_route($namespace, '/comments/(?P<comment_id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'editComment'],
            'permission_callback' => [$this, 'checkCanEditComment'],
            'args' => [
                'comment_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'content' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'wp_kses_post',
                ],
            ],
        ]);

        // Delete a comment
        register_rest_route($namespace, '/comments/(?P<comment_id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteComment'],
            'permission_callback' => [$this, 'checkCanDeleteComment'],
            'args' => [
                'comment_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Check if user is blacklisted
        register_rest_route($namespace, '/comments/blacklist-check', [
            'methods' => 'GET',
            'callback' => [$this, 'checkBlacklist'],
            'permission_callback' => '__return_true',
        ]);

        // Pin a comment (staff pick)
        register_rest_route($namespace, '/comments/(?P<comment_id>\d+)/pin', [
            'methods' => 'POST',
            'callback' => [$this, 'pinComment'],
            'permission_callback' => [$this, 'checkCanPin'],
            'args' => [
                'comment_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Unpin a comment
        register_rest_route($namespace, '/comments/(?P<comment_id>\d+)/pin', [
            'methods' => 'DELETE',
            'callback' => [$this, 'unpinComment'],
            'permission_callback' => [$this, 'checkCanPin'],
            'args' => [
                'comment_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Get comments for a post with votes and user ranks
     */
    public function getComments(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $postId = $request->get_param('post_id');
        $perPage = min($request->get_param('per_page'), 50);
        $page = $request->get_param('page');
        $sort = $request->get_param('sort');
        $offset = ($page - 1) * $perPage;

        // Get current user ID if logged in
        $currentUserId = get_current_user_id();

        // Check if current user can pin comments
        $canPin = $this->checkCanPin();

        // Build order clause based on sort (pinned comments always first)
        switch ($sort) {
            case 'oldest':
                $orderBy = 'is_pinned DESC, c.comment_date_gmt ASC';
                break;
            case 'popular':
                $orderBy = 'is_pinned DESC, vote_score DESC, c.comment_date_gmt DESC';
                break;
            case 'newest':
            default:
                $orderBy = 'is_pinned DESC, c.comment_date_gmt DESC';
                break;
        }

        $votesTable = CommentSchema::table(CommentSchema::TABLE_VOTES);
        $pinnedTable = CommentSchema::table(CommentSchema::TABLE_PINNED);

        // Get top-level comments with vote counts and pin status
        $comments = $wpdb->get_results($wpdb->prepare("
            SELECT
                c.comment_ID,
                c.comment_post_ID,
                c.comment_author,
                c.comment_author_email,
                c.comment_date,
                c.comment_date_gmt,
                c.comment_content,
                c.comment_parent,
                c.user_id,
                COALESCE(SUM(CASE WHEN v.vote_type = 1 THEN 1 ELSE 0 END), 0) as upvotes,
                COALESCE(SUM(CASE WHEN v.vote_type = -1 THEN 1 ELSE 0 END), 0) as downvotes,
                COALESCE(SUM(v.vote_type), 0) as vote_score,
                CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END as is_pinned,
                p.pinned_at
            FROM {$wpdb->comments} c
            LEFT JOIN {$votesTable} v ON c.comment_ID = v.comment_id
            LEFT JOIN {$pinnedTable} p ON c.comment_ID = p.comment_id
            WHERE c.comment_post_ID = %d
                AND c.comment_approved = '1'
                AND c.comment_parent = 0
            GROUP BY c.comment_ID
            ORDER BY {$orderBy}
            LIMIT %d OFFSET %d
        ", $postId, $perPage, $offset), ARRAY_A);

        // Get total count for pagination
        $totalComments = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->comments}
            WHERE comment_post_ID = %d
                AND comment_approved = '1'
                AND comment_parent = 0
        ", $postId));

        // Get all comment IDs for fetching user votes
        $commentIds = array_column($comments, 'comment_ID');

        // Get current user's votes on these comments
        $userVotes = [];
        if ($currentUserId && !empty($commentIds)) {
            $placeholders = implode(',', array_fill(0, count($commentIds), '%d'));
            $params = array_merge([$currentUserId], $commentIds);

            $votes = $wpdb->get_results($wpdb->prepare("
                SELECT comment_id, vote_type
                FROM {$votesTable}
                WHERE user_id = %d AND comment_id IN ({$placeholders})
            ", ...$params), ARRAY_A);

            foreach ($votes as $vote) {
                $userVotes[$vote['comment_id']] = (int) $vote['vote_type'];
            }
        }

        // Format comments with user info and replies
        $formattedComments = [];
        foreach ($comments as $comment) {
            $formattedComments[] = $this->formatComment($comment, $userVotes, $currentUserId, 0, $canPin);
        }

        return new \WP_REST_Response([
            'comments' => $formattedComments,
            'pagination' => [
                'total' => $totalComments,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => ceil($totalComments / $perPage),
            ],
        ], 200);
    }

    /**
     * Format a comment with user info, rank, and replies
     */
    private function formatComment(array $comment, array $userVotes, int $currentUserId, int $depth = 0, bool $canPin = false): array
    {
        global $wpdb;

        $userId = (int) $comment['user_id'];
        $commentId = (int) $comment['comment_ID'];

        // Get user info and rank
        $rank = null;
        $avatar = get_avatar_url($comment['comment_author_email'], ['size' => 64]);
        $isOnline = false;

        if ($userId > 0) {
            $rank = RankCalculator::calculateRank($userId);
            $avatar = AvatarUploader::getAvatarUrl($userId, 64);

            // Check online status
            $sessionsTable = CommentSchema::table(CommentSchema::TABLE_SESSIONS);
            $isOnline = (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$sessionsTable} WHERE user_id = %d AND last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
                $userId
            ));
        }

        // Get replies (max depth of 3)
        $replies = [];
        if ($depth < 3) {
            $votesTable = CommentSchema::table(CommentSchema::TABLE_VOTES);

            $childComments = $wpdb->get_results($wpdb->prepare("
                SELECT
                    c.comment_ID,
                    c.comment_post_ID,
                    c.comment_author,
                    c.comment_author_email,
                    c.comment_date,
                    c.comment_date_gmt,
                    c.comment_content,
                    c.comment_parent,
                    c.user_id,
                    COALESCE(SUM(CASE WHEN v.vote_type = 1 THEN 1 ELSE 0 END), 0) as upvotes,
                    COALESCE(SUM(CASE WHEN v.vote_type = -1 THEN 1 ELSE 0 END), 0) as downvotes,
                    COALESCE(SUM(v.vote_type), 0) as vote_score
                FROM {$wpdb->comments} c
                LEFT JOIN {$votesTable} v ON c.comment_ID = v.comment_id
                WHERE c.comment_parent = %d AND c.comment_approved = '1'
                GROUP BY c.comment_ID
                ORDER BY c.comment_date_gmt ASC
            ", $commentId), ARRAY_A);

            foreach ($childComments as $child) {
                $replies[] = $this->formatComment($child, $userVotes, $currentUserId, $depth + 1, $canPin);
            }
        }

        // Get media attached to this comment
        $media = MediaUploader::getCommentMedia($commentId);

        // Get reactions for this comment
        $reactionsTable = CommentSchema::table(CommentSchema::TABLE_REACTIONS);
        $reactionCounts = $wpdb->get_results($wpdb->prepare("
            SELECT reaction_type, COUNT(*) as count
            FROM {$reactionsTable}
            WHERE comment_id = %d
            GROUP BY reaction_type
        ", $commentId), ARRAY_A);

        $reactions = [];
        $reactionTotal = 0;
        foreach ($reactionCounts as $row) {
            $reactions[$row['reaction_type']] = (int) $row['count'];
            $reactionTotal += (int) $row['count'];
        }

        // Get current user's reaction
        $userReaction = null;
        if ($currentUserId > 0) {
            $userReaction = $wpdb->get_var($wpdb->prepare("
                SELECT reaction_type FROM {$reactionsTable}
                WHERE comment_id = %d AND user_id = %d
            ", $commentId, $currentUserId));
        }

        $formatted = [
            'id' => $commentId,
            'post_id' => (int) $comment['comment_post_ID'],
            'parent_id' => (int) $comment['comment_parent'],
            'author' => [
                'id' => $userId,
                'name' => $comment['comment_author'],
                'avatar' => $avatar,
                'is_online' => $isOnline,
                'rank' => $rank ? [
                    'key' => $rank['key'],
                    'name' => $rank['name'],
                    'color' => $rank['color'],
                    'bg_color' => $rank['bg_color'],
                    'icon' => $rank['icon'] ?? null,
                    'is_special' => $rank['is_special'],
                ] : null,
            ],
            'content' => $comment['comment_content'],
            'date' => $comment['comment_date'],
            'date_gmt' => $comment['comment_date_gmt'],
            'time_ago' => human_time_diff(strtotime($comment['comment_date_gmt']), time()) . ' ago',
            'votes' => [
                'up' => (int) $comment['upvotes'],
                'down' => (int) $comment['downvotes'],
                'score' => (int) $comment['vote_score'],
            ],
            'user_vote' => $userVotes[$commentId] ?? null,
            'can_edit' => $currentUserId > 0 && ($currentUserId === $userId || current_user_can('moderate_comments')),
            'can_delete' => $currentUserId > 0 && ($currentUserId === $userId || current_user_can('moderate_comments')),
            'can_pin' => $canPin,
            'is_pinned' => (bool) ($comment['is_pinned'] ?? false),
            'pinned_at' => $comment['pinned_at'] ?? null,
            'depth' => $depth,
            'replies' => $replies,
            'media' => $media,
            'reactions' => $reactions,
            'reaction_total' => $reactionTotal,
            'user_reaction' => $userReaction,
        ];

        return $formatted;
    }

    /**
     * Vote on a comment
     */
    public function voteOnComment(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $commentId = $request->get_param('comment_id');
        $voteType = $request->get_param('vote_type');
        $userId = get_current_user_id();

        // Verify comment exists
        $comment = get_comment($commentId);
        if (!$comment) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Comment not found',
            ], 404);
        }

        // Can't vote on own comment
        if ((int) $comment->user_id === $userId) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Cannot vote on your own comment',
            ], 400);
        }

        $votesTable = CommentSchema::table(CommentSchema::TABLE_VOTES);

        // Handle vote removal
        if ($voteType === 'remove') {
            $wpdb->delete($votesTable, [
                'user_id' => $userId,
                'comment_id' => $commentId,
            ], ['%d', '%d']);

            // Update comment author's rank
            if ($comment->user_id > 0) {
                RankCalculator::updateUserRank((int) $comment->user_id);
            }

            return $this->getUpdatedVoteCounts($commentId);
        }

        $voteValue = $voteType === 'up' ? 1 : -1;

        // Check for existing vote
        $existingVote = $wpdb->get_var($wpdb->prepare("
            SELECT vote_type FROM {$votesTable}
            WHERE user_id = %d AND comment_id = %d
        ", $userId, $commentId));

        if ($existingVote !== null) {
            // Update existing vote
            $wpdb->update(
                $votesTable,
                ['vote_type' => $voteValue],
                ['user_id' => $userId, 'comment_id' => $commentId],
                ['%d'],
                ['%d', '%d']
            );
        } else {
            // Insert new vote
            $wpdb->insert($votesTable, [
                'user_id' => $userId,
                'comment_id' => $commentId,
                'vote_type' => $voteValue,
                'post_id' => $comment->comment_post_ID,
            ], ['%d', '%d', '%d', '%d']);
        }

        // Update comment author's rank
        if ($comment->user_id > 0) {
            RankCalculator::updateUserRank((int) $comment->user_id);
        }

        return $this->getUpdatedVoteCounts($commentId);
    }

    /**
     * Get updated vote counts for a comment
     */
    private function getUpdatedVoteCounts(int $commentId): \WP_REST_Response
    {
        global $wpdb;

        $votesTable = CommentSchema::table(CommentSchema::TABLE_VOTES);
        $userId = get_current_user_id();

        $votes = $wpdb->get_row($wpdb->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN vote_type = 1 THEN 1 ELSE 0 END), 0) as upvotes,
                COALESCE(SUM(CASE WHEN vote_type = -1 THEN 1 ELSE 0 END), 0) as downvotes,
                COALESCE(SUM(vote_type), 0) as score
            FROM {$votesTable}
            WHERE comment_id = %d
        ", $commentId), ARRAY_A);

        $userVote = $wpdb->get_var($wpdb->prepare("
            SELECT vote_type FROM {$votesTable}
            WHERE user_id = %d AND comment_id = %d
        ", $userId, $commentId));

        return new \WP_REST_Response([
            'success' => true,
            'votes' => [
                'up' => (int) $votes['upvotes'],
                'down' => (int) $votes['downvotes'],
                'score' => (int) $votes['score'],
            ],
            'user_vote' => $userVote !== null ? (int) $userVote : null,
        ], 200);
    }

    /**
     * Get user's vote on a comment
     */
    public function getUserVote(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $commentId = $request->get_param('comment_id');
        $userId = get_current_user_id();

        $votesTable = CommentSchema::table(CommentSchema::TABLE_VOTES);

        $vote = $wpdb->get_var($wpdb->prepare("
            SELECT vote_type FROM {$votesTable}
            WHERE user_id = %d AND comment_id = %d
        ", $userId, $commentId));

        return new \WP_REST_Response([
            'vote' => $vote !== null ? (int) $vote : null,
        ], 200);
    }

    /**
     * Report a comment
     */
    public function reportComment(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $commentId = $request->get_param('comment_id');
        $reason = $request->get_param('reason');
        $details = $request->get_param('details') ?? '';
        $userId = get_current_user_id();

        // Verify comment exists
        $comment = get_comment($commentId);
        if (!$comment) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Comment not found',
            ], 404);
        }

        // Can't report own comment
        if ((int) $comment->user_id === $userId) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Cannot report your own comment',
            ], 400);
        }

        $reportsTable = CommentSchema::table(CommentSchema::TABLE_REPORTS);

        // Check if user already reported this comment
        $existingReport = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$reportsTable}
            WHERE comment_id = %d AND reporter_id = %d
        ", $commentId, $userId));

        if ($existingReport) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'You have already reported this comment',
            ], 400);
        }

        // Insert report
        $wpdb->insert($reportsTable, [
            'comment_id' => $commentId,
            'reporter_id' => $userId,
            'reason' => $reason,
            'details' => $details,
        ], ['%d', '%d', '%s', '%s']);

        // Check if comment has reached report threshold for auto-notification
        $reportCount = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$reportsTable}
            WHERE comment_id = %d AND status = 'pending'
        ", $commentId));

        // Get notification settings
        $notificationSettings = get_option('bbj_admin_notifications', []);
        $threshold = $notificationSettings['comment_reports']['threshold'] ?? 3;
        $notifyEmail = $notificationSettings['comment_reports']['email'] ?? '';
        $notifyEnabled = $notificationSettings['comment_reports']['enabled'] ?? false;

        // Send notification if threshold reached
        if ($notifyEnabled && $notifyEmail && $reportCount >= $threshold) {
            $this->sendReportNotification($comment, $reportCount, $notifyEmail);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Report submitted successfully',
        ], 200);
    }

    /**
     * Send email notification about reported comment
     */
    private function sendReportNotification(\WP_Comment $comment, int $reportCount, string $email): void
    {
        $post = get_post($comment->comment_post_ID);
        $postTitle = $post ? $post->post_title : 'Unknown Post';
        $postUrl = get_permalink($comment->comment_post_ID);
        $adminUrl = admin_url('comment.php?action=editcomment&c=' . $comment->comment_ID);

        $subject = sprintf('[BBJ] Comment reported %d times - Review needed', $reportCount);

        $message = sprintf(
            "A comment has been reported %d times and needs review.\n\n" .
            "Post: %s\n" .
            "Post URL: %s\n\n" .
            "Comment Author: %s\n" .
            "Comment Content:\n%s\n\n" .
            "Review in Admin: %s\n",
            $reportCount,
            $postTitle,
            $postUrl,
            $comment->comment_author,
            $comment->comment_content,
            $adminUrl
        );

        wp_mail($email, $subject, $message);
    }

    /**
     * Get user rank info
     */
    public function getUserRank(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = $request->get_param('user_id');

        $user = get_user_by('ID', $userId);
        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $rank = RankCalculator::calculateRank($userId);

        return new \WP_REST_Response([
            'user_id' => $userId,
            'rank' => $rank,
        ], 200);
    }

    /**
     * Get all ranks info
     */
    public function getAllRanks(): \WP_REST_Response
    {
        return new \WP_REST_Response(RankCalculator::getAllRanks(), 200);
    }

    /**
     * Post a new comment
     */
    public function postComment(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = $request->get_param('post_id');
        $content = $request->get_param('content');
        $parentId = $request->get_param('parent_id');
        $mediaId = $request->get_param('media_id');
        $userId = get_current_user_id();

        // Check if user is blacklisted
        if ($this->isUserBlacklisted($userId)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'You are not allowed to post comments',
            ], 403);
        }

        // Verify post exists and allows comments
        $post = get_post($postId);
        if (!$post) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Post not found',
            ], 404);
        }

        if ($post->comment_status !== 'open') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Comments are closed for this post',
            ], 403);
        }

        // Get user info
        $user = get_user_by('ID', $userId);

        // Create comment
        $commentData = [
            'comment_post_ID' => $postId,
            'comment_content' => $content,
            'comment_parent' => $parentId,
            'user_id' => $userId,
            'comment_author' => $user->display_name,
            'comment_author_email' => $user->user_email,
            'comment_approved' => 1, // Auto-approve for logged in users
        ];

        $commentId = wp_insert_comment($commentData);

        if (!$commentId) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to post comment',
            ], 500);
        }

        // Attach media if provided
        $media = null;
        if ($mediaId) {
            $attachResult = MediaUploader::attachToComment($mediaId, $commentId, $userId);
            if (!is_wp_error($attachResult)) {
                $media = MediaUploader::getCommentMedia($commentId);
            }
        }

        // Create reply notification if this is a reply to someone else's comment
        if ($parentId > 0) {
            $parentComment = get_comment($parentId);
            if ($parentComment && (int) $parentComment->user_id !== $userId && (int) $parentComment->user_id > 0) {
                NotificationService::createReplyNotification(
                    (int) $parentComment->user_id,
                    $userId,
                    $commentId,
                    $postId,
                    $parentId
                );
            }
        }

        // Parse @mentions and create notifications
        $mentionedUserIds = NotificationService::parseMentions($content);
        foreach ($mentionedUserIds as $mentionedUserId) {
            NotificationService::createMentionNotification(
                $mentionedUserId,
                $userId,
                $commentId,
                $postId
            );
        }

        // Notify thread subscribers (excluding user who got reply notification)
        $excludeUserId = null;
        if ($parentId > 0) {
            $parentComment = get_comment($parentId);
            if ($parentComment && (int) $parentComment->user_id > 0) {
                $excludeUserId = (int) $parentComment->user_id;
            }
        }
        NotificationService::notifyPostSubscribers($postId, $userId, $commentId, $excludeUserId);

        // Update user's rank
        RankCalculator::updateUserRank($userId);

        // Get the created comment
        $comment = get_comment($commentId);
        $rank = RankCalculator::calculateRank($userId);

        return new \WP_REST_Response([
            'success' => true,
            'comment' => [
                'id' => $commentId,
                'post_id' => $postId,
                'parent_id' => $parentId,
                'author' => [
                    'id' => $userId,
                    'name' => $user->display_name,
                    'avatar' => AvatarUploader::getAvatarUrl($userId, 64),
                    'is_online' => true, // User posting is obviously online
                    'rank' => [
                        'key' => $rank['key'],
                        'name' => $rank['name'],
                        'color' => $rank['color'],
                        'bg_color' => $rank['bg_color'],
                        'icon' => $rank['icon'] ?? null,
                        'is_special' => $rank['is_special'],
                    ],
                ],
                'content' => $content,
                'date' => $comment->comment_date,
                'date_gmt' => $comment->comment_date_gmt,
                'time_ago' => 'just now',
                'votes' => ['up' => 0, 'down' => 0, 'score' => 0],
                'user_vote' => null,
                'can_edit' => true,
                'can_delete' => true,
                'depth' => 0,
                'replies' => [],
                'media' => $media,
                'reactions' => [],
                'reaction_total' => 0,
                'user_reaction' => null,
            ],
        ], 201);
    }

    /**
     * Edit a comment
     */
    public function editComment(\WP_REST_Request $request): \WP_REST_Response
    {
        $commentId = $request->get_param('comment_id');
        $content = $request->get_param('content');

        $result = wp_update_comment([
            'comment_ID' => $commentId,
            'comment_content' => $content,
        ]);

        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to update comment',
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Comment updated',
            'content' => $content,
        ], 200);
    }

    /**
     * Delete a comment
     */
    public function deleteComment(\WP_REST_Request $request): \WP_REST_Response
    {
        $commentId = $request->get_param('comment_id');

        $result = wp_delete_comment($commentId, true);

        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to delete comment',
            ], 500);
        }

        // Also delete votes for this comment
        global $wpdb;
        $votesTable = CommentSchema::table(CommentSchema::TABLE_VOTES);
        $wpdb->delete($votesTable, ['comment_id' => $commentId], ['%d']);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Comment deleted',
        ], 200);
    }

    /**
     * Check if user is blacklisted
     */
    public function checkBlacklist(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $isBlacklisted = $this->isUserBlacklisted($userId);

        return new \WP_REST_Response([
            'is_blacklisted' => $isBlacklisted,
        ], 200);
    }

    /**
     * Check if a user or IP is blacklisted
     */
    private function isUserBlacklisted(int $userId): bool
    {
        global $wpdb;

        $blacklistTable = CommentSchema::table(CommentSchema::TABLE_BLACKLIST);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $isBlacklisted = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$blacklistTable}
            WHERE is_active = 1
                AND (expires_at IS NULL OR expires_at > NOW())
                AND (user_id = %d OR ip_address = %s)
            LIMIT 1
        ", $userId, $ip));

        return $isBlacklisted !== null;
    }

    /**
     * Permission callback: Check if user is logged in
     */
    public function checkUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Permission callback: Check if user can edit a comment
     */
    public function checkCanEditComment(\WP_REST_Request $request): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $commentId = $request->get_param('comment_id');
        $comment = get_comment($commentId);

        if (!$comment) {
            return false;
        }

        $userId = get_current_user_id();

        // Owner can edit or moderators
        return (int) $comment->user_id === $userId || current_user_can('moderate_comments');
    }

    /**
     * Permission callback: Check if user can delete a comment
     */
    public function checkCanDeleteComment(\WP_REST_Request $request): bool
    {
        return $this->checkCanEditComment($request);
    }

    /**
     * Permission callback: Check if user can pin comments
     */
    public function checkCanPin(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        $allowedRoles = ['administrator', 'second_in_command', 'editor', 'comment_mod'];

        return array_intersect($allowedRoles, $user->roles) ? true : false;
    }

    /**
     * Pin a comment (staff pick)
     */
    public function pinComment(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $commentId = $request->get_param('comment_id');
        $userId = get_current_user_id();

        // Verify comment exists
        $comment = get_comment($commentId);
        if (!$comment) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Comment not found',
            ], 404);
        }

        $pinnedTable = CommentSchema::table(CommentSchema::TABLE_PINNED);

        // Check if already pinned
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$pinnedTable} WHERE comment_id = %d",
            $commentId
        ));

        if ($existing) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Comment is already pinned',
            ], 400);
        }

        // Insert pin
        $result = $wpdb->insert($pinnedTable, [
            'comment_id' => $commentId,
            'post_id' => $comment->comment_post_ID,
            'pinned_by' => $userId,
        ], ['%d', '%d', '%d']);

        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to pin comment',
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Comment pinned',
            'pinned_at' => current_time('mysql'),
        ], 200);
    }

    /**
     * Unpin a comment
     */
    public function unpinComment(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $commentId = $request->get_param('comment_id');

        $pinnedTable = CommentSchema::table(CommentSchema::TABLE_PINNED);

        $result = $wpdb->delete($pinnedTable, [
            'comment_id' => $commentId,
        ], ['%d']);

        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Comment was not pinned',
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Comment unpinned',
        ], 200);
    }
}
