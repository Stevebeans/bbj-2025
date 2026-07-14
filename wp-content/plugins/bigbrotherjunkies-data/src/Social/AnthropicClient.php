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

        $payload = [
            'model'      => $model,
            'max_tokens' => 8000,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $userMessage]],
        ];
        // Sonnet 5 runs ADAPTIVE THINKING when `thinking` is omitted, and
        // max_tokens caps thinking + text combined. On a heavy feed window the
        // model can spend the whole budget thinking and return zero text blocks
        // ("Empty response from model", 2026-07-14 digest failure). These are
        // deterministic summarization calls — disable thinking so the budget is
        // all text. Other allowed models run without thinking when omitted.
        if ($model === 'claude-sonnet-5') {
            $payload['thinking'] = ['type' => 'disabled'];
        }

        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => 120,
            'headers' => [
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode($payload),
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
            if (($data['stop_reason'] ?? '') === 'max_tokens') {
                return ['error' => 'Model hit the token limit before producing text — try a smaller window or retry.'];
            }
            return ['error' => 'Empty response from model'];
        }
        return ['text' => $text];
    }
}
