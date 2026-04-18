<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Permissions\PermissionChecker;

class FacebookRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        register_rest_route($namespace, '/facebook/pages', [
            'methods' => 'GET',
            'callback' => [$this, 'getPages'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route($namespace, '/facebook/post', [
            'methods' => 'POST',
            'callback' => [$this, 'postToPage'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route($namespace, '/facebook/post-photo', [
            'methods' => 'POST',
            'callback' => [$this, 'postPhotoToPage'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);
    }

    public function checkAccess(): bool
    {
        return PermissionChecker::userCan('content_engine');
    }

    public function getPages(\WP_REST_Request $request): \WP_REST_Response
    {
        $settings = get_option('bbjd_content_engine_settings', []);
        $pages = $settings['facebook_pages'] ?? [];

        // Strip tokens, return only id + name + has_token
        $safe = array_map(function ($page) {
            return [
                'id' => $page['id'],
                'name' => $page['name'],
                'has_token' => !empty($page['token']),
            ];
        }, $pages);

        return new \WP_REST_Response(['pages' => $safe]);
    }

    public function postToPage(\WP_REST_Request $request): \WP_REST_Response
    {
        $pageId = sanitize_text_field($request->get_param('page_id'));
        $message = $request->get_param('message');
        $link = esc_url_raw($request->get_param('link') ?? '');
        $queueId = (int) $request->get_param('queue_id');

        $token = $this->getPageToken($pageId);
        if (!$token) {
            return new \WP_REST_Response(['error' => 'No token configured for this page'], 400);
        }

        $body = ['message' => $message, 'access_token' => $token];
        if ($link) {
            $body['link'] = $link;
        }

        $response = wp_remote_post("https://graph.facebook.com/v21.0/{$pageId}/feed", [
            'body' => $body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->updateQueueStatus($queueId, 'failed');
            return new \WP_REST_Response(['error' => $response->get_error_message()], 500);
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($result['error'])) {
            $this->updateQueueStatus($queueId, 'failed');
            return new \WP_REST_Response(['error' => $result['error']['message']], 400);
        }

        $this->updateQueueStatus($queueId, 'posted', $result['id'] ?? null);

        return new \WP_REST_Response([
            'success' => true,
            'fb_post_id' => $result['id'] ?? null,
        ]);
    }

    public function postPhotoToPage(\WP_REST_Request $request): \WP_REST_Response
    {
        $pageId = sanitize_text_field($request->get_param('page_id'));
        $message = $request->get_param('message');
        $imageData = $request->get_param('image_data'); // base64
        $imageUrl = esc_url_raw($request->get_param('image_url') ?? '');
        $queueId = (int) $request->get_param('queue_id');

        $token = $this->getPageToken($pageId);
        if (!$token) {
            return new \WP_REST_Response(['error' => 'No token configured for this page'], 400);
        }

        // If we have base64 image data, upload as multipart
        if ($imageData) {
            $decoded = base64_decode($imageData);
            $boundary = wp_generate_password(24, false);

            $payload = '';
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Disposition: form-data; name=\"message\"\r\n\r\n{$message}\r\n";
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Disposition: form-data; name=\"access_token\"\r\n\r\n{$token}\r\n";
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Disposition: form-data; name=\"source\"; filename=\"image.jpg\"\r\n";
            $payload .= "Content-Type: image/jpeg\r\n\r\n";
            $payload .= $decoded . "\r\n";
            $payload .= "--{$boundary}--\r\n";

            $response = wp_remote_post("https://graph.facebook.com/v21.0/{$pageId}/photos", [
                'headers' => ['Content-Type' => "multipart/form-data; boundary={$boundary}"],
                'body' => $payload,
                'timeout' => 60,
            ]);
        } elseif ($imageUrl) {
            // Post with image URL
            $response = wp_remote_post("https://graph.facebook.com/v21.0/{$pageId}/photos", [
                'body' => [
                    'message' => $message,
                    'url' => $imageUrl,
                    'access_token' => $token,
                ],
                'timeout' => 30,
            ]);
        } else {
            return new \WP_REST_Response(['error' => 'No image provided'], 400);
        }

        if (is_wp_error($response)) {
            $this->updateQueueStatus($queueId, 'failed');
            return new \WP_REST_Response(['error' => $response->get_error_message()], 500);
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($result['error'])) {
            $this->updateQueueStatus($queueId, 'failed');
            return new \WP_REST_Response(['error' => $result['error']['message']], 400);
        }

        $this->updateQueueStatus($queueId, 'posted', $result['id'] ?? $result['post_id'] ?? null);

        return new \WP_REST_Response([
            'success' => true,
            'fb_post_id' => $result['id'] ?? $result['post_id'] ?? null,
        ]);
    }

    private function getPageToken(string $pageId): ?string
    {
        $settings = get_option('bbjd_content_engine_settings', []);
        $pages = $settings['facebook_pages'] ?? [];

        foreach ($pages as $page) {
            if ($page['id'] === $pageId && !empty($page['token'])) {
                return $page['token'];
            }
        }
        return null;
    }

    private function updateQueueStatus(int $queueId, string $status, ?string $fbPostId = null): void
    {
        if (!$queueId) return;

        global $wpdb;
        $table = $wpdb->prefix . 'bbj_content_queue';
        $data = ['status' => $status, 'posted_at' => current_time('mysql')];
        if ($fbPostId) {
            $data['fb_post_id'] = $fbPostId;
        }
        $wpdb->update($table, $data, ['id' => $queueId]);
    }
}
