<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Permissions\PermissionChecker;

class ContentEngineRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        // Drafts CRUD
        register_rest_route($namespace, '/content-engine/drafts', [
            'methods' => 'GET',
            'callback' => [$this, 'getDrafts'],
            'permission_callback' => [$this, 'checkAccess'],
            'args' => [
                'page' => ['default' => 1, 'type' => 'integer'],
                'per_page' => ['default' => 20, 'type' => 'integer'],
                'status' => ['default' => 'draft', 'type' => 'string'],
            ],
        ]);

        register_rest_route($namespace, '/content-engine/drafts', [
            'methods' => 'POST',
            'callback' => [$this, 'createDraft'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route($namespace, '/content-engine/drafts/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateDraft'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route($namespace, '/content-engine/drafts/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteDraft'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        // Queue
        register_rest_route($namespace, '/content-engine/queue', [
            'methods' => 'GET',
            'callback' => [$this, 'getQueue'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route($namespace, '/content-engine/queue/(?P<id>\d+)/reschedule', [
            'methods' => 'POST',
            'callback' => [$this, 'reschedulePost'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        // Post Log
        register_rest_route($namespace, '/content-engine/log', [
            'methods' => 'GET',
            'callback' => [$this, 'getPostLog'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        // Settings
        register_rest_route($namespace, '/content-engine/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'getSettings'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route($namespace, '/content-engine/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'updateSettings'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);
    }

    public function checkAccess(): bool
    {
        return PermissionChecker::userCan('content_engine');
    }

    // --- Drafts ---

    public function getDrafts(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_content_queue';
        $page = (int) $request->get_param('page');
        $perPage = (int) $request->get_param('per_page');
        $status = sanitize_text_field($request->get_param('status'));
        $offset = ($page - 1) * $perPage;

        $where = $status ? $wpdb->prepare("WHERE status = %s", $status) : "";
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $perPage, $offset
        ));

        return new \WP_REST_Response([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ]);
    }

    public function createDraft(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_content_queue';

        $data = [
            'status' => sanitize_text_field($request->get_param('status') ?? 'draft'),
            'source' => sanitize_text_field($request->get_param('source') ?? 'manual'),
            'content_type' => sanitize_text_field($request->get_param('content_type') ?? 'facebook_post'),
            'title' => sanitize_text_field($request->get_param('title') ?? ''),
            'body' => wp_kses_post($request->get_param('body') ?? ''),
            'image_url' => esc_url_raw($request->get_param('image_url') ?? ''),
            'target_page' => sanitize_text_field($request->get_param('target_page') ?? ''),
            'target_page_name' => sanitize_text_field($request->get_param('target_page_name') ?? ''),
            'scheduled_at' => $request->get_param('scheduled_at') ? sanitize_text_field($request->get_param('scheduled_at')) : null,
            'template_type' => sanitize_text_field($request->get_param('template_type') ?? ''),
            'source_url' => esc_url_raw($request->get_param('source_url') ?? ''),
            'ai_variations' => $request->get_param('ai_variations') ? wp_json_encode($request->get_param('ai_variations')) : null,
            'author_id' => get_current_user_id(),
        ];

        // Handle base64 image data
        $imageData = $request->get_param('image_data');
        if ($imageData) {
            $data['image_data'] = base64_decode($imageData);
        }

        if ($data['scheduled_at']) {
            $data['status'] = 'scheduled';
        }

        $wpdb->insert($table, $data);

        return new \WP_REST_Response([
            'success' => true,
            'id' => $wpdb->insert_id,
        ], 201);
    }

    public function updateDraft(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_content_queue';
        $id = (int) $request->get_param('id');

        $updates = [];
        $fields = ['status', 'title', 'body', 'image_url', 'target_page', 'target_page_name',
                    'content_type', 'scheduled_at', 'template_type', 'source_url'];

        foreach ($fields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $updates[$field] = sanitize_text_field($value);
            }
        }

        if (isset($updates['body'])) {
            $updates['body'] = wp_kses_post($request->get_param('body'));
        }

        if (empty($updates)) {
            return new \WP_REST_Response(['error' => 'No fields to update'], 400);
        }

        $wpdb->update($table, $updates, ['id' => $id]);

        return new \WP_REST_Response(['success' => true]);
    }

    public function deleteDraft(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_content_queue';
        $id = (int) $request->get_param('id');

        $wpdb->delete($table, ['id' => $id]);

        return new \WP_REST_Response(['success' => true]);
    }

    // --- Queue ---

    public function getQueue(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_content_queue';

        $items = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'scheduled' ORDER BY scheduled_at ASC"
        );

        return new \WP_REST_Response(['items' => $items]);
    }

    public function reschedulePost(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_content_queue';
        $id = (int) $request->get_param('id');
        $scheduledAt = sanitize_text_field($request->get_param('scheduled_at'));

        $wpdb->update($table, ['scheduled_at' => $scheduledAt], ['id' => $id]);

        return new \WP_REST_Response(['success' => true]);
    }

    // --- Post Log ---

    public function getPostLog(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_content_queue';
        $page = (int) ($request->get_param('page') ?? 1);
        $perPage = (int) ($request->get_param('per_page') ?? 20);
        $offset = ($page - 1) * $perPage;

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE status IN ('posted', 'failed')"
        );
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status IN ('posted', 'failed') ORDER BY posted_at DESC LIMIT %d OFFSET %d",
            $perPage, $offset
        ));

        return new \WP_REST_Response([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ]);
    }

    // --- Settings ---

    public function getSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $settings = get_option('bbjd_content_engine_settings', []);

        // Never expose full tokens to frontend — mask them
        if (!empty($settings['facebook_pages'])) {
            foreach ($settings['facebook_pages'] as &$page) {
                if (!empty($page['token'])) {
                    $page['token_preview'] = substr($page['token'], 0, 20) . '...';
                    $page['has_token'] = true;
                    unset($page['token']);
                }
            }
        }

        // Never expose Anthropic key to frontend
        $settings['has_anthropic_key'] = !empty($settings['anthropic_api_key']);
        unset($settings['anthropic_api_key']);

        return new \WP_REST_Response($settings);
    }

    public function updateSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $current = get_option('bbjd_content_engine_settings', []);
        $params = $request->get_json_params();

        // Merge — don't overwrite keys not sent
        if (isset($params['anthropic_api_key'])) {
            $current['anthropic_api_key'] = sanitize_text_field($params['anthropic_api_key']);
        }
        if (isset($params['facebook_pages'])) {
            $current['facebook_pages'] = $params['facebook_pages'];
        }
        if (isset($params['news_sources'])) {
            $current['news_sources'] = $params['news_sources'];
        }
        if (isset($params['default_posting_times'])) {
            $current['default_posting_times'] = $params['default_posting_times'];
        }
        if (isset($params['news_refresh_interval'])) {
            $current['news_refresh_interval'] = (int) $params['news_refresh_interval'];
        }

        update_option('bbjd_content_engine_settings', $current);

        return new \WP_REST_Response(['success' => true]);
    }
}
