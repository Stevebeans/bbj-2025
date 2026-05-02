<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Permissions\PermissionChecker;

class NewsAggregatorRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        register_rest_route($namespace, '/news/feed', [
            'methods' => 'GET',
            'callback' => [$this, 'getFeed'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route($namespace, '/news/scan', [
            'methods' => 'POST',
            'callback' => [$this, 'scanArticle'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route($namespace, '/news/refresh', [
            'methods' => 'POST',
            'callback' => [$this, 'refreshFeeds'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route($namespace, '/news/sources', [
            'methods' => 'GET',
            'callback' => [$this, 'getSources'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);
    }

    public function checkAccess(): bool
    {
        return PermissionChecker::userCan('content_engine');
    }

    public function getFeed(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_news_feed';
        $page = (int) ($request->get_param('page') ?? 1);
        $perPage = (int) ($request->get_param('per_page') ?? 30);
        $offset = ($page - 1) * $perPage;

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY published_at DESC LIMIT %d OFFSET %d",
            $perPage, $offset
        ));

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

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

    public function scanArticle(\WP_REST_Request $request): \WP_REST_Response
    {
        $url = esc_url_raw($request->get_param('url'));

        if (!$url) {
            return new \WP_REST_Response(['error' => 'No URL provided'], 400);
        }

        // Fetch the article HTML
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'user-agent' => 'Mozilla/5.0 (compatible; BBJContentEngine/1.0)',
        ]);

        if (is_wp_error($response)) {
            return new \WP_REST_Response(['error' => 'Failed to fetch article: ' . $response->get_error_message()], 500);
        }

        $html = wp_remote_retrieve_body($response);

        // Extract main content — strip scripts, styles, nav, header, footer
        $text = $this->extractArticleText($html);

        if (strlen($text) < 100) {
            return new \WP_REST_Response(['error' => 'Could not extract meaningful article content'], 400);
        }

        return new \WP_REST_Response([
            'article_text' => $text,
            'source_url' => $url,
        ]);
    }

    public function refreshFeeds(\WP_REST_Request $request): \WP_REST_Response
    {
        $count = $this->fetchAllFeeds();

        return new \WP_REST_Response([
            'success' => true,
            'new_articles' => $count,
        ]);
    }

    public function getSources(\WP_REST_Request $request): \WP_REST_Response
    {
        $settings = get_option('bbjd_content_engine_settings', []);
        $sources = $settings['news_sources'] ?? $this->getDefaultSources();

        return new \WP_REST_Response(['sources' => $sources]);
    }

    // --- Internal Methods ---

    public function fetchAllFeeds(): int
    {
        $settings = get_option('bbjd_content_engine_settings', []);
        $sources = $settings['news_sources'] ?? $this->getDefaultSources();
        $newCount = 0;

        foreach ($sources as $source) {
            $newCount += $this->fetchSingleFeed($source['url'], $source['name']);
        }

        // Cleanup old articles (>14 days and unused)
        global $wpdb;
        $table = $wpdb->prefix . 'bbj_news_feed';
        $wpdb->query("DELETE FROM {$table} WHERE used = 0 AND fetched_at < DATE_SUB(NOW(), INTERVAL 14 DAY)");

        return $newCount;
    }

    private function fetchSingleFeed(string $feedUrl, string $sourceName): int
    {
        include_once ABSPATH . WPINC . '/feed.php';

        $feed = fetch_feed($feedUrl);

        if (is_wp_error($feed)) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bbj_news_feed';
        $count = 0;

        foreach ($feed->get_items(0, 20) as $item) {
            $url = esc_url_raw($item->get_permalink());
            $title = sanitize_text_field($item->get_title());

            // Skip if already exists
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE url = %s", $url));
            if ($exists) continue;

            $excerpt = wp_trim_words(wp_strip_all_tags($item->get_description()), 40);
            $thumbnail = '';

            // Try to get enclosure image
            $enclosure = $item->get_enclosure();
            if ($enclosure && $enclosure->get_thumbnail()) {
                $thumbnail = esc_url_raw($enclosure->get_thumbnail());
            } elseif ($enclosure && $enclosure->get_link()) {
                $thumbnail = esc_url_raw($enclosure->get_link());
            }

            $publishedAt = $item->get_date('Y-m-d H:i:s');

            $wpdb->insert($table, [
                'title' => $title,
                'url' => $url,
                'source_name' => $sourceName,
                'excerpt' => $excerpt,
                'thumbnail' => $thumbnail,
                'published_at' => $publishedAt,
            ]);

            $count++;
        }

        return $count;
    }

    private function extractArticleText(string $html): string
    {
        // Remove scripts, styles, nav, header, footer, aside
        $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
        $html = preg_replace('/<nav[^>]*>.*?<\/nav>/si', '', $html);
        $html = preg_replace('/<header[^>]*>.*?<\/header>/si', '', $html);
        $html = preg_replace('/<footer[^>]*>.*?<\/footer>/si', '', $html);
        $html = preg_replace('/<aside[^>]*>.*?<\/aside>/si', '', $html);

        // Try to find <article> tag first
        if (preg_match('/<article[^>]*>(.*?)<\/article>/si', $html, $matches)) {
            $html = $matches[1];
        }

        // Strip remaining HTML
        $text = wp_strip_all_tags($html);

        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Limit to reasonable length
        if (strlen($text) > 5000) {
            $text = substr($text, 0, 5000);
        }

        return $text;
    }

    private function getDefaultSources(): array
    {
        return [
            ['name' => 'Google News - Big Brother', 'url' => 'https://news.google.com/rss/search?q=%22Big+Brother%22+CBS&hl=en-US&gl=US&ceid=US:en'],
            ['name' => 'Reality Blurred', 'url' => 'https://www.realityblurred.com/feed/'],
        ];
    }
}
