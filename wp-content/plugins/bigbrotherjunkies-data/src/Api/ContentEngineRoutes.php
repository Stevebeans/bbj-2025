<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Permissions\PermissionChecker;
use BigBrotherJunkies\Data\Social\FbInsightsService;
use BigBrotherJunkies\Data\Social\PromptContext;
use BigBrotherJunkies\Data\Social\SlotLogic;
use BigBrotherJunkies\Data\Social\SocialStore;

class ContentEngineRoutes
{
    /**
     * Every bbj_content_queue column except image_data (a LONGBLOB that can
     * hold a base64-encoded PNG) plus a computed has_image flag — list
     * endpoints only need to know whether an image exists, not fetch it.
     */
    private const QUEUE_ROW_COLUMNS = 'id, status, source, content_type, title, body, image_url,
            (image_data IS NOT NULL AND image_data != \'\') AS has_image,
            target_page, target_page_name, wp_post_id, scheduled_at, posted_at, fb_post_id,
            template_type, source_url, ai_variations, author_id, created_at, updated_at';

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

        register_rest_route($namespace, '/content-engine/queue/(?P<id>\d+)/image', [
            'methods' => 'GET',
            'callback' => [$this, 'getQueueImage'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route($namespace, '/content-engine/slots', [
            'methods' => 'GET',
            'callback' => [$this, 'getSlots'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route($namespace, '/content-engine/schedule', [
            'methods' => 'GET',
            'callback' => [$this, 'getSchedule'],
            'permission_callback' => [$this, 'checkAccess'],
            'args' => [
                'from' => ['required' => true, 'type' => 'string'],
                'to'   => ['required' => true, 'type' => 'string'],
            ],
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
            "SELECT " . self::QUEUE_ROW_COLUMNS . " FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
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
            // FB posts get de-clockworked: grid picks shift 2-14 min, and no
            // two scheduled posts land within 25 min (QueueBridge::humanizeSlot).
            'scheduled_at' => $request->get_param('scheduled_at')
                ? \BigBrotherJunkies\Data\Social\QueueBridge::humanizeSlot(sanitize_text_field($request->get_param('scheduled_at')))
                : null,
            'template_type' => sanitize_text_field($request->get_param('template_type') ?? ''),
            'source_url' => esc_url_raw($request->get_param('source_url') ?? ''),
            'ai_variations' => $request->get_param('ai_variations') ? wp_json_encode($request->get_param('ai_variations')) : null,
            'author_id' => get_current_user_id(),
        ];

        // Stored as-is (base64 string), matching QueueBridge's queue rows —
        // ContentEngineCron::firePost base64_decode()s at send time.
        $imageData = $request->get_param('image_data');
        if ($imageData) {
            $data['image_data'] = $imageData;
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
            "SELECT " . self::QUEUE_ROW_COLUMNS . " FROM {$table} WHERE status = 'scheduled' ORDER BY scheduled_at ASC"
        );

        return new \WP_REST_Response(['items' => $items]);
    }

    /**
     * 30-min scheduling grid for the composer dropdown: next 24h, each slot
     * free or labeled with what already occupies it, plus a CBS-air-window
     * flag so nobody schedules into the episode.
     */
    public function getSlots(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_content_queue';
        $now   = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT scheduled_at AS at, source FROM {$table}
              WHERE status = 'scheduled'
                AND scheduled_at BETWEEN %s AND %s",
            $now->modify('-30 minutes')->format('Y-m-d H:i:s'),
            $now->modify('+25 hours')->format('Y-m-d H:i:s')
        ), ARRAY_A) ?: [];

        $slots = SlotLogic::grid($now, $rows);

        $cbsSlots = (array) (SocialStore::settings()['cbs_slots'] ?? []);
        foreach ($slots as &$s) {
            $s['cbs_caution'] = $cbsSlots !== [] && PromptContext::inCautionWindow(
                $cbsSlots,
                new \DateTimeImmutable($s['at'], new \DateTimeZone('UTC'))
            );
        }

        return new \WP_REST_Response(['success' => true, 'slots' => $slots], 200);
    }

    /**
     * Day-by-day schedule view. Future days show the queued timeline; past days
     * show every page post with FB performance (via FbInsightsService). A window
     * straddling "now" returns both halves.
     *
     * GET /content-engine/schedule?from=&to=  (UTC 'Y-m-d H:i:s')
     */
    public function getSchedule(\WP_REST_Request $request): \WP_REST_Response
    {
        $from = $this->parseUtc((string) $request->get_param('from'));
        $to   = $this->parseUtc((string) $request->get_param('to'));

        if ($from === null || $to === null) {
            return new \WP_REST_Response(['error' => 'Invalid from/to (expect UTC Y-m-d H:i:s)'], 400);
        }
        if ($from >= $to) {
            return new \WP_REST_Response(['error' => 'from must be before to'], 400);
        }

        $now     = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr   = $to->format('Y-m-d H:i:s');

        // Entirely in the past -> posted view with insights.
        if ($to <= $now) {
            $posted = (new FbInsightsService())->dayPosts($fromStr, $toStr);
            return new \WP_REST_Response([
                'mode'           => 'past',
                'items'          => $posted['items'],
                'insights_error' => $posted['insights_error'],
            ]);
        }

        // Entirely in the future -> scheduled queue timeline.
        if ($from >= $now) {
            return new \WP_REST_Response([
                'mode'  => 'future',
                'items' => $this->scheduledRows($fromStr, $toStr),
            ]);
        }

        // Straddles now -> split at now.
        $nowStr = $now->format('Y-m-d H:i:s');
        $posted = (new FbInsightsService())->dayPosts($fromStr, $nowStr);
        return new \WP_REST_Response([
            'mode'      => 'mixed',
            'scheduled' => $this->scheduledRows($nowStr, $toStr),
            'posted'    => [
                'items'          => $posted['items'],
                'insights_error' => $posted['insights_error'],
            ],
        ]);
    }

    /**
     * Scheduled queue rows in [$fromUtc, $toUtc), full QUEUE_ROW_COLUMNS shape.
     */
    private function scheduledRows(string $fromUtc, string $toUtc): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_content_queue';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::QUEUE_ROW_COLUMNS . " FROM {$table}
              WHERE status = 'scheduled'
                AND scheduled_at >= %s AND scheduled_at < %s
              ORDER BY scheduled_at ASC",
            $fromUtc,
            $toUtc
        )) ?: [];
    }

    /**
     * Strict UTC 'Y-m-d H:i:s' parse; null when malformed.
     */
    private function parseUtc(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $tz = new \DateTimeZone('UTC');
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $tz);
        if ($dt === false || $dt->format('Y-m-d H:i:s') !== $value) {
            return null;
        }
        return $dt;
    }

    /**
     * The list endpoints exclude image_data (LONGBLOB); this fetches one row's
     * blob on demand so the queue UI can preview the card before it posts.
     */
    public function getQueueImage(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_content_queue';
        $id = (int) $request->get_param('id');

        $imageData = $wpdb->get_var($wpdb->prepare(
            "SELECT image_data FROM {$table} WHERE id = %d",
            $id
        ));

        if (!$imageData) {
            return new \WP_REST_Response(['error' => 'No image for this item'], 404);
        }

        return new \WP_REST_Response(['image_data' => $imageData]);
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
            "SELECT " . self::QUEUE_ROW_COLUMNS . " FROM {$table} WHERE status IN ('posted', 'failed') ORDER BY posted_at DESC LIMIT %d OFFSET %d",
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
