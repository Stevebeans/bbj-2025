<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Permissions\PermissionChecker;

class AIRoutes
{
    private const ANTHROPIC_API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-sonnet-4-20250514';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        register_rest_route($namespace, '/ai/caption', [
            'methods' => 'POST',
            'callback' => [$this, 'generateCaption'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route($namespace, '/ai/rewrite', [
            'methods' => 'POST',
            'callback' => [$this, 'rewriteArticle'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route($namespace, '/ai/enhance', [
            'methods' => 'POST',
            'callback' => [$this, 'enhanceTemplate'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);
    }

    public function checkAccess(): bool
    {
        return PermissionChecker::userCan('content_engine');
    }

    public function generateCaption(\WP_REST_Request $request): \WP_REST_Response
    {
        $imageData = $request->get_param('image_data'); // base64
        $mediaType = sanitize_text_field($request->get_param('media_type') ?? 'image/jpeg');
        $context = sanitize_text_field($request->get_param('context') ?? '');

        if (!$imageData) {
            return new \WP_REST_Response(['error' => 'No image data provided'], 400);
        }

        $systemPrompt = "You are a social media manager for Big Brother Junkies, a fan page with 160k+ followers on Facebook. Analyze this image and generate 3 engaging Facebook caption options.\n\nRules:\n- Conversational, fan-to-fan tone\n- Ask a question or pose a debate to drive comments\n- Keep each caption under 280 characters for optimal engagement\n- Use emojis sparingly (1-2 max per caption)\n- Never use hashtags on Facebook (they hurt reach)\n- Return ONLY a JSON array of 3 strings, nothing else";

        if ($context) {
            $systemPrompt .= "\n\nAdditional context: {$context}";
        }

        $messages = [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $mediaType,
                            'data' => $imageData,
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Generate 3 engaging Facebook captions for this image.',
                    ],
                ],
            ],
        ];

        $result = $this->callAnthropic($systemPrompt, $messages);

        if (isset($result['error'])) {
            return new \WP_REST_Response($result, 500);
        }

        // Parse the JSON array from the response
        $text = $result['content'][0]['text'] ?? '';
        $captions = json_decode($text, true);

        if (!is_array($captions)) {
            // If AI didn't return clean JSON, split by newlines
            $captions = array_filter(array_map('trim', explode("\n", $text)));
            $captions = array_values(array_slice($captions, 0, 3));
        }

        return new \WP_REST_Response(['captions' => $captions]);
    }

    public function rewriteArticle(\WP_REST_Request $request): \WP_REST_Response
    {
        $articleText = $request->get_param('article_text');
        $sourceUrl = esc_url_raw($request->get_param('source_url') ?? '');

        if (!$articleText) {
            return new \WP_REST_Response(['error' => 'No article text provided'], 400);
        }

        $systemPrompt = "You are writing for Big Brother Junkies. Rewrite this news article as a BBJ blog post.\n\nRules:\n- Add fan perspective and opinion\n- Reference relevant BB history/players when applicable\n- Keep the key facts but make it feel like a fan site, not a news wire\n- Include a discussion question at the end\n- 300-500 words\n- Return the rewritten article text only, no meta commentary";

        $messages = [
            [
                'role' => 'user',
                'content' => "Rewrite this article:\n\n{$articleText}",
            ],
        ];

        $result = $this->callAnthropic($systemPrompt, $messages);

        if (isset($result['error'])) {
            return new \WP_REST_Response($result, 500);
        }

        $rewritten = $result['content'][0]['text'] ?? '';

        return new \WP_REST_Response(['rewritten' => $rewritten]);
    }

    public function enhanceTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        $templateText = $request->get_param('template_text');

        if (!$templateText) {
            return new \WP_REST_Response(['error' => 'No template text provided'], 400);
        }

        $systemPrompt = "Enhance this social media post for maximum Facebook engagement. Keep the core concept but make it more natural and engaging. Return ONLY a JSON array of 3 variation strings, nothing else.\n\nContext: This is for a Big Brother fan page with 160k followers.";

        $messages = [
            [
                'role' => 'user',
                'content' => "Enhance this post:\n\n{$templateText}",
            ],
        ];

        $result = $this->callAnthropic($systemPrompt, $messages);

        if (isset($result['error'])) {
            return new \WP_REST_Response($result, 500);
        }

        $text = $result['content'][0]['text'] ?? '';
        $variations = json_decode($text, true);

        if (!is_array($variations)) {
            $variations = array_filter(array_map('trim', explode("\n", $text)));
            $variations = array_values(array_slice($variations, 0, 3));
        }

        return new \WP_REST_Response(['variations' => $variations]);
    }

    private function callAnthropic(string $systemPrompt, array $messages): array
    {
        $settings = get_option('bbjd_content_engine_settings', []);
        $apiKey = $settings['anthropic_api_key'] ?? '';

        if (!$apiKey) {
            return ['error' => 'Anthropic API key not configured'];
        }

        $response = wp_remote_post(self::ANTHROPIC_API_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => wp_json_encode([
                'model' => self::MODEL,
                'max_tokens' => 1024,
                'system' => $systemPrompt,
                'messages' => $messages,
            ]),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return ['error' => $body['error']['message'] ?? 'Anthropic API error'];
        }

        return $body;
    }
}
