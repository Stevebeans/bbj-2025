<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Comments\CommentSchema;

/**
 * Comment Reactions API Routes
 *
 * Facebook-style emoji reactions: 👍❤️😂😮😢😡
 */
class ReactionRoutes
{
    /**
     * Available reaction types
     */
    public const REACTION_TYPES = [
        'like' => '👍',
        'love' => '❤️',
        'haha' => '😂',
        'wow' => '😮',
        'sad' => '😢',
        'angry' => '😡',
    ];

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        // Get reactions for a comment
        register_rest_route($namespace, '/comments/(?P<comment_id>\d+)/reactions', [
            'methods' => 'GET',
            'callback' => [$this, 'getReactions'],
            'permission_callback' => '__return_true',
            'args' => [
                'comment_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Add/change reaction
        register_rest_route($namespace, '/comments/(?P<comment_id>\d+)/reactions', [
            'methods' => 'POST',
            'callback' => [$this, 'addReaction'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'comment_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'reaction_type' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => array_keys(self::REACTION_TYPES),
                ],
            ],
        ]);

        // Remove reaction
        register_rest_route($namespace, '/comments/(?P<comment_id>\d+)/reactions', [
            'methods' => 'DELETE',
            'callback' => [$this, 'removeReaction'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'comment_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Get reaction types (for frontend reference)
        register_rest_route($namespace, '/reactions/types', [
            'methods' => 'GET',
            'callback' => [$this, 'getReactionTypes'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get reactions for a comment
     */
    public function getReactions(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $commentId = $request->get_param('comment_id');
        $currentUserId = get_current_user_id();

        $table = CommentSchema::table(CommentSchema::TABLE_REACTIONS);

        // Get reaction counts by type
        $counts = $wpdb->get_results($wpdb->prepare("
            SELECT reaction_type, COUNT(*) as count
            FROM {$table}
            WHERE comment_id = %d
            GROUP BY reaction_type
        ", $commentId), ARRAY_A);

        // Format counts into associative array
        $reactionCounts = [];
        $totalCount = 0;
        foreach ($counts as $row) {
            $reactionCounts[$row['reaction_type']] = (int) $row['count'];
            $totalCount += (int) $row['count'];
        }

        // Get current user's reaction
        $userReaction = null;
        if ($currentUserId > 0) {
            $userReaction = $wpdb->get_var($wpdb->prepare("
                SELECT reaction_type FROM {$table}
                WHERE comment_id = %d AND user_id = %d
            ", $commentId, $currentUserId));
        }

        return new \WP_REST_Response([
            'success' => true,
            'reactions' => $reactionCounts,
            'total' => $totalCount,
            'user_reaction' => $userReaction,
        ], 200);
    }

    /**
     * Add or change a reaction
     */
    public function addReaction(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $commentId = $request->get_param('comment_id');
        $reactionType = $request->get_param('reaction_type');
        $userId = get_current_user_id();

        // Verify comment exists
        $comment = get_comment($commentId);
        if (!$comment) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Comment not found',
            ], 404);
        }

        $table = CommentSchema::table(CommentSchema::TABLE_REACTIONS);

        // Check for existing reaction
        $existingReaction = $wpdb->get_var($wpdb->prepare("
            SELECT reaction_type FROM {$table}
            WHERE comment_id = %d AND user_id = %d
        ", $commentId, $userId));

        if ($existingReaction) {
            // Update existing reaction
            $wpdb->update(
                $table,
                ['reaction_type' => $reactionType],
                ['comment_id' => $commentId, 'user_id' => $userId],
                ['%s'],
                ['%d', '%d']
            );
        } else {
            // Insert new reaction
            $wpdb->insert($table, [
                'comment_id' => $commentId,
                'user_id' => $userId,
                'reaction_type' => $reactionType,
            ], ['%d', '%d', '%s']);
        }

        // Return updated reaction counts
        return $this->getReactions($request);
    }

    /**
     * Remove a reaction
     */
    public function removeReaction(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $commentId = $request->get_param('comment_id');
        $userId = get_current_user_id();

        $table = CommentSchema::table(CommentSchema::TABLE_REACTIONS);

        $wpdb->delete($table, [
            'comment_id' => $commentId,
            'user_id' => $userId,
        ], ['%d', '%d']);

        // Return updated reaction counts
        return $this->getReactions($request);
    }

    /**
     * Get available reaction types
     */
    public function getReactionTypes(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'types' => self::REACTION_TYPES,
        ], 200);
    }

    /**
     * Permission callback: Check if user is logged in
     */
    public function checkUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }
}
