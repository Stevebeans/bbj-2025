<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\BugReports\BugReportSchema;
use BigBrotherJunkies\Data\BugReports\ScreenshotUploader;

/**
 * Bug Report REST API Routes
 */
class BugReportRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        // Submit a bug report (authenticated users)
        register_rest_route($namespace, '/bug-reports', [
            'methods' => 'POST',
            'callback' => [$this, 'submitReport'],
            'permission_callback' => [$this, 'checkUserAccess'],
            'args' => [
                'type' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['ui_visual', 'functionality', 'performance', 'content', 'other'],
                ],
                'severity' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['critical', 'major', 'minor', 'cosmetic'],
                ],
                'description' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'page_url' => [
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
                'steps_to_reproduce' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'expected_behavior' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'screenshot_url' => [
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
                'browser_info' => [
                    'type' => 'object',
                ],
                'console_errors' => [
                    'type' => 'array',
                ],
            ],
        ]);

        // Upload screenshot (authenticated users)
        register_rest_route($namespace, '/bug-reports/upload-screenshot', [
            'methods' => 'POST',
            'callback' => [$this, 'uploadScreenshot'],
            'permission_callback' => [$this, 'checkUserAccess'],
        ]);

        // List bug reports (admin/editor only)
        register_rest_route($namespace, '/bug-reports', [
            'methods' => 'GET',
            'callback' => [$this, 'listReports'],
            'permission_callback' => [$this, 'checkAdminAccess'],
            'args' => [
                'status' => [
                    'default' => 'open',
                    'type' => 'string',
                    'enum' => ['open', 'in_progress', 'resolved', 'closed', 'wont_fix', 'all'],
                ],
                'severity' => [
                    'default' => 'all',
                    'type' => 'string',
                ],
                'type' => [
                    'default' => 'all',
                    'type' => 'string',
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

        // Update a bug report (admin/editor only)
        register_rest_route($namespace, '/bug-reports/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateReport'],
            'permission_callback' => [$this, 'checkAdminAccess'],
            'args' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['open', 'in_progress', 'resolved', 'closed', 'wont_fix'],
                ],
                'admin_notes' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
            ],
        ]);

        // Bug report stats (admin/editor only)
        register_rest_route($namespace, '/bug-reports/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getStats'],
            'permission_callback' => [$this, 'checkAdminAccess'],
        ]);
    }

    // ========================================
    // ENDPOINTS
    // ========================================

    /**
     * Submit a new bug report
     */
    public function submitReport(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $userId = get_current_user_id();
        $table = BugReportSchema::table(BugReportSchema::TABLE_BUG_REPORTS);

        $browserInfo = $request->get_param('browser_info');
        $consoleErrors = $request->get_param('console_errors');

        $wpdb->insert($table, [
            'user_id' => $userId,
            'type' => $request->get_param('type'),
            'severity' => $request->get_param('severity'),
            'page_url' => $request->get_param('page_url') ?: null,
            'description' => $request->get_param('description'),
            'steps_to_reproduce' => $request->get_param('steps_to_reproduce') ?: null,
            'expected_behavior' => $request->get_param('expected_behavior') ?: null,
            'screenshot_url' => $request->get_param('screenshot_url') ?: null,
            'browser_info' => $browserInfo ? wp_json_encode($browserInfo) : null,
            'console_errors' => $consoleErrors ? wp_json_encode($consoleErrors) : null,
        ], [
            '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
        ]);

        if ($wpdb->insert_id) {
            return new \WP_REST_Response([
                'success' => true,
                'id' => $wpdb->insert_id,
                'message' => 'Bug report submitted successfully',
            ], 201);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Failed to submit bug report',
        ], 500);
    }

    /**
     * Upload a screenshot
     */
    public function uploadScreenshot(\WP_REST_Request $request): \WP_REST_Response
    {
        $files = $request->get_file_params();

        if (empty($files['screenshot'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'No screenshot file provided',
            ], 400);
        }

        $userId = get_current_user_id();
        $result = ScreenshotUploader::upload($files['screenshot'], $userId);

        if ($result['success']) {
            return new \WP_REST_Response([
                'success' => true,
                'url' => $result['url'],
            ], 200);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => $result['error'],
        ], 400);
    }

    /**
     * List bug reports with filtering
     */
    public function listReports(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $status = $request->get_param('status');
        $severity = $request->get_param('severity');
        $type = $request->get_param('type');
        $perPage = min($request->get_param('per_page'), 50);
        $page = $request->get_param('page');
        $offset = ($page - 1) * $perPage;

        $table = BugReportSchema::table(BugReportSchema::TABLE_BUG_REPORTS);

        $where = [];
        $params = [];

        if ($status !== 'all') {
            $where[] = 'r.status = %s';
            $params[] = $status;
        }

        if ($severity !== 'all') {
            $where[] = 'r.severity = %s';
            $params[] = $severity;
        }

        if ($type !== 'all') {
            $where[] = 'r.type = %s';
            $params[] = $type;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = "
            SELECT
                r.*,
                u.display_name as reporter_name,
                u.user_email as reporter_email,
                resolver.display_name as resolved_by_name
            FROM {$table} r
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            LEFT JOIN {$wpdb->users} resolver ON r.resolved_by = resolver.ID
            {$whereClause}
            ORDER BY
                FIELD(r.severity, 'critical', 'major', 'minor', 'cosmetic'),
                r.created_at DESC
            LIMIT %d OFFSET %d
        ";

        $params[] = $perPage;
        $params[] = $offset;

        $reports = $wpdb->get_results(
            $wpdb->prepare($query, ...$params),
            ARRAY_A
        );

        // Decode JSON fields
        foreach ($reports as &$report) {
            $report['browser_info'] = $report['browser_info'] ? json_decode($report['browser_info'], true) : null;
            $report['console_errors'] = $report['console_errors'] ? json_decode($report['console_errors'], true) : null;
        }

        // Get total count
        $countQuery = "SELECT COUNT(*) FROM {$table} r {$whereClause}";
        $countParams = array_slice($params, 0, -2); // Remove LIMIT/OFFSET params
        $total = !empty($countParams)
            ? (int) $wpdb->get_var($wpdb->prepare($countQuery, ...$countParams))
            : (int) $wpdb->get_var($countQuery);

        return new \WP_REST_Response([
            'reports' => $reports,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => ceil($total / max($perPage, 1)),
            ],
        ], 200);
    }

    /**
     * Update a bug report (status, admin notes)
     */
    public function updateReport(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $table = BugReportSchema::table(BugReportSchema::TABLE_BUG_REPORTS);

        // Check report exists
        $report = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ));

        if (!$report) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Bug report not found',
            ], 404);
        }

        $updateData = [];
        $updateFormat = [];

        $newStatus = $request->get_param('status');
        if ($newStatus) {
            $updateData['status'] = $newStatus;
            $updateFormat[] = '%s';

            // Set resolved_at and resolved_by when marking as resolved or closed
            if (in_array($newStatus, ['resolved', 'closed', 'wont_fix'])) {
                $updateData['resolved_at'] = current_time('mysql');
                $updateData['resolved_by'] = get_current_user_id();
                $updateFormat[] = '%s';
                $updateFormat[] = '%d';
            }
        }

        $adminNotes = $request->get_param('admin_notes');
        if ($adminNotes !== null) {
            $updateData['admin_notes'] = $adminNotes;
            $updateFormat[] = '%s';
        }

        if (empty($updateData)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'No fields to update',
            ], 400);
        }

        $wpdb->update(
            $table,
            $updateData,
            ['id' => $id],
            $updateFormat,
            ['%d']
        );

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Bug report updated',
        ], 200);
    }

    /**
     * Get bug report stats (counts by status)
     */
    public function getStats(): \WP_REST_Response
    {
        global $wpdb;

        $table = BugReportSchema::table(BugReportSchema::TABLE_BUG_REPORTS);

        $stats = $wpdb->get_results("
            SELECT status, COUNT(*) as count
            FROM {$table}
            GROUP BY status
        ", ARRAY_A);

        $counts = [
            'open' => 0,
            'in_progress' => 0,
            'resolved' => 0,
            'closed' => 0,
            'wont_fix' => 0,
            'total' => 0,
        ];

        foreach ($stats as $row) {
            $counts[$row['status']] = (int) $row['count'];
            $counts['total'] += (int) $row['count'];
        }

        return new \WP_REST_Response($counts, 200);
    }

    // ========================================
    // PERMISSION CALLBACKS
    // ========================================

    /**
     * Any authenticated user can submit bug reports
     */
    public function checkUserAccess(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Only admins and editors can view/manage bug reports
     */
    public function checkAdminAccess(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        $allowedRoles = ['administrator', 'editor'];

        return !empty(array_intersect($user->roles, $allowedRoles));
    }
}
