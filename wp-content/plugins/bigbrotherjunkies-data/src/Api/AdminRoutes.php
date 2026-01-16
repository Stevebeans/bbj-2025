<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Comments\CommentSchema;
use BigBrotherJunkies\Data\Comments\CommentMigrator;
use BigBrotherJunkies\Data\Comments\RankCalculator;

/**
 * Admin API Routes
 *
 * Provides endpoints for:
 * - Comment moderation (reports, blacklist)
 * - Admin settings (permissions, notifications)
 * - Database management (migrations, stats)
 */
class AdminRoutes
{
    /**
     * Default admin permissions
     */
    public const DEFAULT_PERMISSIONS = [
        'comment_moderation' => [
            'label' => 'Moderate Comments',
            'description' => 'View reports, approve/delete comments, manage blacklist',
            'roles' => ['administrator', 'editor'],
        ],
        'feed_updates' => [
            'label' => 'Feed Updater',
            'description' => 'Post and edit live feed updates',
            'roles' => ['administrator', 'updater'],
        ],
        'player_management' => [
            'label' => 'Manage Players',
            'description' => 'Add/edit player profiles',
            'roles' => ['administrator', 'editor'],
        ],
        'season_management' => [
            'label' => 'Manage Seasons',
            'description' => 'Add/edit seasons, set current season',
            'roles' => ['administrator'],
        ],
        'admin_settings' => [
            'label' => 'Admin Settings',
            'description' => 'Configure permissions and notifications',
            'roles' => ['administrator'],
        ],
    ];

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        // ========================================
        // ADMIN DASHBOARD
        // ========================================

        // Get admin dashboard data
        register_rest_route($namespace, '/admin/dashboard', [
            'methods' => 'GET',
            'callback' => [$this, 'getDashboard'],
            'permission_callback' => [$this, 'checkAdminAccess'],
        ]);

        // Get user's admin permissions (what features they can access)
        register_rest_route($namespace, '/admin/my-permissions', [
            'methods' => 'GET',
            'callback' => [$this, 'getMyPermissions'],
            'permission_callback' => [$this, 'checkAdminAccess'],
        ]);

        // ========================================
        // COMMENT MODERATION
        // ========================================

        // Get pending reports
        register_rest_route($namespace, '/admin/reports', [
            'methods' => 'GET',
            'callback' => [$this, 'getReports'],
            'permission_callback' => [$this, 'checkCommentModerationAccess'],
            'args' => [
                'status' => [
                    'default' => 'pending',
                    'type' => 'string',
                    'enum' => ['pending', 'reviewed', 'dismissed', 'actioned', 'all'],
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
            ],
        ]);

        // Get report details
        register_rest_route($namespace, '/admin/reports/(?P<report_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getReportDetails'],
            'permission_callback' => [$this, 'checkCommentModerationAccess'],
        ]);

        // Action a report (dismiss, delete comment, blacklist user)
        register_rest_route($namespace, '/admin/reports/(?P<report_id>\d+)/action', [
            'methods' => 'POST',
            'callback' => [$this, 'actionReport'],
            'permission_callback' => [$this, 'checkCommentModerationAccess'],
            'args' => [
                'action' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['dismiss', 'delete_comment', 'spam', 'blacklist_user', 'blacklist_ip'],
                ],
            ],
        ]);

        // Bulk action reports
        register_rest_route($namespace, '/admin/reports/bulk-action', [
            'methods' => 'POST',
            'callback' => [$this, 'bulkActionReports'],
            'permission_callback' => [$this, 'checkCommentModerationAccess'],
            'args' => [
                'report_ids' => [
                    'required' => true,
                    'type' => 'array',
                ],
                'action' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['dismiss', 'delete_comment'],
                ],
            ],
        ]);

        // Get comments for moderation (with report counts)
        register_rest_route($namespace, '/admin/comments', [
            'methods' => 'GET',
            'callback' => [$this, 'getCommentsForModeration'],
            'permission_callback' => [$this, 'checkCommentModerationAccess'],
            'args' => [
                'filter' => [
                    'default' => 'reported',
                    'type' => 'string',
                    'enum' => ['all', 'reported', 'pending'],
                ],
                'per_page' => [
                    'default' => 20,
                    'type' => 'integer',
                ],
                'page' => [
                    'default' => 1,
                    'type' => 'integer',
                ],
            ],
        ]);

        // Moderate a comment directly
        register_rest_route($namespace, '/admin/comments/(?P<comment_id>\d+)/moderate', [
            'methods' => 'POST',
            'callback' => [$this, 'moderateComment'],
            'permission_callback' => [$this, 'checkCommentModerationAccess'],
            'args' => [
                'action' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['approve', 'unapprove', 'spam', 'delete'],
                ],
            ],
        ]);

        // ========================================
        // BLACKLIST MANAGEMENT
        // ========================================

        // Get blacklist entries
        register_rest_route($namespace, '/admin/blacklist', [
            'methods' => 'GET',
            'callback' => [$this, 'getBlacklist'],
            'permission_callback' => [$this, 'checkCommentModerationAccess'],
            'args' => [
                'per_page' => [
                    'default' => 20,
                    'type' => 'integer',
                ],
                'page' => [
                    'default' => 1,
                    'type' => 'integer',
                ],
                'active_only' => [
                    'default' => true,
                    'type' => 'boolean',
                ],
            ],
        ]);

        // Add to blacklist
        register_rest_route($namespace, '/admin/blacklist', [
            'methods' => 'POST',
            'callback' => [$this, 'addToBlacklist'],
            'permission_callback' => [$this, 'checkCommentModerationAccess'],
            'args' => [
                'user_id' => [
                    'type' => 'integer',
                ],
                'ip_address' => [
                    'type' => 'string',
                ],
                'reason' => [
                    'type' => 'string',
                ],
                'expires_days' => [
                    'type' => 'integer',
                    'description' => 'Days until expiry (0 = permanent)',
                ],
            ],
        ]);

        // Remove from blacklist
        register_rest_route($namespace, '/admin/blacklist/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'removeFromBlacklist'],
            'permission_callback' => [$this, 'checkCommentModerationAccess'],
        ]);

        // ========================================
        // ADMIN SETTINGS
        // ========================================

        // Get admin settings
        register_rest_route($namespace, '/admin/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'getSettings'],
            'permission_callback' => [$this, 'checkAdminSettingsAccess'],
        ]);

        // Update admin settings
        register_rest_route($namespace, '/admin/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'updateSettings'],
            'permission_callback' => [$this, 'checkAdminSettingsAccess'],
        ]);

        // Get all WordPress roles
        register_rest_route($namespace, '/admin/roles', [
            'methods' => 'GET',
            'callback' => [$this, 'getRoles'],
            'permission_callback' => [$this, 'checkAdminSettingsAccess'],
        ]);

        // ========================================
        // DATABASE / MIGRATION
        // ========================================

        // Get database status
        register_rest_route($namespace, '/admin/database/status', [
            'methods' => 'GET',
            'callback' => [$this, 'getDatabaseStatus'],
            'permission_callback' => [$this, 'checkAdminSettingsAccess'],
        ]);

        // Run migrations
        register_rest_route($namespace, '/admin/database/migrate', [
            'methods' => 'POST',
            'callback' => [$this, 'runMigration'],
            'permission_callback' => [$this, 'checkAdminSettingsAccess'],
        ]);

        // Get vote migration preview
        register_rest_route($namespace, '/admin/database/vote-migration-preview', [
            'methods' => 'GET',
            'callback' => [$this, 'getVoteMigrationPreview'],
            'permission_callback' => [$this, 'checkAdminSettingsAccess'],
        ]);

        // Run vote migration (from WPDiscuz)
        register_rest_route($namespace, '/admin/database/migrate-votes', [
            'methods' => 'POST',
            'callback' => [$this, 'runVoteMigration'],
            'permission_callback' => [$this, 'checkAdminSettingsAccess'],
            'args' => [
                'batch_size' => [
                    'default' => 5000,
                    'type' => 'integer',
                ],
            ],
        ]);

        // Reset vote migration (to re-run)
        register_rest_route($namespace, '/admin/database/reset-vote-migration', [
            'methods' => 'POST',
            'callback' => [$this, 'resetVoteMigration'],
            'permission_callback' => [$this, 'checkAdminSettingsAccess'],
        ]);

        // Bulk update user ranks
        register_rest_route($namespace, '/admin/ranks/recalculate', [
            'methods' => 'POST',
            'callback' => [$this, 'recalculateRanks'],
            'permission_callback' => [$this, 'checkAdminSettingsAccess'],
            'args' => [
                'limit' => [
                    'default' => 0,
                    'type' => 'integer',
                ],
            ],
        ]);
    }

    // ========================================
    // DASHBOARD ENDPOINTS
    // ========================================

    /**
     * Get admin dashboard data
     */
    public function getDashboard(): \WP_REST_Response
    {
        global $wpdb;

        $reportsTable = CommentSchema::table(CommentSchema::TABLE_REPORTS);

        // Get pending report count
        $pendingReports = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$reportsTable} WHERE status = 'pending'
        ");

        // Get today's stats
        $today = current_time('Y-m-d');
        $todayComments = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->comments}
            WHERE DATE(comment_date) = %s AND comment_approved = '1'
        ", $today));

        $votesTable = CommentSchema::table(CommentSchema::TABLE_VOTES);
        $todayVotes = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$votesTable}
            WHERE DATE(created_at) = %s
        ", $today));

        return new \WP_REST_Response([
            'pending_reports' => $pendingReports,
            'today_comments' => $todayComments,
            'today_votes' => $todayVotes,
            'features' => $this->getUserFeatures(),
        ], 200);
    }

    /**
     * Get current user's permissions
     */
    public function getMyPermissions(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'features' => $this->getUserFeatures(),
        ], 200);
    }

    /**
     * Get features the current user has access to
     */
    private function getUserFeatures(): array
    {
        $permissions = get_option('bbj_admin_permissions', self::DEFAULT_PERMISSIONS);
        $user = wp_get_current_user();
        $userRoles = $user->roles;

        $features = [];
        foreach ($permissions as $key => $permission) {
            $hasAccess = !empty(array_intersect($userRoles, $permission['roles']));
            if ($hasAccess) {
                $features[$key] = [
                    'label' => $permission['label'],
                    'description' => $permission['description'],
                ];
            }
        }

        return $features;
    }

    // ========================================
    // REPORT ENDPOINTS
    // ========================================

    /**
     * Get pending reports
     */
    public function getReports(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $status = $request->get_param('status');
        $perPage = min($request->get_param('per_page'), 50);
        $page = $request->get_param('page');
        $offset = ($page - 1) * $perPage;

        $reportsTable = CommentSchema::table(CommentSchema::TABLE_REPORTS);

        // Build where clause
        $where = '';
        if ($status !== 'all') {
            $where = $wpdb->prepare("WHERE r.status = %s", $status);
        }

        // Get reports with comment and user info
        $reports = $wpdb->get_results("
            SELECT
                r.*,
                c.comment_content,
                c.comment_author,
                c.comment_date,
                c.user_id as comment_user_id,
                c.comment_post_ID,
                p.post_title,
                reporter.display_name as reporter_name,
                (SELECT COUNT(*) FROM {$reportsTable} WHERE comment_id = r.comment_id AND status = 'pending') as total_reports
            FROM {$reportsTable} r
            LEFT JOIN {$wpdb->comments} c ON r.comment_id = c.comment_ID
            LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
            LEFT JOIN {$wpdb->users} reporter ON r.reporter_id = reporter.ID
            {$where}
            ORDER BY r.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ", ARRAY_A);

        // Get total count
        $totalQuery = "SELECT COUNT(*) FROM {$reportsTable} r {$where}";
        $total = (int) $wpdb->get_var($totalQuery);

        return new \WP_REST_Response([
            'reports' => $reports,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => ceil($total / $perPage),
            ],
        ], 200);
    }

    /**
     * Get report details
     */
    public function getReportDetails(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $reportId = $request->get_param('report_id');
        $reportsTable = CommentSchema::table(CommentSchema::TABLE_REPORTS);

        $report = $wpdb->get_row($wpdb->prepare("
            SELECT
                r.*,
                c.comment_content,
                c.comment_author,
                c.comment_author_email,
                c.comment_date,
                c.user_id as comment_user_id,
                c.comment_post_ID,
                p.post_title,
                reporter.display_name as reporter_name,
                reviewer.display_name as reviewer_name
            FROM {$reportsTable} r
            LEFT JOIN {$wpdb->comments} c ON r.comment_id = c.comment_ID
            LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
            LEFT JOIN {$wpdb->users} reporter ON r.reporter_id = reporter.ID
            LEFT JOIN {$wpdb->users} reviewer ON r.reviewed_by = reviewer.ID
            WHERE r.id = %d
        ", $reportId), ARRAY_A);

        if (!$report) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Report not found',
            ], 404);
        }

        // Get all reports for this comment
        $allReports = $wpdb->get_results($wpdb->prepare("
            SELECT r.*, u.display_name as reporter_name
            FROM {$reportsTable} r
            LEFT JOIN {$wpdb->users} u ON r.reporter_id = u.ID
            WHERE r.comment_id = %d
            ORDER BY r.created_at DESC
        ", $report['comment_id']), ARRAY_A);

        $report['all_reports'] = $allReports;

        return new \WP_REST_Response($report, 200);
    }

    /**
     * Action a report
     */
    public function actionReport(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $reportId = $request->get_param('report_id');
        $action = $request->get_param('action');
        $userId = get_current_user_id();

        $reportsTable = CommentSchema::table(CommentSchema::TABLE_REPORTS);

        // Get report
        $report = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$reportsTable} WHERE id = %d
        ", $reportId), ARRAY_A);

        if (!$report) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Report not found',
            ], 404);
        }

        $comment = get_comment($report['comment_id']);

        switch ($action) {
            case 'dismiss':
                // Mark report as dismissed
                $wpdb->update(
                    $reportsTable,
                    [
                        'status' => 'dismissed',
                        'reviewed_by' => $userId,
                        'reviewed_at' => current_time('mysql'),
                        'action_taken' => 'dismissed',
                    ],
                    ['id' => $reportId],
                    ['%s', '%d', '%s', '%s'],
                    ['%d']
                );
                break;

            case 'delete_comment':
                // Delete the comment
                if ($comment) {
                    wp_delete_comment($report['comment_id'], true);
                }
                // Mark all reports for this comment as actioned
                $wpdb->update(
                    $reportsTable,
                    [
                        'status' => 'actioned',
                        'reviewed_by' => $userId,
                        'reviewed_at' => current_time('mysql'),
                        'action_taken' => 'comment_deleted',
                    ],
                    ['comment_id' => $report['comment_id']],
                    ['%s', '%d', '%s', '%s'],
                    ['%d']
                );
                break;

            case 'spam':
                // Mark comment as spam
                if ($comment) {
                    wp_spam_comment($report['comment_id']);
                }
                $wpdb->update(
                    $reportsTable,
                    [
                        'status' => 'actioned',
                        'reviewed_by' => $userId,
                        'reviewed_at' => current_time('mysql'),
                        'action_taken' => 'marked_spam',
                    ],
                    ['comment_id' => $report['comment_id']],
                    ['%s', '%d', '%s', '%s'],
                    ['%d']
                );
                break;

            case 'blacklist_user':
                if ($comment && $comment->user_id > 0) {
                    $this->addUserToBlacklist((int) $comment->user_id, 'Blacklisted via report action', $userId);
                    // Delete the comment
                    wp_delete_comment($report['comment_id'], true);
                }
                $wpdb->update(
                    $reportsTable,
                    [
                        'status' => 'actioned',
                        'reviewed_by' => $userId,
                        'reviewed_at' => current_time('mysql'),
                        'action_taken' => 'user_blacklisted',
                    ],
                    ['comment_id' => $report['comment_id']],
                    ['%s', '%d', '%s', '%s'],
                    ['%d']
                );
                break;

            case 'blacklist_ip':
                // Get IP from comment meta or use a placeholder
                $ip = get_comment_author_IP($report['comment_id']);
                if ($ip) {
                    $this->addIpToBlacklist($ip, 'Blacklisted via report action', $userId);
                    // Delete the comment
                    wp_delete_comment($report['comment_id'], true);
                }
                $wpdb->update(
                    $reportsTable,
                    [
                        'status' => 'actioned',
                        'reviewed_by' => $userId,
                        'reviewed_at' => current_time('mysql'),
                        'action_taken' => 'ip_blacklisted',
                    ],
                    ['comment_id' => $report['comment_id']],
                    ['%s', '%d', '%s', '%s'],
                    ['%d']
                );
                break;
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Action completed successfully',
            'action' => $action,
        ], 200);
    }

    /**
     * Bulk action reports
     */
    public function bulkActionReports(\WP_REST_Request $request): \WP_REST_Response
    {
        $reportIds = $request->get_param('report_ids');
        $action = $request->get_param('action');

        $results = [
            'processed' => 0,
            'errors' => [],
        ];

        foreach ($reportIds as $reportId) {
            $subRequest = new \WP_REST_Request('POST');
            $subRequest->set_param('report_id', $reportId);
            $subRequest->set_param('action', $action);

            $response = $this->actionReport($subRequest);

            if ($response->get_status() === 200) {
                $results['processed']++;
            } else {
                $results['errors'][] = $reportId;
            }
        }

        return new \WP_REST_Response($results, 200);
    }

    /**
     * Get comments for moderation
     */
    public function getCommentsForModeration(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $filter = $request->get_param('filter');
        $perPage = min($request->get_param('per_page'), 50);
        $page = $request->get_param('page');
        $offset = ($page - 1) * $perPage;

        $reportsTable = CommentSchema::table(CommentSchema::TABLE_REPORTS);

        // Build query based on filter
        switch ($filter) {
            case 'reported':
                $query = "
                    SELECT
                        c.*,
                        p.post_title,
                        COUNT(DISTINCT r.id) as report_count,
                        MAX(r.created_at) as last_reported
                    FROM {$wpdb->comments} c
                    INNER JOIN {$reportsTable} r ON c.comment_ID = r.comment_id AND r.status = 'pending'
                    LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
                    GROUP BY c.comment_ID
                    ORDER BY report_count DESC, last_reported DESC
                    LIMIT {$perPage} OFFSET {$offset}
                ";
                $countQuery = "
                    SELECT COUNT(DISTINCT comment_id)
                    FROM {$reportsTable}
                    WHERE status = 'pending'
                ";
                break;

            case 'pending':
                $query = "
                    SELECT c.*, p.post_title, 0 as report_count
                    FROM {$wpdb->comments} c
                    LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
                    WHERE c.comment_approved = '0'
                    ORDER BY c.comment_date DESC
                    LIMIT {$perPage} OFFSET {$offset}
                ";
                $countQuery = "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = '0'";
                break;

            default: // all
                $query = "
                    SELECT
                        c.*,
                        p.post_title,
                        COALESCE(report_counts.cnt, 0) as report_count
                    FROM {$wpdb->comments} c
                    LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
                    LEFT JOIN (
                        SELECT comment_id, COUNT(*) as cnt
                        FROM {$reportsTable}
                        WHERE status = 'pending'
                        GROUP BY comment_id
                    ) report_counts ON c.comment_ID = report_counts.comment_id
                    ORDER BY c.comment_date DESC
                    LIMIT {$perPage} OFFSET {$offset}
                ";
                $countQuery = "SELECT COUNT(*) FROM {$wpdb->comments}";
                break;
        }

        $comments = $wpdb->get_results($query, ARRAY_A);
        $total = (int) $wpdb->get_var($countQuery);

        // Add author avatar to each comment
        foreach ($comments as &$comment) {
            $comment['author_avatar'] = get_avatar_url($comment['comment_author_email'], ['size' => 40]);
        }

        return new \WP_REST_Response([
            'comments' => $comments,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => ceil($total / $perPage),
            ],
        ], 200);
    }

    /**
     * Moderate a comment
     */
    public function moderateComment(\WP_REST_Request $request): \WP_REST_Response
    {
        $commentId = $request->get_param('comment_id');
        $action = $request->get_param('action');

        $comment = get_comment($commentId);
        if (!$comment) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Comment not found',
            ], 404);
        }

        switch ($action) {
            case 'approve':
                wp_set_comment_status($commentId, 'approve');
                break;
            case 'unapprove':
                wp_set_comment_status($commentId, 'hold');
                break;
            case 'spam':
                wp_spam_comment($commentId);
                break;
            case 'delete':
                wp_delete_comment($commentId, true);
                break;
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Comment moderated successfully',
            'action' => $action,
        ], 200);
    }

    // ========================================
    // BLACKLIST ENDPOINTS
    // ========================================

    /**
     * Get blacklist entries
     */
    public function getBlacklist(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $perPage = min($request->get_param('per_page'), 50);
        $page = $request->get_param('page');
        $activeOnly = $request->get_param('active_only');
        $offset = ($page - 1) * $perPage;

        $blacklistTable = CommentSchema::table(CommentSchema::TABLE_BLACKLIST);

        $where = $activeOnly ? "WHERE b.is_active = 1" : "";

        $entries = $wpdb->get_results("
            SELECT
                b.*,
                u.display_name as user_name,
                u.user_email,
                creator.display_name as created_by_name
            FROM {$blacklistTable} b
            LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
            LEFT JOIN {$wpdb->users} creator ON b.created_by = creator.ID
            {$where}
            ORDER BY b.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ", ARRAY_A);

        $countWhere = $activeOnly ? "WHERE is_active = 1" : "";
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$blacklistTable} {$countWhere}");

        return new \WP_REST_Response([
            'entries' => $entries,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => ceil($total / $perPage),
            ],
        ], 200);
    }

    /**
     * Add to blacklist
     */
    public function addToBlacklist(\WP_REST_Request $request): \WP_REST_Response
    {
        $targetUserId = $request->get_param('user_id');
        $ipAddress = $request->get_param('ip_address');
        $reason = $request->get_param('reason') ?? '';
        $expiresDays = $request->get_param('expires_days') ?? 0;

        if (!$targetUserId && !$ipAddress) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Either user_id or ip_address is required',
            ], 400);
        }

        $currentUserId = get_current_user_id();

        if ($targetUserId) {
            $result = $this->addUserToBlacklist($targetUserId, $reason, $currentUserId, $expiresDays);
        } else {
            $result = $this->addIpToBlacklist($ipAddress, $reason, $currentUserId, $expiresDays);
        }

        return new \WP_REST_Response([
            'success' => true,
            'id' => $result,
        ], 201);
    }

    /**
     * Add user to blacklist
     */
    private function addUserToBlacklist(int $userId, string $reason, int $createdBy, int $expiresDays = 0): int
    {
        global $wpdb;

        $blacklistTable = CommentSchema::table(CommentSchema::TABLE_BLACKLIST);

        $expiresAt = $expiresDays > 0
            ? date('Y-m-d H:i:s', strtotime("+{$expiresDays} days"))
            : null;

        $wpdb->insert($blacklistTable, [
            'user_id' => $userId,
            'reason' => $reason,
            'created_by' => $createdBy,
            'expires_at' => $expiresAt,
        ], ['%d', '%s', '%d', '%s']);

        return $wpdb->insert_id;
    }

    /**
     * Add IP to blacklist
     */
    private function addIpToBlacklist(string $ip, string $reason, int $createdBy, int $expiresDays = 0): int
    {
        global $wpdb;

        $blacklistTable = CommentSchema::table(CommentSchema::TABLE_BLACKLIST);

        $expiresAt = $expiresDays > 0
            ? date('Y-m-d H:i:s', strtotime("+{$expiresDays} days"))
            : null;

        $wpdb->insert($blacklistTable, [
            'ip_address' => $ip,
            'reason' => $reason,
            'created_by' => $createdBy,
            'expires_at' => $expiresAt,
        ], ['%s', '%s', '%d', '%s']);

        return $wpdb->insert_id;
    }

    /**
     * Remove from blacklist
     */
    public function removeFromBlacklist(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $id = $request->get_param('id');
        $blacklistTable = CommentSchema::table(CommentSchema::TABLE_BLACKLIST);

        // Soft delete by setting is_active = 0
        $wpdb->update(
            $blacklistTable,
            ['is_active' => 0],
            ['id' => $id],
            ['%d'],
            ['%d']
        );

        return new \WP_REST_Response([
            'success' => true,
        ], 200);
    }

    // ========================================
    // SETTINGS ENDPOINTS
    // ========================================

    /**
     * Get admin settings
     */
    public function getSettings(): \WP_REST_Response
    {
        $permissions = get_option('bbj_admin_permissions', self::DEFAULT_PERMISSIONS);
        $notifications = get_option('bbj_admin_notifications', [
            'comment_reports' => [
                'email' => get_option('admin_email'),
                'threshold' => 3,
                'enabled' => true,
            ],
        ]);

        return new \WP_REST_Response([
            'permissions' => $permissions,
            'notifications' => $notifications,
        ], 200);
    }

    /**
     * Update admin settings
     */
    public function updateSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();

        if (isset($params['permissions'])) {
            update_option('bbj_admin_permissions', $params['permissions']);
        }

        if (isset($params['notifications'])) {
            update_option('bbj_admin_notifications', $params['notifications']);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Settings updated',
        ], 200);
    }

    /**
     * Get all WordPress roles
     */
    public function getRoles(): \WP_REST_Response
    {
        global $wp_roles;

        $roles = [];
        foreach ($wp_roles->roles as $key => $role) {
            $roles[] = [
                'key' => $key,
                'name' => $role['name'],
            ];
        }

        return new \WP_REST_Response($roles, 200);
    }

    // ========================================
    // DATABASE / MIGRATION ENDPOINTS
    // ========================================

    /**
     * Get database status
     */
    public function getDatabaseStatus(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'version' => CommentMigrator::getCurrentVersion(),
            'needs_migration' => CommentMigrator::needsMigration(),
            'tables' => CommentMigrator::getTablesStatus(),
            'vote_migration' => CommentMigrator::getMigrationStatus(),
        ], 200);
    }

    /**
     * Run migrations
     */
    public function runMigration(): \WP_REST_Response
    {
        $results = CommentMigrator::migrate();

        return new \WP_REST_Response([
            'success' => true,
            'results' => $results,
            'version' => CommentMigrator::getCurrentVersion(),
        ], 200);
    }

    /**
     * Get vote migration preview
     */
    public function getVoteMigrationPreview(): \WP_REST_Response
    {
        $stats = CommentMigrator::getWpDiscuzVoteStats();
        $migrationStatus = CommentMigrator::getMigrationStatus();

        return new \WP_REST_Response([
            'source_stats' => $stats,
            'migration_status' => $migrationStatus,
        ], 200);
    }

    /**
     * Run vote migration
     */
    public function runVoteMigration(\WP_REST_Request $request): \WP_REST_Response
    {
        $batchSize = $request->get_param('batch_size');

        $result = CommentMigrator::migrateWpDiscuzVotes($batchSize);

        return new \WP_REST_Response($result, 200);
    }

    /**
     * Reset vote migration
     */
    public function resetVoteMigration(): \WP_REST_Response
    {
        CommentMigrator::resetMigrationStatus();

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Vote migration status reset',
        ], 200);
    }

    /**
     * Recalculate all user ranks
     */
    public function recalculateRanks(\WP_REST_Request $request): \WP_REST_Response
    {
        $limit = $request->get_param('limit');

        $results = RankCalculator::bulkUpdateRanks($limit);

        return new \WP_REST_Response([
            'success' => true,
            'results' => $results,
        ], 200);
    }

    // ========================================
    // PERMISSION CALLBACKS
    // ========================================

    /**
     * Check if user has any admin access
     */
    public function checkAdminAccess(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $permissions = get_option('bbj_admin_permissions', self::DEFAULT_PERMISSIONS);
        $user = wp_get_current_user();
        $userRoles = $user->roles;

        // Check if user has access to any feature
        foreach ($permissions as $permission) {
            if (!empty(array_intersect($userRoles, $permission['roles']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has comment moderation access
     */
    public function checkCommentModerationAccess(): bool
    {
        return $this->checkFeatureAccess('comment_moderation');
    }

    /**
     * Check if user has admin settings access
     */
    public function checkAdminSettingsAccess(): bool
    {
        return $this->checkFeatureAccess('admin_settings');
    }

    /**
     * Check if user has access to a specific feature
     */
    private function checkFeatureAccess(string $feature): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $permissions = get_option('bbj_admin_permissions', self::DEFAULT_PERMISSIONS);

        if (!isset($permissions[$feature])) {
            return false;
        }

        $user = wp_get_current_user();
        $userRoles = $user->roles;

        return !empty(array_intersect($userRoles, $permissions[$feature]['roles']));
    }
}
