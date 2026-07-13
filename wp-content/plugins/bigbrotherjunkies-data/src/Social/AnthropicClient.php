<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Minimal Anthropic Messages API client (wp_remote_post; no SDK dependency).
 * Model + key come from the bbjd_social_monitor settings.
 */
class AnthropicClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    public const ALLOWED_MODELS = ['claude-sonnet-5', 'claude-haiku-4-5', 'claude-opus-4-8'];

    /**
     * @return array{text:string}|array{error:string}
     */
    public function complete(string $system, string $userMessage, string $model, string $apiKey): array
    {
        if ($apiKey === '') {
            return ['error' => 'No Anthropic API key configured'];
        }
        if (!in_array($model, self::ALLOWED_MODELS, true)) {
            $model = 'claude-sonnet-5';
        }

        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => 120,
            'headers' => [
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => $model,
                'max_tokens' => 4000,
                'system'     => $system,
                'messages'   => [['role' => 'user', 'content' => $userMessage]],
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['error' => 'Anthropic request failed: ' . $response->get_error_message()];
        }
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $code);
            return ['error' => 'Anthropic API error: ' . $msg];
        }
        if (($data['stop_reason'] ?? '') === 'refusal') {
            return ['error' => 'The model declined this request'];
        }
        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        if ($text === '') {
            return ['error' => 'Empty response from model'];
        }
        return ['text' => $text];
    }
}
