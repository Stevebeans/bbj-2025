<?php

namespace BigBrotherJunkies\Data\Cron;

use BigBrotherJunkies\Data\Api\NewsAggregatorRoutes;

class ContentEngineCron
{
    public function init(): void
    {
        // Register custom cron interval
        add_filter('cron_schedules', [$this, 'addCronIntervals']);

        // Schedule events on plugin load
        add_action('init', [$this, 'scheduleEvents']);

        // Hook the actual cron functions
        add_action('bbjd_process_scheduled_posts', [$this, 'processScheduledPosts']);
        add_action('bbjd_refresh_news_feeds', [$this, 'refreshNewsFeeds']);
    }

    public function addCronIntervals(array $schedules): array
    {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display' => 'Every Minute',
        ];
        $schedules['every_30_minutes'] = [
            'interval' => 1800,
            'display' => 'Every 30 Minutes',
        ];
        return $schedules;
    }

    public function scheduleEvents(): void
    {
        if (!wp_next_scheduled('bbjd_process_scheduled_posts')) {
            wp_schedule_event(time(), 'every_minute', 'bbjd_process_scheduled_posts');
        }

        if (!wp_next_scheduled('bbjd_refresh_news_feeds')) {
            wp_schedule_event(time(), 'every_30_minutes', 'bbjd_refresh_news_feeds');
        }
    }

    public function processScheduledPosts(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_content_queue';

        $posts = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'scheduled' AND scheduled_at <= NOW()"
        );

        if (empty($posts)) return;

        foreach ($posts as $post) {
            $this->firePost($post);
        }
    }

    private function firePost(object $post): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_content_queue';

        // Get page token
        $settings = get_option('bbjd_content_engine_settings', []);
        $pages = $settings['facebook_pages'] ?? [];
        $token = null;

        foreach ($pages as $page) {
            if ($page['id'] === $post->target_page) {
                $token = $page['token'] ?? null;
                break;
            }
        }

        if (!$token) {
            $wpdb->update($table, ['status' => 'failed', 'posted_at' => current_time('mysql')], ['id' => $post->id]);
            return;
        }

        // Determine if photo or text post
        $hasImage = !empty($post->image_url) || !empty($post->image_data);
        $endpoint = $hasImage ? "/{$post->target_page}/photos" : "/{$post->target_page}/feed";

        $body = [
            'message' => $post->body,
            'access_token' => $token,
        ];

        if (!empty($post->image_url)) {
            $body['url'] = $post->image_url;
        }

        $response = wp_remote_post("https://graph.facebook.com/v21.0{$endpoint}", [
            'body' => $body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $wpdb->update($table, ['status' => 'failed', 'posted_at' => current_time('mysql')], ['id' => $post->id]);
            return;
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($result['error'])) {
            $wpdb->update($table, ['status' => 'failed', 'posted_at' => current_time('mysql')], ['id' => $post->id]);
            return;
        }

        $wpdb->update($table, [
            'status' => 'posted',
            'posted_at' => current_time('mysql'),
            'fb_post_id' => $result['id'] ?? $result['post_id'] ?? null,
        ], ['id' => $post->id]);
    }

    public function refreshNewsFeeds(): void
    {
        $newsRoutes = new NewsAggregatorRoutes();
        $newsRoutes->fetchAllFeeds();
    }
}
